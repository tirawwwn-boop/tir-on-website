# V7 · Rezultate de verificare

Verificări locale cu WordPress 7.1.5, WooCommerce 11.3.1, PHP 8.4.27 și SQLite. Datele de test sunt sintetice.

| Verificare executată | Rezultat |
|---|---|
| Upgrade direct | Am extras ambele ZIP-uri Hostico din commitul exact V5 `5127a78c54242731d367c85b64af8621f8516891`, am creat o comandă și setări V5, apoi am aplicat direct ambele ZIP-uri V7. Aceeași instalare a trecut prin V5 → V7 → V5 → V7. |
| Persistență și schemă | Definițiile tabelelor au rămas identice. Configurația V5, datele comenzii, nota, asocierea fotografiei, fișierul privat de control, stadiul Atelier, opțiunea Profit, prețul, setările Stripe și guest checkout au trecut comparația înainte/după. Opțiunea Studio era absentă la upgrade și a fost creată la salvare. V5 a afișat din nou coperta originală după rollback. V7 a recuperat ultima publicare la reinstalare. |
| Publicare | Verificate salvarea pe secțiune, persistența după reîncărcare, previzualizarea privată cu `no-store`, confirmarea explicită, vizibilitatea pentru vizitatori după publicare, resetarea și restaurarea unei revizii. O salvare cu versiune expirată a primit HTTP 409. |
| Acces și validare | Cererea fără nonce valid și cererile abonatului au primit HTTP 403. Vizitatorul anonim și abonatul nu au putut deschide ciorna cu linkul administratorului. Un fișier din directorul privat, un URL cu schemă nepermisă și o comparație incompletă au fost respinse. Marcajul HTML a fost eliminat din text. |
| Conținut publicat | Verificat pe paginile reale: logo public selectat, două cadre în carusel, secțiune ascunsă, FAQ, anunț, e-mail, galerie, ambalaj, titlu și descriere SEO. Luna a afișat 249 RON. |
| Interfață | Chromium la 360, 390, 430 și 1440 px. Deschiderea dialogului Media, navigarea între file, completarea, salvarea și previzualizarea au trecut, fără depășire orizontală sau excepții JavaScript în scenariile executate. |
| Regresii comerciale | Fișiere valide și invalide, sesiuni de coș separate, cantități și totaluri, cupoane, telefon obligatoriu, comandă guest cu simulatorul local, confirmare, notă și fotografie privată. Au trecut și tarifele RO, datele structurate, metadatele sociale, sitemap și deduplicarea marketing cu transport simulat. |
| Pachete și rollback de fișiere | ZIP-urile au trecut verificarea de integritate și comparația cu sursele. Testul mecanismului cPanel a aplicat planul pe o instalare sintetică pornind de la SHA-ul V5, a refuzat fișiere modificate local și a restaurat exact conținutul anterior, păstrând fișierele neadministrate. |

## Reproducere în mediul de dezvoltare

Folosește o instalare de test `.runtime` și configurează `TIRON_PHP_CGI` pentru PHP CGI. Testele creează copii temporare. Suplimentar, testul vizual folosește Chromium, Playwright și portul local 4173.

```sh
python3 tests/v7-upgrade.py
python3 tests/v7-browser.py
python3 tests/v5-commerce.py
python3 tests/v6-launch.py
python3 tests/hostico-package.py
TIRON_QA_RELEASE_BASE=5127a78c54242731d367c85b64af8621f8516891 python3 tests/v5-hostico.py
```

Pentru un Chromium deja instalat, setează `CHROMIUM_EXECUTABLE`. Dacă Playwright este instalat în alt director, setează `PLAYWRIGHT_MODULE`. Capturile se scriu în `docs/media/v7-studio-390.png` și `docs/media/v7-studio-1440.png`.

## Limitele verificării

Instalarea Hostico reală, MySQL/MariaDB din producție, Safari pe iPhone fizic, Chrome pe un telefon Android fizic și o tranzacție cu serviciul Stripe rămân de verificat pe staging. Suita de upgrade folosește codul și pachetele versiunilor exacte, cu baze de date sintetice. Extensiile suplimentare și setările specifice contului Hostico pot influența rezultatul.
