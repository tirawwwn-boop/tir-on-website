# Luna · Alegerea fotografiei

În pagina Luna, clientul ajunge de lângă preț la formularul existent de personalizare. Poate selecta, previzualiza, înlocui sau elimina o fotografie înainte de a adăuga produsul în coș. Previzualizarea rămâne în browser până la trimiterea formularului.

Ghidul circular este opțional și arată aproximativ zona vizibilă a litofanului. Un chip apropiat de margine poate ieși parțial din această zonă. Alegerea ghidului nu modifică fotografia și nu se transmite odată cu ea. Sub 1.200 px pe latura scurtă, apare o recomandare de rezoluție. Clientul poate folosi în continuare fotografia.

## Validare și recuperare

- Acceptă JPG, PNG și WebP. Limita în MB vine din configurarea WordPress/PHP. Limitele de siguranță sunt 24 MP și 10.000 px pe latură. Serverul verifică tipul real și decodează imaginea independent de browser.
- După ce o trimitere cu sesiune și nonce valide este respinsă, cantitatea și observațiile validate pot fi restaurate o singură dată, timp de cinci minute. Clientul selectează fotografia din nou.
- Ghidul nu aplică nicio decupare. Fișierul de pe dispozitiv rămâne intact. Serverul păstrează fluxul privat existent, cu re-encodare JPEG și eliminarea metadatelor. Noua experiență nu adaugă fișiere în Media sau copii publice.
- Coșul, checkout-ul ca vizitator și prețul de 249 RON rămân în WooCommerce. O fotografie diferită de personalizat se adaugă ca o linie separată în coș.

[Fluxul atelierului](ATELIER-OPERATIONS.md) · [Verificări QA](ATELIER-QA.md)
