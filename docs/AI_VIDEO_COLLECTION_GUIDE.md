# AI Video Collection Guide

Bu belge, model egitimi icin toplanacak ham videolarin hangi kalite standardinda olmasi gerektigini tanimlar.

## Amaç

Yanlis video secimi, iyi etiketlenmis dataset'i bile bozabilir.

Bu yuzden toplama asamasinda su soruya cevap veririz:

- bu video detection modeline gercek fayda saglar mi

## Genel minimum standart

Bir video su ozellikleri tasimali:

- oyuncular secilebilir olmali
- top zaman zaman secilebilir olmali
- kamera asiri titrememeli
- sahne cok karanlik olmamali
- goruntu cok dusuk cozuklukte olmamali
- reklam, montage, agir efekt, hizlandirma olmamali

## Tercih edilen kalite

- 720p ve ustu
- 25 fps ve ustu
- yatay kadraj
- tek saha / tek oyun alani net seciliyor olmali
- zoom cok agresif degil

## Kotu veri ornekleri

Asagidaki videolar mumkunse toplanmaz:

- tiktok benzeri asiri efektli edit videolari
- surekli slow motion highlight
- topun hic gorunmedigi kolajlar
- oyuncularin cok uzak oldugu tribunden cekimler
- dikey ve cok sallantili telefon videolari
- agir watermark ve overlay kapli yayinlar

## Futbol

Iyi futbol videosu:

- saha cizgileri gorunuyor
- ayni anda birden fazla oyuncu seciliyor
- top sik sik secilebiliyor
- pas, kosu, yerlesim anlari var

Ozellikle iste:

- genis acidan mac kaydi
- kanat aksiyonlari
- orta saha gecisleri
- ceza sahasi kalabaligi
- duran top

Kacın:

- sadece gol kutlamasi
- sadece yakin plan oyuncu yuzleri
- sadece 3 saniyelik highlightlar

## Basketbol

Iyi basketbol videosu:

- yari saha veya tam saha okunuyor
- top, oyuncu ve pota zaman zaman ayni frame'de
- drive, pick and roll, transition gorunuyor

Ozellikle iste:

- yarı saha setleri
- fast break
- ribaund kalabaligi
- spot-up ve closeout

Kacin:

- sadece smaç highlight kolaji
- topun kare kare kayboldugu cok zoomlu klipler

## Voleybol

Iyi voleybol videosu:

- file seciliyor
- top servis, pas, smaç, blok anlarinda takip edilebiliyor
- oyuncular saha taraflariyla ayristiriliyor

Ozellikle iste:

- servis karsilama
- pas organizasyonu
- smaç ve blok
- savunma dive

Kacin:

- sadece kutlama
- sadece oyuncu yakin planlari
- topun surekli frame disinda kaldigi videolar

## Video cesitliligi

Dataset'te sadece tek tur video olmamali.

Karistir:

- gunduz / gece
- ic saha / dis saha
- profesyonel / yarı profesyonel / genc kategori
- farkli kamera acilari
- farkli zemin ve forma renkleri

## Toplama hedefi

Ilk model icin pratik hedef:

- spor basi en az `20-30` video
- video basi `2-10` dakika arasi faydali bolum

Bu ilk detection modeli icin yeterli bir MVP baslangici verir.

## Dosyalama

Ham videolari ayri klasorde tut:

- `raw_videos/football`
- `raw_videos/basketball`
- `raw_videos/volleyball`

Sonra prep scriptlerini bu klasorlerden calistir.

Repo standardi:

- [raw_videos/README.md](../raw_videos/README.md)

## Son karar sorusu

Bir videoyu almadan once sor:

- oyuncu seciliyor mu
- top seciliyor mu
- oyun baglami anlasiliyor mu
- detection egitimi icin tekrar kullanilabilir mi

Bu sorulardan cogu `evet` ise video uygundur.
