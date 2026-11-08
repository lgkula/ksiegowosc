<?php

namespace App\Services\Ksef;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

/**
 * Cienki klient API KSeF 2.0: uwierzytelnienie tokenem KSeF, wyszukiwanie metadanych i pobieranie XML faktur.
 * Uwierzytelnienie certyfikatem można dodać jako kolejną metodę zwracającą access token.
 */
class KsefClient
{
    public function __construct(
        private string $baseUrl,
        private string $nip,
        private string $token,
    ) {}

    public static function fromSettings(): self
    {
        $environment = Setting::get('ksef_environment', 'test');
        $nip = (string) Setting::get('ksef_nip');
        $token = (string) Setting::get('ksef_token');

        if ($nip === '' || $token === '') {
            throw new KsefException('Uzupełnij NIP i token KSeF w Ustawienia → KSeF.');
        }

        $baseUrl = config("ksef.environments.{$environment}")
            ?? throw new KsefException("Nieznane środowisko KSeF: {$environment}");

        return new self($baseUrl, $nip, $token);
    }

    public function environmentUrl(): string
    {
        return $this->baseUrl;
    }

    /** Access token z pamięci podręcznej albo z nowego uwierzytelnienia. */
    public function accessToken(): string
    {
        $key = 'ksef_access_token:'.md5($this->baseUrl.$this->nip.$this->token);

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        [$token, $validUntil] = $this->authenticateWithToken();
        $ttl = max(60, CarbonImmutable::parse($validUntil)->diffInSeconds(now()->toImmutable(), true) - 60);
        Cache::put($key, $token, (int) $ttl);

        return $token;
    }

    /** @return array{0:string, 1:string} access token i data ważności */
    public function authenticateWithToken(): array
    {
        $challenge = $this->send('pobranie wyzwania', fn () => $this->http()->post('/auth/challenge'))->json();

        $encrypted = $this->encryptToken($this->token.'|'.$challenge['timestampMs']);

        $init = $this->send('rozpoczęcie uwierzytelnienia', fn () => $this->http()->post('/auth/ksef-token', [
            'challenge' => $challenge['challenge'],
            'contextIdentifier' => ['type' => 'Nip', 'value' => $this->nip],
            'encryptedToken' => $encrypted,
        ]))->json();

        $authToken = $init['authenticationToken']['token'];
        $this->waitForAuthentication($init['referenceNumber'], $authToken);

        $tokens = $this->send('pobranie tokenów', fn () => $this->http()->withToken($authToken)->post('/auth/token/redeem'))->json();

        return [$tokens['accessToken']['token'], $tokens['accessToken']['validUntil']];
    }

    /**
     * Strona wyników wyszukiwania metadanych faktur.
     *
     * @param  string  $subjectType  Subject1 (sprzedaż) albo Subject2 (zakup)
     */
    public function queryMetadata(string $subjectType, CarbonImmutable $from, CarbonImmutable $to, int $pageOffset = 0, ?int $pageSize = null): array
    {
        $query = http_build_query([
            'pageOffset' => $pageOffset,
            'pageSize' => $pageSize ?? config('ksef.page_size'),
            'sortOrder' => 'Asc',
        ]);

        return $this->send('wyszukiwanie faktur', fn () => $this->authorized()->post('/invoices/query/metadata?'.$query, [
            'subjectType' => $subjectType,
            'dateRange' => [
                'dateType' => 'Issue',
                'from' => $from->startOfDay()->toIso8601String(),
                'to' => $to->endOfDay()->toIso8601String(),
            ],
        ]))->json();
    }

    public function downloadInvoice(string $ksefNumber): string
    {
        return $this->send("pobranie faktury {$ksefNumber}", fn () => $this->authorized()
            ->accept('application/xml')
            ->get('/invoices/ksef/'.rawurlencode($ksefNumber)))->body();
    }

    private function waitForAuthentication(string $referenceNumber, string $authToken): void
    {
        for ($i = 0; $i < config('ksef.auth_poll_attempts'); $i++) {
            $status = $this->send('status uwierzytelnienia', fn () => $this->http()->withToken($authToken)->get('/auth/'.$referenceNumber))->json('status');

            if ((int) $status['code'] === 200) {
                return;
            }
            if ((int) $status['code'] >= 400) {
                $details = implode(' ', (array) ($status['details'] ?? []));
                throw new KsefException("KSeF odrzucił uwierzytelnienie ({$status['code']}): {$status['description']} {$details}");
            }

            usleep(1_000_000);
        }

        throw new KsefException('KSeF nie zakończył uwierzytelnienia w oczekiwanym czasie. Spróbuj ponownie.');
    }

    /** Szyfruje RSA-OAEP (SHA-256, MGF1) kluczem publicznym MF przeznaczonym do szyfrowania tokenów. */
    private function encryptToken(string $plain): string
    {
        $certificates = $this->send('pobranie kluczy publicznych', fn () => $this->http()->get('/security/public-key-certificates'))->json();

        $certificate = collect($certificates)
            ->filter(fn (array $c) => in_array('KsefTokenEncryption', $c['usage'] ?? [], true))
            ->filter(fn (array $c) => CarbonImmutable::parse($c['validTo'])->isFuture())
            ->sortByDesc('validFrom')
            ->first() ?? throw new KsefException('KSeF nie udostępnił klucza do szyfrowania tokenu.');

        $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split($certificate['certificate'], 64, "\n")."-----END CERTIFICATE-----\n";

        /** @var RSA\PublicKey $key */
        $key = PublicKeyLoader::load($pem);
        $key = $key->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256');

        return base64_encode($key->encrypt($plain));
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withOptions(config('ksef.ca_bundle') ? ['verify' => config('ksef.ca_bundle')] : [])
            ->acceptJson()
            ->asJson()
            ->timeout(config('ksef.timeout'));
    }

    private function authorized(): PendingRequest
    {
        return $this->http()->withToken($this->accessToken());
    }

    /**
     * Wysyła zapytanie, a po HTTP 429 czeka tyle, ile każe nagłówek Retry-After, i ponawia.
     * Gdy łączne czekanie przekroczyłoby limit z konfiguracji, rzuca KsefRateLimitException.
     *
     * @param  \Closure(): Response  $request
     */
    private function send(string $operation, \Closure $request): Response
    {
        $waited = 0;

        for ($attempt = 1; ; $attempt++) {
            $response = $request();

            if ($response->status() !== 429) {
                return $this->check($operation, $response);
            }

            $delay = $this->retryAfter($response, $attempt);

            if ($attempt > config('ksef.rate_limit_retries') || $waited + $delay > config('ksef.rate_limit_max_wait')) {
                throw new KsefRateLimitException(
                    "KSeF: {$operation} – przekroczono limit zapytań (HTTP 429), kolejna próba możliwa za {$delay} s.",
                    $delay,
                );
            }

            Sleep::for($delay)->seconds();
            $waited += $delay;
        }
    }

    /** Liczba sekund z nagłówka Retry-After (sekundy albo data HTTP); bez nagłówka – narastające opóźnienie. */
    private function retryAfter(Response $response, int $attempt): int
    {
        $header = trim($response->header('Retry-After'));

        if (ctype_digit($header)) {
            return max(1, (int) $header);
        }
        if ($header !== '' && ($time = strtotime($header)) !== false) {
            return max(1, $time - time());
        }

        return min(60, 5 * 2 ** ($attempt - 1));
    }

    private function check(string $operation, Response $response): Response
    {
        if ($response->failed()) {
            throw KsefException::fromResponse($operation, $response);
        }

        return $response;
    }
}
