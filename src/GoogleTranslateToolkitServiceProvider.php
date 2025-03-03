<?php

namespace Gabrielesbaiz\GoogleTranslateToolkit;

use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Gabrielesbaiz\GoogleTranslateToolkit\Http\GoogleTranslateClient;

class GoogleTranslateToolkitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('google-translate-toolkit')
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        Blade::directive('translate', function ($expression) {
            $parts = explode(',', $expression);

            $input = $parts[0];

            $languageCode = $parts[1] ?? config('google-translate-toolkit.default_target_translation');

            return "<?php echo app(\Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit::class)->justTranslate({$input}, {$languageCode}); ?>";
        });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(GoogleTranslateClient::class, fn () => new GoogleTranslateClient(config('google-translate-toolkit')));

        $this->app->singleton(GoogleTranslateToolkit::class, fn () => new GoogleTranslateToolkit(app(GoogleTranslateClient::class)));

        $this->app->alias(GoogleTranslateToolkit::class, 'google-translate-toolkit');
    }
}
