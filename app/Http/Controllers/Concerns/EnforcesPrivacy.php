<?php

namespace App\Http\Controllers\Concerns;

trait EnforcesPrivacy
{
    /**
     * Kullanici admin mi?
     */
    protected function isAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        $role       = strtolower((string) ($user->role ?? ''));
        $editorRole = strtolower((string) ($user->editor_role ?? ''));

        return $role === 'admin' || $editorRole === 'admin';
    }

    /**
     * Profil sahibi mi yoksa admin mi?
     */
    protected function canSeePrivate($authUser, int $profileUserId): bool
    {
        if (! $authUser) {
            return false;
        }

        return (int) $authUser->id === $profileUserId || $this->isAdmin($authUser);
    }

    /**
     * Email maskele:  a***@domain.com
     */
    protected function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '') {
            return '***@' . $domain;
        }

        $visible = mb_substr($local, 0, 1);

        return $visible . '***@' . $domain;
    }

    /**
     * Bir nesne/dizi üzerindeki özel alanları temizle.
     * $record: stdClass veya array
     */
    protected function redactPrivateFields($record, bool $canSee): object
    {
        $obj = (object) (is_array($record) ? $record : (array) $record);

        if (! $canSee) {
            if (property_exists($obj, 'phone')) {
                $obj->phone = null;
            }
            if (property_exists($obj, 'email') && isset($obj->email)) {
                $obj->email = $this->maskEmail((string) $obj->email);
            }
        }

        return $obj;
    }
}
