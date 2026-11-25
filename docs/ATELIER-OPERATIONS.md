# TIR-ON · Atelier

Deschide **WooCommerce → Atelier TIR-ON** cu un cont de administrator care poate gestiona WooCommerce și edita comanda. Lista afișează comenzile Luna, progresul, data, cantitatea, starea WooCommerce și expedierea atunci când există date. Filtrează după etapă și folosește indicatorul de cantități pentru a estima lucrul rămas.

[Panou la 1440 px](media/atelier-dashboard-1440.jpg) · [Detaliu de comandă la 390 px](media/atelier-order-390.jpg). Capturile sunt din WordPress local, cu date sintetice.

1. Deschide o comandă și verifică statusul și plata în WooCommerce.
2. Verifică fotografia și observațiile fiecărei linii Luna. Poți previzualiza sau descărca fotografia din aceeași pagină, cu acces administrativ verificat. Fotografii șterse sau expirate apar ca indisponibile.
3. Selectează etapa și adaugă, dacă este cazul, notele interne de lucru sau de control al calității. Salvează după fiecare actualizare. Dacă aceeași comandă a fost modificată între timp, reîncarcă detaliul și confruntă notele înainte să salvezi din nou.
4. Procesează plata și expedierea prin WooCommerce și circuitul tău de livrare. Etapa internă se păstrează separat și poate fi ajustată dacă o fotografie are nevoie de o nouă verificare.

| Etapă | Ce marchează |
|---|---|
| Comandă primită | Înregistrarea comenzii și verificarea plății |
| Fotografie în așteptarea verificării | Fotografia așteaptă revizia atelierului |
| Fotografie aprobată | Cadrul poate intra în lucru |
| În producție | Lucrul a început |
| Controlul calității | Verificarea reliefului, a luminii și a finisajului |
| Pregătită pentru expediere | Produsul este pregătit pentru predare |
| Finalizată în atelier | Atelierul și-a încheiat lucrul |

WooCommerce păstrează propriul status de plată și expediere. Atelierul salvează doar etapa și notele interne, fără să înscrie note pentru client sau să declanșeze e-mailuri. Înregistrările fotografiilor rămân în stocarea privată și urmează retenția existentă. Păstrează backup-ul cu baza WooCommerce și directorul privat din aceeași captură.

Lista de lucru începe cu comenzile cele mai vechi. Cantitatea rămasă exclude comenzile finalizate în atelier și comenzile WooCommerce eșuate, anulate sau rambursate. Comenzile cu plată neconfirmată sunt marcate în listă. Dacă lipsește o stare de expediere verificabilă, panoul afișează „Fără status”.

## Actualizare Hostico

Pentru verificarea privată, folosește noile arhive din `dist/hostico/`. Înlocuiește tema în **Aspect → Teme** și extrage modulele în `wp-content/mu-plugins/` folosind File Manager. Verifică prezența fișierelor `tiron/atelier.php` și `tiron/atelier.css`. Pe un magazin deja configurat nu este necesară repopularea catalogului sau schimbarea bazei. Pentru o instalare nouă și pentru rollback, consultă [ghidul Hostico](HOSTICO-DEPLOYMENT.md).
