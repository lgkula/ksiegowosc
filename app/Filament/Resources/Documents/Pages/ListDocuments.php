<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\Direction;
use App\Enums\DocumentSource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Tables\DocumentsTable;
use App\Filament\Support\MoneyInput;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\ManualTemplate;
use App\Services\DocumentProcessor;
use App\Services\Ksef\KsefImporter;
use App\Support\CsvExporter;
use App\Support\Money;
use App\Support\Period;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ksefImportAction(),
            $this->templateAction(),
            $this->copyCostsAction(),
            CreateAction::make()->label('Nowy dokument'),
            Action::make('exportCsv')
                ->label('Eksport CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => CsvExporter::documents($this->getFilteredSortedTableQuery())),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Wszystkie'),
            'pending' => Tab::make('Do decyzji')
                ->badge(fn () => Document::pending()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('included')),
            'costs' => Tab::make('Koszty')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('direction', Direction::Cost)),
            'revenue' => Tab::make('Przychody')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('direction', Direction::Revenue)),
        ];
    }

    /** Ręczne pobranie faktur z KSeF za wybrany zakres dat. */
    private function ksefImportAction(): Action
    {
        return Action::make('ksefImport')
            ->label('Pobierz z KSeF')
            ->icon(Heroicon::OutlinedCloudArrowDown)
            ->modalHeading('Pobieranie faktur z KSeF')
            ->modalDescription('Nowe faktury zakupowe trafią do zakładki „Do decyzji”. Faktury już pobrane są pomijane.')
            ->modalSubmitActionLabel('Pobierz')
            ->schema([
                DatePicker::make('from')->label('Od')->default(now()->subMonthNoOverflow()->startOfMonth())->required(),
                DatePicker::make('to')->label('Do')->default(now())->maxDate(now())->afterOrEqual('from')->required(),
                CheckboxList::make('subjects')
                    ->label('Rodzaj faktur')
                    ->options([KsefImporter::PURCHASES => 'Zakupowe (koszty)', KsefImporter::SALES => 'Sprzedażowe (przychody)'])
                    ->default([KsefImporter::PURCHASES])
                    ->required(),
            ])
            ->action(function (array $data) {
                $summary = ['found' => 0, 'imported' => 0, 'errors' => []];
                foreach ($data['subjects'] as $subject) {
                    $result = app(KsefImporter::class)->import(CarbonImmutable::parse($data['from']), CarbonImmutable::parse($data['to']), $subject);
                    $summary['found'] += $result['found'];
                    $summary['imported'] += $result['imported'];
                    $summary['errors'] = [...$summary['errors'], ...$result['errors']];
                }

                $notification = Notification::make()
                    ->title("Pobrano nowych faktur: {$summary['imported']} (znaleziono {$summary['found']})");

                if ($summary['errors']) {
                    $notification->warning()->persistent()->body(implode("\n", array_slice($summary['errors'], 0, 5)));
                } else {
                    $notification->success();
                }
                $notification->send();

                if ($summary['imported'] > 0) {
                    $this->activeTab = 'pending';
                }
            });
    }

    /** Szybkie dodanie dokumentu z szablonu, np. faktury za paliwo z mniejszej sieci. */
    private function templateAction(): Action
    {
        return CreateAction::make('fromTemplate')
            ->label('Z szablonu')
            ->icon(Heroicon::OutlinedBolt)
            ->color('success')
            ->modalHeading('Dokument z szablonu')
            ->modalWidth('lg')
            ->createAnother(true)
            ->createAnotherAction(fn (Action $action) => $action->label('Zapisz i dodaj kolejny'))
            ->schema([
                Select::make('template_id')
                    ->label('Szablon')
                    ->options(fn () => ManualTemplate::orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => session('last_template_id'))
                    ->required()
                    ->live(),
                DatePicker::make('issue_date')
                    ->label('Data')
                    ->default(now())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => $state && $set('period', Carbon::parse($state)->format('Y-m'))),
                TextInput::make('number')->label('Numer faktury'),
                TextInput::make('qty')
                    ->label(fn (Get $get) => 'Ilość'.(($unit = ManualTemplate::find($get('template_id'))?->default_unit) ? " ({$unit})" : ''))
                    ->numeric()
                    ->live(onBlur: true),
                MoneyInput::make('gross')->label('Kwota brutto')->suffix('zł')->required()->live(onBlur: true),
                TextEntry::make('summary')
                    ->label('Wyliczenie')
                    ->state(function (Get $get) {
                        $template = ManualTemplate::find($get('template_id'));
                        $gross = Money::parse($get('gross')) ?? 0;
                        if (! $template || $gross <= 0) {
                            return '—';
                        }
                        $split = DocumentItem::splitGross($gross, $template->vat_rate);
                        $qty = (float) $get('qty');
                        $price = $qty > 0 ? ' · cena brutto '.number_format($gross / $qty, 2, ',', ' ').' zł/'.($template->default_unit ?: 'szt.') : '';

                        return sprintf('netto %s zł · VAT %s zł (%s%%)%s', number_format($split['net'], 2, ',', ' '), number_format($split['vat'], 2, ',', ' '), $template->vat_rate, $price);
                    }),
                TextInput::make('period')
                    ->label('Miesiąc rozliczenia')
                    ->default(now()->format('Y-m'))
                    ->rules(['regex:/^\d{4}-(0[1-9]|1[0-2])$/'])
                    ->required(),
            ])
            ->using(function (array $data) {
                session(['last_template_id' => $data['template_id']]);

                return app(DocumentProcessor::class)->createFromTemplate($data);
            })
            ->successNotificationTitle('Dodano dokument');
    }

    /** Kopiowanie powtarzalnych kosztów wpisanych ręcznie (OIRP, OpenAI, Anthropic…) do innego miesiąca. */
    private function copyCostsAction(): Action
    {
        $periodRule = 'regex:/^\d{4}-(0[1-9]|1[0-2])$/';

        $sourceDocuments = fn (?string $period) => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $period)
            ? Document::with(['contractor', 'items'])
                ->where('period', $period)
                ->where('direction', Direction::Cost)
                ->where('source', '!=', DocumentSource::Ksef)
                ->orderBy('issue_date')
                ->get()
            : collect();

        $alreadyCopied = fn (?string $target) => Document::where('period', (string) $target)
            ->whereNotNull('copied_from_id')
            ->pluck('copied_from_id')
            ->all();

        $toCopy = fn (?string $from, ?string $to) => $sourceDocuments($from)->pluck('id')->diff($alreadyCopied($to))->values()->all();

        return Action::make('copyCosts')
            ->label('Kopiuj koszty')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->modalHeading('Kopiowanie kosztów do innego miesiąca')
            ->modalDescription('Kopiuje dokumenty kosztowe wpisane ręcznie, np. składki OIRP czy opłaty za OpenAI i Anthropic. Daty przesuwają się o tyle miesięcy, ile dzieli oba miesiące, numer zostaje pusty, a dokument w walucie dostaje kurs NBP dla nowej daty. Kwoty można potem poprawić w dokumencie.')
            ->modalSubmitActionLabel('Kopiuj')
            ->schema([
                TextInput::make('from')
                    ->label('Z miesiąca')
                    ->placeholder('RRRR-MM')
                    ->default(fn () => Period::previous(Period::current()))
                    ->rules([$periodRule])
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => $set('documents', $toCopy($state, $get('to')))),
                TextInput::make('to')
                    ->label('Do miesiąca')
                    ->placeholder('RRRR-MM')
                    ->default(fn () => Period::current())
                    ->rules([$periodRule, 'different:from'])
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => $set('documents', $toCopy($get('from'), $state))),
                CheckboxList::make('documents')
                    ->label('Dokumenty')
                    ->options(fn (Get $get) => $sourceDocuments($get('from'))
                        ->mapWithKeys(fn (Document $d) => [$d->id => DocumentProcessor::label($d)])
                        ->all())
                    ->descriptions(fn (Get $get) => $sourceDocuments($get('from'))
                        ->mapWithKeys(fn (Document $d) => [$d->id => self::copyDescription($d, in_array($d->id, $alreadyCopied($get('to'))))])
                        ->all())
                    ->disableOptionWhen(fn (string $value, Get $get) => in_array((int) $value, $alreadyCopied($get('to'))))
                    ->default(fn () => $toCopy(Period::previous(Period::current()), Period::current()))
                    ->bulkToggleable()
                    ->helperText(fn (Get $get) => $sourceDocuments($get('from'))->isEmpty() ? 'Brak ręcznych dokumentów kosztowych w tym miesiącu.' : null)
                    ->required(),
            ])
            ->action(function (array $data) {
                $documents = Document::with(['contractor', 'items'])->whereKey($data['documents'])->orderBy('issue_date')->get();

                DocumentsTable::notifyCopyResult(app(DocumentProcessor::class)->copyToPeriod($documents, $data['to']), $data['to']);
            });
    }

    private static function copyDescription(Document $document, bool $copied): string
    {
        $amount = $document->isForeignCurrency()
            ? Money::format($document->gross_currency, symbol: $document->currency).' ('.Money::format($document->gross).')'
            : Money::format($document->gross);

        return $document->issue_date->format('d.m.Y').' · '.$amount.' brutto'.($copied ? ' · już skopiowany do tego miesiąca' : '');
    }
}
