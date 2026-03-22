<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'message',
        'source',
        'ip_address',
        'user_agent',
    ];
}
