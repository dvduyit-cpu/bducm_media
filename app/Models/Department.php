<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Department extends Model
{
    protected $guarded = [];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function requests()
    {
        return $this->hasMany(MediaRequest::class);
    }

    public function supportedRequests(): BelongsToMany
    {
        return $this->belongsToMany(MediaRequest::class, 'department_media_request')->withTimestamps();
    }
}
