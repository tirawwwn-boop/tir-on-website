# Instalare locală TIR-ON

Ai nevoie de Docker cu Compose v2 și de OpenSSL. Rulează comenzile în Terminal pe macOS, Linux sau Windows cu WSL2 și Docker Desktop. Prima instalare descarcă imaginile Docker și pachetele oficiale WordPress.org. Portul local 8080 trebuie să fie liber.

Din directorul proiectului rulează

```sh
sh setup/start.sh
```

Comanda inițializează WordPress în română, activează WooCommerce și tema TIR-ON și creează produsul Luna. Secretele sunt generate separat pentru fiecare instalare. Le găsești în directorul local protejat `.local/secrets/`, exclus din Git.

| Destinație | Adresă locală |
| --- | --- |
| Acasă | http://localhost:8080/ |
| Luna | http://localhost:8080/produs/luna/ |
| Administrare | http://localhost:8080/wp-admin/ |

Contul de dezvoltare este `atelier`. Parola inițială se află în `.local/secrets/admin_password`. Deschide fișierul local pentru autentificare. WordPress și MariaDB păstrează datele în volume Docker. Scriptul poate fi rulat din nou, inclusiv după repornirea calculatorului. Inițializarea catalogului păstrează modificările efectuate după prima instalare.

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

1. Deschide **Produse → Toate produsele → TIR-ON Luna**.
2. În **Date produs → Generale**, modifică prețul standard de 299 RON. Aici poți actualiza și diametrul, alimentarea, sursa de lumină, finisajul și termenul de producție. Termenul pornește gol.
3. Actualizează descrierile, imaginea produsului și galeria în editorul obișnuit WooCommerce. Folosește aici fotografiile comerciale ale brandului. Fotografiile personale ale clienților necesită fluxul privat din etapa 3.
4. Apasă **Actualizează** și reîncarcă pagina Luna pentru a verifica rezultatul.
5. Pentru Aura sau Zenit, folosește **Produse → Adaugă produs**. Paginile proprii acestor modele și fotografiile lor pot fi pregătite când sunt disponibile.

În **WooCommerce → Setări** se pot configura livrarea, taxele și conturile. Catalogul pornește în RON, cu România ca piață și cu finalizarea fără cont configurată. Tarifele de transport și regimul fiscal așteaptă datele comerciale finale.

## Configurația acestei versiuni

| Componentă | Versiune fixată |
| --- | --- |
| WordPress | 7.1.5 |
| PHP pentru Docker | 8.3 |
| WooCommerce | 11.3.1 |
| MariaDB | 11.4.13 |
| WP-CLI | 2.12.0 |

Tema se află în `theme/tir-on/`. Fișierul `local/mu-plugins/tir-on-catalog.php` adaugă câmpurile produsului în administrare. Fișierul `local/mu-plugins/tir-on-local-preview.php` menține magazinul în evaluare vizuală. Blochează cumpărarea în WooCommerce clasic și în Store API, ascunde metodele de plată și oprește trimiterea e-mailurilor WordPress. În această etapă, experiența de pe pagina Luna poate fi verificată fără o comandă reală.

Serviciul web Docker ascultă exclusiv la `127.0.0.1:8080`; baza de date este accesibilă numai în rețeaua internă Docker. Instalarea inițială solicită motoarelor de căutare să evite indexarea. Stripe, pixelii publicitari și conectarea unui domeniu fac parte din etapele ulterioare.
