<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoLiveFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_pages_use_a_breadcrumb_instead_of_the_big_header(): void
    {
        foreach (['/cars', '/new-cars', '/compare', '/videos', '/contact', '/about', '/car-emi-calculator', '/e-challan', '/ev-charging-stations'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('class="phead"', $html, $url);
            $this->assertStringContainsString('aria-label="Breadcrumb"', $html, $url);
            $this->assertSame(1, substr_count($html, '<h1'), "$url must keep exactly one h1");
        }
    }

    public function test_echallan_page_links_only_to_government_portals(): void
    {
        $this->get('/e-challan')->assertOk()->assertSee('echallan.parivahan.gov.in')->assertSee('Check your e-challan');
        foreach (\App\Http\Controllers\Site\EChallanController::PORTALS as $p) {
            $this->assertMatchesRegularExpression('#^https://[a-z0-9.\-]*\.gov\.in/#', $p[1], $p[0]);
        }
        $this->get('/sitemap.xml')->assertSee('/e-challan')->assertSee('/ev-charging-stations');
    }

    public function test_ev_stations_are_found_nearest_first_and_cached(): void
    {
        Http::fake(['*overpass*' => Http::response(['elements' => [
            ['type' => 'node', 'lat' => 18.53, 'lon' => 73.86, 'tags' => ['amenity' => 'charging_station', 'name' => 'Far Hub', 'operator' => 'Statiq', 'socket:type2' => '2', 'fee' => 'yes']],
            ['type' => 'way', 'center' => ['lat' => 18.5205, 'lon' => 73.8568], 'tags' => ['amenity' => 'charging_station', 'brand' => 'Tata Power', 'socket:type2_combo' => '1', 'socket:type2_combo:output' => '60 kW']],
            ['type' => 'node', 'tags' => ['amenity' => 'charging_station']],   // no position: dropped
        ]])]);

        $r = $this->getJson('/ev-charging-stations/search?lat=18.5204&lng=73.8567&radius=5')->assertOk();
        $this->assertSame(2, $r->json('count'));
        $this->assertSame('Tata Power', $r->json('stations.0.name'));
        $this->assertSame(['CCS2 60 kW'], $r->json('stations.0.sockets'));
        $this->assertSame('Paid', $r->json('stations.1.fee'));
        $this->assertLessThan($r->json('stations.1.km'), $r->json('stations.0.km'));

        $this->getJson('/ev-charging-stations/search?lat=18.5204&lng=73.8567&radius=5')->assertOk();
        Http::assertSentCount(1);   // the second search came from the cache
    }

    public function test_ev_search_survives_a_dead_upstream_and_validates_input(): void
    {
        Http::fake(['*' => Http::response('busy', 504)]);
        $this->getJson('/ev-charging-stations/search?lat=18.5&lng=73.8')->assertStatus(503)->assertJsonStructure(['message', 'stations']);
        $this->getJson('/ev-charging-stations/search?lat=48.8&lng=2.3')->assertStatus(422);   // not in India
    }

    public function test_known_cities_need_no_geocoding_call(): void
    {
        Http::fake(['*nominatim*' => Http::response([])]);
        $this->getJson('/ev-charging-stations/geocode?q=pune')->assertOk()->assertJsonPath('name', 'Pune');
        Http::assertNothingSent();
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*nominatim*' => Http::response([['lat' => '19.99', 'lon' => '73.78', 'display_name' => 'Nashik, Maharashtra, India']])]);
        $this->getJson('/ev-charging-stations/geocode?q=Nashik')->assertOk()->assertJsonPath('name', 'Nashik');
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*nominatim*' => Http::response([])]);
        $this->getJson('/ev-charging-stations/geocode?q=Zzzzqx')->assertStatus(404);
    }

    public function test_tools_menu_migration_adds_links_once(): void
    {
        $m = require database_path('migrations/2026_10_03_000001_add_tools_menu_with_echallan_and_ev.php');
        $m->up(); $m->up();
        $this->assertSame(1, \App\Models\MenuItem::where('location', 'header')->where('url', '/e-challan')->count());
        $this->assertSame(1, \App\Models\MenuItem::where('location', 'header')->where('title', 'Tools')->count());
    }
}
