<?php

namespace App\Filament\Pages;

use App\Models\MonthlySettlement;
use App\Services\Settlement\SettlementService;
use App\Support\Period;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

class MonthlySettlementPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Rozliczenia';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Rozliczenie miesiąca';

    protected static ?string $slug = 'rozliczenie';

    protected string $view = 'filament.pages.monthly-settlement';

    #[Url]
    public string $period = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->period)) {
            $this->period = Period::current();
        }
    }

    public function getTitle(): string
    {
        return 'Rozliczenie: '.Period::label($this->period);
    }

    public function getViewData(): array
    {
        return ['s' => app(SettlementService::class)->monthly($this->period)];
    }

    protected function getHeaderActions(): array
    {
        $settlement = MonthlySettlement::forPeriod($this->period);

        return [
            Action::make('previous')
                ->label('Poprzedni')
                ->icon(Heroicon::OutlinedChevronLeft)
                ->color('gray')
                ->action(fn () => $this->period = Period::previous($this->period)),
            Action::make('next')
                ->label('Następny')
                ->icon(Heroicon::OutlinedChevronRight)
                ->iconPosition('after')
                ->color('gray')
                ->action(fn () => $this->period = Period::next($this->period)),
            Action::make('editMonth')
                ->label('Dane miesiąca')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->disabled($settlement->isClosed())
                ->fillForm(fn () => MonthlySettlement::forPeriod($this->period)->only(['other_income', 'other_income_note', 'account_balance', 'extra_reserve', 'notes']))
                ->schema([
                    TextInput::make('other_income')->label('Inne przychody')->numeric()->suffix('zł')->default(0),
                    TextInput::make('other_income_note')->label('Opis innych przychodów'),
                    TextInput::make('account_balance')->label('Saldo konta firmowego')->numeric()->suffix('zł')->helperText('Stan konta w dniu rozliczenia.'),
                    TextInput::make('extra_reserve')->label('Dodatkowa rezerwa na koncie')->numeric()->suffix('zł')->default(0),
                    Textarea::make('notes')->label('Notatki')->rows(2),
                ])
                ->action(function (array $data) {
                    $settlement = MonthlySettlement::forPeriod($this->period);
                    $settlement->fill([
                        'other_income' => $data['other_income'] ?: 0,
                        'other_income_note' => $data['other_income_note'],
                        'account_balance' => $data['account_balance'] === '' ? null : $data['account_balance'],
                        'extra_reserve' => $data['extra_reserve'] ?: 0,
                        'notes' => $data['notes'],
                    ])->save();
                    Notification::make()->title('Zapisano')->success()->send();
                }),
            $settlement->isClosed()
                ? Action::make('reopen')
                    ->label('Otwórz ponownie')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Rozliczenie znów będzie liczone na bieżąco z dokumentów i parametrów.')
                    ->action(fn () => app(SettlementService::class)->reopen($this->period))
                : Action::make('close')
                    ->label('Zamknij miesiąc')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Wyliczenia zostaną zapisane i nie zmienią się po edycji dokumentów lub parametrów.')
                    ->action(function () {
                        app(SettlementService::class)->close($this->period);
                        Notification::make()->title('Miesiąc zamknięty')->success()->send();
                    }),
        ];
    }
}
