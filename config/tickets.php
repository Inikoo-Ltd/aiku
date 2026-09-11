<?php

return [
    'read_only_types' => env('TICKETS_READ_ONLY')
        ? ['help', 'customer']
        : array_values(array_filter(array_map('trim', explode(',', (string) env('TICKETS_READ_ONLY_TYPES', ''))))),
];
