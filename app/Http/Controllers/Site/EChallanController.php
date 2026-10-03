<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HomeSetting;

/**
 * E-challan: we never scrape or store challan data (the official portals use a captcha and personal data). The page validates the
 * vehicle number, then sends the visitor to the government portal with clear steps, official state links and scam warnings.
 */
class EChallanController extends Controller
{
    /** Official portals only. Anything else must never be listed here. */
    public const PORTALS = [
        'parivahan' => ['Parivahan e-Challan (all India)', 'https://echallan.parivahan.gov.in/index/accused-challan', 'Check and pay any e-challan with your vehicle, challan or driving licence number.'],
        'vcourts' => ['Virtual Courts', 'https://vcourts.gov.in/virtualcourt/', 'Pay or contest a challan that has been sent to the virtual court.'],
        'delhi' => ['Delhi Traffic Police', 'https://traffic.delhipolice.gov.in/notice/pay-notice', 'Delhi traffic notices and payment.'],
        'maharashtra' => ['Maharashtra Traffic Police', 'https://mahatrafficechallan.gov.in/', 'E-challan status and payment for Maharashtra (including Mumbai and Pune).'],
        'telangana' => ['Telangana Traffic Police', 'https://echallan.tspolice.gov.in/publicview/', 'Pending challans in Hyderabad, Cyberabad and Rachakonda.'],
    ];

    public function page()
    {
        return view('site.echallan', [
            'portals' => self::PORTALS,
            'homeSettings' => HomeSetting::current(),
            'latest' => Article::published()->latest('published_at')->take(5)->get(),
        ]);
    }
}
