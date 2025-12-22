<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistItem extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'playlist_id',
        'article_id',
        'position',
    ];

    /**
     * @return BelongsTo<Playlist, PlaylistItem>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return BelongsTo<Article, PlaylistItem>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
