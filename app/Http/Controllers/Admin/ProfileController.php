<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit() { return view('admin.profile', ['user' => auth()->user()]); }

    public function update(Request $r)
    {
        $user = $r->user();
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'bio' => 'nullable|string|max:500',
            'current_password' => 'nullable|required_with:password|current_password',
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        $user->fill(['name' => $d['name'], 'email' => $d['email'], 'bio' => $d['bio'] ?? null]);
        if (! empty($d['password'])) $user->password = $d['password'];
        $user->save();
        return back()->with('success', 'Profile updated.');
    }
}
