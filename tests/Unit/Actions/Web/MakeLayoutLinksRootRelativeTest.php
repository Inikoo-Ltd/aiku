<?php

/*
 * Created: Tue, 22 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Web\MakeLayoutLinksRootRelative;

test('it anchors a bare relative href to the site root', function (string $href, string $expected) {
    expect(MakeLayoutLinksRootRelative::run([
        'name' => '<p><a href="'.$href.'" target="_self">Link</a></p>',
    ]))->toBe([
        'name' => '<p><a href="'.$expected.'" target="_self">Link</a></p>',
    ]);
})->with([
    'page'              => ['faq', '/faq'],
    'page with hyphens' => ['terms-and-conditions', '/terms-and-conditions'],
    'nested page'       => ['guides/packaging', '/guides/packaging'],
]);

test('it leaves every other kind of href untouched', function (string $href) {
    expect(MakeLayoutLinksRootRelative::run([
        'name' => '<a href="'.$href.'">Link</a>',
    ]))->toBe([
        'name' => '<a href="'.$href.'">Link</a>',
    ]);
})->with([
    'root relative'     => ['/faq'],
    'absolute'          => ['https://www.example.com/shipping'],
    'protocol relative' => ['//cdn.example.com/a.pdf'],
    'mailto'            => ['mailto:hello@example.com'],
    'tel'               => ['tel:+421335586076'],
    'anchor'            => ['#subscribe'],
    'query'             => ['?page=2'],
    'empty'             => [''],
]);

test('it walks the whole layout and keeps non string values as they are', function () {
    $layout = [
        'status' => 'active',
        'data'   => [
            'fieldValue' => [
                'columns' => [
                    'column_1' => [
                        'data' => [
                            ['name' => '<a href="faq">FAQ</a>'],
                            ['name' => '<a href=\'contact\'>Contact</a>'],
                        ],
                    ],
                ],
                'subscribe' => ['is_show' => true, 'order' => 3],
            ],
        ],
    ];

    expect(MakeLayoutLinksRootRelative::run($layout))->toBe([
        'status' => 'active',
        'data'   => [
            'fieldValue' => [
                'columns' => [
                    'column_1' => [
                        'data' => [
                            ['name' => '<a href="/faq">FAQ</a>'],
                            ['name' => '<a href=\'/contact\'>Contact</a>'],
                        ],
                    ],
                ],
                'subscribe' => ['is_show' => true, 'order' => 3],
            ],
        ],
    ]);
});
