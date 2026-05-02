<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateDemoRoleAccounts extends Command
{
    protected $signature = 'demo:role-accounts
        {--password=Demo123! : Shared password for generated demo users}
        {--allow-production : Explicitly allow running in production}';

    protected $description = 'Create role and branch based demo accounts for player, scout, manager, club, and coach roles.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production ortaminda calismak icin --allow-production ver.');

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        if (mb_strlen($password) < 6) {
            $this->error('Sifre en az 6 karakter olmali.');

            return self::FAILURE;
        }

        $roles = [
            'player' => 'Oyuncu',
            'scout' => 'Scout',
            'manager' => 'Manager',
            'club' => 'Kulup',
            'coach' => 'Coach',
        ];
        $sports = [
            'football' => 'football',
            'basketball' => 'basketball',
            'volleyball' => 'volleyball',
        ];

        $created = 0;
        $updated = 0;

        foreach ($roles as $role => $roleLabel) {
            foreach ($sports as $sportKey => $sportValue) {
                for ($index = 1; $index <= 2; $index++) {
                    $email = sprintf('demo.%s.%s.%d@nextscout.local', $role, $sportKey, $index);
                    $name = sprintf('%s %s %d', $roleLabel, ucfirst($sportKey), $index);

                    $user = User::query()->where('email', $email)->first();
                    $payload = [
                        'name' => $name,
                        'password' => Hash::make($password),
                        'role' => $role,
                        'sport' => $sportValue,
                        'city' => 'Istanbul',
                        'country' => 'TR',
                        'is_public' => true,
                        'is_verified' => true,
                        'verification_status' => 'verified',
                        'email_verified_at' => now(),
                        'email_verification_token' => null,
                        'auth_provider' => 'laravel',
                        'player_password_initialized' => true,
                        'subscription_status' => 'free',
                    ];

                    if ($user) {
                        $user->forceFill($payload)->save();
                        $updated++;
                    } else {
                        User::query()->create(array_merge($payload, [
                            'email' => $email,
                        ]));
                        $created++;
                    }
                }
            }
        }

        $this->info("Demo hesaplar hazir. created={$created} updated={$updated}");
        $this->line('Ortak sifre: '.$password);
        $this->newLine();

        foreach (array_keys($roles) as $role) {
            foreach (array_keys($sports) as $sport) {
                for ($index = 1; $index <= 2; $index++) {
                    $this->line(sprintf(
                        '%s | %s | demo.%s.%s.%d@nextscout.local',
                        $role,
                        $sport,
                        $role,
                        $sport,
                        $index
                    ));
                }
            }
        }

        return self::SUCCESS;
    }
}
