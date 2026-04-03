# AI Labeling Guide - Volleyball

Bu belge voleybol detection dataset'i icin bbox etiketleme standardini sabitler.

## Siniflar

YOLO class index:

- `0 player`
- `1 ball`
- `2 net`
- `3 referee`

Ilk MVP icin zorunlu:

- `player`
- `ball`

Opsiyonel ama onerilen:

- `net`
- `referee`

## Genel kurallar

- Kutular nesneyi sıkı sarmali
- Gereksiz bosluk birakma
- Kismi gorunen nesneyi anlamliysa etiketle
- Tribun ve bench oyuncularini etiketleme
- Hareket blur varsa tutarli tahmini kutu kullan

## Player

`player` sinifi:

- sahadaki aktif voleybolcular
- servis, manşet, pas, smaç, blok ve savunma aksiyonlarinda

Kutu siniri:

- basin en ustu
- ayagin en alti
- kollar dahil

Etiketleme:

- oyuncu anlamli gorunuyorsa etiketle
- bench, koç, seyirci `player` degil

## Ball

`ball` sinifi:

- gorunen voleybol topu

Etiketleme:

- servis, pas, smaç ve blok sahnelerinde topu kacirma
- top blur olsa da yeri secilebiliyorsa etiketle
- top tamamen belirsizse etiketleme

## Net

`net` sinifi:

- file net seciliyorsa etiketle
- direklerle birlikte cok buyuk kutu yerine file alanina odaklan

Ne zaman faydali:

- blok ve smaç analizinde
- saha taraf ayirimi icin

## Referee

`referee` sinifi:

- hakem net seciliyorsa etiketle
- emin degilsen etiketleme

## Zor durumlar

Smaç ve blok:

- ziplayan oyuncularin tam vucudunu kutula
- topu atlama
- file varsa ayri etiketle

Servis:

- servis atan oyuncu ve top secilmeli

Kalabalik savunma:

- oyuncular ust uste de olsa ayri kutu dene

## Frame secim kurali

Yalnizca topun gorundugu frame'ler degil, su anlar da secilmeli:

- servis hazirligi
- manşet
- pas
- smaç kosusu
- blok yerlesimi
- savunma dive

## Kalite kontrol

Her 100 frame'de sunlari kontrol et:

- top unutulmus mu
- net tutarli etiketlenmis mi
- hakem oyuncu gitmis mi
- class index sirası dogru mu

## Dosya standardi

- YOLO txt format
- her image icin ayni isimli `.txt`
- bos frame ise bos txt olabilir

## MVP hedefi

Ilk modelde hedef:

- `player`
- `ball`

Sonraki adim:

- `net`
- `referee`
