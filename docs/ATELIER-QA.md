# Atelier TIR-ON · Verificări

Testele au rulat local cu WordPress, WooCommerce, PHP-CGI și baze de date izolate. Comenzile au folosit vizitatori sintetici și simulatorul local, fără tranzacții sau e-mailuri externe. Backendul WooCommerce a fost configurat pe rând cu HPOS și stocarea clasică.

| Acoperire | Rezultat |
|---|---|
| Comenzi | 24 de comenzi reale WooCommerce în mediul de test, câte 12 pe fiecare backend. Au inclus 30 de fotografii asociate unor linii distincte, portret și peisaj, JPG/PNG/WebP, cantități variate și comenzi cu sau fără observații. |
| Plată și continuitate | 22 de comenzi confirmate prin simulator și două refuzate intenționat. Reîncercarea după refuz a refolosit comanda. Retrimiterea identică și cererile concurente au păstrat o singură comandă. Un câmp obligatoriu nevalid în checkout a păstrat coșul. |
| Formulare și sesiuni | PASS pentru fișier lipsă, conținut nevalid, format neacceptat, limită depășită, nonce din altă sesiune și expirarea coșului abandonat. Un upload respins a restaurat o singură dată cantitatea și notele, fără datele fișierului. |
| Producție | PASS pentru toate cele șapte etape, filtre, cantități și note de control al calității. Două salvări cu aceeași revizie au produs o salvare și un conflict. Starea WooCommerce, totalul, plata și fotografiile au rămas la valorile comenzii. |
| Acces și dependențe | PASS pentru ID lipsă/nevalid, nonce nevalid, accesul unui vizitator sau client, linia din altă comandă și module lipsă. Previzualizarea și descărcarea funcționează numai cu acces administrativ și răspuns privat fără cache. |
| Chromium | PASS la 390 și 1440 px pentru panou și detaliu, fără overflow sau erori JS. La aceleași lățimi au trecut previzualizarea clientului, ghidul circular, un portret și un peisaj, avertismentul de rezoluție, înlocuirea și verificarea că octeții selectați rămân aceiași. |
| Pachet și previzualizare | PASS pentru ZIP-uri și sumele SHA-256. Atelierul a funcționat local din arhivele Hostico extrase. Fișierul V4 offline a afișat ghidul la 390 px, cu comanda dezactivată și zero cereri de rețea. |

## Repetare locală

- Cu mediul WordPress `.runtime` pregătit, instalează Requests, lxml și Pillow pentru Python. Rulează `TIRON_PHP_CGI=/cale/către/php-cgi python3 tests/atelier-operations.py`. Driverul clonează baza SQLite de test și creează un director privat gol. Există și opțiuni pentru un singur backend și pentru un alt driver local.
- Rulează `CHROMIUM_EXECUTABLE=/cale/către/chromium node tests/customer-photo.cjs http://127.0.0.1:8080` pe o instalare de QA accesibilă prin loopback, cu Playwright disponibil.
- Rulează `python3 tests/hostico-package.py` pentru structura și integritatea arhivelor.

Hostico și o plată prin Stripe TEST extern rămân de verificat pe contul real. [Fluxul atelierului](ATELIER-OPERATIONS.md) · [Experiența clientului](CUSTOMER-PHOTO-EXPERIENCE.md)
