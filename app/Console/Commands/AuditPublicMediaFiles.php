<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\User;
use App\Models\VideoClip;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AuditPublicMediaFiles extends Command
{
    protected $signature = 'media:audit-public-files
        {--user-id= : Only audit a single user id}
        {--fix-null : Null broken optional references and delete broken primary media/video rows}
        {--allow-production : Explicitly allow write mode in production}';

    protected $description = 'Audit public disk-backed user photos, media, and videos; optionally null broken references.';

    public function handle(): int
    {
        $fixNull = (bool) $this->option('fix-null');

        if ($fixNull && app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production ortaminda yazmak icin --allow-production gerekli.');

            return self::FAILURE;
        }

        $userId = $this->resolveUserId();
        if ($userId === false) {
            return self::FAILURE;
        }

        $rows = collect()
            ->concat($this->auditUsers($userId, $fixNull))
            ->concat($this->auditMedia($userId, $fixNull))
            ->concat($this->auditVideos($userId, $fixNull))
            ->values();

        $broken = $rows->where('status', 'missing')->values();

        $this->table(
            ['type', 'id', 'user_id', 'field', 'path', 'status', 'action'],
            $broken->map(fn (array $row) => [
                $row['type'],
                $row['id'],
                $row['user_id'],
                $row['field'],
                $row['path'],
                $row['status'],
                $row['action'],
            ])->all()
        );

        $this->newLine();
        $this->line('Toplam kayit: '.$rows->count());
        $this->line('Kirik local referans: '.$broken->count());
        $this->line('Mod: '.($fixNull ? 'fix-null' : 'dry-run'));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{type:string,id:int,user_id:int,field:string,path:string,status:string,action:string}>
     */
    private function auditUsers(?int $userId, bool $fixNull): Collection
    {
        $query = User::query()->select(['id', 'photo_url']);
        if ($userId !== null) {
            $query->whereKey($userId);
        }

        return $this->auditQuery(
            $query,
            'user',
            'photo_url',
            static fn (User $user): int => (int) $user->id,
            $fixNull
        );
    }

    /**
     * @return Collection<int, array{type:string,id:int,user_id:int,field:string,path:string,status:string,action:string}>
     */
    private function auditMedia(?int $userId, bool $fixNull): Collection
    {
        $query = Media::query()->select(['id', 'user_id', 'url', 'thumb_url']);
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return collect()
            ->concat($this->auditQuery(
                clone $query,
                'media',
                'url',
                static fn (Media $media): int => (int) $media->user_id,
                $fixNull
            ))
            ->concat($this->auditQuery(
                $query,
                'media',
                'thumb_url',
                static fn (Media $media): int => (int) $media->user_id,
                $fixNull
            ));
    }

    /**
     * @return Collection<int, array{type:string,id:int,user_id:int,field:string,path:string,status:string,action:string}>
     */
    private function auditVideos(?int $userId, bool $fixNull): Collection
    {
        $query = VideoClip::query()->select(['id', 'user_id', 'video_url', 'thumbnail_url']);
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return collect()
            ->concat($this->auditQuery(
                clone $query,
                'video_clip',
                'video_url',
                static fn (VideoClip $clip): int => (int) $clip->user_id,
                $fixNull
            ))
            ->concat($this->auditQuery(
                $query,
                'video_clip',
                'thumbnail_url',
                static fn (VideoClip $clip): int => (int) $clip->user_id,
                $fixNull
            ));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): int  $userIdResolver
     * @return Collection<int, array{type:string,id:int,user_id:int,field:string,path:string,status:string,action:string}>
     */
    private function auditQuery(Builder $query, string $type, string $field, callable $userIdResolver, bool $fixNull): Collection
    {
        $rows = collect();

        $query->whereNotNull($field)->chunkById(200, function ($items) use (&$rows, $type, $field, $userIdResolver, $fixNull): void {
            foreach ($items as $item) {
                $path = $this->extractPublicDiskPath($item->{$field});
                if ($path === null) {
                    continue;
                }

                if (Storage::disk('public')->exists($path)) {
                    continue;
                }

                $action = 'reported';
                if ($fixNull) {
                    $action = $this->applyFix($item, $type, $field);
                }

                $rows->push([
                    'type' => $type,
                    'id' => (int) $item->getKey(),
                    'user_id' => $userIdResolver($item),
                    'field' => $field,
                    'path' => $path,
                    'status' => 'missing',
                    'action' => $action,
                ]);
            }
        });

        return $rows;
    }

    private function applyFix(object $item, string $type, string $field): string
    {
        if (($type === 'media' && $field === 'url') || ($type === 'video_clip' && $field === 'video_url')) {
            $item->delete();

            return 'deleted';
        }

        $item->{$field} = null;
        $item->save();

        return 'nulled';
    }

    private function extractPublicDiskPath(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (! str_contains($raw, '://') && ! str_starts_with($raw, '/')) {
            return ltrim($raw, '/');
        }

        $path = parse_url($raw, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, '/media-files/')) {
            return ltrim(substr($path, strlen('/media-files/')), '/');
        }

        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }

        return null;
    }

    private function resolveUserId(): int|false|null
    {
        $raw = $this->option('user-id');
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_numeric($raw) || (int) $raw < 1) {
            $this->error('--user-id pozitif bir integer olmali.');

            return false;
        }

        return (int) $raw;
    }
}
