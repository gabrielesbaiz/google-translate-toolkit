<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->lang = sys_get_temp_dir().'/gtt-lang-'.bin2hex(random_bytes(4));

    File::ensureDirectoryExists($this->lang.'/en');
    File::put($this->lang.'/en/auth.php', "<?php return ['failed' => 'These credentials do not match :attribute.', 'nested' => ['throttle' => 'Too many attempts.']];");
    File::put($this->lang.'/en.json', json_encode(['Hello' => 'Hello', 'Bye' => 'Bye']));

    app()->useLangPath($this->lang);
});

afterEach(fn () => File::deleteDirectory($this->lang));

it('translates lang files and keeps placeholders', function () {
    GoogleTranslateToolkit::fake();

    $this->artisan('translate:lang', ['--from' => 'en', '--to' => ['it']])->assertSuccessful();

    $translated = include $this->lang.'/it/auth.php';

    expect($translated['failed'])->toBe('[it] These credentials do not match :attribute.')
        ->and($translated['nested']['throttle'])->toBe('[it] Too many attempts.')
        ->and(json_decode(File::get($this->lang.'/it.json'), true))->toBe([
            'Bye' => '[it] Bye',
            'Hello' => '[it] Hello',
        ]);
});

it('skips lines that already exist unless forced', function () {
    GoogleTranslateToolkit::fake();

    File::ensureDirectoryExists($this->lang.'/it');
    File::put($this->lang.'/it/auth.php', "<?php return ['failed' => 'Credenziali errate.'];");

    $this->artisan('translate:lang', ['--from' => 'en', '--to' => ['it'], '--group' => ['auth']])->assertSuccessful();

    expect((include $this->lang.'/it/auth.php')['failed'])->toBe('Credenziali errate.');

    $this->artisan('translate:lang', ['--from' => 'en', '--to' => ['it'], '--group' => ['auth'], '--force' => true])->assertSuccessful();

    expect((include $this->lang.'/it/auth.php')['failed'])->toBe('[it] These credentials do not match :attribute.');
});

it('reports a dry run without translating', function () {
    $fake = GoogleTranslateToolkit::fake();

    $this->artisan('translate:lang', ['--from' => 'en', '--to' => ['it'], '--dry-run' => true])->assertSuccessful();

    $fake->assertNothingTranslated();
    expect(File::exists($this->lang.'/it/auth.php'))->toBeFalse();
});

it('audits existing translations', function () {
    GoogleTranslateToolkit::fake(['Credenziali errate.' => 'Wrong credentials.']);

    File::ensureDirectoryExists($this->lang.'/it');
    File::put($this->lang.'/it/auth.php', "<?php return ['failed' => 'Credenziali errate.'];");

    $this->artisan('translate:audit', ['--from' => 'en', '--to' => 'it', '--group' => ['auth'], '--threshold' => 0.9])
        ->expectsOutputToContain('auth.failed')
        ->assertSuccessful();
});

it('lists languages offline', function () {
    $this->artisan('translate:languages', ['--offline' => true, '--search' => 'ital'])
        ->expectsOutputToContain('Italian')
        ->assertSuccessful();
});

it('prices a translation without sending it', function () {
    $fake = GoogleTranslateToolkit::fake();

    $this->artisan('translate:cost', ['text' => ['hello world'], '--to' => ['it', 'fr']])
        ->expectsOutputToContain('estimated cost')
        ->assertSuccessful();

    $fake->assertNothingTranslated();
});

it('shows statistics and clears the cache', function () {
    GoogleTranslateToolkit::fake();
    GoogleTranslateToolkit::justTranslate('hello');

    $this->artisan('translate:stats', ['--days' => 2])->assertSuccessful();
    $this->artisan('translate:cache-clear')->assertSuccessful();
});

it('translates text from the console', function () {
    GoogleTranslateToolkit::fake();

    $this->artisan('translate:text', ['text' => ['hello'], '--to' => ['it']])
        ->expectsOutputToContain('[it] hello')
        ->assertSuccessful();
});
