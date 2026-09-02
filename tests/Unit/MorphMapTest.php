<?php

use Illuminate\Database\Eloquent\Relations\Relation;

it('maps every audited model to its alias', function () {
    $unmapped = [];

    foreach (glob(app_path('Models/*/*.php')) as $file) {
        $class = 'App\\Models\\'.str_replace('/', '\\', substr($file, strlen(app_path('Models/')), -4));

        if (!class_exists($class) || !is_subclass_of($class, \OwenIt\Auditing\Contracts\Auditable::class)) {
            continue;
        }

        if ((Relation::morphMap()[class_basename($class)] ?? null) !== $class) {
            $unmapped[] = $class;
        }
    }

    expect($unmapped)->toBe([]);
});
