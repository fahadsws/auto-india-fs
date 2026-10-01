<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LeadNotifier
{
    public static function notify(Lead $lead): void
    {
        $to = array_filter(array_map('trim', explode(',', (string) Setting::get('leads.notify_emails', Setting::get('site.email', '')))));
        if (! $to) return;

        $lead->loadMissing('listing');
        $type = str_replace('_', ' ', $lead->type);
        $body = "New {$type} lead on ".config('app.name')."\n\n"
            ."Name: {$lead->name}\nPhone: {$lead->phone}\nEmail: {$lead->email}\n"
            .($lead->listing ? "Car: {$lead->listing->title} ({$lead->listing->url})\n" : '')
            ."Message: {$lead->message}\n\nOpen in admin: ".route('admin.leads.show', $lead);
        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject("New {$type} lead: {$lead->name}"));
        } catch (\Throwable $e) {
            Log::warning('Lead email failed: '.$e->getMessage());
        }
    }
}
