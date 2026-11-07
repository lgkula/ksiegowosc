<?php

namespace App\Services\Nbp;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Kursy średnie NBP (tabela A). Do przeliczenia faktury bierze się kurs
 * z ostatniego dnia roboczego przed datą wystawienia.
 */
class NbpRates
{
    public const CURRENCIES = ['PLN', 'USD', 'EUR', 'CHF'];

    /** Kursy ogłaszane w dni robocze; 14 dni wystarcza z zapasem na święta. */
    private const LOOKBACK_DAYS = 14;

    /**
     * @return array{rate: float, date: string, table: string}
     *
     * @throws NbpException
     */
    public function rateBefore(string $currency, DateTimeInterface|string $date): array
    {
        $currency = strtoupper($currency);
        if ($currency === 'PLN') {
            return ['rate' => 1.0, 'date' => CarbonImmutable::parse($date)->format('Y-m-d'), 'table' => ''];
        }
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw new NbpException("Nieobsługiwana waluta: {$currency}.");
        }

        // Dla daty z przyszłości kursu jeszcze nie ma – bierze się ostatni opublikowany.
        $end = CarbonImmutable::parse($date)->startOfDay()->subDay()->min(CarbonImmutable::today());
        $start = $end->subDays(self::LOOKBACK_DAYS);
        $key = "nbp:{$currency}:{$end->format('Y-m-d')}";

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        try {
            $response = Http::baseUrl(config('nbp.base_url'))
                ->withOptions(config('ksef.ca_bundle') ? ['verify' => config('ksef.ca_bundle')] : [])
                ->acceptJson()
                ->timeout(config('nbp.timeout'))
                ->get("exchangerates/rates/a/{$currency}/{$start->format('Y-m-d')}/{$end->format('Y-m-d')}/", ['format' => 'json']);
        } catch (Throwable $e) {
            throw new NbpException('Brak połączenia z API NBP: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new NbpException("API NBP zwróciło błąd {$response->status()} dla {$currency} przed {$end->addDay()->format('d.m.Y')}.");
        }

        $last = collect($response->json('rates', []))->sortBy('effectiveDate')->last();
        if (! $last || ! is_numeric($last['mid'] ?? null)) {
            throw new NbpException("Brak kursu {$currency} w tabeli NBP przed {$end->addDay()->format('d.m.Y')}.");
        }

        $result = ['rate' => (float) $last['mid'], 'date' => $last['effectiveDate'], 'table' => (string) ($last['no'] ?? '')];

        // Kurs z przeszłości się nie zmienia; dla dzisiejszej daty końcowej tabela może się jeszcze pojawić.
        $end->isToday() ? Cache::put($key, $result, now()->addHour()) : Cache::forever($key, $result);

        return $result;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(self::CURRENCIES, self::CURRENCIES);
    }
}
