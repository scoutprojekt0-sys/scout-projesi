<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Auth Design Demo</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="ds-page ds-stack">
            <header class="ds-stack">
                <h1 class="ds-h1">Auth Design System v1</h1>
                <p class="ds-caption">Login, register ve reset yuzeylerinde ortak token ve component kullanimi.</p>
            </header>

            <section class="ds-grid md:grid-cols-3">
                <article class="ds-card ds-stack">
                    <h2 class="ds-h2">Login</h2>
                    <div class="ds-field">
                        <label class="ds-label" for="login-email">Email</label>
                        <input id="login-email" class="ds-input" type="email" placeholder="player@scoutarena.com">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="login-password">Password</label>
                        <input id="login-password" class="ds-input" type="password" placeholder="********">
                        <p class="ds-help">Minimum 8 karakter.</p>
                    </div>
                    <div class="ds-button-row">
                        <button class="ds-btn ds-btn-primary" type="button">Sign In</button>
                        <button class="ds-btn ds-btn-ghost" type="button">Forgot Password</button>
                    </div>
                </article>

                <article class="ds-card ds-stack">
                    <h2 class="ds-h2">Register</h2>
                    <div class="ds-field">
                        <label class="ds-label" for="register-name">Full Name</label>
                        <input id="register-name" class="ds-input" type="text" placeholder="Scout User">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="register-email">Email</label>
                        <input id="register-email" class="ds-input ds-input-error" type="email" placeholder="mail@example.com">
                        <p class="ds-help ds-help-error">Bu email zaten kullaniliyor.</p>
                    </div>
                    <div class="ds-button-row">
                        <button class="ds-btn ds-btn-primary" type="button">Create Account</button>
                        <button class="ds-btn ds-btn-secondary" type="button">Sign In Instead</button>
                    </div>
                </article>

                <article class="ds-card ds-stack">
                    <h2 class="ds-h2">Reset Password</h2>
                    <div class="ds-alert ds-alert-success">Reset linki email adresine gonderildi.</div>
                    <div class="ds-alert ds-alert-warning">Link suresi dolmak uzere.</div>
                    <div class="ds-alert ds-alert-danger">Token gecersiz veya suresi dolmus.</div>
                    <div class="ds-button-row">
                        <button class="ds-btn ds-btn-primary" type="button">Resend Link</button>
                        <button class="ds-btn ds-btn-secondary ds-btn-disabled" type="button" disabled>Sending...</button>
                    </div>
                </article>
            </section>
        </main>
    </body>
</html>
