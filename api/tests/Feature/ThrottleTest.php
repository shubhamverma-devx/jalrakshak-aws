<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Public endpoints jo bahar kuch bhejte hain unpe limit honi chahiye.
 *
 * /subscribe kisi bhi address pe Amazon SNS se mail bhijwa sakta hai, aur /relief
 * officer ki queue mein rows daal sakta hai. Dono bina login ke hain, isliye bina
 * limit ke ye dusron ko pareshan karne ka raasta ban jaate.
 */
class ThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
    }

    public function test_subscribe_is_rate_limited(): void
    {
        $payload = ['village_id' => 1, 'email' => 'someone@example.test'];

        $statuses = [];

        for ($i = 0; $i < 7; $i++) {
            $statuses[] = $this->postJson('/api/subscribe', $payload)->status();
        }

        $this->assertContains(429, $statuses, 'subscribe should start refusing after a few tries');
    }

    public function test_officer_login_is_rate_limited(): void
    {
        $statuses = [];

        for ($i = 0; $i < 7; $i++) {
            $statuses[] = $this->postJson('/api/officer/login', [
                'email' => 'officer@jalrakshak.in',
                'password' => 'guess-'.$i,
            ])->status();
        }

        $this->assertContains(429, $statuses, 'login should start refusing after a few tries');
    }

    public function test_reading_villages_is_not_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/villages')->assertOk();
        }
    }
}
