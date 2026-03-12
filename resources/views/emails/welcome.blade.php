<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextScout'a Hoş Geldiniz</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: #1a56db; padding: 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 28px; }
        .header p { color: #93c5fd; margin: 8px 0 0; }
        .body { padding: 32px; }
        .body h2 { color: #1e293b; margin-top: 0; }
        .body p { color: #475569; line-height: 1.6; }
        .btn { display: inline-block; background: #1a56db; color: #fff !important; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 16px; font-weight: bold; margin: 24px 0; }
        .footer { background: #f8fafc; padding: 20px 32px; text-align: center; color: #94a3b8; font-size: 13px; }
        .footer a { color: #64748b; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>⚽ {{ $appName }}</h1>
        <p>Futbol scouting platformu</p>
    </div>
    <div class="body">
        <h2>Hoş Geldiniz, {{ $user->name }}! 👋</h2>
        <p>
            <strong>{{ $appName }}</strong> ailesine katıldığınız için teşekkürler.
            Hesabınız başarıyla oluşturuldu. Platformu kullanmaya başlamak için
            e-posta adresinizi doğrulamanız gerekmektedir.
        </p>

        @if($verificationLink)
        <p style="text-align:center;">
            <a href="{{ $verificationLink }}" class="btn">✅ E-Postamı Doğrula</a>
        </p>
        <p style="color:#94a3b8; font-size:13px;">
            Butona tıklamıyor musunuz? Bu linki tarayıcınıza kopyalayın:<br>
            <a href="{{ $verificationLink }}" style="color:#1a56db; word-break:break-all;">{{ $verificationLink }}</a>
        </p>
        @endif

        <hr style="border:none; border-top:1px solid #e2e8f0; margin: 24px 0;">
        <p style="font-size:13px; color:#94a3b8;">
            Bu e-postayı siz talep etmediyseniz güvenle yok sayabilirsiniz.
            Hesabınız otomatik olarak silinecektir.
        </p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} {{ $appName }}. Tüm hakları saklıdır.<br>
        <a href="{{ config('app.url') }}">{{ config('app.url') }}</a>
    </div>
</div>
</body>
</html>
