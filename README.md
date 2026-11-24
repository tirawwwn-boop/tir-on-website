# TIR-ON · Turning Memories ON

Magazin WordPress și WooCommerce în română, cu tema proprie TIR-ON, sigla oficială V6 și produsul Luna. Prețul inițial este 299 RON și se modifică în WooCommerce.

## Previzualizarea V3.1

Pe branch-ul `design-v3-refined`, V3.1 păstrează structura editorială espresso și ivory și actualizează textele de pe homepage. Sloganul este sub sigla V6, iar portretul din Hero ocupă mai mult spațiu pe mobil. În Transformarea, butoanele schimbă vederea completă în detaliul mărit al aceleiași imagini cu Luna stinsă. O comparație stinsă/aprinsă are nevoie de două imagini cu același cadraj.

Deschide [HTML-ul V3.1](preview/TIR-ON-V3.1.html) în GitHub, autentificat în contul cu acces, și alege **Download raw file**. Pe Android, deschide fișierul din **Descărcări** cu Chrome. Previzualizarea include navigarea, galeria și selecția locală a unei fotografii. Coșul și checkout-ul se folosesc din WordPress.

[Captură la 390 px](preview/TIR-ON-V3.1-390.jpg) · [Captură la 1440 px](preview/TIR-ON-V3.1-1440.jpg) · [HTML-ul V3 pentru comparație](preview/TIR-ON-V3.html)

## Pornire locală

Ai nevoie de Docker Compose v2 și OpenSSL. Din directorul proiectului rulează

```sh
sh setup/start.sh
```

Magazinul local este la [localhost:8080](http://localhost:8080/), iar administrarea la [wp-admin](http://localhost:8080/wp-admin/). Folosește contul `atelier` și parola generată în `.local/secrets/admin_password`. [Instrucțiuni pentru administrare și testare](setup/INSTALARE.md)

## Catalog și comenzi

- **Produse → TIR-ON Luna**. Editează prețul, imaginile, descrierea și detaliile tehnice din **Date produs → Generale**. Imaginile incluse sunt materiale de concept. Înlocuiește-le cu fotografii comerciale din editorul produsului și din **Aspect → Personalizare**.
- **WooCommerce → Comenzi**. Comenzile testate includ produsul, cantitatea și observațiile. Administratorul poate descărca sau șterge fotografia din linia produsului. **WooCommerce → Fotografii private** controlează limita de încărcare și termenele de păstrare.
- **WooCommerce → Setări → Livrare**. Instalarea locală include o opțiune simulată pentru România. Completează zonele și tarifele comerciale înainte de lansare.
- **WooCommerce → Setări → Plăți**. Fluxul local folosește simulatorul TIR-ON. Pluginul oficial Stripe este pregătit exclusiv în modul test și apare în checkout după conectarea contului de test. Apple Pay și Google Pay depind de eligibilitatea dispozitivului, browserului și domeniului.

## Verificare

Rulează `python3 tests/commerce_smoke.py http://localhost:8080/` pe o instalare locală cu livrarea și simulatorul de test active. Scriptul folosește Requests, lxml și Pillow, generează un PNG sintetic la execuție și lasă o comandă de test în WooCommerce. Șterge-o ulterior din administrare, împreună cu fotografia, dacă nu mai ai nevoie de ea.

Am verificat formularul cu încărcări multipart în WordPress și WooCommerce active, checkout-ul fără cont, asocierea fotografiei, fișiere invalide, reluarea trimiterii și expirarea coșului. Am verificat descărcarea, ștergerea și retenția fotografiilor din administrare cu stocarea comenzilor WooCommerce HPOS activată și dezactivată. Paginile Acasă și Luna au fost testate în Chromium la 360, 390, 430 și 1.440 px. Pentru plăți externe trebuie conectat contul Stripe de test și verificată eligibilitatea portofelelor pe dispozitive reale.

## Înainte de lansare

Rămân de stabilit fotografiile și specificațiile comerciale, tarifele de livrare și taxele, datele operatorului și textele juridice verificate. Mediul de lansare are nevoie de HTTPS, volum privat cu backup și retenție configurată, e-mailuri funcționale, verificare SEO și configurarea consimțământului înainte de orice urmărire opțională. Modul live Stripe necesită aprobarea explicită a proprietarului.
