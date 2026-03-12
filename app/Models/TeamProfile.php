<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamProfile extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'team_name',
        'league_level',
        'city',
        'founded_year',
        'needs_text',
    ];

    protected $casts = [
        'user_id'      => 'integer',
        'founded_year' => 'integer',
        'updated_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
