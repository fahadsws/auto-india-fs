<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HomeSetting;
use App\Models\Setting;
use App\Models\VehicleModel;
use App\Services\AssistantGuard;
use App\Services\Roast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** "Roast my car": the visitor must have passed the same lead + email-OTP step as the AI assistant (asked once, then remembered). */
class RoastController extends Controller
{
    public function page()
    {
        $cars = VehicleModel::published()->ofType('car')->orderBy('name')->limit(400)->get()->map(fn ($m) => $m->full_name)->unique()->values();

        return view('site.roast', [
            'cars' => $cars,
            'homeSettings' => HomeSetting::current(),
            'latest' => Article::published()->latest('published_at')->take(5)->get(),
        ]);
    }

    public function roast(Request $r)
    {
        $d = $r->validate([
            'car' => 'required|string|min:2|max:80',
            'plate' => ['nullable', 'string', 'max:13', 'regex:/^[A-Za-z0-9 \-]*$/'],
            'owned' => 'required|in:'.implode(',', array_keys(Roast::OWNED)),
            'level' => 'required|in:'.implode(',', array_keys(Roast::LEVELS)),
            'habits' => 'nullable|array|max:3',
            'habits.*' => 'in:'.implode(',', array_keys(Roast::HABITS)),
            'name' => 'nullable|string|max:40',
        ], ['car.required' => 'Pehle gaadi ka naam batao.']);

        $car = Roast::clean($d['car'], 60);
        $name = Roast::clean((string) ($d['name'] ?? ''), 24);
        $plate = Roast::plate((string) ($d['plate'] ?? ''));
        if (Roast::isUnsafe($plate)) $plate = '';   // a rude "plate" is simply left off the card
        if (mb_strlen($car) < 2 || Roast::isUnsafe($car.' '.$name)) {
            return response()->json(['message' => 'Gaadi ya naam theek se likho - sirf naam, koi gaali ya baat nahi.', 'errors' => ['car' => ['Gaadi ka sahi naam likho.']]], 422);
        }

        // Daily cap per verified visitor, so one person cannot burn the AI budget.
        $s = $r->attributes->get('assistant_session');
        $key = 'roast:'.($s?->id ?? $r->ip()).':'.today()->toDateString();
        $limit = max(1, (int) Setting::get('roast.limit_day', 10));
        Cache::add($key, 0, now()->addDay());
        if (Cache::increment($key) > $limit) {
            return response()->json(['message' => 'Aaj ke roast khatam! Kal wapas aana, gaadi kahin nahi ja rahi. 😄', 'reason' => 'daily'], 429);
        }

        // Over the site-wide AI budget: still answer, from the hand-written lines.
        $allowAi = AssistantGuard::todayGlobalTokens() < AssistantGuard::limit('global_tokens_day');
        $card = Roast::generate(['car' => $car, 'owned' => $d['owned'], 'level' => $d['level'], 'habits' => array_values(array_unique($d['habits'] ?? [])), 'name' => $name, 'plate' => $plate], $allowAi);

        $u = \App\Services\AiClient::lastUsage();
        if ($card['ai']) AssistantGuard::spend($u['in'] + $u['out'], (string) $r->ip());

        return response()->json(['ok' => true, 'card' => $card + ['car' => $car, 'name' => $name, 'plate' => $plate], 'left' => max(0, $limit - (int) Cache::get($key, 0))]);
    }
}
