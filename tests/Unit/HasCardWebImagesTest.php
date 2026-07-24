<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Http\Resources\Traits\HasCardWebImages;

$subject = new class () {
    use HasCardWebImages;

    public function cardWebImages(mixed $webImages): array
    {
        return $this->getCardWebImages($webImages);
    }
};

$gallery = fn (string $src) => ['original' => $src, 'webp' => "$src.webp"];

test('secondary falls back to first non-main image in all', function () use ($subject, $gallery) {
    $webImages = [
        'main' => ['gallery' => $gallery('main.jpg')],
        'all'  => [
            ['gallery' => $gallery('main.jpg')],
            ['gallery' => $gallery('second.jpg')],
        ],
    ];

    $result = $subject->cardWebImages($webImages);

    expect($result['secondary']['gallery']['original'])->toBe('second.jpg');
});

test('secondary omitted when all only contains the main image', function () use ($subject, $gallery) {
    $result = $subject->cardWebImages([
        'main' => ['gallery' => $gallery('main.jpg')],
        'all'  => [['gallery' => $gallery('main.jpg')]],
    ]);

    expect($result)->not->toHaveKey('secondary');
});

test('explicit secondary wins over fallback', function () use ($subject, $gallery) {
    $result = $subject->cardWebImages([
        'main'      => ['gallery' => $gallery('main.jpg')],
        'secondary' => ['gallery' => $gallery('front.jpg')],
        'all'       => [['gallery' => $gallery('other.jpg')]],
    ]);

    expect($result['secondary']['gallery']['original'])->toBe('front.jpg');
});
