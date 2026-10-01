<?php

namespace App\Models;

use App\Models\Concerns\Indexable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Video extends Model
{
    use Indexable;

    protected $guarded = [];
    protected $casts = ['published_at' => 'datetime', 'is_active' => 'boolean'];

    public static function knowledgeType(): string { return 'video'; }

    public function articles() { return $this->belongsToMany(Article::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function getUrlAttribute(): string { return route('videos.show', $this->youtube_id); }

    public function getEmbedUrlAttribute(): string { return 'https://www.youtube-nocookie.com/embed/'.$this->youtube_id; }

    public function toKnowledge(): ?array
    {
        if (! $this->is_active) return null;
        return [
            'title' => $this->title,
            'content' => 'Video by '.$this->channel.'. '.Str::limit(strip_tags((string) $this->description), 1200, ''),
            'url' => $this->url,
            'image' => $this->thumbnail,
        ];
    }
}
