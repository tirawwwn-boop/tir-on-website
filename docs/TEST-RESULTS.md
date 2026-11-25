# Verificări locale pentru pachetul Hostico

WordPress **7.1.5**, WooCommerce **11.3.1**, WooCommerce Stripe Gateway **11.2.0**, PHP **8.4.27**. Instanțele de test au folosit copii izolate ale bazei locale, PHP-CGI și un server loopback pentru Chromium. Docker Compose a validat configurația. Instalarea containerelor s-a oprit la extragerea imaginilor din lipsă de spațiu în stocarea Docker, astfel că suitele de mai jos au rulat în mediul local existent.

| Scenariu | Rezultat efectiv |
|---|---|
| Arhive și configurare | PASS. Verificare ZIP, SHA-256 și potrivirea cu sursele. Am extras ambele arhive în WordPress izolat și am rulat configurarea din browser. Cererile fără autentificare sau cu nonce invalid au fost refuzate. Rularea repetată a fost sigură. Eliminarea modulului Stripe TEST a blocat cumpărarea, API-ul Stripe și webhook-ul. |
| Homepage și Luna | PASS la 360, 390, 430 și 1440 px în Chromium. Navigație, CTA mobil, carusel, lumină stinsă/aprinsă, imagini decodate, 249 RON, lipsa erorilor de consolă și a derulării orizontale. |
| Încărcare | PASS. PNG sintetic acceptat și re-encodat în JPEG. Fișierul lipsă, extensia respinsă, conținutul nevalid și fișierul de peste 10 MB au fost respinse cu revenire la formular. Când permisiunile directorului privat au devenit nesigure, formularul a devenit indisponibil. |
| Coș, sesiune și checkout | PASS. Două bucăți, total 498 RON, checkout ca vizitator, confirmare, adresă și date de contact, observații și asocierea fotografiei cu linia comenzii. Reîncercările de upload identice au păstrat un singur articol, inclusiv concurent. Coșul abandonat a expirat, iar tokenul din altă sesiune a fost refuzat. |
| Plată refuzată | PASS cu simulatorul local. Afișează eroare, păstrează coșul, apoi confirmă numai după reîncercare. Refuzul și reîncercarea au folosit aceeași comandă. |
| Admin și protecția fotografiilor | PASS în HPOS și CPT. Fotografia a fost disponibilă în admin pentru comanda corectă, cu antete private. Clientul, vizitatorul, nonce-ul eronat și ID-ul de linie străin au fost refuzate. Setările, ștergerea și retenția pentru înregistrările expirate au trecut. |
| Stripe | PASS pentru gardurile locale, inclusiv respingerea cheilor live, evenimentelor live și cererilor nesemnate. Plata a fost simulată local. O plată prin contul Stripe TEST, wallet-urile și webhook-ul prin Hostico necesită acces și configurare. |
| E-mail și SEO | PASS. Cele șase tipuri WooCommerce au generat HTML cu textele configurate și expedierea oprită. Verificate local noindex, canonice, Open Graph și schema WooCommerce cu RON, fără recenzii fabricate. |

## Repetare în mediul local de QA

- `python3 scripts/package-hostico.py && python3 tests/hostico-package.py` verifică pachetul.
- `python3 tests/regression.py http://localhost:8080/` rulează verificările HTTP cu Requests, lxml și Pillow, pe instalarea locală cu simulatorul și livrarea de test active. Creează comenzi sintetice în baza de QA.
- Pentru refuzul de plată, adaugă `tests/fixtures/decline-gateway.php` în `wp-content/mu-plugins/` **doar în instalația locală**, apoi rulează cu `--wp-cli 'wp --path=/calea/instalarii-locale'`. Verifică datele comenzii reușite prin `wp eval-file tests/verify-order.php ID_COMANDA` în aceeași instanță.
- `python3 tests/admin-regression.py http://localhost:8080/ ID_COMANDA UTILIZATOR_ADMIN` verifică fotografia comenzii sintetice și refuzul accesului fără autorizare. Parola se introduce interactiv, în mediul local.
- `node tests/browser-regression.cjs http://localhost:8080` folosește Playwright și Chromium în mediul de test. Node.js și WP-CLI sunt pentru verificare locală și nu fac parte din procedura cPanel.

Hostico, PHP/MySQL pe contul comandat, DNS, TLS, SMTP real, Lighthouse pe conexiune mobilă și Stripe extern rămân neverificate. Nu s-au trimis e-mailuri și nu s-au inițiat tranzacții reale.
