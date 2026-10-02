<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HomeSetting;
use App\Models\Setting;
use App\Models\VehicleModel;
use App\Services\CostPerKm;

class CalculatorController extends Controller
{
    public function emi()
    {
        $cfg = [
            'amount' => (int) Setting::get('emi.default_amount', 1000000),
            'min' => (int) Setting::get('emi.min_amount', 100000),
            'max' => (int) Setting::get('emi.max_amount', 50000000),
            'rate' => (float) Setting::get('emi.default_rate', 10),
            'tenure' => (int) Setting::get('emi.default_tenure', 5),
        ];
        $cfg['min'] = max(1000, $cfg['min'] ?: 100000);
        $cfg['max'] = max($cfg['min'] * 2, $cfg['max'] ?: 50000000);
        $cfg['amount'] = min(max($cfg['amount'] ?: 1000000, $cfg['min']), $cfg['max']);
        $cfg['rate'] = min(max($cfg['rate'] ?: 10, 1), 30);
        $cfg['tenure'] = min(max($cfg['tenure'] ?: 5, 1), 10);

        return view('site.emi', [
            'cfg' => $cfg,
            'homeSettings' => HomeSetting::current(),
            'latest' => Article::published()->latest('published_at')->take(5)->get(),
        ]);
    }

    /** Cost-per-km page. Fuel cost comes from km + average (asked from the visitor), never typed in. */
    public function costPerKm()
    {
        $cfg = CostPerKm::config();
        $rate = min(max((float) Setting::get('emi.default_rate', 10), 1), 30);

        // Cars the visitor can pick: average (parsed from the specs "Mileage" text), fuel and a rough EMI come prefilled, all editable.
        $cars = VehicleModel::published()->ofType('car')->with(['brandMaster:id,name', 'fuels:id,name'])->orderBy('name')->limit(300)->get()->map(function ($m) use ($rate) {
            $fuel = collect($m->fuels->pluck('name'))->map(fn ($n) => str_contains(strtolower($n), 'electric') ? 'electric' : (str_contains(strtolower($n), 'cng') ? 'cng' : (str_contains(strtolower($n), 'diesel') ? 'diesel' : 'petrol')))->first() ?? 'petrol';
            $avg = null;
            foreach (array_change_key_case((array) $m->specs, CASE_LOWER) as $k => $v) {
                if (preg_match('/mileage|arai|average|efficiency/', $k) && preg_match('/\d+(?:\.\d+)?/', (string) $v, $x) && (float) $x[0] >= 3 && (float) $x[0] <= 60) { $avg = (float) $x[0]; break; }
            }
            $loan = $m->price_min * 0.9; $r = $rate / 1200; $n = 60;                             // rough: 90% loan, 5 years
            $emi = $loan > 0 ? round(($r ? $loan * $r * (1 + $r) ** $n / ((1 + $r) ** $n - 1) : $loan / $n) / 100) * 100 : null;
            return ['id' => $m->id, 'name' => $m->full_name, 'fuel' => $fuel, 'average' => $avg, 'emi' => $emi];
        })->values();

        return view('site.costperkm', [
            'cfg' => $cfg,
            'cars' => $cars,
            'homeSettings' => HomeSetting::current(),
            'latest' => Article::published()->latest('published_at')->take(5)->get(),
        ]);
    }
}
