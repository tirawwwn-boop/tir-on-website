# V5 · Experiența de cumpărare pe mobil

Branch-ul `design-v5-mobile-commerce` continuă Profit Studio de la `1d6ad55`. V5 este disponibil pentru evaluare. Testele și capturile provin dintr-o copie locală WordPress cu date sintetice.

| Pagină | Modificare | Unde o poți verifica |
| --- | --- | --- |
| Acasă | Acțiune champagne mai vizibilă, legendă și navigare sub fotografia de pe telefon. Meniul are text vizibil de deschidere și închidere și răspunde la Escape. | [Înainte și după la 390 px](media/v5/comparatie-390.jpg) |
| Luna | Acces direct la personalizare cu prețul din WooCommerce. Pe mobil bara rămâne în zona galeriei și se retrage înaintea formularului. Ghidul circular este activ la prima selecție. Mesajul explică rezoluția și riscul de decupare pentru cadrele alungite. | [Luna](media/v5/after/luna-390.jpg) · [Ghidul foto](media/v5/after/guide-390.jpg) |
| Coș | Fiecare articol are o fotografie de catalog, confirmarea personalizării, cantitate, subtotal și eliminare. Cuponul se deschide la cerere. WooCommerce recalculează reducerile și totalul. Dacă treci direct la checkout după editarea cantității, întâi se actualizează coșul pentru verificarea totalului. | [Coș la 390 px](media/v5/after/cart-390.jpg) |
| Checkout | Un singur telefon de livrare, obligatoriu pentru produse fizice. Câmpuri de contact la început, observații opționale restrânse, sumar și plată grupate. Se păstrează checkout-ul clasic WooCommerce folosit de instalare. | [Checkout la 390 px](media/v5/after/checkout-390.jpg) |
| Subsol | Asistență, livrare, informații legale și social. Administratorul poate configura meniurile din **Aspect → Meniuri**. Profilurile sociale se adaugă când există URL-uri oficiale. | [Comparație desktop](media/v5/comparatie-1440.jpg) |

## Fotografii comerciale și ambalare

În **Aspect → Personalizare → TIR-ON · Pagina de start** selectezi imaginile principale. În **TIR-ON · Fotografii de copertă pe mobil** poți seta un cadraj separat al aceleiași fotografii. În **Produse → Luna** configurezi imaginea principală, galeria, ambalarea și termenul confirmat de producție. Completează textele alternative în Media. Tarifele și metodele de livrare vin din WooCommerce. Ghidul afișat clientului este o suprapunere locală. Fișierul din formular își păstrează octeții originali. Procesarea privată existentă din atelier produce copia JPEG igienizată la dimensiunile originale, fără metadatele personale.

## Verificări executate

- În Chromium, Acasă, Luna, Coș și Checkout la **320, 360, 390, 430, 768 și 1440 px**. În proba completă, cele 24 de combinații au avut zero depășire orizontală și zero imagini încărcate defectuos. Fluxurile au trecut fără erori JavaScript sau solicitări externe. Caruselul, selectorul lumină stinsă/aprinsă, navigarea tastaturii și selectarea fotografiei au fost exercitate în browser. [Măsurători pe pagină](media/v5/measurements.json)
- În prima rulare, checkout-ul la 390 px a avut un CLS de **0,1471**. În rularea completă ulterioară a avut **0,0067**, iar maximul pe cele 24 de probe a fost **0,0829**. Acestea sunt probe locale de încărcare. Verifică stabilitatea pe staging cu cache-ul și gateway-ul efectiv instalate.
- În WordPress și WooCommerce, două sesiuni guest au păstrat personalizările separate. Luna a costat **249 RON**, două bucăți **498 RON**, iar cuponul sintetic de 10% a dat **448,20 RON**. Au trecut aplicarea și eliminarea cuponului, respingerea cuponului invalid și a cantității excesive, eliminarea articolului, checkout-ul și confirmarea cu simulatorul local.
- În comanda de test s-au păstrat fotografia privată, dimensiunile, observațiile și totalul. Au fost respinse fotografii lipsă, invalide și supradimensionate, precum și telefonul lipsă sau invalid. A fost verificată și livrarea la o adresă diferită. Browserul a verificat separat la 390 și 1440 px că ghidul poate fi dezactivat și că previzualizarea păstrează octeții originali.
- Arhivele instalabile au fost comparate cu sursele și manifestul SHA-256. Testele de calcul Profit Studio au trecut. Fluxul de actualizare cPanel a fost rulat pe o clonă locală izolată cu plan, aprobare consumabilă, backup, rollback și refuz la SHA greșit sau fișiere locale divergente.
- Rămân pentru staging verificările cPanel ale contului Hostico, permisiunile Git și PHP CLI, dispozitivele fizice și o tranzacție prin extensia oficială Stripe în **TEST**. Simularea locală nu folosește o rețea de plăți. Atelier, motorul Profit Studio, stocarea privată și gardul Stripe își păstrează implementarea.

## Reproducere și instalare

Testele funcționale folosesc instalarea locală `.runtime` cu SQLite, PHP CGI și date temporare. Configurează `TIRON_PHP_CGI` cu executabilul local. Rulează `python3 tests/v5-commerce.py`, `python3 tests/checkout-contact.py` și `python3 tests/v5-hostico.py`. Pentru browser, pornește `python3 tests/v5-browser-server.py`, apoi `node tests/v5-browser.cjs` cu Playwright și Chromium disponibile. `TIRON_QA_OUTPUT` permite salvarea capturilor. Serverul acceptă `--baseline 1d6ad55` pentru comparația vizuală.

[Pachetele Hostico și sumele de control](../dist/hostico/) · [Actualizare aprobată și rollback din cPanel](HOSTICO-UPDATES.md) · [Capturi înainte](media/v5/before/) · [Capturi după](media/v5/after/)
