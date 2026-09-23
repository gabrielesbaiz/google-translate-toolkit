<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('the package declares strict types')
    ->expect('Gabrielesbaiz\GoogleTranslateToolkit')
    ->toUseStrictTypes();

arch('data objects stay immutable')
    ->expect('Gabrielesbaiz\GoogleTranslateToolkit\Data')
    ->toBeFinal();

arch('exceptions extend the package base exception')
    ->expect('Gabrielesbaiz\GoogleTranslateToolkit\Exceptions')
    ->toExtend('Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\GoogleTranslateException')
    ->ignoring('Gabrielesbaiz\GoogleTranslateToolkit\Exceptions\GoogleTranslateException');
