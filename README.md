# Księgowość

Prywatna aplikacja do rozliczeń jednoosobowej działalności: skala podatkowa, czynny VAT, pełny ZUS. Pobiera faktury z KSeF 2.0, przyjmuje dokumenty dodawane ręcznie, sama przypisuje kategorie i liczy miesięczne rozliczenie (dochód, PIT, ZUS, zdrowotna, VAT, podział środków na koncie).

Technologia: Laravel 12, Filament 4, MySQL/MariaDB. Na serwerze nie potrzeba Node ani crona.

## Uruchomienie lokalne (XAMPP)

1. Uruchom MySQL w XAMPP i utwórz bazę `ksiegowosc`.
2. `composer install`
3. Skopiuj `.env.example` do `.env`. Ustaw `DB_*`, `ADMIN_EMAIL` i `ADMIN_PASSWORD`, potem `php artisan key:generate`.
4. `php artisan migrate --seed` (kategorie, reguły, grupy stacji, szablony paliwowe, parametry 2025–2026, konto logowania).
5. `php artisan serve` i otwórz http://localhost:8000.

Jeśli połączenie z KSeF kończy się błędem `cURL error 60`, PHP nie ma pakietu certyfikatów CA. Wskaż go w `KSEF_CA_BUNDLE` w `.env`, np. plik `ca-bundle.crt` z instalacji Gita.

Testy: `./vendor/bin/pest`. Używają SQLite w pamięci i nie dotykają bazy MySQL.

## Wdrożenie na hosting współdzielony

1. Wymagania: PHP 8.2+ z rozszerzeniami `intl`, `pdo_mysql`, `mbstring`, `openssl`, `dom`, `curl`, `zip`.
2. Lokalnie: `composer install --no-dev --optimize-autoloader`.
3. Wgraj cały katalog (z `vendor/`) przez SSH/git albo FTP. Nie wgrywaj lokalnego `.env`.
4. Na serwerze utwórz `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://twoja-domena`, dane bazy, nowy `APP_KEY`.
5. Ustaw katalog `public/` jako document root domeny. Jeśli hosting na to nie pozwala, plik `.htaccess` w katalogu głównym przekieruje ruch do `public/`.
6. Przez SSH: `php artisan migrate --seed --force`, potem `php artisan optimize` i `php artisan filament:optimize`.
   Bez SSH: zaimportuj bazę wyeksportowaną lokalnie.
7. Katalogi `storage/` i `bootstrap/cache/` muszą być zapisywalne.

Kolejnego użytkownika można dodać poleceniem `php artisan make:filament-user`.

## Jak korzystać

- **KSeF** (Ustawienia → KSeF): środowisko, NIP i token z Aplikacji Podatnika (uprawnienie do przeglądania faktur). Token jest szyfrowany kluczem `APP_KEY`. Przycisk „Sprawdź połączenie” wykonuje pełne uwierzytelnienie.
- **Pobieranie**: Dokumenty → „Pobierz z KSeF” → zakres dat. Faktury zakupowe trafiają do zakładki „Do decyzji”, gdzie akcjami masowymi oznaczasz je jako ujęte w kosztach albo pominięte. Faktury sprzedażowe od razu trafiają do przychodów.
- **Dokumenty ręczne**: „Nowy dokument” (faktura spoza KSeF, przelew ZUS, wypłata promocji) albo „Z szablonu”, czyli szybka faktura paliwowa: data, numer, litry, kwota brutto, a netto, VAT i kategoria liczą się same.
- **Kategorie** są przypisywane automatycznie w kolejności: reguła na nazwę towaru, reguła na NIP lub nazwę sprzedawcy, domyślna kategoria kontrahenta. Zmiana kategorii ręcznie (na dokumencie albo w widoku „Pozycje faktur”) nie jest nadpisywana.
- **Kontrahenci** mają nazwę skróconą i nazwę grupową (np. wszystkie stacje Orlen to grupa „Orlen”). Nowi kontrahenci trafiają do grupy automatycznie według fragmentów nazwy zdefiniowanych w grupie. Opcja „Osobna sekcja w rozliczeniu” wydziela kontrahenta, np. Joannę Kokot, w rozliczeniu miesiąca.
- **Rozliczenie miesiąca**: przychody, inne przychody, sekcje kosztów, dochód, PIT, ZUS, zdrowotna, VAT oraz konto firmowe. Saldo konta, inne przychody i rezerwę wpisuje się w „Dane miesiąca”. Raty kredytów ustawia się w Ustawienia → Kredyty. „Zamknij miesiąc” zapisuje wyliczenia na stałe.
- **Prognoza dochodu** (Rozliczenia → Prognoza dochodu): model dochodu na wybrany miesiąc. Koszty są zaczytywane z dokumentów (KSeF i ręcznych, opcjonalnie także tych czekających na decyzję). Przychody wpisuje się ręcznie: Kruk (wynagrodzenie podstawowe i dodatki, zaczytywane z faktury za miesiąc, jeśli już jest) oraz inne przychody. Fakturę Joanny Kokot (NIP 9161389290) liczy się z liczby godzin × stawka plus dodatkowe wynagrodzenie (stawka podpowiada się z poprzedniego miesiąca), a obok widać netto, brutto i obciążenia wystawcy (VAT + PIT wg wpisanej stawki, domyślnie 12% od netto); a jej faktura z KSeF jest wtedy pomijana w kosztach, żeby nie liczyć jej dwa razy. Wynik to prognozowany dochód, dochód narastająco, średni dochód w roku i odchylenie od dotychczasowej średniej. Wpisane wartości zapisują się automatycznie, a „Zaczytaj z dokumentów” przywraca dane z faktur. Rozpoznawanie faktury Kruka i jej pozycji z dodatkami ustawia się w `config/forecast.php` (opcjonalnie `FORECAST_KRUK_NIP` w `.env`).
- **Zestawienie roczne**: przychody, wydatki, dochód i dochód narastający dla każdego miesiąca, sumy roczne i średni dochód.

## Zasady wyliczeń

- Koszt PIT pozycji = (netto + nieodliczony VAT) × % PIT kategorii. VAT odliczony = VAT × % VAT kategorii. Paliwo i eksploatacja auta mieszanego: 75% PIT i 50% VAT.
- Kategoria „ZUS społeczne” pomniejsza dochód w miesiącu zapłaty. „Składka zdrowotna” na skali nie wpływa na PIT.
- Zaliczka PIT liczona jest narastająco od stycznia: podatek wg skali od (dochód − składki społeczne) minus zaliczki należne za poprzednie miesiące.
- Składka zdrowotna to 9% dochodu z poprzedniego miesiąca (pomniejszonego o składki społeczne), nie mniej niż 9% minimalnej podstawy. Minimalna podstawa obowiązuje od wskazanego miesiąca do miesiąca przed kolejnym wpisem (Ustawienia → Minimalna zdrowotna), bo zmienia się w trakcie roku, np. od lutego do stycznia.
- VAT = należny − naliczony − nadwyżka z poprzednich miesięcy. Nadwyżka przechodzi na kolejny miesiąc.
- Stawki, progi i podstawę ZUS edytuje się na każdy rok w Ustawienia → Parametry podatkowe. Wartości startowe na 2025–2026 trzeba zweryfikować.

## Na przyszłość

Model danych przewiduje wystawianie faktur (`source = issued`): formularz, generowanie FA(3), wysyłka do KSeF i PDF.
