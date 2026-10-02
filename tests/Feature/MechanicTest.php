<?php

namespace Tests\Feature;

use App\Models\AssistantSession;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\Mechanic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MechanicTest extends TestCase
{
    use RefreshDatabase;

    private function verified(): array
    {
        $lead = Lead::create(['type' => 'chatbot', 'name' => 'Rahul Sharma', 'phone' => '9876543210', 'email' => uniqid().'@example.com', 'status' => 'new', 'email_verified_at' => now()]);
        $s = AssistantSession::create(['lead_id' => $lead->id, 'token' => 'tok-'.bin2hex(random_bytes(16))]);

        return ['X-Assistant-Token' => $s->token];
    }

    private function fakeAi(array|string $payload, array $usage = ['prompt_tokens' => 400, 'completion_tokens' => 300]): void
    {
        Setting::put('ai.api_key', 'k');
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => is_string($payload) ? $payload : json_encode($payload)]]], 'usage' => $usage])]);
    }

    private function body(array $over = []): array
    {
        return $over + ['message' => 'Brake lagane pe awaaz aati hai', 'history' => [], 'profile' => ['car' => 'Maruti Swift', 'age' => '2 saal', 'km' => '30000', 'fuel' => 'petrol', 'city' => 'Pune']];
    }

    private function diagnosis(array $over = []): array
    {
        return ['reply' => 'Lagta hai brake pad ghis gaye hain.', 'quick_replies' => ['x'], 'facts' => ['car' => 'Maruti Swift', 'symptom' => 'brake awaaz', 'recent_work' => 'tyre 6 mahine pehle'], 'stage' => 'diagnosis', 'diagnosis' => $over + [
            'title' => 'Brake pad ghis gaye', 'severity' => 'high', 'summary' => 'Pads patle ho gaye hain.', 'why' => ['Barsaat mein roz city traffic'],
            'causes' => [['name' => 'Pad ghisna', 'likelihood' => 40, 'why' => 'a'], ['name' => 'Disc par ridge', 'likelihood' => 70, 'why' => 'b']],
            'confirm_with' => ['Wheel kholke pad ki motai dekho'], 'stop_it_now' => ['Tez braking mat karo'],
            'fix' => [['work' => 'Front brake pad', 'parts_min' => 1800, 'parts_max' => 1200, 'labour_min' => 300, 'labour_max' => 500, 'note' => 'n'], ['work' => 'Disc skimming', 'parts_min' => 0, 'parts_max' => 0, 'labour_min' => 400, 'labour_max' => 700]],
            'total_min' => 1, 'total_max' => 99999999, 'avoid' => ['Poora disc badalna'], 'city_note' => 'Pune mein metro se thoda sasta',
        ]];
    }

    public function test_page_renders_and_is_in_the_sitemap(): void
    {
        $this->get('/online-mechanic')->assertOk()->assertSee('Mechanic Bhai')->assertSee('Gaadi ki Job Card');
        $this->get('/sitemap.xml')->assertSee('/online-mechanic');
    }

    public function test_unverified_visitor_gets_the_gate(): void
    {
        $this->postJson('/online-mechanic/chat', $this->body())->assertStatus(401)->assertJson(['gate' => true]);
    }

    public function test_asking_turn_passes_quick_replies_and_facts(): void
    {
        $this->fakeAi(['reply' => 'Kab hoti hai ye awaaz? Tyre kab badle the?', 'quick_replies' => ['Subah', 'Hamesha', '<b>x</b>'], 'facts' => ['symptom' => 'brake awaaz', 'city' => 'Pune'], 'stage' => 'asking', 'diagnosis' => null]);
        $this->postJson('/online-mechanic/chat', $this->body(), $this->verified())
            ->assertOk()->assertJsonPath('stage', 'asking')->assertJsonPath('diagnosis', null)->assertJsonPath('facts.symptom', 'brake awaaz')->assertJsonPath('quick_replies.2', 'x');
    }

    public function test_diagnosis_totals_are_recomputed_and_values_clamped(): void
    {
        $this->fakeAi($this->diagnosis());
        $r = $this->postJson('/online-mechanic/chat', $this->body(), $this->verified())->assertOk()->assertJsonPath('stage', 'diagnosis');
        $r->assertJsonPath('diagnosis.fix.0.parts_min', 1200)->assertJsonPath('diagnosis.fix.0.parts_max', 1800);   // swapped min/max fixed
        $r->assertJsonPath('diagnosis.total_min', 1200 + 300 + 400)->assertJsonPath('diagnosis.total_max', 1800 + 500 + 700);   // model's own totals ignored
        $r->assertJsonPath('diagnosis.causes.0.name', 'Disc par ridge');   // ranked by likelihood
    }

    public function test_bad_severity_and_absurd_prices_are_coerced(): void
    {
        $d = $this->diagnosis(['severity' => 'catastrophe', 'fix' => [['work' => 'Engine', 'parts_min' => 5000000000, 'parts_max' => 9000000000, 'labour_min' => -50, 'labour_max' => 'abc']]]);
        $this->fakeAi($d);
        $r = $this->postJson('/online-mechanic/chat', $this->body(), $this->verified())->assertOk();
        $r->assertJsonPath('diagnosis.severity', 'medium')->assertJsonPath('diagnosis.fix.0.parts_max', 1000000)->assertJsonPath('diagnosis.fix.0.labour_min', 0);
    }

    public function test_unsafe_diagnosis_is_dropped(): void
    {
        $this->fakeAi($this->diagnosis(['summary' => 'Tu ek chutiya hai']));
        $this->postJson('/online-mechanic/chat', $this->body(), $this->verified())->assertOk()->assertJsonPath('stage', 'asking')->assertJsonPath('diagnosis', null);
    }

    public function test_prose_reply_is_shown_as_a_normal_chat_message(): void
    {
        $this->fakeAi('Pehle ye batao, gaadi kab start nahi hoti?');
        $this->postJson('/online-mechanic/chat', $this->body(), $this->verified())->assertOk()->assertJsonPath('reply', 'Pehle ye batao, gaadi kab start nahi hoti?');
    }

    public function test_ai_not_configured_returns_the_safe_note(): void
    {
        $this->postJson('/online-mechanic/chat', $this->body(), $this->verified())->assertOk()->assertJsonPath('degraded', true)->assertJsonPath('reply', Mechanic::SAFE_NOTE);
    }

    public function test_input_limits_and_abuse(): void
    {
        $h = $this->verified();
        $this->postJson('/online-mechanic/chat', $this->body(['message' => '']), $h)->assertStatus(422);
        $this->postJson('/online-mechanic/chat', $this->body(['message' => str_repeat('a', 501)]), $h)->assertStatus(422);
        $this->postJson('/online-mechanic/chat', $this->body(['history' => array_fill(0, 17, ['role' => 'user', 'content' => 'x'])]), $h)->assertStatus(422);
        $this->postJson('/online-mechanic/chat', $this->body(['history' => [['role' => 'system', 'content' => 'x']]]), $h)->assertStatus(422);
        $this->postJson('/online-mechanic/chat', $this->body(['profile' => ['fuel' => 'water']]), $h)->assertStatus(422);
        $this->postJson('/online-mechanic/chat', $this->body(['message' => 'madarchod gaadi']), $h)->assertStatus(422);
    }

    public function test_prompt_carries_profile_and_closing_nudge(): void
    {
        $m = Mechanic::messages(['car' => 'Swift', 'city' => 'Pune', 'age' => '', 'km' => '', 'fuel' => ''], [['role' => 'user', 'content' => 'a']], 'b', 6);
        $this->assertStringContainsString('City: Pune', $m[0]['content']);
        $this->assertStringContainsString('diagnosis NOW', $m[0]['content']);
        $this->assertSame('b', end($m)['content']);
    }

    public function test_daily_message_limit_applies(): void
    {
        Setting::put('assistant.limit_msgs_day', 1);
        Setting::put('assistant.limit_min_gap', 0);
        $this->fakeAi(['reply' => 'ok?', 'stage' => 'asking']);
        $h = $this->verified();
        $this->postJson('/online-mechanic/chat', $this->body(), $h)->assertOk();
        $this->postJson('/online-mechanic/chat', $this->body(), $h)->assertStatus(429);
    }
}
