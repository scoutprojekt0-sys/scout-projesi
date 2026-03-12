<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpArticle extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'content',
        'meta_description',
        'keywords',
        'view_count',
        'helpful_count',
        'unhelpful_count',
        'is_published',
        'order',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_published' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'category_id');
    }

    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    public function markHelpful(): void
    {
        $this->increment('helpful_count');
    }

    public function markUnhelpful(): void
    {
        $this->increment('unhelpful_count');
    }
}
