# TIR-ON · Turning Memories ON

[V5 · Modificări, teste și capturi](docs/V5-COMMERCE.md) · [Actualizări din GitHub prin cPanel](docs/HOSTICO-UPDATES.md)

Magazin WordPress și WooCommerce în română, cu tema proprie TIR-ON, sigla oficială V6 și produsul Luna. Prețul standard este 249 RON și se poate modifica în WooCommerce.

Pentru o instalare privată pe Hostico folosește [pachetul cPanel](dist/hostico/) și [ghidul din browser](docs/HOSTICO-DEPLOYMENT.md).

## Previzualizare V4

Deschide [HTML-ul V4](preview/TIR-ON-V4.html) în GitHub, autentificat în contul cu acces. Alege **Download raw file**. Pe Android, deschide fișierul `.html` din **Descărcări**, apoi alege **Deschide cu → Chrome**. Previzualizarea include caruselul, comparația interactivă, galeria și selecția unei fotografii pe dispozitiv. Coșul și checkout-ul sunt disponibile în instalarea WordPress.

[Homepage la 390 px](preview/TIR-ON-V4-390.jpg) · [Homepage la 1440 px](preview/TIR-ON-V4-1440.jpg) · [Hero original și V4](preview/TIR-ON-V4-hero-comparison.jpg)

## Pornire locală

Ai nevoie de Docker Compose v2 și OpenSSL. Din directorul proiectului rulează

```sh
sh setup/start.sh
```

Magazinul local este la [localhost:8080](http://localhost:8080/), iar administrarea la [wp-admin](http://localhost:8080/wp-admin/). Folosește contul `atelier` și parola generată în `.local/secrets/admin_password`. [Instrucțiuni pentru administrare și testare](setup/INSTALARE.md)

## Catalog și comenzi

- **Produse → Luna**. Editează prețul, imaginile, descrierea și detaliile tehnice din **Date produs → Generale**. Imaginile incluse sunt materiale de concept. Înlocuiește-le cu fotografii comerciale din editorul produsului și din **Aspect → Personalizare**.
- **WooCommerce → Comenzi**. Comenzile testate includ produsul, cantitatea și observațiile. Administratorul poate descărca sau șterge fotografia din linia produsului. **WooCommerce → Fotografii private** controlează limita de încărcare și termenele de păstrare.
- **WooCommerce → Setări → Livrare**. Instalarea locală include o opțiune simulată pentru România. Completează zonele și tarifele comerciale înainte de lansare.
- **WooCommerce → Setări → Plăți**. Fluxul local folosește simulatorul TIR-ON. Pluginul oficial Stripe este pregătit exclusiv în modul test și apare în checkout după conectarea contului de test. Apple Pay și Google Pay depind de eligibilitatea dispozitivului, browserului și domeniului.

## Verificare

Rulează `python3 tests/commerce_smoke.py http://localhost:8080/` pe o instalare locală cu livrarea și simulatorul de test active. Scriptul folosește Requests, lxml și Pillow, generează un PNG sintetic la execuție și lasă o comandă de test în WooCommerce. Șterge-o ulterior din administrare, împreună cu fotografia, dacă nu mai ai nevoie de ea.

Am verificat formularul cu încărcări multipart în WordPress și WooCommerce active, checkout-ul fără cont, asocierea fotografiei, fișiere invalide, reluarea trimiterii și expirarea coșului. Am verificat descărcarea, ștergerea și retenția fotografiilor din administrare cu stocarea comenzilor WooCommerce HPOS activată și dezactivată. Paginile Acasă și Luna au fost testate în Chromium la 390 px și 1.440 px. Pentru plăți externe trebuie conectat contul Stripe de test și verificată eligibilitatea portofelelor pe dispozitive reale.

## Înainte de lansare

Rămân de stabilit fotografiile și specificațiile comerciale, tarifele de livrare și taxele, datele operatorului și textele juridice verificate. Mediul de lansare are nevoie de HTTPS, volum privat cu backup și retenție configurată, e-mailuri funcționale, verificare SEO și configurarea consimțământului înainte de orice urmărire opțională. Modul live Stripe necesită aprobarea explicită a proprietarului.
