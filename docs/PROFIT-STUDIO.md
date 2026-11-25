# TIR-ON Profit Studio

Deschide **WooCommerce → TIR-ON Profit** cu un cont de administrator care gestionează WooCommerce. Câmpurile pornesc goale. Calculatorul păstrează un set comun de valori pentru administrarea magazinului. Apasă **Calculează și salvează** pentru a actualiza rezultatele. Acceptă virgula sau punctul zecimal, fără separatori de mii.

## Introdu costurile

1. Pentru fiecare material, completează furnizorul, cantitatea cumpărată, unitatea, suma efectiv plătită, partea din transportul achiziției și consumul pentru o Luna. Folosește aceeași unitate pentru cantitatea cumpărată și cea consumată. Formula este **(sumă plătită + transport alocat) × consum / cantitate cumpărată**. Dacă transporți mai multe materiale într-o singură comandă, împarte transportul între ele.
2. Completează energia, minutele și tariful pe oră. Include la ambalajul suplimentar numai ce nu ai trecut în lista de materiale. Adaugă regia alocată unei bucăți.
3. Introdu rata de rebuturi observată. Calculatorul aplică **rată / (1 − rată)** la materialele fără ambalaj, energie și manoperă. Modelul estimează pierderea acelorași resurse la fiecare încercare eșuată. Ambalajul, regia și costurile de vânzare se adaugă după această estimare.
4. Completează livrarea plătită de atelier, comisionul fix, comisionul procentual și achiziția clientului. Scenariile presupun **o Luna per comandă**. Câmpurile goale au contribuție zero, astfel încât rezultatele rămân parțiale până completezi costurile aplicabile.

## Citește rezultatele

- **Costul de producție** însumează materialele, ambalajul, energia, munca, rebuturile și regia. Loturile de 6, 10 și 20 înmulțesc costul înaintea rotunjirii afișate la bani.
- **Marja brută** este diferența dintre preț și costul de producție, în RON și ca procent din preț.
- **Contribuția** scade și livrarea suportată, comisioanele de plată și publicitatea.
- **Pragul de publicitate** este suma disponibilă pentru achiziția clientului înainte ca această contribuție să ajungă la zero. O valoare negativă arată o pierdere chiar înainte de publicitate.

Prețurile de **249, 269 și 299 RON** sunt referințe pentru comparație. Prețurile din magazin se editează separat în WooCommerce. Rezultatele sunt înainte de impozite. Calculatorul folosește sumele introduse ca atare, fără ipoteze de TVA, cote fiscale sau profit net.

[Captură cu valori ilustrative](media/profit-studio-exemplu.jpg) · [Pachetul de instalare Hostico](../dist/hostico/)

Pe o instalare Hostico existentă, actualizează arhiva modulelor MU în `wp-content/mu-plugins/`, conform [ghidului de instalare](HOSTICO-DEPLOYMENT.md). Calculatorul devine disponibil în meniu după extragere.

## Verificare

`php tests/profit-studio.php` exercită alocarea transportului, consumul fracționar, cantitățile zero, rebuturile, loturile, marjele negative și validarea datelor. În WordPress local, am verificat salvarea și reîncărcarea, respingerea unui nonce greșit, accesul restricționat și afișarea la 390 și 1440 px.
