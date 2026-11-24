# Instalare locală TIR-ON

Ai nevoie de Docker cu Compose v2 și de OpenSSL. Rulează comenzile în Terminal pe macOS, Linux sau Windows cu WSL2 și Docker Desktop. Prima instalare descarcă imaginile Docker și pachetele oficiale WordPress.org. Portul local 8080 trebuie să fie liber.

Din directorul proiectului rulează

```sh
sh setup/start.sh
```

Comanda inițializează WordPress în română, activează WooCommerce, pluginul oficial Stripe și tema TIR-ON și creează produsul Luna. Secretele sunt generate separat pentru fiecare instalare. Le găsești în directorul local protejat `.local/secrets/`, exclus din Git.

| Destinație | Adresă locală |
| --- | --- |
| Acasă | http://localhost:8080/ |
| Luna | http://localhost:8080/produs/luna/ |
| Administrare | http://localhost:8080/wp-admin/ |

Contul de dezvoltare este `atelier`. Parola inițială se află în `.local/secrets/admin_password`. Deschide fișierul local pentru autentificare. WordPress și MariaDB păstrează datele în volume Docker. La prima rulare cu V4, scriptul actualizează denumirea și prețul standard al produsului existent la Luna și 249 RON și elimină prețul promoțional, dacă există. Rulările ulterioare păstrează modificările din WooCommerce.

Pentru a opri serviciile și a păstra catalogul

```sh
docker compose stop
```

Pentru diagnosticare

```sh
docker compose ps
docker compose logs --tail=50 wordpress db
docker compose run --rm -T cli wp plugin list
```

## Administrarea produsului

1. Deschide **Produse → Toate produsele → Luna**.
2. În **Date produs → Generale**, poți modifica prețul standard de 249 RON. Tot aici poți actualiza diametrul, alimentarea, sursa de lumină, finisajul și termenul de producție. Termenul pornește gol.
3. Actualizează descrierile, imaginea produsului și galeria în editorul obișnuit WooCommerce. Folosește aici fotografiile comerciale ale brandului. Fotografiile personale ale clienților intră numai prin formularul de personalizare și volumul privat.
4. Apasă **Actualizează** și reîncarcă pagina Luna pentru a verifica rezultatul.
5. Pentru Aura sau Zenit, folosește **Produse → Adaugă produs**. Paginile proprii acestor modele și fotografiile lor pot fi pregătite când sunt disponibile.

În **WooCommerce → Setări** se pot configura livrarea, taxele și conturile. Catalogul pornește în RON, cu România ca piață și cu finalizarea fără cont configurată. Tarifele de transport și regimul fiscal așteaptă datele comerciale finale.

## Configurația acestei versiuni

| Componentă | Versiune fixată |
| --- | --- |
| WordPress | 7.1.5 |
| PHP pentru Docker | 8.3 |
| WooCommerce | 11.3.1 |
| WooCommerce Stripe Gateway | 11.2.0 |
| MariaDB | 11.4.13 |
| WP-CLI | 2.12.0 |

Tema se află în `theme/tir-on/`, iar extensiile proprii în `local/mu-plugins/`. Serviciul web Docker ascultă la `127.0.0.1:8080`. MariaDB este accesibilă numai în rețeaua internă Docker. Instalarea inițială dezactivează indexarea. E-mailurile sunt oprite în mediul WordPress local.

## Comenzile și fotografiile clienților

Din pagina Luna, alege un JPG, PNG sau WebP și verifică imaginea înainte de adăugarea în coș. WordPress validează formatul real, dimensiunea și rezoluția, apoi salvează un JPEG reencodat, fără metadatele originale. Observațiile sunt opționale. Fiecare personalizare are o linie separată în coș.

Din **WooCommerce → Comenzi**, deschide comanda. Administratorul găsește fotografia și observațiile sub produs. Descărcarea este disponibilă după autentificare. **Șterge fotografia…** deschide o confirmare și elimină fișierul personalizat, păstrând datele comenzii.

Setările din **WooCommerce → Fotografii private** pornesc cu o limită de 10 MB, două zile pentru fotografiile de coș și 30 de zile după închiderea comenzii. Limita efectivă ține cont de configurația PHP. Curățarea rulează prin WP-Cron. Dacă mediul oprește WP-Cron, programează executarea evenimentelor scadente prin WP-CLI pe acel mediu.

Volumul Docker `private_photos` este montat în `/var/lib/tiron/photos`, separat de directorul web. Păstrează volumul la repornire și aplică aceeași protecție și retenție backupurilor. La o instalare echivalentă, setează `TIRON_PRIVATE_PHOTO_DIR` la un director cu acces `0700`, deținut de procesul PHP, în afara rutelor publice și a aliasurilor serverului. Folosește `TIRON_ADDITIONAL_PUBLIC_ROOTS` pentru rădăcinile publice suplimentare. Directorul privat trebuie să suporte blocarea POSIX a fișierelor.

## Plăți și livrare de test

Instalarea locală activează simulatorul TIR-ON și o metodă de livrare simulată pentru România dacă magazinul nu are deja zone sau metode configurate. Comanda simulată rămâne **În așteptare**, fără debitare și fără expediere.

Pentru un test Stripe efectiv, conectează contul de test prin **WooCommerce → Setări → Plăți → Stripe**. Completează datele de test și configurarea webhook de test din pluginul oficial. Aplicația acceptă exclusiv chei de test. Formularul de card este furnizat de Stripe Elements. În setările Stripe, activează Apple Pay și Google Pay pentru coș sau checkout. Verifică pe adresa HTTPS privată de test cu domeniul verificat și un dispozitiv eligibil. Introdu datele sensibile doar în interfața de administrare sau prin mecanismul de secrete al mediului.
