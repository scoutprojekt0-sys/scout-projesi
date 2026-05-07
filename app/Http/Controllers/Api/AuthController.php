<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Concerns\ResolvesPublicFileUrls;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateMeRequest;
use App\Jobs\SendWelcomeEmail;
use App\Models\AuditEvent;
use App\Models\ClubInternalPlayer;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Support\SportBranch;
use App\Services\BrevoEmailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    use ApiResponds;
    use ResolvesPublicFileUrls;

    private const CLUB_LOGIN_ROLES = ['team', 'club', 'kulup'];
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $sport = SportBranch::normalize($data['branch'] ?? null);
        $verificationRequired = $this->emailVerificationRequired();
        $verificationToken = $verificationRequired ? Str::random(64) : null;

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'sport' => $sport,
            'city' => $data['city'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_verified' => ! $verificationRequired,
            'email_verified_at' => $verificationRequired ? null : now(),
            'email_verification_token' => $verificationToken,
        ]);
        $verificationLink = $verificationToken ? $this->buildEmailVerificationLink($verificationToken) : null;

        if (in_array($user->role, ['manager', 'coach', 'scout'], true)) {
            DB::table('staff_profiles')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'role_type' => $user->role,
                    'branch' => SportBranch::label($sport),
                    'updated_at' => now(),
                ]
            );
        }

        // HoÅŸgeldin emailini kuyruÄŸa gÃ¶nder
        if ($verificationRequired && $verificationLink) {
            SendWelcomeEmail::dispatchAfterResponse($user, $verificationLink);
        }

        return response()->json([
            'ok' => true,
            'code' => 'auth_registered',
            'message' => $verificationRequired
                ? 'Kayit alindi. E-posta dogrulamasi bekleniyor.'
                : 'Kayit alindi. Artik giris yapabilirsiniz.',
            'data' => [
                'user' => $user,
                'verification_required' => $verificationRequired,
                'verification_preview_link' => $verificationLink,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $email = strtolower($credentials['email']);
        $ip = (string) $request->ip();
        $lockKey = 'auth-lock:'.$email.'|'.$ip;
        $attemptKey = 'auth-attempt:'.$email.'|'.$ip;
        $maxFailedAttempts = (int) config('scout.rate_limits.auth_failed_attempts_before_lock', 5);
        $lockSeconds = (int) config('scout.rate_limits.auth_lock_seconds', 15 * 60);

        if (RateLimiter::tooManyAttempts($lockKey, 1)) {
            $seconds = RateLimiter::availableIn($lockKey);

            return response()->json([
                'ok' => false,
                'code' => 'auth_temporarily_locked',
                'message' => 'Cok fazla hatali deneme. Lutfen daha sonra tekrar deneyin.',
                'retry_after' => $seconds,
            ], Response::HTTP_LOCKED);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($attemptKey, $lockSeconds);
            $attempts = RateLimiter::attempts($attemptKey);

            if ($attempts >= $maxFailedAttempts) {
                RateLimiter::hit($lockKey, $lockSeconds);
                RateLimiter::clear($attemptKey);
            }

            Log::channel('security')->warning('Login failed', [
                'email' => $email,
                'ip' => $ip,
                'attempts' => $attempts,
                'locked' => $attempts >= $maxFailedAttempts,
            ]);

            return response()->json([
                'ok' => false,
                'code' => 'auth_invalid_credentials',
                'message' => 'E-posta veya sifre hatali.',
                'errors' => [
                    'email' => ['E-posta veya sifre hatali.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->emailVerificationRequired() && ! $this->isUserVerified($user)) {
            return response()->json([
                'ok' => false,
                'code' => 'email_not_verified',
                'message' => 'Hesap dogrulanmadi. E-postadaki linke tiklayip tekrar giris yapin.',
                'errors' => [
                    'email' => ['Hesap dogrulanmadi. E-postadaki linke tiklayip tekrar giris yapin.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        RateLimiter::clear($attemptKey);
        RateLimiter::clear($lockKey);

        // Keep only latest 5 tokens for each user.
        $tokenCount = $user->tokens()->count();
        if ($tokenCount >= 5) {
            $user->tokens()->oldest()->limit($tokenCount - 4)->delete();
        }

        [$token, $expiresAt] = $this->issueToken($user, $request);

        Log::channel('security')->info('Login success', [
            'user_id' => $user->id,
            'email' => $email,
            'ip' => $ip,
        ]);

        return response()->json([
            'ok' => true,
            'code' => 'auth_logged_in',
            'message' => 'Giris basarili.',
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt,
                'user' => $user,
            ],
        ]);
    }

    public function playerLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_name' => ['required', 'string', 'min:2', 'max:140'],
            'player_name' => ['required', 'string', 'min:2', 'max:120'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $teamName = $this->normalizeLookupValue($validated['team_name']);
        $playerName = $this->normalizeLookupValue($validated['player_name']);
        $password = (string) $validated['password'];
        $ip = (string) $request->ip();
        $identity = $teamName.'|'.$playerName;
        $lockKey = 'player-auth-lock:'.$identity.'|'.$ip;
        $attemptKey = 'player-auth-attempt:'.$identity.'|'.$ip;
        $maxFailedAttempts = (int) config('scout.rate_limits.auth_failed_attempts_before_lock', 5);
        $lockSeconds = (int) config('scout.rate_limits.auth_lock_seconds', 15 * 60);

        if (RateLimiter::tooManyAttempts($lockKey, 1)) {
            return response()->json([
                'ok' => false,
                'code' => 'auth_temporarily_locked',
                'message' => 'Cok fazla hatali deneme. Lutfen daha sonra tekrar deneyin.',
                'retry_after' => RateLimiter::availableIn($lockKey),
            ], Response::HTTP_LOCKED);
        }

        $user = $this->findPlayerForClubLogin($teamName, $playerName);

        if (! $user || ! (bool) $user->player_password_initialized || ! Hash::check($password, (string) $user->password)) {
            return $this->playerLoginFailedResponse($teamName, $playerName, $ip, $attemptKey, $lockKey, $lockSeconds, $maxFailedAttempts);
        }

        RateLimiter::clear($attemptKey);
        RateLimiter::clear($lockKey);

        [$token, $expiresAt] = $this->issueToken($user, $request);

        return response()->json([
            'ok' => true,
            'code' => 'player_logged_in',
            'message' => 'Oyuncu girisi basarili.',
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt,
                'user' => $user,
            ],
        ]);
    }

    public function playerSetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_name' => ['required', 'string', 'min:2', 'max:140'],
            'player_name' => ['required', 'string', 'min:2', 'max:120'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $teamName = $this->normalizeLookupValue($validated['team_name']);
        $playerName = $this->normalizeLookupValue($validated['player_name']);
        $user = $this->findPlayerForClubLogin($teamName, $playerName)
            ?? $this->createPlayerUserFromInternalPlayer($teamName, $playerName);

        if (! $user) {
            return response()->json([
                'ok' => false,
                'code' => 'player_not_found_for_club',
                'message' => 'Takim adi veya oyuncu adi hatali.',
                'errors' => [
                    'player_name' => ['Takim adi veya oyuncu adi hatali.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ((bool) $user->player_password_initialized) {
            return response()->json([
                'ok' => false,
                'code' => 'player_password_already_initialized',
                'message' => 'Bu oyuncu icin ilk giris sifresi zaten olusturulmus.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->forceFill([
            'password' => Hash::make((string) $validated['password']),
            'player_password_initialized' => true,
        ])->save();

        $freshUser = $user->fresh();
        [$token, $expiresAt] = $this->issueToken($freshUser, $request);

        return response()->json([
            'ok' => true,
            'code' => 'player_password_initialized',
            'message' => 'Ilk giris sifresi olusturuldu.',
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt,
                'user' => $freshUser,
            ],
        ], Response::HTTP_CREATED);
    }

    public function exchangeSupabaseToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string', 'min:20'],
            'role' => ['nullable', Rule::in(['player', 'manager', 'coach', 'scout', 'team'])],
            'name' => ['nullable', 'string', 'min:2', 'max:120'],
        ]);

        $supabaseUrl = rtrim((string) config('supabase.url', ''), '/');
        $supabaseAnonKey = (string) config('supabase.anon_key', '');

        if ($supabaseUrl === '' || $supabaseAnonKey === '') {
            return response()->json([
                'ok' => false,
                'code' => 'supabase_not_configured',
                'message' => 'Supabase ayarlari tamamlanmadi.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $supabaseResponse = Http::withHeaders([
            'apikey' => $supabaseAnonKey,
            'Authorization' => 'Bearer '.$validated['access_token'],
            'Accept' => 'application/json',
        ])->get($supabaseUrl.'/auth/v1/user');

        if (! $supabaseResponse->successful()) {
            return response()->json([
                'ok' => false,
                'code' => 'supabase_token_invalid',
                'message' => 'Supabase oturumu dogrulanamadi.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $supabaseUser = $supabaseResponse->json();
        $supabaseUserId = (string) ($supabaseUser['id'] ?? '');
        $email = strtolower((string) ($supabaseUser['email'] ?? ''));
        $emailConfirmedAt = $supabaseUser['email_confirmed_at'] ?? null;
        $metadata = is_array($supabaseUser['user_metadata'] ?? null) ? $supabaseUser['user_metadata'] : [];
        $name = trim((string) ($validated['name']
            ?? $metadata['name']
            ?? $metadata['full_name']
            ?? Str::before($email, '@')));

        if ($supabaseUserId === '' || $email === '') {
            return response()->json([
                'ok' => false,
                'code' => 'supabase_user_payload_invalid',
                'message' => 'Supabase kullanici bilgisi eksik.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($emailConfirmedAt === null) {
            return response()->json([
                'ok' => false,
                'code' => 'email_not_verified',
                'message' => 'Hesap dogrulanmadi. E-postadaki linke tiklayip tekrar deneyin.',
                'errors' => [
                    'email' => ['Hesap dogrulanmadi. E-postadaki linke tiklayip tekrar deneyin.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('supabase_user_id', $supabaseUserId)
            ->orWhere('email', $email)
            ->first();

        if (! $user) {
            $role = (string) ($validated['role'] ?? 'player');
            $user = User::create([
                'name' => $name !== '' ? $name : 'NextScout User',
                'email' => $email,
                'supabase_user_id' => $supabaseUserId,
                'password' => Hash::make(Str::random(32)),
                'auth_provider' => 'supabase',
                'role' => $role,
                'is_verified' => true,
                'email_verified_at' => Carbon::parse((string) $emailConfirmedAt),
            ]);
        } else {
            $user->forceFill([
                'supabase_user_id' => $user->supabase_user_id ?: $supabaseUserId,
                'auth_provider' => 'supabase',
                'is_verified' => true,
                'email_verified_at' => $user->email_verified_at ?: Carbon::parse((string) $emailConfirmedAt),
            ])->save();
        }

        [$token, $expiresAt] = $this->issueToken($user->fresh(), $request);

        return response()->json([
            'ok' => true,
            'code' => 'auth_logged_in',
            'message' => 'Giris basarili.',
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt,
                'user' => $user->fresh(),
            ],
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:16'],
        ]);

        /** @var User|null $user */
        $user = User::query()
            ->where('email_verification_token', $validated['token'])
            ->first();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'code' => 'email_verification_token_invalid',
                'message' => 'Dogrulama linki gecersiz veya suresi dolmus.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'is_verified' => true,
            'email_verification_token' => null,
        ])->save();

        [$token, $expiresAt] = $this->issueToken($user, $request);

        return response()->json([
            'ok' => true,
            'code' => 'email_verified',
            'message' => 'E-posta dogrulandi. Artik giris yapabilirsiniz.',
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt,
                'user' => $user->fresh(),
            ],
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        /** @var User|null $user */
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            return response()->json([
                'ok' => true,
                'code' => 'verification_resend_accepted',
                'message' => 'Eger hesap varsa dogrulama linki tekrar gonderilir.',
            ]);
        }

        if ($this->isUserVerified($user)) {
            return response()->json([
                'ok' => true,
                'code' => 'already_verified',
                'message' => 'Bu hesap zaten dogrulanmis.',
            ]);
        }

        $user->forceFill([
            'email_verification_token' => $user->email_verification_token ?: Str::random(64),
        ])->save();

        SendWelcomeEmail::dispatchAfterResponse(
            $user,
            $this->buildEmailVerificationLink((string) $user->email_verification_token)
        );

        return response()->json([
            'ok' => true,
            'code' => 'verification_link_ready',
            'message' => 'Dogrulama linki hazirlandi.',
            'data' => [
                'verification_preview_link' => $this->buildEmailVerificationLink((string) $user->email_verification_token),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'ok' => true,
            'code' => 'auth_logged_out',
            'message' => 'Cikis yapildi.',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = strtolower((string) $request->validated('email'));
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $token = Password::broker()->createToken($user);

            try {
                app(BrevoEmailService::class)->sendPasswordResetEmail(
                    $user,
                    $this->buildPasswordResetLink($email, $token)
                );
            } catch (\Throwable $e) {
                Log::error('Password reset email failed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'code' => 'password_reset_link_sent',
            'message' => 'Eger hesap mevcutsa sifre yenileme baglantisi gonderildi.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower((string) $validated['email']);

        $status = Password::reset(
            [
                'email' => $email,
                'token' => (string) $validated['token'],
                'password' => (string) $validated['password'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'ok' => false,
                'code' => 'password_reset_token_invalid',
                'message' => 'Sifre yenileme baglantisi gecersiz veya suresi dolmus.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'ok' => true,
            'code' => 'password_reset_success',
            'message' => 'Sifre basariyla guncellendi. Lutfen tekrar giris yapin.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        if ((string) $user->auth_provider === 'supabase') {
            return response()->json([
                'ok' => false,
                'code' => 'password_change_not_supported_for_provider',
                'message' => 'Bu hesap icin sifre degistirme uygulama icinden desteklenmiyor.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return response()->json([
                'ok' => false,
                'code' => 'password_change_current_password_invalid',
                'message' => 'Mevcut sifre hatali.',
                'errors' => [
                    'current_password' => ['Mevcut sifre hatali.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (Hash::check((string) $validated['password'], (string) $user->password)) {
            return response()->json([
                'ok' => false,
                'code' => 'password_change_same_as_current',
                'message' => 'Yeni sifre mevcut sifre ile ayni olamaz.',
                'errors' => [
                    'password' => ['Yeni sifre mevcut sifre ile ayni olamaz.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $currentTokenId = $user->currentAccessToken()?->id;

        $user->forceFill([
            'password' => Hash::make((string) $validated['password']),
            'player_password_initialized' => true,
        ])->save();

        if ($currentTokenId !== null) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        Log::channel('security')->info('Password changed', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);
        $this->recordAuditEvent($user->id, 'auth.password.changed', [
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'code' => 'password_changed',
            'message' => 'Sifre basariyla guncellendi.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse($this->transformUser($request->user()), 'Profil bilgileri hazir.');
    }

    public function sessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $sessions = $user->tokens()
            ->orderByDesc('id')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
            ->map(function ($token) use ($currentTokenId) {
                $abilities = is_array($token->abilities) ? $token->abilities : [];

                return [
                    'id' => $token->id,
                    'device_label' => (string) $token->name,
                    'is_current' => (int) $token->id === (int) $currentTokenId,
                    'abilities' => $abilities,
                    'last_used_at' => optional($token->last_used_at)?->toISOString(),
                    'expires_at' => optional($token->expires_at)?->toISOString(),
                    'created_at' => optional($token->created_at)?->toISOString(),
                ];
            })->values();

        return $this->successResponse($sessions, 'Aktif oturumlar hazir.');
    }

    public function updateMe(UpdateMeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (array_key_exists('email', $data)) {
            $data['email'] = strtolower($data['email']);
        }

        if (array_key_exists('password', $data)) {
            $data['password'] = Hash::make($data['password']);
            $user->tokens()->delete();
        }

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('profile-photos', 'public');
            $data['photo_url'] = $path;
        }

        $user->fill($data);
        $user->save();

        return response()->json([
            'ok' => true,
            'code' => 'profile_updated',
            'message' => 'Profil guncellendi.',
            'data' => $this->transformUser($user->fresh()),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken?->id ?? $this->resolveBearerTokenId($request->bearerToken());

        [$token, $expiresAt] = $this->issueToken($user, $request);

        if ($currentTokenId !== null) {
            $user->tokens()->where('id', $currentTokenId)->delete();
        }

        Log::channel('security')->info('Token refreshed', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);
        $this->recordAuditEvent($user->id, 'auth.session.refresh', [
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'code' => 'auth_refreshed',
            'message' => 'Oturum yenilendi.',
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt,
            ],
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $query = $user->tokens();
        if ($currentTokenId !== null) {
            $query->where('id', '!=', $currentTokenId);
        }

        $revoked = $query->count();
        $query->delete();

        Log::channel('security')->info('All sessions revoked except current', [
            'user_id' => $user->id,
            'revoked_count' => $revoked,
            'ip' => $request->ip(),
        ]);
        $this->recordAuditEvent($user->id, 'auth.session.revoke_others', [
            'revoked_count' => $revoked,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'code' => 'sessions_revoked_except_current',
            'message' => 'Diger tum cihazlardan cikis yapildi.',
            'data' => [
                'revoked_count' => $revoked,
            ],
        ]);
    }

    public function revokeSession(Request $request, int $tokenId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        if ($currentTokenId !== null && (int) $tokenId === (int) $currentTokenId) {
            return response()->json([
                'ok' => false,
                'code' => 'session_revoke_current_not_allowed',
                'message' => 'Mevcut oturumu bu endpoint ile kapatamazsiniz. Logout kullanin.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $token = $user->tokens()->where('id', $tokenId)->first();
        if (! $token) {
            return response()->json([
                'ok' => false,
                'code' => 'session_not_found',
                'message' => 'Oturum bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        $token->delete();

        Log::channel('security')->info('Session revoked', [
            'user_id' => $user->id,
            'token_id' => $tokenId,
            'ip' => $request->ip(),
        ]);
        $this->recordAuditEvent($user->id, 'auth.session.revoke_one', [
            'token_id' => $tokenId,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'code' => 'session_revoked',
            'message' => 'Oturum sonlandirildi.',
        ]);
    }

    private function issueToken(User $user, Request $request): array
    {
        $expirationMinutes = (int) config('sanctum.expiration');
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;
        $tokenName = $this->resolveDeviceLabel((string) $request->userAgent());
        $plainTextToken = $user->createToken($tokenName, $user->tokenAbilities(), $expiresAt)->plainTextToken;
        $tokenId = (int) explode('|', $plainTextToken)[0];
        $tokenRecord = $user->tokens()->where('id', $tokenId)->first();
        $userAgent = trim((string) $request->userAgent());
        if ($tokenRecord instanceof Model) {
            $updates = [];
            if (Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                $updates['ip_address'] = (string) $request->ip();
            }
            if (Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                $updates['user_agent'] = $userAgent !== '' ? substr($userAgent, 0, 1000) : null;
            }
            if ($updates !== []) {
                $tokenRecord->forceFill($updates)->save();
            }
        }

        return [
            $plainTextToken,
            $expiresAt instanceof Carbon ? $expiresAt->toISOString() : null,
        ];
    }

    private function resolveDeviceLabel(string $userAgent): string
    {
        $ua = trim($userAgent);
        if ($ua === '') {
            return 'Unknown device';
        }

        $short = substr($ua, 0, 48);

        return 'Device: '.$short;
    }

    private function recordAuditEvent(?int $userId, string $eventType, array $metadata = []): void
    {
        AuditEvent::query()->create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'metadata' => $metadata,
        ]);
    }

    private function resolveBearerTokenId(?string $bearerToken): ?int
    {
        if (! is_string($bearerToken) || ! str_contains($bearerToken, '|')) {
            return null;
        }

        [$id] = explode('|', $bearerToken, 2);

        return ctype_digit($id) ? (int) $id : null;
    }

    private function isUserVerified(User $user): bool
    {
        if ((bool) $user->is_verified) {
            return true;
        }

        if (! empty($user->email_verified_at)) {
            return true;
        }

        return false;
    }

    private function buildEmailVerificationLink(string $token): string
    {
        return 'nextscout://verify-email?token=' . urlencode($token);
    }

    private function buildPasswordResetLink(string $email, string $token): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');

        if ($frontendUrl !== '') {
            return $frontendUrl.'/reset-password?token='.urlencode($token).'&email='.urlencode($email);
        }

        return 'nextscout://reset-password?token='.urlencode($token).'&email='.urlencode($email);
    }

    private function emailVerificationRequired(): bool
    {
        return (bool) config('app.auth_require_email_verification', true);
    }

    private function findPlayerForClubLogin(string $teamName, string $playerName): ?User
    {
        $exactMatch = User::query()
            ->select('users.*')
            ->join('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'player')
            ->whereRaw("LOWER(REPLACE(TRIM(users.name), ' ', '')) = ?", [$playerName])
            ->whereRaw("LOWER(REPLACE(TRIM(player_profiles.current_team), ' ', '')) = ?", [$teamName])
            ->first();

        if ($exactMatch) {
            return $exactMatch;
        }

        return User::query()
            ->select('users.*', 'player_profiles.current_team as login_current_team')
            ->join('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'player')
            ->get()
            ->first(function (User $user) use ($teamName, $playerName): bool {
                $currentTeam = (string) ($user->getAttribute('login_current_team') ?? '');

                return $this->lookupValuesMatch((string) $user->name, $playerName)
                    && $this->lookupValuesMatch($currentTeam, $teamName);
            });
    }

    private function createPlayerUserFromInternalPlayer(string $teamName, string $playerName): ?User
    {
        $clubPlayerMatch = $this->findClubInternalPlayerForLookup($teamName, $playerName);

        if ($clubPlayerMatch === null) {
            return null;
        }

        /** @var User $club */
        $club = $clubPlayerMatch['club'];
        /** @var ClubInternalPlayer $internalPlayer */
        $internalPlayer = $clubPlayerMatch['player'];

        $displayTeamName = trim((string) ($club->teamProfile?->team_name ?? $club->name ?? ''));
        if ($displayTeamName === '') {
            $displayTeamName = (string) $internalPlayer->group_key;
        }

        return DB::transaction(function () use ($internalPlayer, $displayTeamName): User {
            $playerUser = $this->findPlayerForClubLogin(
                $this->normalizeLookupValue($displayTeamName),
                $this->normalizeLookupValue((string) $internalPlayer->name)
            );

            if (! $playerUser) {
                $emailBase = Str::slug((string) ($internalPlayer->name ?: 'player'), '.');
                $email = sprintf('%s.%s@nextscout.local', $emailBase ?: 'player', Str::lower(Str::random(8)));

                $playerUser = User::query()->create([
                    'name' => $internalPlayer->name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'role' => 'player',
                    'sport' => $internalPlayer->sport,
                    'city' => null,
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'player_password_initialized' => false,
                ]);
            }

            PlayerProfile::query()->updateOrCreate(
                ['user_id' => (int) $playerUser->id],
                [
                    'birth_year' => is_numeric((string) $internalPlayer->birth_year) ? (int) $internalPlayer->birth_year : null,
                    'position' => $internalPlayer->position,
                    'dominant_foot' => $internalPlayer->dominant_foot,
                    'height_cm' => is_numeric((string) $internalPlayer->height) ? (int) $internalPlayer->height : null,
                    'current_team' => $displayTeamName,
                ]
            );

            return $playerUser->fresh() ?? $playerUser;
        });
    }

    private function findClubInternalPlayerForLookup(string $teamName, string $playerName): ?array
    {
        $club = User::query()
            ->whereIn('role', self::CLUB_LOGIN_ROLES)
            ->with('teamProfile')
            ->get()
            ->first(function (User $user) use ($teamName): bool {
                foreach ($this->clubLookupCandidates($user) as $candidateTeamName) {
                    if ($this->lookupValuesMatch($candidateTeamName, $teamName)) {
                        return true;
                    }
                }

                return false;
            });

        if ($club) {
            $internalPlayer = ClubInternalPlayer::query()
                ->where('club_user_id', (int) $club->id)
                ->get()
                ->first(fn (ClubInternalPlayer $player): bool => $this->lookupValuesMatch((string) $player->name, $playerName));

            if ($internalPlayer) {
                return [
                    'club' => $club,
                    'player' => $internalPlayer,
                ];
            }
        }

        $matches = ClubInternalPlayer::query()
            ->select('club_internal_players.*')
            ->join('users', 'users.id', '=', 'club_internal_players.club_user_id')
            ->leftJoin('team_profiles', 'team_profiles.user_id', '=', 'users.id')
            ->whereIn('users.role', self::CLUB_LOGIN_ROLES)
            ->where('club_internal_players.name', '<>', '')
            ->get();

        foreach ($matches as $internalPlayer) {
            if (! $internalPlayer instanceof ClubInternalPlayer) {
                continue;
            }

            if (! $this->lookupValuesMatch((string) $internalPlayer->name, $playerName)) {
                continue;
            }

            $candidateClub = User::query()
                ->with('teamProfile')
                ->find((int) $internalPlayer->club_user_id);

            if (! $candidateClub) {
                continue;
            }

            foreach ($this->clubLookupCandidates($candidateClub) as $candidateTeamName) {
                if ($this->lookupValuesMatch($candidateTeamName, $teamName)) {
                    return [
                        'club' => $candidateClub,
                        'player' => $internalPlayer,
                    ];
                }
            }
        }

        return null;
    }

    private function clubLookupCandidates(User $club): array
    {
        return array_values(array_filter([
            (string) ($club->teamProfile?->team_name ?? ''),
            (string) $club->name,
        ], static fn (string $value): bool => trim($value) !== ''));
    }

    private function playerLoginFailedResponse(
        string $teamName,
        string $playerName,
        string $ip,
        string $attemptKey,
        string $lockKey,
        int $lockSeconds,
        int $maxFailedAttempts
    ): JsonResponse {
        RateLimiter::hit($attemptKey, $lockSeconds);
        $attempts = RateLimiter::attempts($attemptKey);

        if ($attempts >= $maxFailedAttempts) {
            RateLimiter::hit($lockKey, $lockSeconds);
            RateLimiter::clear($attemptKey);
        }

        Log::channel('security')->warning('Player login failed', [
            'team_name' => $teamName,
            'player_name' => $playerName,
            'ip' => $ip,
            'attempts' => $attempts,
            'locked' => $attempts >= $maxFailedAttempts,
        ]);

        return response()->json([
            'ok' => false,
            'code' => 'auth_invalid_credentials',
            'message' => 'Takim adi, oyuncu adi veya sifre hatali.',
            'errors' => [
                'player_name' => ['Takim adi, oyuncu adi veya sifre hatali.'],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function normalizeLookupValue(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->squish()
            ->replace(' ', '')
            ->ascii('tr')
            ->lower()
            ->value();
    }

    private function normalizeCompactLookupValue(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', $this->normalizeLookupValue($value));
    }

    private function lookupValuesMatch(string $left, string $right): bool
    {
        $normalizedLeft = $this->normalizeLookupValue($left);
        $normalizedRight = $this->normalizeLookupValue($right);

        if ($normalizedLeft === '' || $normalizedRight === '') {
            return false;
        }

        if ($normalizedLeft === $normalizedRight) {
            return true;
        }

        return $this->normalizeCompactLookupValue($normalizedLeft) === $this->normalizeCompactLookupValue($normalizedRight);
    }

    private function transformUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'role' => (string) $user->role,
            'city' => $user->city,
            'phone' => $user->phone,
            'photo_url' => $this->publicFileUrl($user->photo_url),
            'sport' => $user->sport,
            'position' => $user->position,
            'is_verified' => (bool) $user->is_verified,
            'created_at' => optional($user->created_at)?->toIso8601String(),
            'updated_at' => optional($user->updated_at)?->toIso8601String(),
        ];
    }

}
