# AI Labeling Guide - Football

Bu belge futbol detection dataset'i icin bbox etiketleme standardini sabitler.

## Siniflar

YOLO class index:

- `0 player`
- `1 ball`
- `2 goalkeeper`
- `3 referee`

Ilk MVP icin zorunlu:

- `player`
- `ball`

Opsiyonel ama onerilen:

- `goalkeeper`
- `referee`

## Genel kurallar

- Her kutu nesneyi olabildigince sıkı sarmali
- Bos alan birakma
- Nesne kismi gorunuyorsa yine etiketle
- Asiri kucuk ve anlamsiz nesneleri etiketleme
- Hareket blur varsa tahmini ama tutarli kutu koy
- Ayni frame icinde tum benzer nesnelerde tutarli davran

## Player

`player` sinifi:

- saha icindeki futbolcular
- ayakta, kosarken, kayarken, ziplarken
- gorunen oyuncu kismi anlamliysa etiketlenir

Kutu siniri:

- basin en ustu
- ayagin en alti
- omuz ve kollar dahil

Etiketleme:

- oyuncunun en az yaklasik `%35`i gorunuyorsa etiketle
- cok uzakta tek piksellik kalabalik oyunculari etiketleme

Etiketleme:

- kulubede oturan insanlar `player` degil
- tribundeki insanlar `player` degil
- teknik ekip `player` degil

## Ball

`ball` sinifi:

- gorunen futbol topu

Kutu siniri:

- topun dis sinirini yakin sar

Etiketleme:

- top net seciliyorsa etiketle
- topun yeri kuvvetli sekilde tahmin ediliyorsa ve blur olsa da etiketle
- top tamamen belirsizse etiketleme

Not:

- top cok kucuk oldugu icin bu sinif zor
- veri setinde top gorunen frameleri bilincli secmek model kalitesini ciddi etkiler

## Goalkeeper

`goalkeeper` sinifi:

- kaleci net ayristirilebiliyorsa kullan
- kaleci diger oyuncudan ayirt edilemiyorsa `player` olarak etiketle

Ne zaman ayri etiketle:

- farkli forma rengi
- kaleye yakin net konum
- frame baglami acik

## Referee

`referee` sinifi:

- hakem veya yardimci hakem net seciliyorsa kullan
- emin degilsen etiketleme

## Kismi gorunen nesne

Etiketle:

- kutunun anlamli kismi frame icindeyse
- nesnenin tipi secilebiliyorsa

Etiketleme:

- sadece belirsiz bir uzuv gorunuyorsa
- nesne sinifi anlasilmiyorsa

## Zor durumlar

Kalabalik ceza sahasi:

- oyuncular ust uste biniyorsa yine ayri kutu dene
- tam ayiramadigin iki oyuncuyu tek kutuya koyma

Blur:

- hareket blur varsa merkezi nesneyi yine kutula

Kamera uzakta:

- anlamsiz kadar kucuk oyunculari atla
- ama saha icindeki secilebilir oyunculari etiketle

## Frame secim kurali

Yalnizca topun gorundugu frame'leri degil, su tipleri de dahil et:

- topsuz kosu
- savunma yerlesimi
- pas hazirligi
- orta ve ceza sahasi kosulari
- duran top
- gecis anlari

## Kalite kontrol

Her 100 frame'de sunlari kontrol et:

- class index dogru mu
- kutular fazla buyuk mu
- top unutulmus mu
- kaleci yanlis `player` gitmis mi
- hakemler oyuncu diye isaretlenmis mi

## Dosya standardi

- YOLO txt format
- her image icin ayni isimli `.txt`
- bos frame ise bos txt olabilir

## MVP hedefi

Ilk modelde hedef:

- `player`
- `ball`

Kaleci ve hakem sonra da eklenebilir. Ama bastan eklenecekse tutarli etiketlenmeli.
