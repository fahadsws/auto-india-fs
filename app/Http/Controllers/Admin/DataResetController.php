<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DataReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/** Admin -> System -> Reset site data. Destructive: needs the data.reset permission, the admin's password and the word RESET. */
class DataResetController extends Controller
{
    public function index(Request $r)
    {
        $keep = array_values(array_intersect((array) $r->query('keep', []), array_keys(DataReset::OPTIONAL_KEEP)));
        return view('admin.data-reset', ['counts' => DataReset::counts($keep), 'keep' => $keep, 'optional' => DataReset::OPTIONAL_KEEP, 'kept' => DataReset::KEEP]);
    }

    public function run(Request $r)
    {
        $d = $r->validate([
            'confirm' => ['required', 'in:RESET'],
            'password' => ['required', 'string'],
            'keep' => ['nullable', 'array'],
            'keep.*' => ['string'],
        ], ['confirm.in' => 'Type the word RESET (capital letters) to confirm.']);

        if (! Hash::check($d['password'], (string) $r->user()->password)) {
            return back()->withErrors(['password' => 'That password is not correct.'])->withInput($r->except('password'));
        }

        $removed = DataReset::run($d['keep'] ?? []);
        Log::warning('Site data reset', ['user_id' => $r->user()->id, 'email' => $r->user()->email, 'tables' => count($removed), 'rows' => array_sum($removed)]);

        return redirect()->route('admin.data-reset')->with('success', 'Done. '.number_format(array_sum($removed)).' rows were removed from '.count($removed).' tables. Users, settings and roles were kept. Re-index the knowledge base from Settings if you use the AI assistant.');
    }
}
