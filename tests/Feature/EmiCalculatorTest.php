<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmiCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_emi_page_loads(): void
    {
        $this->get('/car-emi-calculator')->assertOk()->assertSee('Car Loan EMI Calculator')->assertSee('Get best offers');
    }

    public function test_lead_saves_emi_context(): void
    {
        $this->post('/lead', ['name' => 'Test', 'phone' => '9876543210', 'city' => 'Delhi', 'emi_context' => 'Amount ₹1,000,000, EMI ₹21,247'])->assertRedirect();
        $this->assertStringContainsString('EMI calc:', \App\Models\Lead::first()->message);
    }
}
