<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class ResetLegacyPasswords extends Command
{
    protected $signature = 'users:reset-legacy-passwords {--send-email}';
    protected $description = 'Tüm eski kullanıcıların şifrelerini sıfırlar ve eksik alanları günceller.';

    public function handle()
    {
        $updated = 0;
        $users = User::all();
        foreach ($users as $user) {
            // Şifre bcrypt ile hashlenmiş mi kontrolü (60 karakter ve $2y$ ile başlar)
            if (!is_string($user->password) || strlen($user->password) !== 60 || strpos($user->password, '$2y$') !== 0) {
                $newPassword = Str::random(10);
                $user->password = Hash::make($newPassword);
                $updated++;
                $this->info("[{$user->email}] için yeni şifre: $newPassword");
                if ($this->option('send-email')) {
                    // Burada e-posta gönderme kodu eklenebilir
                    // \Mail::to($user->email)->send(new \App\Mail\LegacyPasswordResetMail($user, $newPassword));
                }
            }
            // Eksik alanları güncelle
            if (is_null($user->is_verified)) {
                $user->is_verified = true;
            }
            if (is_null($user->email_verified_at)) {
                $user->email_verified_at = Carbon::now();
            }
            $user->save();
        }
        $this->info("Toplam $updated kullanıcının şifresi sıfırlandı ve alanlar güncellendi.");
        return 0;
    }
}
