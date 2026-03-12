<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_user_id',
        'title',
        'position',
        'age_min',
        'age_max',
        'city',
        'details',
        'status',
    ];

    protected $casts = [
        'age_min' => 'integer',
        'age_max' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
