<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VideoClip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditPublicMediaFilesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_missing_local_references_without_mutation(): void
    {
        Storage::fake('public');

        $player = User::factory()->create([
            'role' => 'player',
            'photo_url' => 'profile-photos/missing-player.jpg',
        ]);

        DB::table('media')->insert([
            'user_id' => $player->id,
            'type' => 'image',
            'url' => 'media/'.$player->id.'/missing.jpg',
            'thumb_url' => null,
            'title' => 'Broken Media',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        VideoClip::query()->create([
            'user_id' => $player->id,
            'title' => 'Broken Video',
            'video_url' => '/storage/videos/'.$player->id.'/missing.mp4',
            'thumbnail_url' => null,
            'platform' => 'custom',
        ]);

        $this->artisan('media:audit-public-files', ['--user-id' => $player->id])
            ->expectsOutputToContain('Kirik local referans: 3')
            ->expectsOutputToContain('Mod: dry-run')
            ->assertExitCode(0);

        $this->assertSame('profile-photos/missing-player.jpg', $player->fresh()->photo_url);
    }

    public function test_command_can_null_broken_local_references(): void
    {
        Storage::fake('public');

        $player = User::factory()->create([
            'role' => 'player',
            'photo_url' => 'profile-photos/missing-player.jpg',
        ]);

        $mediaId = DB::table('media')->insertGetId([
            'user_id' => $player->id,
            'type' => 'image',
            'url' => 'media/'.$player->id.'/missing.jpg',
            'thumb_url' => 'media/'.$player->id.'/missing-thumb.jpg',
            'title' => 'Broken Media',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clip = VideoClip::query()->create([
            'user_id' => $player->id,
            'title' => 'Broken Video',
            'video_url' => '/storage/videos/'.$player->id.'/missing.mp4',
            'thumbnail_url' => 'videos/'.$player->id.'/missing-thumb.jpg',
            'platform' => 'custom',
        ]);

        $this->artisan('media:audit-public-files', ['--user-id' => $player->id, '--fix-null' => true])
            ->expectsOutputToContain('Kirik local referans: 3')
            ->expectsOutputToContain('Mod: fix-null')
            ->assertExitCode(0);

        $this->assertNull($player->fresh()->photo_url);
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
        $this->assertDatabaseMissing('video_clips', ['id' => $clip->id]);
    }
}
