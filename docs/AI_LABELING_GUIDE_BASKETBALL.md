# AI Labeling Guide - Basketball

Bu belge basketbol detection dataset'i icin bbox etiketleme standardini sabitler.

## Siniflar

YOLO class index:

- `0 player`
- `1 ball`
- `2 hoop`
- `3 referee`

Ilk MVP icin zorunlu:

- `player`
- `ball`

Opsiyonel ama onerilen:

- `hoop`
- `referee`

## Genel kurallar

- Her kutu nesneyi olabildigince sıkı sarmali
- Bos alan birakma
- Nesne kismi gorunuyorsa yine etiketle
- Gereksiz kalabalik tribunu etiketleme
- Hareket blur varsa tutarli tahmini kutu koy

## Player

`player` sinifi:

- sahadaki aktif oyuncular
- kosarken, savunmadayken, suta kalkarken, box-out yaparken

Kutu siniri:

- basin en ustu
- ayagin en alti
- top surerken acilan kollar dahil

Etiketleme:

- oyuncu anlamli sekilde gorunuyorsa etiketle
- bench, koç, taraftar `player` degil

## Ball

`ball` sinifi:

- gorunen basketbol topu

Etiketleme:

- top net seciliyorsa etiketle
- dribbling blur olsa da top yeri secilebiliyorsa etiketle
- tamamen kaybolmussa etiketleme

## Hoop

`hoop` sinifi:

- pota cemberi veya neti net secilebiliyorsa etiketle
- backboard tamamini degil, cember merkezli alanı kutula

Ne zaman etiketle:

- şut, drive ve finish senaryolarinda faydali
- uzaktan anlamsiz kadar kucukse atlanabilir

## Referee

`referee` sinifi:

- hakem net seciliyorsa etiketle
- emin degilsen etiketleme

## Zor durumlar

Temasli pozisyon:

- oyuncular ust uste de olsa ayri kutu vermeye calis

Fast break:

- hareket blur olsa bile ana oyunculari kutula
- topu kacirma

Ribaund kalabaligi:

- tek buyuk kutu kullanma
- ayri oyunculari ayri etiketle

## Frame secim kurali

Sadece top elindeyken degil, su anlari da sec:

- topsuz kat
- savunma slide
- transition
- pick and roll
- spot-up
- rebound
- closeout

## Kalite kontrol

Her 100 frame'de sunlari kontrol et:

- top unutulmus mu
- pota yanlis etiketlenmis mi
- bench oyunculari sahadaki oyuncu gibi gitmis mi
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

- `hoop`
- `referee`
