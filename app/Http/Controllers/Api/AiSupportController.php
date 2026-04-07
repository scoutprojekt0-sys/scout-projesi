<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiSupportController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $role = strtolower((string) ($user?->role ?? 'member'));
        $message = trim((string) $validated['message']);
        $normalized = mb_strtolower($message, 'UTF-8');

        $payload = $this->buildReply($normalized, $role);

        return response()->json([
            'ok' => true,
            'message' => 'AI destek cevabi hazirlandi.',
            'data' => [
                'reply' => $payload['reply'],
                'category' => $payload['category'],
                'severity' => $payload['severity'],
                'should_open_ticket' => $payload['should_open_ticket'],
                'ticket_subject' => $payload['ticket_subject'],
                'ticket_description' => $payload['ticket_description'],
                'suggested_actions' => $payload['suggested_actions'],
                'quick_replies' => $payload['quick_replies'],
            ],
        ], Response::HTTP_OK);
    }

    private function buildReply(string $message, string $role): array
    {
        if ($this->containsAny($message, ['401', '403', 'unauthorized', 'yetki', 'iznin yok', 'forbidden'])) {
            return [
                'category' => 'auth',
                'severity' => 'high',
                'should_open_ticket' => false,
                'reply' => 'Bu hata genelde oturum suresi doldugunda ya da bulundugun role acik olmayan bir islemi denediginde gorulur. Yeniden giris yap, sonra ayni ekrani tekrar dene. Hala ayni hata geliyorsa yetki akisinda sorun olabilir.',
                'ticket_subject' => 'Yetki veya oturum hatasi',
                'ticket_description' => 'Kullanici yetki veya oturum hatasi aliyor. Olası 401/403, unauthorized ya da forbidden durumu. Ekran ve rol kontrol edilmeli.',
                'suggested_actions' => [
                    ['label' => 'Profili Kontrol Et', 'target' => 'profile'],
                    ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
                ],
                'quick_replies' => [
                    'Bu ozellik bana acik mi?',
                    'Yetki hatasi neden aliyorum?',
                ],
            ];
        }

        if ($this->containsAny($message, ['giris', 'login', 'sifre', 'parola', 'oturum'])) {
            return [
                'category' => 'auth',
                'severity' => 'high',
                'should_open_ticket' => false,
                'reply' => 'Giris sorunu icin once e-posta ve sifreni kontrol et. Sifre hatasi aliyorsan sifre yenileme akisini dene. Oturum hemen dusuyorsa tekrar giris yapip profil sayfasinin acildigini dogrula. Sorun devam ederse destek talebi acman gerekir.',
                'ticket_subject' => 'Giris sorunu',
                'ticket_description' => 'Kullanici giris yapamiyor veya oturum kapanma sorunu yasiyor. E-posta, sifre ve oturum akisi kontrol edilmeli.',
                'suggested_actions' => [
                    ['label' => 'Giris Ekranina Don', 'target' => 'login'],
                    ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
                ],
                'quick_replies' => [
                    'Sifre yenileme nasil yapilir?',
                    'Oturum neden kapaniyor?',
                ],
            ];
        }

        if ($this->containsAny($message, ['upload failed', 'yukleme basarisiz', 'dosya buyuk', 'file too large', 'network error'])) {
            return [
                'category' => 'video_upload',
                'severity' => 'high',
                'should_open_ticket' => false,
                'reply' => 'Yukleme sirasinda baglanti kopmasi, dosya boyutunun buyuk olmasi veya format sorunu bu hataya neden olabilir. Daha kucuk ya da desteklenen formatta dosya ile tekrar dene. Mobil baglantin dengesizse Wi-Fi ile tekrar yukle.',
                'ticket_subject' => 'Video yukleme basarisiz',
                'ticket_description' => 'Kullanici video yukleme sirasinda hata aliyor. Dosya boyutu, format, baglanti ve yukleme loglari kontrol edilmeli.',
                'suggested_actions' => $this->videoActionsForRole($role),
                'quick_replies' => [
                    'Hangi video formati destekleniyor?',
                    'Maksimum dosya boyutu ne kadar?',
                ],
            ];
        }

        if ($this->containsAny($message, ['video', 'yukle', 'upload', 'mp4', 'medya', 'media'])) {
            return [
                'category' => 'video_upload',
                'severity' => 'medium',
                'should_open_ticket' => false,
                'reply' => 'Video yukleme sorunu icin dosya boyutunu, formatini ve baglantini kontrol et. Yukleme tamamlandiktan sonra videonun profil ya da medya alaninda gorunmesi gerekir. Gorunmuyorsa uygulamayi yenileyip tekrar kontrol et. Tekrarlayan hata varsa teknik destek talebi ac.',
                'ticket_subject' => 'Video veya medya sorunu',
                'ticket_description' => 'Kullanici yukledigi video veya medya icerigini goremiyor ya da yukleme akisinda sorun yasiyor.',
                'suggested_actions' => $this->videoActionsForRole($role),
                'quick_replies' => [
                    'Hangi video formati destekleniyor?',
                    'Yuklenen video neden gorunmuyor?',
                ],
            ];
        }

        if ($this->containsAny($message, ['eksik alan', 'missing field', 'zorunlu alan', 'profil eksik'])) {
            return [
                'category' => 'profile_completion',
                'severity' => 'medium',
                'should_open_ticket' => false,
                'reply' => 'Bu durumda genelde profilin zorunlu alanlarindan biri eksiktir. Ad, pozisyon, yas, sehir, iletisim veya medya alanlarindan birini tamamlaman gerekebilir. Kaydetme sonrasi ekrani tekrar acip degisikligin yansidigini kontrol et.',
                'ticket_subject' => 'Profilde eksik zorunlu alan',
                'ticket_description' => 'Kullanici profilindeki zorunlu alanlardan biri eksik oldugu icin akista sorun yasiyor. Profil alanlari kontrol edilmeli.',
                'suggested_actions' => $this->profileActionsForRole($role),
                'quick_replies' => [
                    'Hangi alanlar zorunlu?',
                    'Profil neden tamamlanmamis gorunuyor?',
                ],
            ];
        }

        if ($this->containsAny($message, ['profil', 'eksik', 'tamamla', 'gorunmuyor', 'bio'])) {
            return [
                'category' => 'profile_completion',
                'severity' => 'medium',
                'should_open_ticket' => false,
                'reply' => 'Profilinde eksik alan varsa uygulama bazi ozellikleri tam gostermeyebilir. Ad, pozisyon, yas, sehir, medya ve ozet alanlarini kontrol et. Profilin bos ya da eksik gorunuyorsa kaydetme sonrasi sayfayi yenilemeni oneririm.',
                'ticket_subject' => 'Profil bilgisi eksik veya gorunmuyor',
                'ticket_description' => 'Kullanici profilinde eksik alan oldugunu veya profilin hatali gorundugunu bildiriyor. Kayit ve senkronizasyon kontrol edilmeli.',
                'suggested_actions' => $this->profileActionsForRole($role),
                'quick_replies' => [
                    'Profilimde hangi alanlar zorunlu?',
                    'Profil neden eksik gorunuyor?',
                ],
            ];
        }

        if ($this->containsAny($message, ['pending', 'bekliyor', 'isleniyor', 'sirada', 'analiz bekliyor'])) {
            return [
                'category' => 'analysis',
                'severity' => 'medium',
                'should_open_ticket' => false,
                'reply' => 'AI analiz beklemede gorunuyorsa video kuyrukta olabilir. Birkac dakika sonra tekrar kontrol et. Uzun sure ayni durumda kalirsa analiz servisine iletim ya da callback tarafinda sorun olabilir.',
                'ticket_subject' => 'AI analiz beklemede kaldi',
                'ticket_description' => 'Kullanici AI analizin uzun sure beklemede kaldigini bildiriyor. Kuyruk, callback ve worker durumu kontrol edilmeli.',
                'suggested_actions' => $this->analysisActionsForRole($role),
                'quick_replies' => [
                    'Analiz ne kadar surer?',
                    'Beklemede kalirsa ne yapmaliyim?',
                ],
            ];
        }

        if ($this->containsAny($message, ['analiz', 'ai', 'discovery', 'clip', 'klip', 'event'])) {
            return [
                'category' => 'analysis',
                'severity' => 'high',
                'should_open_ticket' => false,
                'reply' => 'AI analiz tarafinda once videonun sisteme basariyla yuklendiginin gorunmesi gerekir. Analiz baslamiyorsa video uygun formatta olmayabilir ya da analiz sirasi henuz olusmamis olabilir. Birkac dakika sonra tekrar kontrol et. Hala baslamiyorsa teknik destek talebi acman gerekir.',
                'ticket_subject' => 'AI analiz sorunu',
                'ticket_description' => 'Kullanici AI analiz akisinda sorun yasiyor. Video uygunlugu, analiz kuyrugu ve callback islemleri kontrol edilmeli.',
                'suggested_actions' => $this->analysisActionsForRole($role),
                'quick_replies' => [
                    'Analiz neden baslamadi?',
                    'AI Discovery sonucu ne zaman gelir?',
                ],
            ];
        }

        if ($this->containsAny($message, ['basvuru', 'ilan', 'opportunity', 'uygulama', 'application'])) {
            return [
                'category' => 'applications',
                'severity' => 'medium',
                'should_open_ticket' => false,
                'reply' => 'Basvurular icin once uygun ilanlari ve gonderilen basvurulari kontrol et. Basvuru gorunmuyor ya da durum guncellenmiyorsa ilgili ilan ve cikis tarihini yeniden kontrol et. Rolune gore gelen ve giden basvuru ekranlari farkli olabilir.',
                'ticket_subject' => 'Basvuru veya ilan sorunu',
                'ticket_description' => 'Kullanici basvuru, ilan veya uygulama akisinda sorun yasiyor. Basvuru kaydi ve durum gecmisleri kontrol edilmeli.',
                'suggested_actions' => $this->applicationActionsForRole($role),
                'quick_replies' => [
                    'Basvurum neden gorunmuyor?',
                    'Gelen basvurulari nereden gorurum?',
                ],
            ];
        }

        if ($this->containsAny($message, ['scout merkezi', 'rapor', 'score', 'scorecard', 'benzerini bul', 'look-alike'])) {
            return [
                'category' => 'scout_tools',
                'severity' => 'low',
                'should_open_ticket' => false,
                'reply' => $this->scoutToolsReplyForRole($role),
                'ticket_subject' => 'Scout araclari yonlendirme sorunu',
                'ticket_description' => 'Kullanici scout araclarina erisim veya dogru ekran yonlendirmesi konusunda destek istiyor.',
                'suggested_actions' => $this->scoutToolActionsForRole($role),
                'quick_replies' => [
                    'Scout Merkezi nerede?',
                    'Benzerini Bul ne ise yarar?',
                ],
            ];
        }

        if ($this->containsAny($message, ['mac yok', 'uygun mac yok', 'no matches', 'kamuya acik mac yok'])) {
            return [
                'category' => 'watch_requests',
                'severity' => 'low',
                'should_open_ticket' => false,
                'reply' => 'Bu durumda secilen tarih, bolge veya pozisyon icin acik mac kaydi bulunmamis olabilir. Tarih araligini genisletmeyi, sehir ya da yaricap bilgisini degistirmeyi dene.',
                'ticket_subject' => 'Canli izleme sonucu bos',
                'ticket_description' => 'Kullanici canli izleme talebinde uygun mac veya oyuncu sonucu alamiyor. Tarih, bolge ve acik mac verisi kontrol edilmeli.',
                'suggested_actions' => $this->watchActionsForRole($role),
                'quick_replies' => [
                    'Yaricapi artirsam daha fazla sonuc gelir mi?',
                    'Hangi tarihlerde mac bulabilirim?',
                ],
            ];
        }

        if ($this->containsAny($message, ['izleme', 'mac', 'canli', 'watch'])) {
            return [
                'category' => 'watch_requests',
                'severity' => 'low',
                'should_open_ticket' => false,
                'reply' => 'Canli izleme talebi icin tarih, sehir, bolge ve pozisyon secimi gerekir. Talep olusturduktan sonra sistem uygun maclari ve sicak bolgeleri listeler. Sonuc gelmiyorsa o tarih araliginda acik mac kaydi olmayabilir.',
                'ticket_subject' => 'Canli izleme talebi sorunu',
                'ticket_description' => 'Kullanici canli izleme talebi akisi veya sonuc ekraninda sorun yasiyor. Watch request ve acik mac verisi kontrol edilmeli.',
                'suggested_actions' => $this->watchActionsForRole($role),
                'quick_replies' => [
                    'Canli izleme sonucu neden bos?',
                    'Hangi alanlar zorunlu?',
                ],
            ];
        }

        if ($this->containsAny($message, ['mesaj', 'iletisim', 'contact'])) {
            return [
                'category' => 'communication',
                'severity' => 'low',
                'should_open_ticket' => false,
                'reply' => 'Mesaj ve iletisim tarafinda once alici arama ve inbox ekranlarini kontrol et. Gonderilen mesaj gorunmuyorsa baglanti sorunu ya da alici yetki siniri olabilir. Tekrarlayan durumda destek talebi acman gerekir.',
                'ticket_subject' => 'Mesajlasma veya iletisim sorunu',
                'ticket_description' => 'Kullanici mesajlasma akisinda sorun yasiyor. Alici arama, inbox ve gonderim durumlari kontrol edilmeli.',
                'suggested_actions' => $this->messageActionsForRole($role),
                'quick_replies' => [
                    'Mesajim neden gitmedi?',
                    'Inbox nerede?',
                ],
            ];
        }

        return [
            'category' => 'general',
            'severity' => 'low',
            'should_open_ticket' => true,
            'reply' => 'Sorununu anladim ama daha net yonlendirme icin biraz daha ayrinti gerekir. Hangi ekranda oldugunu, neye bastigini ve gordugun hata mesajini yazarsan daha dogru yardim edebilirim. Istersen destek talebi de acabilirsin.',
            'ticket_subject' => 'Genel destek talebi',
            'ticket_description' => 'Kullanici genel bir sorun bildiriyor. Hangi ekranda ve hangi adimda sorun yasadigi detaylandirilmali.',
            'suggested_actions' => [
                ['label' => 'Sorunu Detaylandir', 'target' => 'retry'],
                ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
            ],
            'quick_replies' => [
                'Video yuklenmiyor',
                'Profilim eksik gorunuyor',
                'Analiz baslamiyor',
            ],
        ];
    }

    private function videoActionsForRole(string $role): array
    {
        if ($role === 'player') {
            return [
                ['label' => 'Medya Alanina Git', 'target' => 'media'],
                ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
            ];
        }

        return [
            ['label' => 'Profili Kontrol Et', 'target' => 'profile'],
            ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
        ];
    }

    private function profileActionsForRole(string $role): array
    {
        $actions = [
            ['label' => 'Profili Kontrol Et', 'target' => 'profile'],
        ];

        if ($role === 'player') {
            $actions[] = ['label' => 'Medya Alanini Kontrol Et', 'target' => 'media'];
        } elseif (in_array($role, ['scout', 'manager', 'coach', 'team', 'club'], true)) {
            $actions[] = ['label' => 'Scout Merkezi', 'target' => 'scout_reports'];
        }

        return $actions;
    }

    private function analysisActionsForRole(string $role): array
    {
        if ($role === 'player') {
            return [
                ['label' => 'Video Analizlerini Kontrol Et', 'target' => 'analysis'],
                ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
            ];
        }

        return [
            ['label' => 'Scout Merkezi', 'target' => 'scout_reports'],
            ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
        ];
    }

    private function applicationActionsForRole(string $role): array
    {
        if ($role === 'player') {
            return [
                ['label' => 'Basvurulari Ac', 'target' => 'applications'],
                ['label' => 'Ilanlari Ac', 'target' => 'opportunities'],
            ];
        }

        return [
            ['label' => 'Profili Kontrol Et', 'target' => 'profile'],
            ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
        ];
    }

    private function scoutToolsReplyForRole(string $role): string
    {
        if ($role === 'scout') {
            return 'Scout araclari icin profil ve workspace icindeki ilgili butonlari kullanabilirsin. Scout Merkezi rapor havuzunu, Score Card hizli degerlendirmeyi, Benzerini Bul ise referans oyuncu aramasini aciyor.';
        }

        if (in_array($role, ['manager', 'coach', 'team', 'club'], true)) {
            return 'Bu rolde Scout Merkezi ve canli izleme akisi kullanilabilir. Benzerini Bul gibi bazi araclar yalnizca scout tarafinda acik olabilir.';
        }

        return 'Scout araclari rol bazlidir. Hesabinda ilgili ekranlar gorunmuyorsa bu aracin senin rolune acik olup olmadigini kontrol etmen gerekir.';
    }

    private function scoutToolActionsForRole(string $role): array
    {
        if ($role === 'scout') {
            return [
                ['label' => 'Scout Merkezi', 'target' => 'scout_reports'],
                ['label' => 'Benzerini Bul', 'target' => 'look_alike'],
                ['label' => 'Scout Profil', 'target' => 'profile'],
            ];
        }

        if (in_array($role, ['manager', 'coach', 'team', 'club'], true)) {
            return [
                ['label' => 'Scout Merkezi', 'target' => 'scout_reports'],
                ['label' => 'Canli Izleme Talebi', 'target' => 'watch_requests'],
                ['label' => 'Profili Kontrol Et', 'target' => 'profile'],
            ];
        }

        return [
            ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
        ];
    }

    private function watchActionsForRole(string $role): array
    {
        if (in_array($role, ['scout', 'manager', 'coach', 'team', 'club'], true)) {
            return [
                ['label' => 'Canli Izleme Talebi', 'target' => 'watch_requests'],
                ['label' => 'Acik Maclari Kontrol Et', 'target' => 'live_matches'],
            ];
        }

        return [
            ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
        ];
    }

    private function messageActionsForRole(string $role): array
    {
        if ($role === 'player') {
            return [
                ['label' => 'Mesajlari Ac', 'target' => 'messages'],
                ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
            ];
        }

        return [
            ['label' => 'Profili Kontrol Et', 'target' => 'profile'],
            ['label' => 'Destek Talebi Ac', 'target' => 'support_ticket'],
        ];
    }

    private function containsAny(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
