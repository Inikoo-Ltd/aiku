<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Mcp\Servers\AikuServer;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

test('every registered tool builds a valid schema', function () {
    $tools = (new ReflectionClass(AikuServer::class))->getDefaultProperties()['tools'];

    expect($tools)->not->toBeEmpty();

    $schema   = new JsonSchemaTypeFactory();
    $failures = [];

    foreach ($tools as $toolClass) {
        try {
            $definition = (new $toolClass())->schema($schema);
            expect($definition)->toBeArray();
        } catch (Throwable $e) {
            $failures[] = class_basename($toolClass).': '.$e->getMessage();
        }
    }

    expect($failures)->toBe([], 'a tool with a broken schema breaks tools/list for the whole server');
});
