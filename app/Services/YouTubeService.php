<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Setting;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

/** YouTube Data API v3 search (100 quota units per search; the free 10,000/day quota allows ~100 searches). */
class YouTubeService
{
    public static function configured(): bool { return (bool) Setting::get('youtube.api_key'); }

    /** @return Video[] videos found (new ones are stored) */
    public static function search(string $query, int $max = 6, ?string $keyword = null): array
    {
        if (! self::configured()) return [];
        $params = [
            'part' => 'snippet', 'type' => 'video', 'maxResults' => $max, 'q' => $query,
            'regionCode' => 'IN', 'relevanceLanguage' => 'en', 'videoEmbeddable' => 'true', 'safeSearch' => 'strict',
            'key' => Setting::get('youtube.api_key'),
        ];
        if ($ch = Setting::get('youtube.channel_id')) $params['channelId'] = $ch;

        $res = Http::timeout(20)->get('https://www.googleapis.com/youtube/v3/search', $params);
        if (! $res->successful()) throw new \RuntimeException('YouTube API: '.data_get($res->json(), 'error.message', $res->status()));

        $videos = [];
        foreach ($res->json('items', []) as $it) {
            $id = data_get($it, 'id.videoId');
            if (! $id) continue;
            $sn = $it['snippet'];
            $videos[] = Video::firstOrCreate(['youtube_id' => $id], [
                'title' => html_entity_decode($sn['title'], ENT_QUOTES),
                'channel' => $sn['channelTitle'] ?? null,
                'thumbnail' => data_get($sn, 'thumbnails.high.url') ?? data_get($sn, 'thumbnails.medium.url'),
                'description' => $sn['description'] ?? null,
                'keyword' => $keyword ?? $query,
                'published_at' => isset($sn['publishedAt']) ? \Carbon\Carbon::parse($sn['publishedAt']) : null,
            ]);
        }
        return $videos;
    }

    /** Find and attach up to 2 relevant videos to an article. */
    public static function attachTo(Article $article): int
    {
        if (! self::configured()) return 0;
        $vids = self::search($article->title.' review India', 3, $article->category?->name);
        $article->videos()->syncWithoutDetaching(collect($vids)->take(2)->pluck('id')->all());
        return min(2, count($vids));
    }

    /** Scheduled job: refresh the library from configured keywords + attach videos to recent articles without one. */
    public static function refresh(): string
    {
        if (! self::configured()) return 'Skipped: YouTube API key not set.';
        $keywords = array_filter(array_map('trim', preg_split('/\R/', (string) Setting::get('youtube.keywords', "new car launch India\ncar review India\nupcoming cars India 2026\nused car buying tips India"))));
        $new = 0;
        foreach (array_slice($keywords, 0, 3) as $k) { // 3 searches/run keeps quota use low
            $before = Video::count();
            self::search($k, 8);
            $new += Video::count() - $before;
        }
        $attached = 0;
        foreach (Article::published()->doesntHave('videos')->latest('published_at')->limit(3)->get() as $a) {
            $attached += self::attachTo($a) ? 1 : 0;
        }
        return "Added $new new video(s); attached videos to $attached article(s).";
    }
}
