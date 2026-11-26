# Actualizarea magazinului din GitHub prin cPanel

Acest flux este pentru magazinul **deja instalat**. Un push pe GitHub păstrează codul în repository. Publicarea presupune o copie Git privată pe contul Hostico, verificarea unui plan și aprobarea explicită a versiunii. Aplicația rulează cu PHP și WooCommerce existente.

## Configurare o singură dată

1. Cere Hostico să confirme că **Git Version Control → Pull or Deploy** este disponibil pe planul tău, inclusiv pentru branch-uri private. Sarcina cPanel folosește Git, Bash și PHP CLI 8.3+ cu ZipArchive, proc_open și flock. Instalarea nu presupune acces interactiv prin SSH. Directorul de control și WordPress trebuie să fie pe același sistem de fișiere.
2. În cPanel, configurează cu Hostico o cheie de acces a clonei. În GitHub → Settings → Deploy keys adaugă **doar cheia publică**, cu acces de citire. În Git Version Control clonează `git@github.com:tirawwwn-boop/tir-on-website.git` într-un director privat din Home, în afara tuturor domeniilor și aliasurilor. În **Manage** selectează branch-ul versiunii aprobate. Pentru verificarea V7 pe staging folosește `engineering/v7-brand-studio`. Folosește cPanel pentru pull. Restricționează accesul de scriere la contul cPanel. Nu configura webhook sau push către clona cPanel.
3. În File Manager creează în Home directorul `tiron-release-control` cu permisiuni **0700**. Directorul trebuie să fie separat de clonă și de fotografiile private. Confirmă din **Domains** că aceste directoare nu sunt servite pe web. Află calea absolută a instalării și identifică exact commitul pachetului instalat. Pentru pachetul Profit Studio original acesta este `1d6ad55b78893399fdea32f4e0fa2f383c7704b6`. Scriptul verifică fișierele înainte de orice înlocuire.
4. Verifică existența unui backup recuperabil al magazinului, bazei de date și fotografiilor private, cu protecția corespunzătoare. Backup-ul automat al acestui flux acoperă doar fișierele de cod afectate. Dacă Hostico nu oferă funcțiile necesare, păstrează fluxul oprit. Pachetele ZIP verificate rămân disponibile pentru actualizare manuală aprobată.

## Plan și aprobare pentru fiecare versiune

1. Deschide branch-ul privat GitHub, verifică raportul și copiază SHA-ul complet de 40 de caractere al versiunii aprobate pentru test. În cPanel selectează **Update from Remote** și verifică dacă **HEAD Commit** coincide. Testează mai întâi pe staging.
2. În File Manager creează local în directorul de control un fișier temporar cu următorul conținut. Completează toate valorile cu datele instalării respective și enumeră toate rădăcinile web. Setează permisiuni **0600**, apoi redenumește fișierul în `request.json`. Cererea se consumă o singură dată.

```json
{
  "action": "plan",
  "approved_commit": "<SHA complet ales din GitHub>",
  "installed_commit": "<SHA complet instalat>",
  "wordpress_root": "/home/CONT/public_html",
  "public_roots": ["/home/CONT/public_html"]
}
```

3. În **Pull or Deploy → Deploy HEAD Commit** rulează planificarea. Deschide `plan.json` în directorul de control. Verifică lista de fișiere și cele două SHA-uri. La nepotrivirea conținutului instalat, oprește-te și identifică modificările locale înainte de reconciliere.
4. După aprobarea proprietarului, alege o fereastră fără checkout-uri în curs. Activează protecția de mentenanță la nivel de hosting pentru a evita servirea unui amestec de versiuni între înlocuirile individuale. Creează din nou `request.json` cu aceleași valori și cu `"action": "apply"`. Apasă **Deploy HEAD Commit**. Se verifică din nou planul, se salvează copiile fișierelor afectate în `backups/` și se verifică amprentele fișierelor instalate. În lipsa unei cereri, sarcina cPanel încheie fără modificări.
5. Verifică `state.json`, golește cache-ul LiteSpeed și, dacă este necesar, cache-ul PHP prin Hostico. Verifică pagina Luna, prețul, formularul, coșul, checkout-ul de test și accesul administratorului la Atelier și Profit Studio. După verificare, proprietarul decide redeschiderea. În prima instalare a V5 nu se rulează configuratorul inițial. Scriptul gestionează exclusiv fișierele temei și modulele MU TIR-ON aprobate.

## Rollback din browser

Închide temporar accesul la magazin și păstrează clona la commitul aprobat curent. Consultă identificatorul `backup` din `state.json`, sau din `pending.json` dacă procesul a fost întrerupt. Creează în directorul de control o cerere ca mai sus cu `"action": "rollback"` și cheia suplimentară `"backup_id": "<identificatorul exact>"`. Apasă **Deploy HEAD Commit**, verifică starea, golește cache-ul și repetă verificările. Revenirea retrage fișierele introduse de versiune și restaurează codul anterior. Baza de date, fișierele Media, configurația WordPress și fotografiile clienților rămân pe loc. Dacă verificarea găsește modificări independente, păstrează instalarea protejată și reconciliază fișierele cu backup-ul privat înainte de redeschidere.
