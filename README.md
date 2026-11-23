# TIR-ON · Turning Memories ON

Prima versiune pentru feedback reunește o instalare locală WordPress + WooCommerce, o temă construită pentru TIR-ON și paginile Acasă și Luna. Prețul de lucru este 299 RON.

## Previzualizare

**Pentru a vedea designul imediat**, extrage întreaga arhivă de previzualizare și deschide `index.html` în browser. Apasă **Creează-ți Luna** pentru pagina produsului. Poți încerca galeria, comparația dintre fotografie și litofan și personalizarea în browser. Exportul offline reflectă catalogul din momentul generării.

**Pentru catalogul editabil și administrare**, extrage proiectul pe calculator. Cu Docker Compose v2 și OpenSSL disponibile, rulează din directorul care conține `docker-compose.yml`

```sh
sh setup/start.sh
```

Deschide [pagina principală](http://localhost:8080/), [pagina Luna](http://localhost:8080/produs/luna/) sau [administrarea WordPress](http://localhost:8080/wp-admin/). Utilizatorul local este `atelier`. Parola inițială se găsește în `.local/secrets/admin_password`. Detaliile de instalare, oprire și diagnosticare sunt în [ghidul local](setup/INSTALARE.md).

## Etapa 1 — Mediul și catalogul

WordPress 7.1.5, WooCommerce 11.3.1, PHP 8.3 și MariaDB 11.4.13 formează configurația Docker. Catalogul folosește limba română, moneda RON și adrese lizibile. Tema se află în `theme/tir-on/`. Instalarea creează produsul Luna o singură dată. Repornirea păstrează prețul, fotografiile și modificările din administrare.

În această versiune, comenzile, metodele de plată și trimiterea e-mailurilor magazinului sunt blocate. Raportarea utilizării WooCommerce și atribuirea sursei comenzilor sunt dezactivate. Accesul web prin Docker este limitat la calculatorul local.

## Etapa 2 — Acasă și Luna

Pagina principală prezintă produsul, transformarea fotografiei, procesul și ocaziile pentru cadouri. Pagina Luna citește prețul, imaginile și specificațiile din WooCommerce. Randările de concept sunt înlocuitoare provizorii pentru fotografiile comerciale ale prototipului.

Pe pagina Luna poți alege un JPG, PNG sau WebP, vedea fotografia și verifica mesajele pentru format și dimensiune. Fotografia și observațiile opționale există doar pe pagina deschisă. Browserul le folosește pentru această previzualizare, fără a le trimite pe server. Coșul de probă poate păstra în fila curentă denumirea, prețul și cantitatea. Transferul fotografiei, asocierea cu o comandă și plata aparțin etapelor următoare.

În **Produse → TIR-ON Luna**, modifică prețul, descrierea scurtă, imaginea principală și galeria. În **Date produs → Generale** găsești finisajul, alimentarea, lumina, diametrul și termenul de producție. În **Aspect → Personalizare**, secțiunile TIR-ON controlează textele principale, imaginile de copertă și cele pentru comparație. Pentru acestea din urmă, folosește imagini cu același cadraj.

## Verificări și continuare

Validarea configurației Docker Compose și verificarea sintaxei scripturilor de instalare au trecut. Daemonul Docker din mediul de lucru a refuzat accesul la socket, astfel că pornirea containerelor rămâne de verificat pe calculatorul de destinație.

Testele efectuate cu WordPress și WooCommerce active, pe SQLite, au confirmat adresa produsului, prețul de 299 RON și păstrarea unui singur produs după repetarea inițializării. Cererile de cumpărare și de checkout prin Store API au primit răspunsul 403. Lista metodelor de plată a fost goală și numărul de comenzi a rămas zero.

După feedbackul vizual, etapa 3 adaugă încărcarea privată, validarea pe server, retenția și asocierea fotografiilor cu comenzile. Etapa 4 integrează Stripe exclusiv în modul de test. Etapele 5 și 6 includ consimțământul, verificările SEO, securitatea, performanța și testele complete ale comenzilor. Pentru lansare vor fi necesare fotografiile finale, datele operatorului, condițiile comerciale verificate juridic și aprobarea explicită pentru activarea plăților.
