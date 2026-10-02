<?php

namespace Tests\Unit;

use App\Services\QueryIntent;
use PHPUnit\Framework\TestCase;

class QueryIntentTest extends TestCase
{
    public function test_hinglish_filler_is_removed_and_the_meaning_is_kept(): void
    {
        $this->assertSame('news', QueryIntent::clean('muje aaj ki latest news do'));
        $this->assertSame('tata siera', QueryIntent::clean('Tata siera ke bar me bata'));
        $this->assertSame('creta mileage', QueryIntent::clean('creta ka mileage kya hai'));
    }

    public function test_news_is_detected_with_or_without_the_word_news(): void
    {
        $this->assertTrue(QueryIntent::wantsNews('muje aaj ki latest news do', false));
        $this->assertTrue(QueryIntent::wantsNews('latest updates', false));
        $this->assertTrue(QueryIntent::wantsNews('Tata Sierra news', true));
        $this->assertFalse(QueryIntent::wantsNews('latest cars', true));          // a car question, not a news request
        $this->assertFalse(QueryIntent::wantsNews('tata sierra price', true));
    }
}
