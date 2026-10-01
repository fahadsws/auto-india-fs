<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Image/media uploads from the rich-text editor: files go to disk, only the URL is stored in the content. */
class MediaController extends Controller
{
    public function upload(Request $r)
    {
        $r->validate(['file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp,avif,mp4,webm']);
        $path = $r->file('file')->store('editor/'.date('Y/m'), 'public');

        return response()->json(['location' => asset('storage/'.$path)]);
    }
}
