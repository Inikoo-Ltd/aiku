<?php

return [
    'enabled' => env('NIGHTWATCH_ENABLED', true) && env('NIGHTOWL_TOKEN') !== null,
];
