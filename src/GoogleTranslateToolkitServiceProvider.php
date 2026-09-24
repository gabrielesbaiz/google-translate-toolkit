<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit;

use Gabrielesbaiz\GoogleTranslateToolkit\Commands\TranslateAuditCommand;
use Gabrielesbaiz\GoogleTranslateToolkit\Commands\TranslateCacheClearCommand;
use Gabrielesbaiz\GoogleTranslateToolkit\Commands\TranslateCostCommand;
use Gabrielesbaiz\GoogleTranslateToolkit\Commands\TranslateLangCommand;
use Gabrielesbaiz\GoogleTranslateToolkit\Commands\TranslateLanguagesCommand;
use Gabrielesbaiz\GoogleTranslateToolkit\Commands\TranslateModelCommand;
use Gabrielesbaiz\GoogleTranslateToolkit\Commands\TranslateStatsCommand;
use Gabrielesbaiz\GoogleTranslateToolkit\Commands\TranslateTextCommand;
use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translator;
use Gabrielesbaiz\GoogleTranslateToolkit\Drivers\GoogleTranslateApi;
use Gabrielesbaiz\GoogleTranslateToolkit\Middleware\TranslateResponse;
use Gabrielesbaiz\GoogleTranslateToolkit\Rules\LanguageRule;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Chunker;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Config;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Glossary;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Placeholders;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Throttle;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\TranslationCache;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\TranslationRunner;
use Gabrielesbaiz\GoogleTranslateToolkit\Support\Usage;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Validation\Rule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GoogleTranslateToolkitServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('google-translate-toolkit')
            ->hasConfigFile()
            ->hasCommands([
                TranslateTextCommand::class,
                TranslateLanguagesCommand::class,
                TranslateLangCommand::class,
                TranslateModelCommand::class,
                TranslateAuditCommand::class,
                TranslateCostCommand::class,
                TranslateStatsCommand::class,
                TranslateCacheClearCommand::class,
            ]);
    }

    /**
     * Register the package services.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(Config::class, fn ($app) => new Config($app['config']));
        $this->app->singleton(Chunker::class);
        $this->app->singleton(Throttle::class);
        $this->app->singleton(Placeholders::class);
        $this->app->singleton(Glossary::class);
        $this->app->singleton(TranslationCache::class);
        $this->app->singleton(Usage::class);
        $this->app->singleton(TranslationRunner::class);

        $this->app->singleton(Translator::class, GoogleTranslateApi::class);
        $this->app->singleton(GoogleTranslateToolkit::class);

        $this->app->alias(GoogleTranslateToolkit::class, 'google-translate-toolkit');
        $this->app->alias(GoogleTranslateToolkit::class, 'google-translate');
    }

    /**
     * Bootstrap the package services.
     */
    public function packageBooted(): void
    {
        $this->registerBladeDirectives();
        $this->registerMacros();
        $this->registerMiddleware();
    }

    /**
     * Register the package Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // The text comes first, then the optional target and source languages.
        Blade::directive(
            'translate',
            fn (string $expression): string => "<?php echo e(app('google-translate-toolkit')->blade({$expression})); ?>",
        );

        // The same directive for markup, whose output is left unescaped.
        Blade::directive(
            'translateHtml',
            fn (string $expression): string => "<?php echo app('google-translate-toolkit')->bladeHtml({$expression}); ?>",
        );
    }

    /**
     * Register the package macros on the framework classes.
     */
    protected function registerMacros(): void
    {
        if (! Str::hasMacro('translate')) {
            Str::macro('translate', fn (string $value, mixed $to = null, mixed $from = null): string => app('google-translate-toolkit')->justTranslate($value, $from, $to));
        }

        if (! Stringable::hasMacro('translate')) {
            Stringable::macro('translate', function (mixed $to = null, mixed $from = null): Stringable {
                /** @var Stringable $this */
                return new Stringable(app('google-translate-toolkit')->justTranslate((string) $this, $from, $to));
            });
        }

        if (! Stringable::hasMacro('translateTo')) {
            Stringable::macro('translateTo', function (mixed $to): Stringable {
                /** @var Stringable $this */
                return new Stringable(app('google-translate-toolkit')->justTranslate((string) $this, null, $to));
            });
        }

        if (! Stringable::hasMacro('detectLanguage')) {
            Stringable::macro('detectLanguage', function (): string {
                /** @var Stringable $this */
                return app('google-translate-toolkit')->detect((string) $this)->languageCode;
            });
        }

        if (! Collection::hasMacro('translate')) {
            Collection::macro('translate', function (mixed $to = null, mixed $from = null) {
                /** @var Collection<array-key, string> $this */
                return app('google-translate-toolkit')->translateBatch($this->all(), $from, $to);
            });
        }

        if (! Rule::hasMacro('language')) {
            Rule::macro('language', fn (array $only = []): LanguageRule => LanguageRule::make($only));
        }
    }

    /**
     * Register the package middleware alias.
     */
    protected function registerMiddleware(): void
    {
        if (! $this->app->bound(Router::class)) {
            return;
        }

        $this->app->make(Router::class)->aliasMiddleware('translate.response', TranslateResponse::class);
    }
}
