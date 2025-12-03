<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
    ];

    protected $casts = [];

    /**
     * Get the posts for this model.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
