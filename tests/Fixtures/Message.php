<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Tests\Fixtures;

use Gabrielesbaiz\GoogleTranslateToolkit\Casts\AsLanguage;
use Gabrielesbaiz\GoogleTranslateToolkit\Concerns\HasTranslations;
use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translatable;
use Illuminate\Database\Eloquent\Model;

class Message extends Model implements Translatable
{
    use HasTranslations;

    protected $guarded = [];

    public $timestamps = false;

    /** @return array<int, string> */
    public function translatableAttributes(): array
    {
        return ['body'];
    }

    protected function casts(): array
    {
        return ['locale' => AsLanguage::class];
    }
}
