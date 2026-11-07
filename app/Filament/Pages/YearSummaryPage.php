<?php

namespace App\Filament\Pages;

use App\Services\Settlement\SettlementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

class YearSummaryPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Rozliczenia';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Zestawienie roczne';

    protected static ?string $slug = 'zestawienie';

    protected string $view = 'filament.pages.year-summary';

    #[Url]
    public int $year = 0;

    public function mount(): void
    {
        if ($this->year < 2000 || $this->year > 2100) {
            $this->year = (int) now()->format('Y');
        }
    }

    public function getTitle(): string
    {
        return 'Zestawienie roczne '.$this->year;
    }

    public function getViewData(): array
    {
        return ['y' => app(SettlementService::class)->yearly($this->year)];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previous')
                ->label((string) ($this->year - 1))
                ->icon(Heroicon::OutlinedChevronLeft)
                ->color('gray')
                ->action(fn () => $this->year--),
            Action::make('next')
                ->label((string) ($this->year + 1))
                ->icon(Heroicon::OutlinedChevronRight)
                ->iconPosition('after')
                ->color('gray')
                ->action(fn () => $this->year++),
        ];
    }
}
