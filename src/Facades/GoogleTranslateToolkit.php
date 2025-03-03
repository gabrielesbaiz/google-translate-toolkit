<?php

namespace Gabrielesbaiz\GoogleTranslateToolkit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit
 */
class GoogleTranslateToolkit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkit::class;
    }
}
