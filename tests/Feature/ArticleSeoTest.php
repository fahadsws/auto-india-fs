<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArticleSeoTest extends TestCase
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
        return $o + ['title' => 'Tata Sierra EV launched', 'body' => '<p>The Tata Sierra EV is here with a 500 km range.</p>', 'status' => 'published',
            'excerpt' => 'Sierra EV launch', 'meta_title' => 'Sierra EV launched', 'meta_description' => 'Tata Sierra EV launched with 500 km range.',
            'meta_keywords' => 'tata sierra ev, electric suv', 'canonical_url' => 'https://example.com/original', 'robots' => 'index,follow', 'og_title' => 'OG Sierra',
            'og_image' => 'https://example.com/og.jpg', 'schema_type' => 'Article', 'tags_text' => 'Tata, EV, Sierra', 'tldr_text' => "Range 500 km\nLaunch this year",
            'faq' => [['q' => 'What is the range?', 'a' => '500 km.'], ['q' => '', 'a' => '']]];
    }

    private function aiOn(array $reply): void
    {
        Setting::put('ai.api_key', 'test-key');
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode($reply)]]]])]);
    }

    private function ld(string $html): array
    {
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

        return collect($m[1])->map(fn ($j) => json_decode($j, true))->each(fn ($j) => $this->assertIsArray($j, 'invalid JSON-LD'))->all();
    }

    public function test_manual_save_stores_advanced_seo_and_public_page_uses_it(): void
    {
        $this->actingAs($this->admin())->get('/admin/articles/create')->assertOk()->assertSee('Generate')->assertSee('Custom JSON-LD')->assertSee('Key takeaways');
        $this->actingAs($this->admin())->post('/admin/articles', $this->payload(['schema_json' => '{"@context":"https://schema.org","@type":"Thing","name":"Extra"}']))->assertRedirect();

        $a = Article::firstOrFail();
        $this->assertSame(['tata', 'ev', 'sierra'], $a->tags);
        $this->assertSame(['Range 500 km', 'Launch this year'], $a->tldr);
        $this->assertCount(1, $a->faq);
        $this->assertSame('Article', $a->schema_type);

        $html = $this->get($a->url)->assertOk()->getContent();
        $this->assertStringContainsString('rel="canonical" href="https://example.com/original"', $html);
        $this->assertStringContainsString('name="keywords" content="tata sierra ev, electric suv"', $html);
        $this->assertStringContainsString('property="og:title" content="OG Sierra"', $html);
        $this->assertStringContainsString('property="og:image" content="https://example.com/og.jpg"', $html);
        $types = collect($this->ld($html))->pluck('@type')->all();
        $this->assertSame(1, count(array_keys($types, 'FAQPage')));
        $this->assertContains('Article', $types);
        $this->assertContains('Thing', $types);
        $this->assertNotContains('NewsArticle', $types);
        $this->actingAs($this->admin())->get("/admin/articles/{$a->id}/edit")->assertOk()->assertSee('What is the range?')->assertSee('Generate SEO with AI');
    }

    public function test_invalid_json_rejected_and_noindex_hidden_from_sitemap(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/articles', $this->payload(['schema_json' => '{bad']))->assertSessionHasErrors('schema_json');
        $this->assertSame(0, Article::count());

        $this->actingAs($admin)->post('/admin/articles', $this->payload(['title' => 'Hidden one', 'robots' => 'noindex,follow']))->assertRedirect();
        $this->actingAs($admin)->post('/admin/articles', $this->payload(['title' => 'Visible one']))->assertRedirect();
        $this->get('/sitemap.xml')->assertSee('visible-one', false)->assertDontSee('hidden-one', false);
        $this->get(Article::where('title', 'Hidden one')->first()->url)->assertSee('name="robots" content="noindex,follow"', false);
    }

    public function test_legacy_article_without_new_fields_still_renders(): void
    {
        $a = Article::create(['title' => 'Old', 'slug' => 'old', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        $level = ob_get_level();
        $html = $this->get($a->url)->assertOk()->getContent();
        while (ob_get_level() > $level) ob_end_clean();   // the article page leaves one output buffer open (already the case before this change)
        $this->assertContains('NewsArticle', collect($this->ld($html))->pluck('@type')->all());
        $this->assertStringNotContainsString('name="keywords"', $html);
    }

    public function test_ai_suggestions_ai_draft_and_bulk_fill_only_empty_fields(): void
    {
        $admin = $this->admin();
        $reply = ['title' => 'T', 'excerpt' => 'E', 'body_html' => '<p>b</p>', 'meta_title' => 'AI title', 'meta_description' => 'AI description', 'meta_keywords' => 'a, b',
            'tldr' => ['one', 'two', 'three'], 'tags' => ['Tata', 'EV'], 'faq' => [['q' => 'Q?', 'a' => 'A.']]];
        $this->aiOn($reply);

        $a = Article::create(['title' => 'Mine', 'slug' => 'mine', 'body' => '<p>Real body text about a car.</p>', 'status' => 'draft', 'meta_title' => 'Hand written title']);

        $this->actingAs($admin)->postJson("/admin/articles/{$a->id}/ai-seo", ['title' => 'Mine', 'body' => '<p>Real body</p>'])
            ->assertOk()->assertJsonPath('meta_description', 'AI description')->assertJsonPath('tags.0', 'tata')->assertJsonPath('faq.0.q', 'Q?');
        $this->assertSame('Hand written title', $a->fresh()->meta_title, 'suggestions are not saved');

        $this->actingAs($admin)->postJson('/admin/articles/ai-draft', ['topic' => 'Sierra'])->assertOk()->assertJsonPath('meta_keywords', 'a, b')->assertJsonPath('faq.0.a', 'A.');

        $this->actingAs($admin)->postJson('/admin/articles/bulk', ['action' => 'ai_seo', 'ids' => [$a->id]])->assertOk()->assertJsonPath('count', 1);
        $a->refresh();
        $this->assertSame('Hand written title', $a->meta_title, 'existing text is never overwritten');
        $this->assertSame('AI description', $a->meta_description);
        $this->assertSame('a, b', $a->meta_keywords);
        $this->assertSame(['one', 'two', 'three'], $a->tldr);
        $this->assertSame('Q?', $a->faq[0]['q']);
    }

    public function test_ai_seo_requires_provider(): void
    {
        $a = Article::create(['title' => 'X', 'slug' => 'x', 'body' => '<p>y</p>', 'status' => 'draft']);
        $this->actingAs($this->admin())->postJson("/admin/articles/{$a->id}/ai-seo", [])->assertStatus(422);
    }
}
