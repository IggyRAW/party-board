<?php

namespace Tests\Unit;

use App\Support\LanAwareVite;
use Illuminate\Http\Request;
use Tests\TestCase;

class LanAwareViteTest extends TestCase
{
    public function test_rewrites_localhost_origin_to_the_request_host(): void
    {
        $hotFile = storage_path('framework/testing-vite-hot');
        file_put_contents($hotFile, 'http://localhost:5174');

        $this->app->instance('request', Request::create('http://192.168.10.104:8000'));

        $url = (new LanAwareVite)->useHotFile($hotFile)->asset('resources/js/app.js');

        $this->assertSame('http://192.168.10.104:5174/resources/js/app.js', $url);

        unlink($hotFile);
    }

    public function test_keeps_localhost_when_browsing_from_localhost(): void
    {
        $hotFile = storage_path('framework/testing-vite-hot');
        file_put_contents($hotFile, 'http://localhost:5174');

        $this->app->instance('request', Request::create('http://localhost:8000'));

        $url = (new LanAwareVite)->useHotFile($hotFile)->asset('resources/js/app.js');

        $this->assertSame('http://localhost:5174/resources/js/app.js', $url);

        unlink($hotFile);
    }
}
