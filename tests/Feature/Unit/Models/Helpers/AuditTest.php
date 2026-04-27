<?php

use App\Models\Helpers\Audit;

it('merges into recent updated audit and does not create a second row', function () {
    $auditableId = random_int(100000, 999999);

    $firstAudit = Audit::create([
        'auditable_type' => 'App\\Models\\Catalogue\\Product',
        'auditable_id' => $auditableId,
        'event' => 'updated',
        'user_type' => 'App\\Models\\SysAdmin\\User',
        'user_id' => 1,
        'tags' => 'alpha, beta,, gamma ',
        'old_values' => ['name' => 'before'],
        'new_values' => ['name' => 'after'],
    ]);

    Audit::create([
        'auditable_type' => 'App\\Models\\Catalogue\\Product',
        'auditable_id' => $auditableId,
        'event' => 'updated',
        'user_type' => 'App\\Models\\SysAdmin\\User',
        'user_id' => 1,
        'tags' => 'delta',
        'old_values' => ['price' => 10],
        'new_values' => ['price' => 15],
    ]);

    $firstAudit->refresh();

    expect(Audit::query()->where('auditable_id', $auditableId)->count())->toBe(1)
        ->and($firstAudit->old_values)->toBe([
            'name' => 'before',
            'price' => 10,
        ])
        ->and($firstAudit->new_values)->toBe([
            'name' => 'after',
            'price' => 15,
        ])
        ->and(json_decode($firstAudit->tags, true))->toBe(['alpha', 'beta', 'gamma']);
});

it('deletes recent updated audit when merged diff becomes empty', function () {
    $auditableId = random_int(100000, 999999);

    Audit::create([
        'auditable_type' => 'App\\Models\\Catalogue\\Product',
        'auditable_id' => $auditableId,
        'event' => 'updated',
        'user_type' => 'App\\Models\\SysAdmin\\User',
        'user_id' => 2,
        'tags' => 'audit',
        'old_values' => ['quantity' => 5],
        'new_values' => ['quantity' => 7],
    ]);

    Audit::create([
        'auditable_type' => 'App\\Models\\Catalogue\\Product',
        'auditable_id' => $auditableId,
        'event' => 'updated',
        'user_type' => 'App\\Models\\SysAdmin\\User',
        'user_id' => 2,
        'tags' => 'audit',
        'old_values' => ['quantity' => 7],
        'new_values' => ['quantity' => 5],
    ]);

    expect(Audit::query()->where('auditable_id', $auditableId)->exists())->toBeFalse();
});

it('handles null incoming new values without failing', function () {
    $auditableId = random_int(100000, 999999);

    $firstAudit = Audit::create([
        'auditable_type' => 'App\\Models\\Catalogue\\Product',
        'auditable_id' => $auditableId,
        'event' => 'updated',
        'user_type' => 'App\\Models\\SysAdmin\\User',
        'user_id' => 3,
        'tags' => 'safety',
        'old_values' => ['sku' => 'A1'],
        'new_values' => ['sku' => 'B1'],
    ]);

    Audit::create([
        'auditable_type' => 'App\\Models\\Catalogue\\Product',
        'auditable_id' => $auditableId,
        'event' => 'updated',
        'user_type' => 'App\\Models\\SysAdmin\\User',
        'user_id' => 3,
        'tags' => 'safety',
        'old_values' => null,
        'new_values' => null,
    ]);

    $firstAudit->refresh();

    expect(Audit::query()->where('auditable_id', $auditableId)->count())->toBe(1)
        ->and($firstAudit->old_values)->toBe(['sku' => 'A1'])
        ->and($firstAudit->new_values)->toBe(['sku' => 'B1']);
});
