<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HomeSetting;
use App\Models\Setting;

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
}
