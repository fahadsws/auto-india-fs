<?php

namespace Tests\Feature;

use App\Models\SeoEntry;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        $u = User::factory()->create();
        $u->assignRole('Super Admin');

        return $u;
    }

    private function payload(array $o = []): array
    {
        return $o + ['robots' => 'index,follow', 'schema_type' => 'WebApplication', 'meta_title' => 'EMI Calculator India', 'meta_description' => 'Calculate your car loan EMI.',
            'meta_keywords' => 'car loan, emi', 'canonical_url' => 'https://example.com/car-emi-calculator', 'og_title' => 'Share title', 'og_image' => 'https://example.com/og.jpg',
            'faq' => [['q' => 'Custom Q?', 'a' => 'Custom A.'], ['q' => '', 'a' => '']]];
    }

    private function ld(string $html): array
    {
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

        return collect($m[1])->map(fn ($j) => json_decode($j, true))->each(fn ($j) => $this->assertIsArray($j, 'invalid JSON-LD'))->all();
    }

    public function test_without_entry_pages_keep_their_defaults(): void
    {
        $this->get('/car-emi-calculator')->assertOk()->assertSee('<title>Car Loan EMI Calculator |', false)->assertDontSee('name="keywords"', false);
    }

    public function test_entry_overrides_meta_for_emi_and_home(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/seo/emi', $this->payload())->assertRedirect();
        $this->actingAs($admin)->put('/admin/seo/home', $this->payload(['meta_title' => 'Home SEO title', 'robots' => 'noindex,follow']))->assertRedirect();

        $html = $this->get('/car-emi-calculator')->assertOk()->getContent();
        $this->assertStringContainsString('<title>EMI Calculator India</title>', $html);
        $this->assertStringContainsString('name="description" content="Calculate your car loan EMI."', $html);
        $this->assertStringContainsString('name="keywords" content="car loan, emi"', $html);
        $this->assertStringContainsString('rel="canonical" href="https://example.com/car-emi-calculator"', $html);
        $this->assertStringContainsString('property="og:title" content="Share title"', $html);
        $this->assertStringContainsString('name="twitter:image" content="https://example.com/og.jpg"', $html);
        $types = collect($this->ld($html))->pluck('@type')->all();
        $this->assertSame(1, count(array_keys($types, 'FAQPage')), 'exactly one FAQPage block');
        $this->assertContains('WebApplication', $types);
        $this->assertStringContainsString('Custom Q?', $html);

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<title>Home SEO title</title>', $home);
        $this->assertStringContainsString('name="robots" content="noindex,follow"', $home);
        $this->assertContains('Organization', collect($this->ld($home))->pluck('@type')->all());
    }

    public function test_reset_validation_and_access(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/seo/emi', $this->payload(['schema_json' => '{bad']))->assertSessionHasErrors('schema_json');
        $this->actingAs($admin)->put('/admin/seo/nope', $this->payload())->assertNotFound();
        $this->actingAs($admin)->get('/admin/seo')->assertOk()->assertSee('Car loan EMI calculator');
        $this->actingAs($admin)->get('/admin/seo/emi')->assertOk()->assertSee('Save SEO');
        $this->actingAs($admin)->put('/admin/seo/emi', $this->payload())->assertRedirect();
        $this->actingAs($admin)->delete('/admin/seo/emi')->assertRedirect();
        $this->assertSame(0, SeoEntry::count());
        auth()->logout();
        $this->get('/admin/seo')->assertRedirect();
    }

    public function test_noindex_dropped_from_sitemap_and_global_settings_applied(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/seo/about', $this->payload(['robots' => 'noindex,nofollow']))->assertRedirect();
        $this->get('/sitemap.xml')->assertSee('/car-emi-calculator', false)->assertDontSee('/about', false);

        Setting::put('seo.google_verification', 'abc123');
        Setting::put('seo.ga4_id', 'G-TEST123');
        Setting::put('seo.robots_extra', 'Disallow: /private');
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('name="google-site-verification" content="abc123"', $html);
        $this->assertStringContainsString('G-TEST123', $html);
        $this->get('/robots.txt')->assertSee('Disallow: /private');
    }

    public function test_settings_and_page_edit_forms_render_with_shared_seo_fields(): void
    {
        $admin = $this->admin();
        $page = \App\Models\Page::create(['title' => 'P', 'slug' => 'p', 'status' => 'draft', 'faq' => [['q' => 'Q1', 'a' => 'A1']]]);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('SEO');
        $this->actingAs($admin)->get("/admin/pages/{$page->id}/edit")->assertOk()->assertSee('Custom JSON-LD')->assertSee('Q1')->assertSee('faqAdd', false);
        $this->actingAs($admin)->get('/admin/home-settings')->assertOk()->assertSee('Home page SEO');
    }
}
