<?php

namespace Tests\Feature;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.github.com/*' => function (Request $request): PromiseInterface {
                return Http::response(
                    $this->fixture(
                        parse_url($request->url(), PHP_URL_PATH)
                    ),
                    Response::HTTP_OK
                );
            },
        ]);
    }

    protected function fixture(string $path): array
    {
        return json_decode(File::get(
            Str::of($path)
                ->trim('/')
                ->prepend(__DIR__.'/../fixtures/')
                ->finish('.json')
        ), true);
    }

    protected function requiresPostgreSQL(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This test requires a PostgreSQL database connection');
        }
    }
}
