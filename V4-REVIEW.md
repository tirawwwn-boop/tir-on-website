# TIR-ON V4 — Audit UX/UI

V4 rămâne varianta de lucru. Pe desktop, fotografia integrată în Hero echilibrează textul, iar alternanța espresso–ivory separă clar prezentarea, demonstrația și cumpărarea. Pe mobil, „Creează-ți Luna” apare înaintea imaginii și este ușor de găsit. Cormorant Garamond susține titlurile, iar DM Sans păstrează textele și controalele lizibile. Accentele champagne și butoanele espresso se leagă cromatic, cu o diferență de ierarhie între homepage și produs.

## Îmbunătățiri în ordinea impactului

1. **Adu selecția fotografiei mai sus pe pagina Luna.** La 390 × 844 px, prețul de 249 RON este vizibil, iar „Alege o fotografie” începe la aproximativ 867 px. Aș testa reducerea spațiilor din jurul galeriei și al prețului până când acțiunea intră în primul ecran. Recomandare pentru următoarea iterație.
2. **Arată rezultatul când clientul schimbă lumina.** Cu fotografia la 100%, butonul schimba starea fără să arate litofanul. Corectat. Comutarea revine la 50/50 în acel caz, iar în rest păstrează poziția aleasă. Fotografia originală și cele două vizualizări de concept rămân aceleași.
3. **Păstrează Hero-ul lizibil la lățimile intermediare și mici.** La 701–800 px, mobilul trecea la o compoziție desktop care tăia Luna în dreapta. La 320 px, legenda caruselului se rupea pe cinci rânduri. Corectate prin ajustarea încadrării primei imagini pe ecrane intermediare și ascunderea contorului redundant sub 340 px. Indicatorii și săgețile au rămas.
4. **Fă ierarhia de cumpărare consecventă.** Pe homepage, champagne semnalează acțiunea principală. Pe pagina Luna, „Adaugă în coș” folosește espresso, asemenea butonului de explorare din prezentare. Aș testa champagne pentru acțiunea de cumpărare și espresso pentru navigare. Recomandare pentru următoarea iterație.

## Verificare

Am revăzut Hero-ul și pagina Luna în Chromium la lățimi de la 320 la 1440 px. La 390 px, fluxul real a trecut din „Creează-ți Luna” prin selectarea unei fotografii sintetice, coș și checkout ca vizitator până la confirmare, prin simulatorul local. Două bucăți au totalizat 498 RON. Am reverificat caruselul, comparatorul, galeria și lipsa derulării orizontale în HTML-ul offline după corecții.
