<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->post('/games', ['name' => '侵入'])->assertRedirect('/login');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Login'));
    }

    public function test_valid_credentials_open_the_board(): void
    {
        $this->post('/login', [
            'login_id' => 'party',
            'password' => 'board',
            'website' => '',
        ])->assertRedirect('/');

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Board'));
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->post('/login', [
            'login_id' => 'party',
            'password' => 'wrong',
            'website' => '',
        ])->assertSessionHasErrors('login_id');

        $this->get('/')->assertRedirect('/login');
    }

    public function test_honeypot_submissions_are_rejected(): void
    {
        $this->post('/login', [
            'login_id' => 'party',
            'password' => 'board',
            'website' => 'http://bot.example',
        ])->assertSessionHasErrors('login_id');

        $this->get('/')->assertRedirect('/login');
    }

    public function test_logout_clears_access(): void
    {
        $this->withSession(['board_authenticated' => true])
            ->post('/logout')
            ->assertRedirect('/login');

        $this->get('/')->assertRedirect('/login');
    }

    public function test_robots_txt_disallows_all_crawlers(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false);
    }
}
