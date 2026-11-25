# Audit tehnic TIR-ON

Bază auditată `design-v4-original-premium` la `1cc6c06`. Tema V4 deține interfața. WooCommerce deține prețul, sesiunile, coșul, checkout-ul clasic, comenzile și stările de plată. `theme/tir-on/` include Hero-ul, comparatorul, pagina Luna și fișierele locale CSS, JS, fonturi și imagini. `local/mu-plugins/` încarcă detaliile de catalog, personalizarea, stocarea privată, administrarea și gardul Stripe TEST. Simulatorul de plată și livrarea simulată sunt locale. `setup/` și Compose gestionează dezvoltarea. `scripts/runtime-*` exportă previzualizările fără conectare la checkout.

| Severitate | Constatare | Acțiune |
|---|---|---|
| Critical | Stocarea trebuie să fie în afara tuturor rădăcinilor web, sub utilizatorul PHP, cu permisiuni private și blocare de fișiere. Compatibilitatea contului Hostico este de confirmat. | Verificare în administrare pentru permisiuni, formate GD, limită și blocare. Formularul devine indisponibil dacă precondițiile de stocare sau decodare eșuează. |
| High | Instalarea existentă pornea din Docker și WP-CLI. Copierea manuală a fișierelor lăsa neconfigurate catalogul, paginile clasice și RON. | Două arhive și configurare protejată prin permisiune și nonce în WordPress. Refolosește seed-ul existent și refuză să îl ruleze peste produse sau comenzi. |
| High | Lipsea o instalare de verificare cu oprirea garantată a e-mailurilor și indexării. Copierea incompletă a modulului Stripe putea produce o eroare fatală. | Versiunea Hostico oprește expedierea și indexarea. Loaderul MU dezactivează cumpărarea și metodele la instalare incompletă și afișează o eroare admin. Modulul de simulare este exclus din arhivă. |
| High | Nonce-ul Luna depinde de sesiunea Woo. Un page cache configurat greșit ar servi un formular din altă sesiune. | Antete Woo, marcaj `DONOTCACHEPAGE`, semnal către LiteSpeed și instrucțiuni de excludere. Cache-ul la nivel de host/CDN rămâne de verificat. |
| Medium | Tema personalizată nu invoca producerea schemei WooCommerce a produsului. Meta produsului se aplica și altor produse. | Schema oficială pentru Luna, meta și titluri specifice, Open Graph cu imagine JPEG de concept și URL canonic prin WordPress. |
| Medium | E-mailurile aveau setările standard Woo. Retenția depinde de WP-Cron, iar autentificarea cPanel poate bloca loopback-ul și webhook-ul Stripe. | Mesaje și paletă TIR-ON în șabloanele Woo, ghid SMTP/DNS și verificări operaționale pentru cron și Stripe TEST. |
| Low | Configurația de dezvoltare conține căi absolute locale intenționate, iar designul folosește imagini de concept. | Pachetul cPanel primește separat calea privată. Imaginile oficiale se pot înlocui în WordPress după aprobare. |

## Securitate și performanță

Upload-ul necesită multipart nativ, extensie, Fileinfo, dimensiuni și decodare GD. În stocare este scris un JPEG re-encodat, cu ID aleatoriu și fără numele original, GPS sau EXIF. Legătura cu coșul este semnată pentru sesiune, iar WooCommerce păstrează referința pe linia comenzii. Descărcarea și ștergerea verifică rolul, comanda și nonce-ul. Interogarea pentru retenție folosește parametri pregătiți și oprește ștergerea dacă baza răspunde cu eroare. Testele au verificat accesul admin, refuzul oaspeților și al clienților, expirarea și ambele moduri HPOS/CPT.

Hero-ul păstrează WebP-ul mobil de **67 KiB**, cu aproximativ **33%** mai mic decât cel desktop de **100 KiB**. Prima imagine are prioritate și celelalte cadre sunt încărcate la selectare. Imaginile secundare sunt lazy, fonturile sunt locale, iar stilurile și scripturile dedicate se încarcă pe rutele relevante. Cache-ul de pagină rămâne o decizie de configurare după verificarea sesiunilor. Rezultatele de latență și scorurile pe conexiuni mobile reale depind de Hostico și de fotografiile comerciale.

Gardul Stripe impune chei de test și respinge evenimentele live înainte de procesare. Câmpurile de card și eligibilitatea portofelelor sunt gestionate de extensia oficială. Activarea contului, autentificarea SMTP și accesul la cPanel rămân acțiuni separate. [Instalare și revenire](HOSTICO-DEPLOYMENT.md) · [Rezultatele testelor](TEST-RESULTS.md)
