<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Language
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of language is your base language.
    | The base language select will be created as json file when scanning.
    |
    */

    'base_language' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Functions
    |--------------------------------------------------------------------------
    |
    | Here you define an array describing all the function names to scan files for.
    |
    */

    'functions' => ['__','t','trans','trans_choice','wtrans','ctrans'],

    /*
    |--------------------------------------------------------------------------
    | Excluded directories
    |--------------------------------------------------------------------------
    |
    | Here you define which directories are excluded from scan.
    |
    */

    'excluded_directories' => ['vendor', 'storage', 'public', 'node_modules'],

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    |
    | Here you define an array describing all the file extensions to scan through.
    |
    */

    'extensions' => ['*.php', '*.vue'],

];
