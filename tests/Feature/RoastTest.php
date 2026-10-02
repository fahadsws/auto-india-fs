<?php

namespace Tests\Feature;

use App\Models\AssistantSession;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\Roast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoastTest extends TestCase
{
    use RefreshDatabase;

    private function verified(): array
    {
        $lead = Lead::create(['type' => 'chatbot', 'name' => 'Rahul Sharma', 'phone' => '9876543210', 'email' => uniqid().'@example.com', 'status' => 'new', 'email_verified_at' => now()]);
        $s = AssistantSession::create(['lead_id' => $lead->id, 'token' => 'tok-'.bin2hex(random_bytes(16))]);

        return ['X-Assistant-Token' => $s->token];
    }

    private function body(array $over = []): array
    {
        return $over + ['car' => 'Maruti Swift', 'owned' => 'y1', 'level' => 'medium', 'habits' => ['ac24', 'parking']];
    }

    public function test_page_renders_and_is_in_the_sitemap(): void
    {
        $this->get('/roast-my-car')->assertOk()->assertSee('challan')->assertSee('Dil pe mat lena', false);
        $this->get('/sitemap.xml')->assertSee('/roast-my-car');
    }

    public function test_unverified_visitor_gets_the_gate_not_a_roast(): void
    {
        $this->postJson('/roast-my-car/roast', $this->body())->assertStatus(401)->assertJson(['gate' => true]);
    }

    public function test_verified_visitor_gets_a_card_without_ai(): void
    {
        $this->postJson('/roast-my-car/roast', $this->body(), $this->verified())
            ->assertOk()->assertJsonPath('card.mode', 'roast')->assertJsonPath('card.ai', false)->assertJsonCount(3, 'card.lines');
    }

    public function test_gangster_cars_are_never_roasted(): void
    {
        foreach (['Mahindra Thar', 'Scorpio-N', 'Toyota Fortuner', 'Bolero Neo'] as $car) {
            $this->assertSame('aura', Roast::archetype($car)[0], $car);
        }
        $this->postJson('/roast-my-car/roast', $this->body(['car' => 'Mahindra Thar Roxx']), $this->verified())->assertOk()->assertJsonPath('card.mode', 'aura');
        $this->assertSame('roast', Roast::archetype('Maruti Swift')[0]);
        $this->assertSame('family', Roast::archetype('Toyota Innova Crysta')[1]);
        $this->assertSame('ev', Roast::archetype('Tata Nexon EV')[1]);
    }

    public function test_ai_reply_is_used_when_clean(): void
    {
        Setting::put('ai.api_key', 'k');
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'title' => 'City ka Sher', 'lines' => ['Parking mein 7 baar aage-peeche.', 'AC 24 pe, sweater saath.', 'EMI ki date yaad, birthday nahi.'], 'verdict' => 'emi warrior', 'fine' => '₹499 + 1 chai', 'score' => ['label' => 'Dukh Meter', 'value' => 74], 'tag' => 'EMIWarrior',
        ])]]], 'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 120]])]);

        $this->postJson('/roast-my-car/roast', $this->body(), $this->verified())
            ->assertOk()->assertJsonPath('card.ai', true)->assertJsonPath('card.verdict', 'EMI WARRIOR')->assertJsonPath('card.score.value', 74)->assertJsonPath('card.tag', '#EMIWarrior');
    }

    public function test_unsafe_ai_output_is_replaced_by_safe_fallback(): void
    {
        Setting::put('ai.api_key', 'k');
        foreach (['Maruti ki gaadi bilkul ghatiya company hai.', 'Tu ek chutiya hai.', 'Ye gaadi sirf Hindu logon ke liye hai.'] as $bad) {
            Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'title' => 'x', 'lines' => [$bad, 'theek line', 'theek line 2'], 'verdict' => 'ok', 'fine' => '₹1', 'score' => ['label' => 'm', 'value' => 50], 'tag' => 't'])]]]])]);
            $this->postJson('/roast-my-car/roast', $this->body(), $this->verified())->assertOk()->assertJsonPath('card.ai', false);
        }
    }

    public function test_safety_filter_catches_abuse_and_brand_bashing_but_not_normal_hinglish(): void
    {
        foreach (['Tata ek bekaar company hai', 'worst brand ever, Mahindra', 'madarchod', 'ye sab fuck hai', 'hindu muslim'] as $t) $this->assertTrue(Roast::isUnsafe($t), $t);
        foreach (['AC 24 pe, sweater saath. Sikhna padta hai parking.', 'Dhulai sirf barish karwati hai.', 'Swift ka loan aur Maggi ka dinner.'] as $t) $this->assertFalse(Roast::isUnsafe($t), $t);
        foreach (Roast::HABITS as $k => $_) {
            $this->assertFalse(Roast::isUnsafe(Roast::fallback('roast', 'generic', 'x', [$k])['lines'][1]), $k);
        }
    }

    public function test_every_built_in_fallback_is_clean(): void
    {
        foreach (['aura', 'first', 'hatch', 'family', 'suv', 'sedan', 'ev', 'luxury', 'generic'] as $t) {
            for ($i = 0; $i < 5; $i++) $this->assertFalse(Roast::isUnsafe(json_encode(Roast::fallback($t === 'aura' ? 'aura' : 'roast', $t, 'x', []), JSON_UNESCAPED_UNICODE)), $t);
        }
    }

    public function test_abusive_car_name_and_bad_habit_are_rejected(): void
    {
        $this->postJson('/roast-my-car/roast', $this->body(['car' => 'madarchod gaadi']), $this->verified())->assertStatus(422);
        $this->postJson('/roast-my-car/roast', $this->body(['habits' => ['hack']]), $this->verified())->assertStatus(422);
        $this->postJson('/roast-my-car/roast', $this->body(['habits' => ['ac24', 'wash', 'emi', 'horn']]), $this->verified())->assertStatus(422);
    }

    public function test_daily_cap(): void
    {
        Setting::put('roast.limit_day', 2);
        $h = $this->verified();
        $this->postJson('/roast-my-car/roast', $this->body(), $h)->assertOk();
        $this->postJson('/roast-my-car/roast', $this->body(), $h)->assertOk();
        $this->postJson('/roast-my-car/roast', $this->body(), $h)->assertStatus(429);
    }
}
