<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Officer routes live internet pe hain, isliye inka band rehna test se guard kiya hai.
 * SIH build mein koi auth nahi tha; agar kabhi galti se middleware hat gaya to ye
 * test turant lal ho jayega.
 */
class OfficerGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jalrakshak.officer.email' => 'officer@example.test',
            'jalrakshak.officer.password' => 'correct-horse',
            'jalrakshak.officer.token' => 'test-token',
        ]);
    }

    public function test_officer_routes_reject_a_request_with_no_token(): void
    {
        $this->postJson('/api/alert')->assertStatus(401);
        $this->getJson('/api/relief')->assertStatus(401);
        $this->patchJson('/api/relief/1')->assertStatus(401);
        $this->postJson('/api/officer/village/1/map')->assertStatus(401);
    }

    public function test_officer_routes_reject_a_wrong_token(): void
    {
        $this->withHeader('Authorization', 'Bearer not-the-token')
            ->getJson('/api/relief')
            ->assertStatus(401);
    }

    public function test_officer_routes_accept_the_issued_token(): void
    {
        $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('/api/relief')
            ->assertOk();
    }

    public function test_login_returns_a_token_for_the_right_password(): void
    {
        $this->postJson('/api/officer/login', [
            'email' => 'officer@example.test',
            'password' => 'correct-horse',
        ])->assertOk()->assertJsonPath('token', 'test-token');
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        $this->postJson('/api/officer/login', [
            'email' => 'officer@example.test',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_citizen_read_routes_stay_public(): void
    {
        $this->getJson('/api/villages')->assertOk();
        $this->getJson('/api/alerts')->assertOk();
        $this->getJson('/api/shelters')->assertOk();
    }
}
