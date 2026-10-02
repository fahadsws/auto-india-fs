<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostPerKmTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_and_is_in_the_sitemap(): void
    {
        $this->get('/cost-per-km-calculator')->assertOk()->assertSee('Car Cost Per KM Calculator')->assertSee('Kaun sasta');
        $this->get('/sitemap.xml')->assertSee('/cost-per-km-calculator');
    }

    public function test_admin_prices_and_benchmarks_reach_the_page(): void
    {
        Setting::put('costkm.petrol_price', '111');
        Setting::put('costkm.auto_rate', '14');
        $html = $this->get('/cost-per-km-calculator')->assertOk()->getContent();
        $this->assertStringContainsString('&quot;price&quot;:111', $html);
        $this->assertStringContainsString('&quot;rate&quot;:14', $html);
    }

    public function test_no_ai_endpoint_is_exposed(): void
    {
        $this->postJson('/cost-per-km-calculator/line', [])->assertNotFound();
    }
}
