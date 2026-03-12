<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $table = 'faq';

    protected $fillable = [
        'question',
        'answer',
        'user_type',
        'topic',
        'view_count',
        'helpful_count',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function markHelpful(): void
    {
        $this->increment('helpful_count');
    }
}
