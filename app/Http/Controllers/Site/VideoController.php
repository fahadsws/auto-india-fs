<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Video;

class VideoController extends Controller
{
    public function index()
    {
        $q = trim((string) request('q'));
        $videos = Video::active()->when($q, fn ($x) => $x->where('title', 'like', "%$q%"))
            ->latest('published_at')->paginate(12)->withQueryString();

        return view('site.videos.index', compact('videos', 'q'));
    }

    public function show(string $youtubeId)
    {
        $video = Video::active()->where('youtube_id', $youtubeId)->firstOrFail();
        $articles = $video->articles()->published()->latest('published_at')->take(4)->get();
        $more = Video::active()->where('id', '!=', $video->id)->latest('published_at')->take(6)->get();

        return view('site.videos.show', compact('video', 'articles', 'more'));
    }
}
