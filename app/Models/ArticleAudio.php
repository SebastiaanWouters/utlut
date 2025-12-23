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
        'content_hash',
        'status',
        'progress_percent',
        'estimated_duration_ms',
        'processing_started_at',
        'processing_completed_at',
        'total_chunks',
        'completed_chunks',
        'retry_count',
        'next_retry_at',
        'audio_path',
        'duration_seconds',
        'voice',
        'error_message',
        'error_code',
        'content_length',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Article, ArticleAudio>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
