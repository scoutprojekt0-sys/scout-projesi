<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'order',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(HelpArticle::class, 'category_id')->orderBy('order');
    }
}
