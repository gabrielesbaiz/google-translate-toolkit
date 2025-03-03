<?php

return [
    /*
     * The ISO 639-1 code of the default source language.
     */
    'default_source_translation' => 'en',

    /*
     * The ISO 639-1 code of the language in lowercase to which
     * the text will be translated to by default.
     */
    'default_target_translation' => 'en',

    /*
     * Api Key generated within Google Cloud Dashboard.
     */
    'api_key' => env('GOOGLE_TRANSLATE_API_KEY'),
];
