<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\AutomationLog;
use App\Models\ChatLog;
use App\Models\KnowledgeChunk;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\Video;
use App\Services\AiClient;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());

        $articlesPerDay = Article::where('created_at', '>=', $days->first())->get()->groupBy(fn ($a) => $a->created_at->format('Y-m-d'));
        $leadsPerDay = Lead::where('created_at', '>=', $days->first())->get()->groupBy(fn ($a) => $a->created_at->format('Y-m-d'));

        return view('admin.dashboard', [
            'stats' => [
                'articles' => Article::count(),
                'published' => Article::where('status', 'published')->count(),
                'ai_articles' => Article::where('is_ai_generated', true)->count(),
                'listings' => Listing::where('status', 'active')->count(),
                'leads_new' => Lead::where('status', 'new')->count(),
                'videos' => Video::count(),
                'chats' => ChatLog::where('created_at', '>=', now()->subDays(7))->count(),
                'kb' => KnowledgeChunk::count(),
            ],
            'chart' => [
                'labels' => $days->map->format('d M')->all(),
                'articles' => $days->map(fn ($d) => $articlesPerDay->get($d->format('Y-m-d'), collect())->count())->all(),
                'leads' => $days->map(fn ($d) => $leadsPerDay->get($d->format('Y-m-d'), collect())->count())->all(),
            ],
            'recentLeads' => $user->can('leads.view') ? Lead::with('listing')->latest()->take(6)->get() : collect(),
            'recentArticles' => Article::with('category')->when(! $user->can('articles.edit_all'), fn ($q) => $q->where('user_id', $user->id))->latest()->take(6)->get(),
            'logs' => $user->can('automation.manage') ? AutomationLog::latest('id')->take(6)->get() : collect(),
            'health' => [
                'AI provider' => AiClient::configured(),
                'YouTube API' => (bool) Setting::get('youtube.api_key'),
                'ElevenLabs voice' => (bool) Setting::get('elevenlabs.api_key'),
                'Cron ran in last 24h' => AutomationLog::where('created_at', '>=', now()->subDay())->exists(),
            ],
        ]);
    }
}
