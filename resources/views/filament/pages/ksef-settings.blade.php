<x-filament-panels::page>
    @include('filament.pages.partials.styles')

    <div class="ks-grid">
        <x-filament::section heading="Połączenie">
            <form wire:submit="save" class="ks-grid">
                {{ $this->form }}
                <div>
                    <x-filament::button type="submit">Zapisz</x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section heading="Historia pobrań" description="Faktury pobiera się z listy dokumentów przyciskiem „Pobierz z KSeF”.">
            <div class="ks-scroll">
                <table class="ks-table">
                    <thead>
                        <tr><th>Kiedy</th><th>Zakres</th><th>Rodzaj</th><th class="num">Znalezione</th><th class="num">Nowe</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at->format('d.m.Y H:i') }}</td>
                                <td>{{ $log->date_from->format('d.m.Y') }} – {{ $log->date_to->format('d.m.Y') }}</td>
                                <td>{{ $log->subject_type === 'Subject1' ? 'sprzedaż' : 'zakupy' }}</td>
                                <td class="num">{{ $log->found }}</td>
                                <td class="num">{{ $log->imported }}</td>
                                <td>
                                    @if ($log->status === 'ok')
                                        <span class="ks-positive">OK</span>
                                    @else
                                        <span class="ks-negative">Błąd</span>
                                        <div class="ks-muted" style="white-space: pre-line">{{ $log->error }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="ks-muted"><td colspan="6">Jeszcze nie pobierano faktur.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
