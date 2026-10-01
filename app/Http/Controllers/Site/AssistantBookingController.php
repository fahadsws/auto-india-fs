<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\AssistantMemory;
use Illuminate\Http\Request;

/**
 * Starting a new chat. Test drives, inspections and enquiries are no longer taken with manual buttons or forms:
 * the AI collects the details in conversation and saves them (see App\Services\AssistantCapture).
 */
class AssistantBookingController extends Controller
{
    public function reset(Request $r)
    {
        AssistantMemory::clear($r->attributes->get('assistant_session'));
        return response()->json(['ok' => true]);
    }
}
