<?php

use App\Models\Category;
use App\Models\Document;
use App\Models\KsefSyncLog;
use App\Models\Setting;
use App\Services\Ksef\FaXmlParser;
use App\Services\Ksef\KsefImporter;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
    $this->xml = file_get_contents(base_path('tests/Fixtures/fa3-orlen.xml'));
});

it('parsuje fakturę FA(3) i wyrównuje VAT do sumy z nagłówka', function () {
    $data = app(FaXmlParser::class)->parse($this->xml);

    expect($data['number'])->toBe('F/123/11/2026/S4567')
        ->and($data['issue_date'])->toBe('2026-11-03')
        ->and($data['seller']['nip'])->toBe('7740001454')
        ->and($data['seller']['address'])->toBe('ul. Chemików 7, 09-411 Płock')
        ->and($data['net'])->toBe(235.78)
        ->and($data['vat'])->toBe(54.23)
        ->and($data['gross'])->toBe(290.01)
        ->and($data['items'])->toHaveCount(2)
        ->and($data['items'][0]['qty'])->toBe(40.12)
        ->and($data['items'][0]['vat_rate'])->toBe('23')
        // 225.07 × 23% = 51.77, 10.71 × 23% = 2.46 → 54.23 zgodnie z nagłówkiem
        ->and(round($data['items'][0]['vat'] + $data['items'][1]['vat'], 2))->toBe(54.23);
});

it('zapisuje fakturę zakupową jako "do decyzji" z kontrahentem w grupie i kategoriami', function () {
    $document = app(KsefImporter::class)->storeInvoice($this->xml, '7740001454-20261103-ABCDEF-01', KsefImporter::PURCHASES);
    $document->load('items', 'contractor.group');

    expect($document->included)->toBeNull()
        ->and($document->period)->toBe('2026-11')
        ->and((float) $document->gross)->toBe(290.01)
        ->and($document->contractor->group->name)->toBe('Orlen')
        ->and($document->items->pluck('category_id')->all())->toBe([
            Category::where('name', 'Paliwo')->value('id'),
            Category::where('name', 'Gastronomia')->value('id'),
        ]);
});

it('uwierzytelnia się tokenem, pobiera faktury i pomija już zapisane', function () {
    $private = RSA::createKey(2048);
    $certificate = makeCertificate($private);

    Setting::put('ksef_environment', 'test');
    Setting::put('ksef_nip', '8990001122');
    Setting::put('ksef_token', 'TOKEN-TESTOWY');

    $base = 'https://api-test.ksef.mf.gov.pl/v2';
    Http::fake([
        "{$base}/auth/challenge" => Http::response(['challenge' => '20261107-CR-1', 'timestamp' => '2026-11-07T10:00:00Z', 'timestampMs' => 1793786400000, 'clientIp' => '1.2.3.4']),
        "{$base}/security/public-key-certificates" => Http::response([
            ['certificate' => $certificate, 'certificateId' => 'c1', 'publicKeyId' => 'k1', 'validFrom' => '2025-01-01T00:00:00Z', 'validTo' => '2030-01-01T00:00:00Z', 'usage' => ['KsefTokenEncryption']],
        ]),
        "{$base}/auth/ksef-token" => Http::response(['referenceNumber' => 'REF-1', 'authenticationToken' => ['token' => 'AUTH-JWT', 'validUntil' => '2026-11-07T10:10:00Z']], 202),
        "{$base}/auth/REF-1" => Http::sequence()
            ->push(['status' => ['code' => 100, 'description' => 'Uwierzytelnianie w toku']])
            ->push(['status' => ['code' => 200, 'description' => 'Uwierzytelnianie zakończone sukcesem']]),
        "{$base}/auth/token/redeem" => Http::response([
            'accessToken' => ['token' => 'ACCESS-JWT', 'validUntil' => now()->addMinutes(15)->toIso8601String()],
            'refreshToken' => ['token' => 'REFRESH-JWT', 'validUntil' => now()->addDays(7)->toIso8601String()],
        ]),
        "{$base}/invoices/query/metadata*" => Http::response([
            'hasMore' => false, 'isTruncated' => false,
            'invoices' => [['ksefNumber' => 'KSEF-1'], ['ksefNumber' => 'KSEF-OLD']],
        ]),
        "{$base}/invoices/ksef/KSEF-1" => Http::response($this->xml, 200, ['Content-Type' => 'application/xml']),
    ]);
    config(['ksef.auth_poll_attempts' => 3]);

    Document::create(['direction' => 'cost', 'source' => 'ksef', 'ksef_number' => 'KSEF-OLD', 'issue_date' => '2026-11-01']);

    $result = app(KsefImporter::class)->import(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'));

    expect($result)->toBe(['found' => 2, 'imported' => 1, 'errors' => []])
        ->and(Document::where('ksef_number', 'KSEF-1')->exists())->toBeTrue()
        ->and(KsefSyncLog::first()->status)->toBe('ok');

    Http::assertSent(function (Request $request) use ($private) {
        if (! str_ends_with($request->url(), '/auth/ksef-token')) {
            return false;
        }
        $decrypted = $private->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256')
            ->decrypt(base64_decode($request['encryptedToken']));

        return $decrypted === 'TOKEN-TESTOWY|1793786400000'
            && $request['contextIdentifier'] === ['type' => 'Nip', 'value' => '8990001122'];
    });

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/invoices/query/metadata')
        && $request->hasHeader('Authorization', 'Bearer ACCESS-JWT')
        && $request['subjectType'] === 'Subject2'
        && $request['dateRange']['dateType'] === 'Issue');
});

it('zapisuje błąd w logu, gdy KSeF odrzuci uwierzytelnienie', function () {
    Setting::put('ksef_nip', '8990001122');
    Setting::put('ksef_token', 'ZLY');
    Http::fake(['*/auth/challenge' => Http::response(['exception' => ['exceptionDetailList' => [['exceptionDescription' => 'Nieprawidłowe żądanie']]]], 400)]);

    $result = app(KsefImporter::class)->import(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-05'));

    expect($result['errors'][0])->toContain('Nieprawidłowe żądanie')
        ->and(KsefSyncLog::first()->status)->toBe('error');
});

/** Samopodpisany certyfikat X.509 (base64 DER) dla klucza testowego. */
function makeCertificate(RSA\PrivateKey $private): string
{
    $x509 = new X509;
    $subject = new X509;
    $subject->setDNProp('id-at-commonName', 'KSeF test');
    $subject->setPublicKey($private->getPublicKey());
    $issuer = new X509;
    $issuer->setPrivateKey($private->withPadding(RSA::SIGNATURE_PKCS1)->withHash('sha256'));
    $issuer->setDN($subject->getDN());
    $pem = $x509->saveX509($x509->sign($issuer, $subject));

    return trim(preg_replace('/-----[^-]+-----|\s+/', '', $pem));
}
