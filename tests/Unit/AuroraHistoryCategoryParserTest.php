<?php

use App\Transfers\Aurora\History\Parsers\ParseCategoryHistory;

function categoryRow(array $overrides = []): object
{
    return (object) array_merge([
        'Direct Object'     => 'Category',
        'Indirect Object'   => null,
        'Action'            => null,
        'History Abstract'  => '',
        'History Details'   => '',
        'aikuCategoryScope'   => 'Product',
        'aikuCategorySubject' => 'Category',
    ], $overrides);
}

test('Product scope + Category subject created is imported', function () {
    $row = categoryRow(['Action' => 'created', 'History Abstract' => 'Category created']);

    $result = ParseCategoryHistory::classify($row);

    expect($result)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'ProductCategory']);
});

test('Product scope + Product subject is skipped', function () {
    $row = categoryRow(['aikuCategorySubject' => 'Product', 'Action' => 'edited']);

    $result = ParseCategoryHistory::classify($row);

    expect($result['handling'])->toBe('skip');
});

test('Part scope + Part subject is imported as StockFamily', function () {
    $row = categoryRow([
        'aikuCategoryScope'   => 'Part',
        'aikuCategorySubject' => 'Part',
        'Action'              => 'created',
    ]);

    $result = ParseCategoryHistory::classify($row);

    expect($result)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'StockFamily']);
});

test('orphan category with null scope is skipped', function () {
    $row = categoryRow(['aikuCategoryScope' => null]);

    $result = ParseCategoryHistory::classify($row);

    expect($result['handling'])->toBe('skip');
});

test('Customer scope is skipped', function () {
    $row = categoryRow(['aikuCategoryScope' => 'Customer', 'aikuCategorySubject' => 'Customer']);

    $result = ParseCategoryHistory::classify($row);

    expect($result['handling'])->toBe('skip');
});

test('blank Action with mapped Indirect Object is treated as edited', function () {
    $row = categoryRow(['Action' => '', 'Indirect Object' => 'Category Code']);

    $result = ParseCategoryHistory::classify($row);

    expect($result)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'code', 'auditable_type' => 'ProductCategory']);
});

test('deleted action is skipped', function () {
    $row = categoryRow(['Action' => 'deleted']);

    $result = ParseCategoryHistory::classify($row);

    expect($result['handling'])->toBe('skip');
});

test('associated action is skipped', function () {
    $row = categoryRow(['Action' => 'associated']);

    $result = ParseCategoryHistory::classify($row);

    expect($result['handling'])->toBe('skip');
});

test('abstract era a arrow syntax extracts old and new', function () {
    $row = categoryRow([
        'Action'           => 'edited',
        'Indirect Object'  => 'Category Label',
        'History Abstract' => 'Category label changed (Old Name→New Name)',
    ]);

    $classified = ParseCategoryHistory::classify($row);
    $values     = ParseCategoryHistory::extractValues($row, $classified['event'], $classified['field'], $classified['auditable_type']);

    expect($values)->toBe([
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'New Name'],
        'data'       => [],
    ]);
});

test('abstract era a rarr entity extracts old and new', function () {
    $row = categoryRow([
        'Action'           => 'edited',
        'Indirect Object'  => 'Category Label',
        'History Abstract' => 'Category label changed (Old Name&rarr;New Name)',
    ]);

    $classified = ParseCategoryHistory::classify($row);
    $values     = ParseCategoryHistory::extractValues($row, $classified['event'], $classified['field'], $classified['auditable_type']);

    expect($values['old_values'])->toBe(['name' => 'Old Name']);
    expect($values['new_values'])->toBe(['name' => 'New Name']);
});

test('abstract era b was changed to extracts new only', function () {
    $row = categoryRow([
        'Action'           => 'edited',
        'Indirect Object'  => 'Category Sticky Note',
        'History Abstract' => "Category's sticky note was changed to Discontinued",
    ]);

    $classified = ParseCategoryHistory::classify($row);
    $values     = ParseCategoryHistory::extractValues($row, $classified['event'], $classified['field'], $classified['auditable_type']);

    expect($values)->toBe([
        'old_values' => [],
        'new_values' => ['notes' => 'Discontinued'],
        'data'       => [],
    ]);
});

test('abstract era c double space arrow entity extracts new only', function () {
    $row = categoryRow([
        'Action'           => 'edited',
        'Indirect Object'  => 'Part Category Status',
        'History Abstract' => 'Category &rArr;  status was changed to Discontinuing',
    ]);

    $classified = ParseCategoryHistory::classify($row);
    $values     = ParseCategoryHistory::extractValues($row, $classified['event'], $classified['field'], $classified['auditable_type']);

    expect($values)->toBe([
        'old_values' => [],
        'new_values' => ['state' => 'Discontinuing'],
        'data'       => [],
    ]);
});

test('status value passes through raw', function () {
    $row = categoryRow([
        'Action'           => 'edited',
        'Indirect Object'  => 'Part Category Status Including Parts',
        'History Abstract' => "Category's status was changed to Discontinued",
    ]);

    $classified = ParseCategoryHistory::classify($row);
    $values     = ParseCategoryHistory::extractValues($row, $classified['event'], $classified['field'], $classified['auditable_type']);

    expect($values['new_values'])->toBe(['state' => 'Discontinued']);
});

test('Family created extracts name and code', function () {
    $row = categoryRow([
        'Direct Object'    => 'Family',
        'Action'           => 'created',
        'History Details'  => 'Family Widgets (WDG) Created',
    ]);

    $classified = ParseCategoryHistory::classify($row);

    expect($classified)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'ProductCategory']);

    $values = ParseCategoryHistory::extractValues($row, $classified['event'], $classified['field'], $classified['auditable_type']);

    expect($values)->toBe([
        'old_values' => [],
        'new_values' => ['name' => 'Widgets', 'code' => 'WDG'],
        'data'       => [],
    ]);
});

test('Family Description plain sentence extracts old and new', function () {
    $row = categoryRow([
        'Direct Object'    => 'Family',
        'Action'           => 'edited',
        'Indirect Object'  => 'Product Family Description',
        'History Details'  => 'Product Family Description changed from "" to "New description"',
    ]);

    $classified = ParseCategoryHistory::classify($row);
    $values     = ParseCategoryHistory::extractValues($row, $classified['event'], $classified['field'], $classified['auditable_type']);

    expect($values)->toBe([
        'old_values' => ['description' => ''],
        'new_values' => ['description' => 'New description'],
        'data'       => [],
    ]);
});

test('Department created extracts name and code', function () {
    $row = categoryRow([
        'Direct Object'    => 'Department',
        'Action'           => 'created',
        'History Details'  => 'Department Electronics (ELE) Created',
    ]);

    $classified = ParseCategoryHistory::classify($row);

    expect($classified)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'ProductCategory']);

    $values = ParseCategoryHistory::extractValues($row, $classified['event'], $classified['field'], $classified['auditable_type']);

    expect($values)->toBe([
        'old_values' => [],
        'new_values' => ['name' => 'Electronics', 'code' => 'ELE'],
        'data'       => [],
    ]);
});

test('no-value X Changed abstract returns empty arrays', function () {
    $row = categoryRow([
        'Direct Object'    => 'Family',
        'Action'           => 'edited',
        'Indirect Object'  => 'Product Family Name',
        'History Details'  => '',
        'History Abstract' => 'Name Changed',
    ]);

    $classified = ParseCategoryHistory::classify($row);
    $values     = ParseCategoryHistory::extractValues($row, $classified['event'], $classified['field'], $classified['auditable_type']);

    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => []]);
});
