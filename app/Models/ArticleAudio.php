<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleAudio extends Model
{
    use HasFactory;

    /** @var string */
    protected $table = 'article_audio';

    /** @var list<string> */
    protected $fillable = [
        'article_id',
        'status',
        'audio_path',
        'duration_seconds',
        'voice',
        'error_message',
    ];

    /**
     * @return BelongsTo<Article, ArticleAudio>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
