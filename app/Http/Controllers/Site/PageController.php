<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Video;
use App\Models\VehicleModel;
use App\Services\LeadNotifier;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function search(Request $r)
    {
        $q = trim((string) $r->q);
        return view('site.search', [
            'q' => $q,
            'articles' => $q ? Article::published()->where(fn ($w) => $w->where('title', 'like', "%$q%")->orWhere('excerpt', 'like', "%$q%"))->latest('published_at')->take(8)->get() : collect(),
            'listings' => $q ? Listing::active()->where(fn ($w) => $w->where('title', 'like', "%$q%")->orWhereHas('brandMaster', fn ($b) => $b->where('name', 'like', "%$q%"))->orWhere('city', 'like', "%$q%"))->take(8)->get() : collect(),
            'videos' => $q ? Video::active()->where('title', 'like', "%$q%")->take(4)->get() : collect(),
            'vehicleModels' => $q ? VehicleModel::published()->with('brandMaster')->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhereHas('brandMaster', fn ($b) => $b->where('name', 'like', "%$q%")))->take(8)->get() : collect(),
        ]);
    }

    public function suggestions(Request $r)
    {
        $q = trim((string) $r->query('q'));
        if (mb_strlen($q) < 2) return response()->json([]);

        $like = "%{$q}%";
        $results = collect();
        $results = $results->merge(VehicleModel::published()->with('brandMaster')->where(fn ($w) => $w->where('name', 'like', $like)->orWhereHas('brandMaster', fn ($b) => $b->where('name', 'like', $like)))->take(8)->get()->map(fn ($car) => ['label' => $car->full_name, 'url' => $car->url]));
        $results = $results->merge(Listing::active()->where(fn ($w) => $w->where('title', 'like', $like)->orWhereHas('brandMaster', fn ($b) => $b->where('name', 'like', $like))->orWhere('model', 'like', $like))->take(5)->get()->map(fn ($car) => ['label' => $car->title, 'url' => $car->url]));
        $results = $results->merge(Article::published()->where('title', 'like', $like)->take(5)->get()->map(fn ($article) => ['label' => $article->title, 'url' => $article->url]));
        $results = $results->merge(Video::active()->where('title', 'like', $like)->take(5)->get()->map(fn ($video) => ['label' => $video->title, 'url' => $video->url]));

        return response()->json($results->unique('url')->take(10)->values());
    }

    public function about() { return view('site.about'); }

    public function contact() { return view('site.contact'); }

    public function contactSubmit(Request $r)
    {
        return $this->saveLead($r, 'contact', 'Thanks for reaching out! We will get back to you soon.');
    }

    public function sell() { return view('site.sell'); }

    public function sellSubmit(Request $r)
    {
        return $this->saveLead($r, 'sell', 'Thanks! Our team will call you shortly with an offer for your car.');
    }

    public function quickLead(Request $r)
    {
        if ($r->filled('website')) return back(); // honeypot
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'phone' => ['required', 'regex:/^[+0-9 \-]{8,15}$/'],
            'city' => 'required|string|max:80',
            'emi_context' => 'nullable|string|max:200',
        ]);
        $page = parse_url((string) url()->previous(), PHP_URL_PATH) ?: '/';
        $emi = $r->filled('emi_context') ? ' EMI calc: '.preg_replace('/[^\w\s.,:₹%@\/()+-]/u', '', $data['emi_context']).'.' : '';
        $lead = Lead::create([
            'name' => $data['name'], 'phone' => $data['phone'], 'type' => 'enquiry', 'ip' => $r->ip(),
            'message' => "Best-offer request. City: {$data['city']}.{$emi} From page: {$page}",
        ]);
        LeadNotifier::notify($lead);

        return redirect(url()->previous().'#lead')->with('lead_success', "Thanks {$lead->name}! Our team will call you with the best offers.");
    }

    private function saveLead(Request $r, string $type, string $msg)
    {
        if ($r->filled('website')) return back(); // honeypot
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'phone' => 'required|string|min:8|max:20',
            'email' => 'nullable|email|max:150',
            'message' => ($type === 'sell' ? 'required' : 'nullable').'|string|max:1500',
        ]);
        $lead = Lead::create($data + ['type' => $type, 'ip' => $r->ip()]);
        LeadNotifier::notify($lead);
        return back()->with('success', $msg);
    }

    public function sitemap()
    {
        $urls = collect([route('home'), route('news.index'), route('newcars.index'), route('newbikes.index'), route('newtrucks.index'), route('cars.index'), route('videos.index'), route('about'), route('contact'), route('sell'), route('emi')])
            ->map(fn ($u) => ['loc' => $u, 'lastmod' => now()->toAtomString()])
            ->merge(\App\Models\Page::published()->where('robots', 'not like', 'noindex%')->latest('updated_at')->take(1000)->get()->map(fn ($p) => ['loc' => $p->url, 'lastmod' => $p->updated_at->toAtomString()]))
            ->merge(\App\Models\VehicleModel::published()->latest('updated_at')->take(1000)->get()->map(fn ($c) => ['loc' => $c->url, 'lastmod' => $c->updated_at->toAtomString()]))
            ->merge(Article::published()->latest('published_at')->take(1000)->get()->map(fn ($a) => ['loc' => $a->url, 'lastmod' => $a->updated_at->toAtomString()]))
            ->merge(Listing::active()->latest()->take(1000)->get()->map(fn ($l) => ['loc' => $l->url, 'lastmod' => $l->updated_at->toAtomString()]));

        return response()->view('site.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    /** Search and AI crawlers are explicitly welcome on public content. */
    public function robots()
    {
        $ai = ['GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'Claude-Web', 'anthropic-ai', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'cohere-ai'];
        $txt = "User-agent: *\nDisallow: /admin\nDisallow: /cron\nDisallow: /assistant/\nAllow: /\n\n";
        foreach ($ai as $bot) $txt .= "User-agent: $bot\nAllow: /\nDisallow: /admin\nDisallow: /cron\n\n";
        $txt .= 'Sitemap: '.route('sitemap')."\n";
        return response($txt)->header('Content-Type', 'text/plain');
    }

    /** llms.txt: a plain-language map of the site for AI assistants and answer engines. */
    public function llms()
    {
        $name = \App\Models\Setting::get('site.name');
        $lines = ["# $name", '', '> '.\App\Models\Setting::get('site.tagline'), '', (string) \App\Models\Setting::get('site.about'), '', '## Sections',
            '- [New cars]('.route('newcars.index').'): launches, facelifts and upcoming cars with price, specs and images, updated as news breaks',
            '- [New bikes]('.route('newbikes.index').'): new bike and scooter launches with price, specs and images',
            '- [New trucks]('.route('newtrucks.index').'): new truck and commercial vehicle launches with price, specs and images',
            '- [Car news]('.route('news.index').'): AI-assisted, source-credited news and buying guides',
            '- [Used cars]('.route('cars.index').'): used-car listings with enquiry',
            '- [Videos]('.route('videos.index').'): car review and launch videos',
            '- [Car loan EMI calculator]('.route('emi').'): free car loan EMI calculator with interest and total payable',
            '- [Sitemap]('.route('sitemap').')', '', '## New car models'];
        foreach (\App\Models\VehicleModel::published()->orderByDesc('updated_at')->take(60)->get() as $c) $lines[] = "- [{$c->full_name}]({$c->url}): {$c->status_label}. {$c->price_label}";
        $pages = \App\Models\Page::published()->where('robots', 'not like', 'noindex%')->orderBy('title')->take(100)->get();
        if ($pages->isNotEmpty()) { $lines[] = ''; $lines[] = '## Pages'; foreach ($pages as $pg) $lines[] = "- [{$pg->title}]({$pg->url}): ".\Illuminate\Support\Str::limit((string) ($pg->meta_description ?: $pg->excerpt), 140); }
        $lines[] = ''; $lines[] = '## Latest articles';
        foreach (Article::published()->latest('published_at')->take(40)->get() as $a) $lines[] = "- [{$a->title}]({$a->url}): ".\Illuminate\Support\Str::limit((string) $a->excerpt, 140);

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=utf-8');
    }

    public function feed()
    {
        return response()->view('site.feed', ['articles' => Article::published()->with('category')->latest('published_at')->take(30)->get()])->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
