<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'title' => $this->title,
            'body' => $this->body,
            'audio_url' => $this->audio_url,
            'extraction_status' => $this->extraction_status,
            'audio_status' => $this->audio?->status ?? 'pending',
            'status' => $this->audio?->status ?? 'pending',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
