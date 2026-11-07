<?php

namespace App\Filament\Pages;

use App\Services\Forecast\IncomeForecastService;
use App\Support\Period;
use App\Support\VatRates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Livewire\Attributes\Url;
use UnitEnum;

class IncomeForecastPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Rozliczenia';

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Prognoza dochodu';

    protected static ?string $slug = 'prognoza';

    protected string $view = 'filament.pages.income-forecast';

    #[Url]
    public string $period = '';

    public ?array $data = [];

    protected ?array $forecastResult = null;

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->period)) {
            $this->period = Period::current();
        }

        $this->loadInput();
    }

    public function getTitle(): string
    {
        return 'Prognoza dochodu: '.Period::label($this->period);
    }

    public function form(Schema $schema): Schema
    {
        // Kwoty z odstępem tysięcy i przecinkiem dziesiętnym, np. 20 000,00; serwis zamienia je na liczby.
        $money = fn (string $name, string $label) => TextInput::make($name)
            ->label($label)
            ->mask(RawJs::make("\$money(\$input, ',', ' ', 2)"))
            ->inputMode('decimal')
            ->suffix('zł')
            ->live(onBlur: true);

        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Group::make([
                            Section::make('Kruk')
                                ->description('Faktura za miesiąc zaczytuje się automatycznie, kwoty można zmienić.')
                                ->columns(['default' => 1, 'sm' => 3])
                                ->schema([
                                    $money('kruk_base', 'Podstawa netto'),
                                    $money('kruk_additions', 'Dodatki netto'),
                                    Select::make('kruk_vat_rate')
                                        ->label('Stawka VAT')
                                        ->options(array_intersect_key(VatRates::options(), array_flip(['23', '8', '5', '0'])))
                                        ->selectablePlaceholder(false)
                                        ->live(),
                                ]),
                            Section::make('Inne przychody')
                                ->schema([
                                    Repeater::make('other_income')
                                        ->hiddenLabel()
                                        ->columns(3)
                                        ->defaultItems(0)
                                        ->addActionLabel('Dodaj przychód')
                                        ->reorderable(false)
                                        ->live()
                                        ->schema([
                                            TextInput::make('name')->label('Opis')->columnSpan(2)->live(onBlur: true),
                                            $money('net', 'Kwota netto'),
                                        ]),
                                ]),
                            Toggle::make('include_pending')
                                ->label('Uwzględnij w kosztach dokumenty czekające na decyzję')
                                ->live(),
                        ])->extraAttributes(['class' => 'ks-stack']),
                        Group::make([
                            Section::make('Faktura: '.config('forecast.subcontractor.name'))
                                ->description('NIP: PL'.config('forecast.subcontractor.nip').'. Netto = godziny × stawka + dodatkowe wynagrodzenie.')
                                ->columns(['default' => 1, 'sm' => 2])
                                ->schema([
                                    TextInput::make('sub_hours')->label('Liczba godzin')->numeric()->step(0.01)->suffix('h')->live(onBlur: true),
                                    $money('sub_rate', 'Stawka godzinowa netto')->suffix('zł/h'),
                                    $money('sub_extra', 'Dodatkowe wynagrodzenie netto'),
                                    TextInput::make('sub_pit_rate')->label('Stawka PIT wystawcy')->numeric()->step(0.01)->suffix('%')->live(onBlur: true),
                                    View::make('filament.pages.partials.forecast-subcontractor')
                                        ->viewData(fn () => ['f' => $this->forecast()])
                                        ->columnSpanFull(),
                                ]),
                            View::make('filament.pages.partials.forecast-summary')
                                ->viewData(fn () => ['f' => $this->forecast()]),
                        ])->extraAttributes(['class' => 'ks-stack']),
                    ]),
            ]);
    }

    /** Wynik modelu liczony raz na żądanie. */
    public function forecast(): array
    {
        return $this->forecastResult ??= app(IncomeForecastService::class)->calculate($this->period, $this->data ?? []);
    }

    /** Każda zmiana w formularzu zapisuje model miesiąca. */
    public function updatedData(): void
    {
        $this->forecastResult = null;
        app(IncomeForecastService::class)->save($this->period, $this->data ?? []);
    }

    public function updatedPeriod(): void
    {
        $this->loadInput();
    }

    public function getViewData(): array
    {
        return ['f' => $this->forecast()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previous')
                ->label('Poprzedni')
                ->icon(Heroicon::OutlinedChevronLeft)
                ->color('gray')
                ->action(fn () => $this->changePeriod(Period::previous($this->period))),
            Action::make('next')
                ->label('Następny')
                ->icon(Heroicon::OutlinedChevronRight)
                ->iconPosition('after')
                ->color('gray')
                ->action(fn () => $this->changePeriod(Period::next($this->period))),
            Action::make('reload')
                ->label('Zaczytaj z dokumentów')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Wpisane wartości zostaną zastąpione danymi z faktur tego miesiąca.')
                ->action(function () {
                    app(IncomeForecastService::class)->forget($this->period);
                    $this->loadInput();
                    Notification::make()->title('Zaczytano dane z dokumentów')->success()->send();
                }),
            Action::make('settlement')
                ->label('Rozliczenie miesiąca')
                ->icon(Heroicon::OutlinedCalculator)
                ->color('gray')
                ->url(fn () => MonthlySettlementPage::getUrl(['period' => $this->period])),
        ];
    }

    private function changePeriod(string $period): void
    {
        $this->period = $period;
        $this->loadInput();
    }

    private function loadInput(): void
    {
        $this->forecastResult = null;
        $input = app(IncomeForecastService::class)->input($this->period);
        $this->form->fill(self::formatAmounts($input));
    }

    /** Kwoty do pól z maską: przecinek dziesiętny i odstęp tysięcy. */
    private static function formatAmounts(array $input): array
    {
        $format = fn ($v) => number_format((float) $v, 2, ',', ' ');

        foreach (['kruk_base', 'kruk_additions', 'sub_rate', 'sub_extra'] as $key) {
            $input[$key] = $format($input[$key]);
        }
        $input['other_income'] = array_map(fn ($row) => ['name' => $row['name'], 'net' => $format($row['net'])], $input['other_income']);

        return $input;
    }
}
