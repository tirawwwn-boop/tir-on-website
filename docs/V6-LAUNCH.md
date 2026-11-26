# V6 · Pregătire comercială

V6 continuă commitul V5 `5127a78`. Prima investigație pentru plata 4242 se poate face pe versiunea deja instalată. [Arbore de diagnostic și loguri](V6-STRIPE-DIAGNOSTIC.md)

## Administrarea magazinului

- **WooCommerce → Magazin TIR-ON** grupează comenzile, Atelier, Profit Studio, produsul Luna, zonele de livrare, plățile și jurnalele. Afișează modul Stripe configurat, starea simulatorului, disponibilitatea procesării private și indexarea. Aceste indicatoare nu fac plăți de probă.
- În **WooCommerce → Setări → Livrare**, verifică ordinea zonelor. În zona care deservește adresa din România poți adăuga una sau mai multe instanțe **Livrare TIR-ON · România**. Fiecare are propriul nume, tarif pe colet, regim de taxare și text estimativ după expediere. Tariful gol ține metoda indisponibilă. Zero reprezintă o alegere explicită de transport gratuit. Instalarea pachetului nu creează zone, nu modifică metodele existente și nu alege tarife în locul proprietarului.
- În **Produse → Luna → Date produs → Generale** editezi separat termenul de producție și estimarea generală după expediere. Metoda selectată la checkout poate afișa o estimare proprie. Folosește numai informațiile confirmate de atelier și transportator.
- În **Magazin TIR-ON** poți completa mesajul de Crăciun și prima/ultima zi în fusul orar al magazinului. Ambele zile sunt incluse. Implicit, campania este goală. Golește cache-ul după editare și programează expirarea cache-ului homepage-ului la limitele campaniei.
- Tot aici poți edita descrierile SEO pentru Acasă și Luna. Tema oferă titluri și URL-uri canonice WordPress, Open Graph, card social mare și schema Product/Offer din WooCommerce. Prețul și moneda vin din produs. Paginile tranzacționale sunt excluse din sitemap și marcate noindex. Verifică `/wp-sitemap.xml` pe staging cu date sintetice. Indexarea Hostico rămâne blocată implicit; publicarea pentru motoarele de căutare necesită separat mediu production, aprobarea explicită `TIRON_INDEXING_APPROVED` și opțiunea WordPress **Citire** corespunzătoare. Pachetul nu schimbă aceste setări.

## Măsurarea publicității

Integrarea este inactivă la instalare. După aprobarea proprietarului și verificarea politicii de confidențialitate, administratorul introduce ID-urile aprobate și configurează separat constanta booleană `TIRON_MARKETING_APPROVED`. Se poate configura un singur furnizor. Evită o instalare paralelă a acelorași pixeli prin alt plugin sau Tag Manager. Verifică în conturile furnizorilor opțiunile de colectare automată și potrivire avansată înaintea activării.

Vizitatorul poate accepta, refuza sau retrage măsurarea din subsol. Alegerea are o durată de 180 de zile. Global Privacy Control prevalează asupra acceptului. Numai după acord se creează identificatorul aleator de măsurare. Bibliotecile furnizorilor primesc PageView pe pagina de start cu URL curat. Fluxul de personalizare, coșul, checkout-ul și pagina de confirmare sunt excluse de la încărcarea bibliotecilor. Implementarea limitează deliberat măsurarea vizitelor cu parametri URL și nu transmite fotografii, observații sau date de facturare.

Pentru **Purchase**, fiecare furnizor necesită token de server și cod din instrumentele sale de test. Numele constantelor sunt `TIRON_META_TOKEN`, `TIRON_META_TEST_EVENT_CODE`, `TIRON_TIKTOK_TOKEN` și `TIRON_TIKTOK_TEST_EVENT_CODE`. Păstrează secretele exclusiv în configurația privată. Nu distribui valori prin GitHub sau în rapoarte. Evenimentul pleacă de pe server numai după deschiderea confirmării unei comenzi plătite, dintr-un browser cu acord, cu verificarea accesului și a dovezii temporare. Vizita care precedă confirmarea asincronă a plății poate fi măsurată la redeschiderea confirmării. O comandă fără o asemenea vizită poate rămâne nemăsurată.

Valoarea și moneda vin din comandă. Furnizorul primește un identificator pseudonim, un ID de eveniment stabil și URL-ul generic de checkout. Meta și TikTok au confirmări independente în meta-comenzi HPOS. La refresh se omit evenimentele acceptate; la răspuns incert se reutilizează același ID pentru deduplicarea la furnizor. În această versiune conversiile necesită coduri de test. Testarea reală a recepției în Events Manager și decizia privind măsurarea comercială sunt ulterioare.

## Validare și actualizare

Testele au folosit o copie locală WordPress/WooCommerce și imagini sintetice. Coșurile separate, cuponul, cantitatea, telefonul obligatoriu, livrarea la altă adresă, fotografia și comanda guest au trecut cu simulatorul. În Chromium am verificat 320, 360, 390, 430, 768 și 1440 px, inclusiv că suma și moneda sunt pe aceeași linie în sumarul checkout-ului. În proba locală nu au apărut depășiri orizontale, imagini defecte sau erori de consolă.

Testele V6 verifică tarifele RO (gol, zero, pozitiv, negativ și destinație diferită), setările, perioadele campaniei, schema de produs, metadatele sociale și sitemap-ul. Testele de consimțământ folosesc scripturi simulate. Pentru Purchase răspunsurile celor doi furnizori sunt simulate; s-au verificat refuzul pentru o comandă neplătită, suma, moneda, ID-ul comun și repetarea confirmării. Plata 4242 de pe Hostico, portofelele, webhook-urile și recepția reală la platformele publicitare rămân neverificate.

Pentru actualizarea aprobată, folosește ghidul cPanel cu branch-ul **engineering/v6-commercial-readiness**, SHA-ul complet V5 instalat și SHA-ul complet V6 revizuit. Fă mai întâi planul pe staging. Verifică ambele ZIP-uri împreună și nu rula instalatorul inițial pe magazinul existent. [Pachete și verificări SHA-256](../dist/hostico/) · [Plan, backup și rollback](HOSTICO-UPDATES.md)
