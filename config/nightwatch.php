<?php

return [
    'enabled' => env('NIGHTWATCH_ENABLED', true) && env('NIGHTWATCH_TOKEN') !== null,
];
