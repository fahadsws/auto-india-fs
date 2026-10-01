<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PagesTest extends TestCase
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
        return $o + ['title' => 'Car Insurance Guide', 'slug' => '', 'template' => 'default', 'status' => 'published', 'robots' => 'index,follow', 'schema_type' => 'WebPage',
            'body' => '<p>Hello body</p>', 'meta_title' => 'Best car insurance', 'meta_description' => 'Compare car insurance plans.',
            'faq' => [['q' => 'What is IDV?', 'a' => 'Insured declared value.'], ['q' => '', 'a' => '']], 'show_lead' => 1, 'show_ads' => 1, 'show_news' => 1];
    }

    public function test_guest_cannot_manage_pages(): void
    {
        $this->get('/admin/pages')->assertRedirect();
        $this->post('/admin/pages', $this->payload())->assertRedirect();
        $this->assertSame(0, Page::count());
    }

    public function test_admin_creates_page_and_it_opens_on_its_url(): void
    {
        $this->actingAs($this->admin())->get('/admin/pages')->assertOk();
        $this->actingAs($this->admin())->get('/admin/pages/create')->assertOk()->assertSee('SEO');

        $this->actingAs($this->admin())->post('/admin/pages', $this->payload())->assertRedirect();
        $page = Page::firstOrFail();
        $this->assertSame('car-insurance-guide', $page->slug);
        $this->assertCount(1, $page->faq);

        $html = $this->get('/car-insurance-guide')->assertOk()->getContent();
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        $types = collect($m[1])->map(fn ($j) => json_decode($j, true))->each(fn ($j) => $this->assertIsArray($j))->pluck('@type')->all();
        $this->assertSame(['WebPage', 'FAQPage', 'BreadcrumbList'], $types);

        $this->get('/car-insurance-guide')
            ->assertSee('<title>Best car insurance', false)
            ->assertSee('Compare car insurance plans.', false)
            ->assertSee('rel="canonical" href="'.url('/car-insurance-guide').'"', false)
            ->assertSee('FAQPage', false)->assertSee('What is IDV?')->assertSee('Hello body', false)->assertSee('Get best offers');
    }

    public function test_draft_is_404_publicly_but_previewable_by_admin(): void
    {
        $admin = $this->admin();
        $p = Page::create(['title' => 'Hidden', 'slug' => 'hidden', 'status' => 'draft', 'body' => 'x']);
        $this->get('/hidden')->assertNotFound();
        $this->actingAs($admin)->get("/admin/pages/{$p->id}/preview")->assertOk()->assertSee('noindex', false);
    }

    public function test_reserved_and_duplicate_slugs_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/pages', $this->payload(['slug' => 'news']))->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post('/admin/pages', $this->payload(['slug' => 'dup']))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/pages', $this->payload(['slug' => 'dup']))->assertSessionHasErrors('slug');
    }

    public function test_invalid_custom_schema_rejected_and_unknown_slug_404(): void
    {
        $this->actingAs($this->admin())->post('/admin/pages', $this->payload(['schema_json' => '{bad']))->assertSessionHasErrors('schema_json');
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_update_delete_and_sitemap(): void
    {
        $admin = $this->admin();
        $p = Page::create(['title' => 'Mapped', 'slug' => 'mapped', 'status' => 'published', 'published_at' => now()->subMinute(), 'body' => 'x']);
        $this->get('/sitemap.xml')->assertSee('/mapped', false);
        $this->actingAs($admin)->put("/admin/pages/{$p->id}", $this->payload(['title' => 'Mapped 2', 'slug' => 'mapped', 'show_lead' => 0]))->assertRedirect();
        $this->assertSame('Mapped 2', $p->fresh()->title);
        $this->assertFalse($p->fresh()->show_lead);
        $this->actingAs($admin)->delete("/admin/pages/{$p->id}")->assertRedirect();
        $this->get('/mapped')->assertNotFound();
    }

    public function test_listing_data_endpoint(): void
    {
        $admin = $this->admin();
        Page::create(['title' => 'Row', 'slug' => 'row', 'status' => 'published', 'body' => 'x']);
        $this->actingAs($admin)->getJson('/admin/pages/data?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal', 1);
    }
}
