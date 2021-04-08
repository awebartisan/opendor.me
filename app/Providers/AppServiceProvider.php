<?php

namespace App\Providers;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client as Dropbox;
use Spatie\FlysystemDropbox\DropboxAdapter;
use Stillat\Numeral\Languages\LanguageManager;
use Stillat\Numeral\Numeral;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Numeral::class, static function (): Numeral {
            $numeral = new Numeral();
            $numeral->setLanguageManager(new LanguageManager());

            return $numeral;
        });
    }

    public function boot(): void
    {
        PendingRequest::macro('when', function ($condition, Closure $callback): PendingRequest {
            /** @var \Illuminate\Http\Client\PendingRequest $this */
            $condition = value($condition);

            if ($condition) {
                $callback($this, $condition);
            }

            return $this;
        });

        Http::macro('github', function (): PendingRequest {
            /** @var \Illuminate\Http\Client\Factory $this */
            return $this
                ->baseUrl('https://api.github.com')
                ->accept('application/vnd.github.v3+json')
                ->withUserAgent(config('app.name').' '.config('app.url'))
                ->withOptions(['http_errors' => true])
                ->when(
                    User::whereIsRegistered()->inRandomOrder()->first()?->github_access_token,
                    fn (PendingRequest $request, $token) => $request->withToken($token)
                );
        });

        Str::macro('domain', function (string $value): string {
            $value = parse_url($value, PHP_URL_HOST) ?: $value;

            return preg_replace('`^(www\d?|m)\.`', '', $value);
        });

        Str::macro('numeral', function (int $value, string $format = '4a'): string {
            return app(Numeral::class)->format($value, $format);
        });

        Storage::extend('dropbox', static function (Container $app, array $config): FilesystemContract {
            $client = new Dropbox($config['access_token']);
            $adapter = new DropboxAdapter($client);
            $filesystem = new Filesystem($adapter, ['case_sensitive' => false]);

            return new FilesystemAdapter($filesystem);
        });
    }
}
