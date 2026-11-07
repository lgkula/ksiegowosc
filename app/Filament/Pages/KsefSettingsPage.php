<?php

namespace App\Filament\Pages;

use App\Models\KsefSyncLog;
use App\Models\Setting;
use App\Services\Ksef\KsefClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

class KsefSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Ustawienia';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'KSeF';

    protected static ?string $title = 'Ustawienia KSeF';

    protected static ?string $slug = 'ksef';

    protected string $view = 'filament.pages.ksef-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'ksef_environment' => Setting::get('ksef_environment', 'test'),
            'ksef_nip' => Setting::get('ksef_nip'),
            'ksef_token' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $hasToken = filled(Setting::get('ksef_token'));

        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Select::make('ksef_environment')
                    ->label('Środowisko')
                    ->options(['test' => 'Testowe (api-test)', 'demo' => 'Przedprodukcyjne (api-demo)', 'prod' => 'Produkcyjne'])
                    ->required(),
                TextInput::make('ksef_nip')
                    ->label('NIP firmy')
                    ->required()
                    ->rules(['regex:/^\d{10}$/'])
                    ->validationMessages(['regex' => 'NIP to 10 cyfr bez kresek.']),
                TextInput::make('ksef_token')
                    ->label('Token KSeF')
                    ->password()
                    ->revealable()
                    ->required(! $hasToken)
                    ->placeholder($hasToken ? 'zapisany – zostaw puste, żeby nie zmieniać' : null)
                    ->helperText('Token z uprawnieniem do przeglądania faktur, wygenerowany w Aplikacji Podatnika KSeF. Przechowywany w bazie w postaci zaszyfrowanej.')
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::put('ksef_environment', $data['ksef_environment']);
        Setting::put('ksef_nip', $data['ksef_nip']);
        if (filled($data['ksef_token'])) {
            Setting::put('ksef_token', trim($data['ksef_token']));
        }

        $this->form->fill([...$data, 'ksef_token' => null]);
        Notification::make()->title('Zapisano ustawienia KSeF')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Sprawdź połączenie')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(function () {
                    try {
                        $client = KsefClient::fromSettings();
                        $client->authenticateWithToken();
                        Notification::make()->title('Połączenie z KSeF działa')->body($client->environmentUrl())->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Błąd połączenia z KSeF')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }

    public function getViewData(): array
    {
        return ['logs' => KsefSyncLog::latest()->limit(15)->get()];
    }
}
