<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\Direction;
use App\Enums\DocumentKind;
use App\Enums\DocumentSource;
use App\Filament\Support\MoneyInput;
use App\Models\DocumentItem;
use App\Services\Nbp\NbpException;
use App\Services\Nbp\NbpRates;
use App\Support\Money;
use App\Support\VatRates;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Dokument')
                    ->columns(4)
                    ->schema([
                        ToggleButtons::make('direction')
                            ->label('Rodzaj')
                            ->options(Direction::class)
                            ->default(Direction::Cost)
                            ->inline()
                            ->required(),
                        Select::make('kind')
                            ->label('Typ dokumentu')
                            ->options(DocumentKind::class)
                            ->default(DocumentKind::Invoice)
                            ->required(),
                        TextInput::make('number')->label('Numer'),
                        Select::make('contractor_id')
                            ->label('Kontrahent')
                            ->relationship('contractor', 'full_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->displayName().($record->nip ? " ({$record->nip})" : ''))
                            ->searchable(['full_name', 'short_name', 'nip'])
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('full_name')->label('Pełna nazwa')->required(),
                                TextInput::make('nip')->label('NIP'),
                                TextInput::make('short_name')->label('Nazwa skrócona'),
                            ]),
                        DatePicker::make('issue_date')
                            ->label('Data wystawienia / zapłaty')
                            ->default(now())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                if ($state) {
                                    $set('period', Carbon::parse($state)->format('Y-m'));
                                }
                                self::fetchRate($get, $set);
                            }),
                        DatePicker::make('sale_date')->label('Data sprzedaży'),
                        TextInput::make('period')
                            ->label('Miesiąc rozliczenia')
                            ->placeholder('RRRR-MM')
                            ->default(now()->format('Y-m'))
                            ->rules(['regex:/^\d{4}-(0[1-9]|1[0-2])$/'])
                            ->required(),
                        ToggleButtons::make('included')
                            ->label('Ujęcie w rozliczeniu')
                            ->options(['1' => 'Tak', '0' => 'Pomiń', '' => 'Do decyzji'])
                            ->colors(['1' => 'success', '0' => 'gray', '' => 'warning'])
                            ->default('1')
                            ->inline()
                            ->formatStateUsing(fn ($state) => $state === null ? '' : ($state ? '1' : '0'))
                            ->dehydrateStateUsing(fn ($state) => $state === '' || $state === null ? null : (bool) $state),
                        Select::make('currency')
                            ->label('Waluta')
                            ->options(NbpRates::options())
                            ->default('PLN')
                            ->selectablePlaceholder(false)
                            ->required()
                            ->disabled(fn (Get $get) => self::source($get) === DocumentSource::Ksef)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::fetchRate($get, $set)),
                        MoneyInput::make('exchange_rate', 4)
                            ->label(fn (Get $get) => 'Kurs średni NBP '.$get('currency'))
                            ->suffix('zł')
                            ->required()
                            ->visible(fn (Get $get) => self::isForeign($get))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set) {
                                // Kurs wpisany ręcznie nie pochodzi z tabeli NBP.
                                $set('exchange_rate_date', null);
                                $set('exchange_rate_table', null);
                            })
                            ->helperText(fn (Get $get) => filled($get('exchange_rate_date'))
                                ? 'Tabela '.($get('exchange_rate_table') ?: '—').' z '.Carbon::parse($get('exchange_rate_date'))->format('d.m.Y')
                                : 'Wpisany ręcznie. Przycisk obok pobiera kurs NBP z dnia roboczego przed datą wystawienia.')
                            ->suffixAction(
                                Action::make('fetchRate')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->tooltip('Pobierz kurs z NBP')
                                    ->action(fn (Get $get, Set $set) => self::fetchRate($get, $set, notifySuccess: true))
                            ),
                        Hidden::make('exchange_rate_date')
                            ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('Y-m-d') : null),
                        Hidden::make('exchange_rate_table'),
                        TextInput::make('ksef_number')
                            ->label('Numer KSeF')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?string $state) => filled($state))
                            ->columnSpan(2),
                        Select::make('source')
                            ->label('Źródło')
                            ->options(DocumentSource::class)
                            ->default(DocumentSource::Manual)
                            ->disabled()
                            ->dehydrated(fn (string $operation) => $operation === 'create'),
                    ]),
                Section::make('Pozycje')
                    ->description(fn (Get $get) => self::isForeign($get)
                        ? 'Kwoty wpisuje się w walucie '.$get('currency').'. Podstawą rozliczenia są kwoty w PLN wyliczone kursem NBP.'
                        : null)
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('position')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Dodaj pozycję')
                            ->columns(12)
                            ->schema(self::itemSchema()),
                    ]),
                Textarea::make('notes')->label('Uwagi')->rows(2),
            ]);
    }

    private static function itemSchema(): array
    {
        $foreign = fn (Get $get) => self::isForeign($get, '../../');
        $currency = fn (Get $get) => $get('../../currency');

        // Kwoty netto/VAT/brutto w PLN albo w walucie (sufiks pola), przeliczane z netto lub z brutto.
        $amounts = function (string $suffix) use ($foreign, $currency) {
            $net = 'net'.$suffix;
            $vat = 'vat'.$suffix;
            $gross = 'gross'.$suffix;
            $visible = fn (Get $get) => $suffix === '' ? ! $foreign($get) : $foreign($get);
            $label = fn (string $text) => fn (Get $get) => $suffix === '' ? $text : "{$text} ".$currency($get);

            return [
                MoneyInput::make($net)
                    ->label($label('Netto'))
                    ->required()
                    ->visible($visible)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcFromNet($get, $set, $suffix))
                    ->columnSpan(1),
                MoneyInput::make($vat)
                    ->label($label('Kwota VAT'))
                    ->default(0)
                    ->visible($visible)
                    ->live(onBlur: true)
                    ->columnSpan(1),
                MoneyInput::make($gross)
                    ->label($label('Brutto'))
                    ->required()
                    ->visible($visible)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($net, $vat) {
                        $split = DocumentItem::splitGross(Money::parse($state) ?? 0, $get('vat_rate'));
                        $set($net, Money::input($split['net']));
                        $set($vat, Money::input($split['vat']));
                    })
                    ->columnSpan(1),
            ];
        };

        return [
            TextInput::make('name')->label('Nazwa')->required()->columnSpan(4),
            TextInput::make('qty')->label('Ilość')->numeric()->default(1)->columnSpan(1),
            TextInput::make('unit')->label('J.m.')->columnSpan(1),
            Select::make('vat_rate')
                ->label('VAT')
                ->options(VatRates::options())
                ->default('23')
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcFromNet($get, $set, $foreign($get) ? '_currency' : ''))
                ->columnSpan(1),
            ...$amounts(''),
            ...$amounts('_currency'),
            Select::make('category_id')
                ->label('Kategoria')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->placeholder('automatycznie')
                ->live()
                ->afterStateUpdated(fn ($state, Set $set) => $set('category_source', $state ? 'manual' : 'auto'))
                ->columnSpan(2),
            Hidden::make('category_source')->default('auto'),
            TextEntry::make('pln_amounts')
                ->hiddenLabel()
                ->visible($foreign)
                ->state(function (Get $get) {
                    $rate = Money::parse($get('../../exchange_rate'), 4);
                    if (! $rate) {
                        return 'W PLN: brak kursu.';
                    }
                    $pln = fn (string $field) => Money::format(round((Money::parse($get($field)) ?? 0) * $rate, 2));

                    return sprintf('W PLN (kurs %s): netto %s · VAT %s · brutto %s',
                        Money::input($rate, 4), $pln('net_currency'), $pln('vat_currency'), $pln('gross_currency'));
                })
                ->color('gray')
                ->columnSpanFull(),
        ];
    }

    private static function recalcFromNet(Get $get, Set $set, string $suffix): void
    {
        $net = Money::parse($get('net'.$suffix)) ?? 0;
        $vat = round($net * DocumentItem::rateFraction($get('vat_rate')), 2);
        $set('vat'.$suffix, Money::input($vat));
        $set('gross'.$suffix, Money::input($net + $vat));
    }

    private static function source(Get $get, string $prefix = ''): ?DocumentSource
    {
        $source = $get($prefix.'source');

        return $source instanceof DocumentSource ? $source : DocumentSource::tryFrom((string) $source);
    }

    /** Dokument ręczny w walucie obcej (faktury z KSeF zostają w kwotach z pliku). */
    private static function isForeign(Get $get, string $prefix = ''): bool
    {
        $currency = $get($prefix.'currency');

        return filled($currency) && $currency !== 'PLN' && self::source($get, $prefix) !== DocumentSource::Ksef;
    }

    /** Pobiera kurs średni NBP z dnia roboczego przed datą wystawienia. */
    private static function fetchRate(Get $get, Set $set, bool $notifySuccess = false): void
    {
        if (! self::isForeign($get) || blank($get('issue_date'))) {
            return;
        }

        try {
            $rate = app(NbpRates::class)->rateBefore($get('currency'), $get('issue_date'));
        } catch (NbpException $e) {
            Notification::make()->title('Nie udało się pobrać kursu NBP')->body($e->getMessage().' Wpisz kurs ręcznie.')->warning()->send();

            return;
        }

        $set('exchange_rate', Money::input($rate['rate'], 4));
        $set('exchange_rate_date', $rate['date']);
        $set('exchange_rate_table', $rate['table']);

        if ($notifySuccess) {
            Notification::make()->title("Kurs {$get('currency')}: ".Money::input($rate['rate'], 4).' zł')->success()->send();
        }
    }
}
