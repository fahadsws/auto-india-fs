<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoEntry;
use App\Support\SeoRules;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function index()
    {
        return view('admin.seo.index', ['entries' => SeoEntry::all_()]);
    }

    public function edit(string $key)
    {
        abort_unless(isset(SeoEntry::PAGES[$key]), 404);
        $entry = SeoEntry::all_()->get($key) ?? new SeoEntry(['route_key' => $key, 'robots' => 'index,follow', 'schema_type' => 'None']);

        return view('admin.seo.form', ['entry' => $entry, 'key' => $key, 'label' => SeoEntry::PAGES[$key][0], 'url' => route(SeoEntry::PAGES[$key][1])]);
    }

    public function update(Request $r, string $key)
    {
        abort_unless(isset(SeoEntry::PAGES[$key]), 404);
        $d = $r->validate(SeoRules::rules());
        if ($err = SeoRules::schemaError($d)) return back()->withInput()->withErrors(['schema_json' => $err]);

        SeoEntry::updateOrCreate(['route_key' => $key], SeoRules::attributes($d));

        return redirect()->route('admin.seo.edit', $key)->with('success', 'SEO saved for '.SeoEntry::PAGES[$key][0].'.');
    }

    /** Remove the override so the page goes back to its built-in title and description. */
    public function destroy(string $key)
    {
        abort_unless(isset(SeoEntry::PAGES[$key]), 404);
        SeoEntry::where('route_key', $key)->get()->each->delete();

        return redirect()->route('admin.seo.index')->with('success', 'Reset to the page defaults.');
    }
}
