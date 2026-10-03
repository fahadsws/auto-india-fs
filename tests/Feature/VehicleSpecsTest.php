<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\SpecFiller;
use App\Services\VehicleImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VehicleSpecsTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE_TABLE = '<html><head><title>Hero Xtreme 160R launch</title><meta name="description" content="New Xtreme"></head><body><article><h1>Hero Xtreme 160R</h1>%s
        <table><tr><td>Engine</td><td>163 cc, air cooled</td></tr><tr><th>Max Power</th><td>16.66 bhp @ 8500 rpm</td></tr><tr><td>Torque</td><td>14.6 Nm</td></tr><tr><td>Fuel tank</td><td>12 litres</td></tr><tr><td>Ground clearance</td><td>167 mm</td></tr></table>
        <dl><dt>Kerb weight</dt><dd>144 kg</dd><dt>Gearbox</dt><dd>5-speed manual</dd></dl>
        <ul><li>Front brake: 276 mm disc</li><li>Rear brake: 220 mm disc</li><li>Subscribe to our newsletter: yes please</li></ul></article></body></html>';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        \App\Services\CarMasters::flush();   // static id cache must not outlive a rolled-back test
        Setting::put('ai.api_key', 'k');
        Setting::put('ai.base_url', 'https://ai.test/v1');
    }

    private function article(bool $thin = false): string
    {
        $html = sprintf(self::PAGE_TABLE, '<p>'.str_repeat('The new motorcycle brings sharp styling and a punchy engine for city and highway use. ', 8).'</p>');
        // a page with only two spec rows: the AI has to top the sheet up
        return $thin ? preg_replace('#<tr><td>Torque.*?</dl>.*?</ul>#s', '', $html) : $html;
    }

    private function ai(array ...$replies): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        $seq = Http::sequence();
        foreach ($replies as $r) $seq->push(['choices' => [['message' => ['content' => json_encode($r)]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10]]);
        Http::fake(['ai.test/*' => $seq, '*' => Http::response($this->article(), 200, ['Content-Type' => 'text/html'])]);
    }

    private function baseModel(array $d): array
    {
        return $d + ['brand' => 'Hero', 'name' => 'Xtreme 160R', 'status' => 'launched', 'body_type' => '', 'fuel_types' => ['Petrol'], 'price_min_lakh' => 1.2, 'price_max_lakh' => 1.3,
            'tagline' => 't', 'overview_html' => '<p>Hello</p>', 'highlights' => ['a'], 'faq' => [], 'meta_title' => 'm', 'meta_description' => 'd'];
    }

    public function test_spec_tables_lists_and_definition_lists_are_read_from_the_page(): void
    {
        $s = SpecFiller::extract($this->article());
        $this->assertSame('163 cc, air cooled', $s['Engine']);
        $this->assertSame('16.66 bhp @ 8500 rpm', $s['Max Power']);
        $this->assertSame('144 kg', $s['Kerb weight']);
        $this->assertSame('5-speed manual', $s['Gearbox']);
        $this->assertSame('276 mm disc', $s['Front brake']);
        $this->assertArrayNotHasKey('Subscribe to our newsletter', $s);   // marketing lines are not specs
    }

    public function test_ai_output_is_flattened_cleaned_and_merged_without_overwriting(): void
    {
        $n = SpecFiller::normalize(['Engine' => ['Type' => '1.5L petrol', 'Power' => '113 bhp'], 'Mileage' => 'N/A', 'Seats' => '', ['label' => 'Boot', 'value' => '350 L'], 'Torque: 144 Nm', 'Airbags' => 6]);
        $this->assertSame('1.5L petrol', $n['Engine Type']);
        $this->assertSame('350 L', $n['Boot']);
        $this->assertSame('144 Nm', $n['Torque']);
        $this->assertSame('6', $n['Airbags']);
        $this->assertArrayNotHasKey('Mileage', $n);
        $this->assertSame(['Power' => 'real'], SpecFiller::merge(['Power' => 'real'], ['power' => 'guess']));
    }

    public function test_import_uses_the_pages_spec_table_even_when_the_ai_returns_no_specs(): void
    {
        Http::fake(['*example.com*' => Http::response($this->article(true), 200, ['Content-Type' => 'text/html']), 'ai.test/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => json_encode($this->baseModel(['specs' => []]))]]]])
            ->push(['choices' => [['message' => ['content' => json_encode(['specs' => ['Engine' => '163 cc', 'Top speed' => '110 km/h', 'Length' => '2050 mm', 'Wheelbase' => '1325 mm', 'Tyres' => '100/80 R17', 'Seating capacity' => '2', 'Fuel tank' => '12 L', 'Kerb weight' => '144 kg']])]]]])
            ->push(['choices' => [['message' => ['content' => json_encode(['specs' => []])]]]])]);
        $m = (new VehicleImporter())->importUrl('https://example.com/xtreme', 'bike', true);
        $this->assertInstanceOf(VehicleModel::class, $m);
        $this->assertSame('163 cc, air cooled', $m->specs['Engine']);       // the page's own value wins over the AI's
        $this->assertSame('110 km/h', $m->specs['Top speed']);              // thin sheet -> topped up
        $this->assertGreaterThanOrEqual(SpecFiller::ENOUGH, count($m->specs));
    }

    public function test_several_links_for_one_model_are_combined(): void
    {
        $this->ai($this->baseModel(['specs' => ['Engine' => '163 cc']]));
        $m = (new VehicleImporter())->importUrl(['https://a.example/xtreme', 'https://b.example/xtreme-specs', 'https://a.example/xtreme'], 'bike', false);
        $this->assertInstanceOf(VehicleModel::class, $m);
        $requests = Http::recorded(fn ($r) => str_contains($r->url(), 'example'));
        $this->assertCount(2, $requests);   // duplicates dropped, both pages read
    }

    private function admin(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        $u = User::factory()->create();
        $u->assignRole('Super Admin');
        return $u;
    }

    public function test_admin_imports_many_links_one_json_request_each_and_reports_failures(): void
    {
        VehicleBrand::firstOrCreate(['name' => 'Hero'], ['slug' => 'hero']);
        $this->ai($this->baseModel(['specs' => []]), ['specs' => []], ['specs' => ['A' => '1', 'B' => '2', 'C' => '3', 'D' => '4', 'E' => '5', 'F' => '6', 'G' => '7', 'H' => '8']]);
        $r = $this->actingAs($this->admin())->postJson('/admin/car-models/import-url', ['url' => 'https://example.com/xtreme', 'vehicle_type' => 'bike', 'draft' => 1])->assertOk();
        $this->assertTrue($r->json('ok'));
        $this->assertGreaterThanOrEqual(5, $r->json('specs'));

        $this->actingAs($this->admin())->postJson('/admin/car-models/import-url', ['urls' => "not a link", 'vehicle_type' => 'bike'])->assertStatus(422)->assertJsonPath('ok', false);
        $this->actingAs($this->admin())->postJson('/admin/car-models/import-url', ['urls' => implode("\n", array_map(fn ($i) => "https://x.example/$i", range(1, 31))), 'vehicle_type' => 'bike'])->assertStatus(422);
    }

    public function test_fill_specs_button_adds_missing_specs_and_never_overwrites_or_touches_locked(): void
    {
        $brand = VehicleBrand::create(['name' => 'Hero', 'slug' => 'hero']);
        $m = VehicleModel::create(['name' => 'Xtreme', 'slug' => 'hero-xtreme', 'vehicle_type' => 'bike', 'brand_id' => $brand->id, 'status' => 'launched', 'is_published' => true, 'specs' => ['Engine' => 'mine']]);
        $this->ai(['specs' => ['Engine' => 'theirs', 'Power' => '16 bhp', 'Torque' => '14 Nm']], ['specs' => ['Fuel tank' => '12 L']]);
        $this->actingAs($this->admin())->post("/admin/car-models/{$m->id}/fill-specs")->assertRedirect()->assertSessionHas('success');
        $m->refresh();
        $this->assertSame('mine', $m->specs['Engine']);
        $this->assertSame('16 bhp', $m->specs['Power']);

        $m->update(['locked' => ['specs'], 'specs' => ['Engine' => 'pinned']]);
        $this->actingAs($this->admin())->post("/admin/car-models/{$m->id}/fill-specs")->assertSessionHas('error');
        $this->assertSame(['Engine' => 'pinned'], $m->fresh()->specs);
    }

    public function test_fill_command_tops_up_empty_models(): void
    {
        $brand = VehicleBrand::create(['name' => 'Hero', 'slug' => 'hero']);
        $empty = VehicleModel::create(['name' => 'Empty', 'slug' => 'hero-empty', 'vehicle_type' => 'bike', 'brand_id' => $brand->id, 'status' => 'launched']);
        $full = VehicleModel::create(['name' => 'Full', 'slug' => 'hero-full', 'vehicle_type' => 'bike', 'brand_id' => $brand->id, 'status' => 'launched', 'specs' => ['Engine' => 'x']]);
        $this->ai(['specs' => ['Engine' => '100 cc', 'Power' => '8 bhp']], ['specs' => []]);
        $this->artisan('vehicles:fill-specs')->assertSuccessful();
        $this->assertNotEmpty($empty->fresh()->specs);
        $this->assertSame(['Engine' => 'x'], $full->fresh()->specs);   // only empty ones unless --all
    }
}
