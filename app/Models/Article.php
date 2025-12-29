<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Article extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'device_token_id',
        'url',
        'source_type',
        'title',
        'body',
        'audio_url',
        'extraction_status',
    ];

    public function isYouTube(): bool
    {
        return $this->source_type === 'youtube';
    }

    /**
     * @return BelongsTo<DeviceToken, Article>
     */
    public function deviceToken(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class);
    }

    /**
     * @return HasOne<ArticleAudio, Article>
     */
    public function audio(): HasOne
    {
        return $this->hasOne(ArticleAudio::class);
    }
}
