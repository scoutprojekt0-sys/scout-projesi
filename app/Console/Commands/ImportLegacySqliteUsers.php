<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportLegacySqliteUsers extends Command
{
    protected $signature = 'users:import-legacy-sqlite {--source=* : Optional absolute SQLite file paths}';

    protected $description = 'Import users from local legacy SQLite databases into the active backend database.';

    public function handle(): int
    {
        $sources = $this->resolveSources();

        if ($sources === []) {
            $this->warn('Import edilecek legacy SQLite veritabani bulunamadi.');

            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($sources as $path) {
            $this->line("Kaynak: {$path}");

            config([
                'database.connections.sqlite_import' => [
                    'driver' => 'sqlite',
                    'database' => $path,
                    'prefix' => '',
                    'foreign_key_constraints' => false,
                ],
            ]);
            DB::purge('sqlite_import');

            try {
                $rows = DB::connection('sqlite_import')->select('SELECT * FROM users ORDER BY id');
            } catch (\Throwable $e) {
                $this->error("  Okunamadi: {$e->getMessage()}");
                continue;
            }

            foreach ($rows as $row) {
                $email = strtolower(trim((string) ($row->email ?? '')));

                if ($email === '') {
                    $skipped++;
                    $this->warn('  Atlandi: bos email');
                    continue;
                }

                if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    $skipped++;
                    $this->line("  Atlandi: {$email} zaten aktif DB'de var");
                    continue;
                }

                $password = (string) ($row->password ?? '');
                if (! $this->looksLikeBcrypt($password)) {
                    $password = Hash::make(Str::random(16));
                }

                $createdAt = $this->normalizeDate($row->created_at ?? null);
                $updatedAt = $this->normalizeDate($row->updated_at ?? null);

                User::query()->create([
                    'name' => $this->normalizeName($row->name ?? null, $email),
                    'email' => $email,
                    'password' => $password,
                    'role' => $this->normalizeRole($row->role ?? null),
                    'city' => $this->nullableTrim($row->city ?? null),
                    'phone' => $this->nullableTrim($row->phone ?? null),
                    'position' => $this->nullableTrim($row->position ?? null),
                    'country' => $this->nullableTrim($row->country ?? null),
                    'age' => $this->nullableInt($row->age ?? null),
                    'photo_url' => $this->nullableTrim($row->photo_url ?? null),
                    'views_count' => $this->nullableInt($row->views_count ?? null) ?? 0,
                    'rating' => $this->nullableNumber($row->rating ?? null),
                    'is_verified' => $this->normalizeVerified($row),
                    'email_verified_at' => $this->normalizeVerifiedAt($row),
                    'player_password_initialized' => (bool) ($row->player_password_initialized ?? false),
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt ?? $createdAt ?? now(),
                ]);

                $imported++;
                $this->info("  Aktarildi: {$email}");
            }
        }

        $this->newLine();
        $this->info("Tamamlandi. Aktarilan: {$imported}, atlanan: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveSources(): array
    {
        $cliSources = array_values(array_filter(array_map(
            fn ($path) => is_string($path) ? trim($path) : '',
            (array) $this->option('source')
        )));

        if ($cliSources !== []) {
            return array_values(array_filter($cliSources, 'is_file'));
        }

        $root = realpath(base_path('..'));
        if (! is_string($root) || $root === '') {
            return [];
        }

        $candidates = [
            $root.'\archive_legacy_backend\scout_api_legacy_2026_03_11\database\database.sqlite',
            $root.'\PhpstormProjects\PhpstormProjects\untitled\scout_api\database\database.sqlite',
            $root.'\PhpstormProjects\PhpstormProjects\untitled\untitled\scout_api\database\database.sqlite',
        ];

        $activeDb = realpath(database_path('database.sqlite'));

        return array_values(array_unique(array_filter($candidates, function (string $path) use ($activeDb): bool {
            $real = realpath($path);

            return $real !== false && $real !== $activeDb && is_file($real);
        })));
    }

    private function normalizeName(mixed $value, string $email): string
    {
        $name = trim((string) $value);
        if ($name !== '') {
            return Str::limit($name, 120, '');
        }

        return Str::limit(Str::before($email, '@'), 120, '');
    }

    private function normalizeRole(mixed $value): string
    {
        $role = strtolower(trim((string) $value));

        return match ($role) {
            'admin' => 'manager',
            'club' => 'team',
            'antrenor' => 'coach',
            'menajer', 'menejer' => 'manager',
            'oyuncu' => 'player',
            default => in_array($role, ['player', 'manager', 'coach', 'scout', 'team', 'lawyer'], true) ? $role : 'player',
        };
    }

    private function normalizeVerified(object $row): bool
    {
        if (property_exists($row, 'is_verified') && $row->is_verified !== null) {
            return (bool) $row->is_verified;
        }

        return true;
    }

    private function normalizeVerifiedAt(object $row): ?Carbon
    {
        $explicit = $this->normalizeDate($row->email_verified_at ?? null);
        if ($explicit instanceof Carbon) {
            return $explicit;
        }

        return $this->normalizeVerified($row) ? now() : null;
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function looksLikeBcrypt(string $hash): bool
    {
        return preg_match('/^\$2y\$\d{2}\$.{53}$/', $hash) === 1;
    }
}
