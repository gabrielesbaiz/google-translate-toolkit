<?php

declare(strict_types=1);

use Gabrielesbaiz\GoogleTranslateToolkit\Enums\Language;
use Gabrielesbaiz\GoogleTranslateToolkit\Facades\GoogleTranslateToolkit;
use Gabrielesbaiz\GoogleTranslateToolkit\Jobs\TranslateAttributesJob;
use Gabrielesbaiz\GoogleTranslateToolkit\Tests\Fixtures\Message;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('messages', function ($table) {
        $table->id();
        $table->text('body')->nullable();
        $table->text('body_it')->nullable();
        $table->string('locale')->nullable();
    });
});

it('fills the translated column', function () {
    GoogleTranslateToolkit::fake(['The message bounced' => 'Il messaggio è stato rifiutato']);

    $message = Message::create(['body' => 'The message bounced']);

    $message->translateAttributes()->save();

    expect($message->fresh()->body_it)->toBe('Il messaggio è stato rifiutato')
        ->and($message->getTranslatedAttribute('body'))->toBe('Il messaggio è stato rifiutato');
});

it('leaves an already translated row alone', function () {
    $fake = GoogleTranslateToolkit::fake();

    $message = Message::create(['body' => 'hello', 'body_it' => 'ciao']);

    $message->translateAttributes()->save();

    expect($message->fresh()->body_it)->toBe('ciao');

    $fake->assertNothingTranslated();
});

it('overwrites when asked', function () {
    GoogleTranslateToolkit::fake();

    $message = Message::create(['body' => 'hello', 'body_it' => 'ciao']);

    $message->translateAttributes(overwrite: true)->save();

    expect($message->fresh()->body_it)->toBe('[it] hello');
});

it('finds rows still missing a translation', function () {
    Message::create(['body' => 'one']);
    Message::create(['body' => 'two', 'body_it' => 'due']);
    Message::create(['body' => null]);

    expect(Message::query()->whereTranslationMissing('body')->pluck('body')->all())->toBe(['one']);
});

it('queues the translation', function () {
    Queue::fake();

    $message = Message::create(['body' => 'hello']);

    $message->queueTranslateAttributes('it');

    Queue::assertPushed(TranslateAttributesJob::class, fn (TranslateAttributesJob $job) => $job->target === 'it');
});

it('backfills a table with the artisan command', function () {
    GoogleTranslateToolkit::fake();

    Message::create(['body' => 'one']);
    Message::create(['body' => 'two']);
    Message::create(['body' => 'three', 'body_it' => 'tre']);

    $this->artisan('translate:model', ['model' => Message::class, '--to' => 'it'])->assertSuccessful();

    expect(Message::pluck('body_it')->all())->toBe(['[it] one', '[it] two', 'tre']);
});

it('casts a language column to the enum', function () {
    $message = Message::create(['body' => 'x', 'locale' => 'IT']);

    expect($message->fresh()->locale)->toBe(Language::Italian);
});
