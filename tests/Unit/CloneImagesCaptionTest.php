<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sept 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Concerns\CanCloneImages;

$subject = new class () {
    use CanCloneImages;

    public function keep(?string $sourceCaption, ?object $currentPivot): array
    {
        return $this->captionToKeep($sourceCaption, $currentPivot);
    }
};

$pivot = fn (?string $caption, ?string $sourceCaption, bool $reviewed = false) => (object)[
    'caption'             => $caption,
    'source_caption'      => $sourceCaption,
    'is_caption_reviewed' => $reviewed,
];

test('a product with no image row yet takes the source caption', function () use ($subject) {
    expect($subject->keep('Red ceramic mug', null))->toBe([
        'caption'             => 'Red ceramic mug',
        'source_caption'      => null,
        'is_caption_reviewed' => false,
    ]);
});

test('a translated caption survives a re-clone of the same source caption', function () use ($subject, $pivot) {
    $result = $subject->keep('Red ceramic mug', $pivot('Rote Keramiktasse', 'Red ceramic mug'));

    expect($result['caption'])->toBe('Rote Keramiktasse')
        ->and($result['source_caption'])->toBe('Red ceramic mug');
});

test('a changed source caption drops the stale translation so it is translated again', function () use ($subject, $pivot) {
    $result = $subject->keep('Blue ceramic mug', $pivot('Rote Keramiktasse', 'Red ceramic mug'));

    expect($result['caption'])->toBe('Blue ceramic mug')
        ->and($result['source_caption'])->toBeNull();
});

test('a hand edited caption survives a changed source caption', function () use ($subject, $pivot) {
    $result = $subject->keep('Blue ceramic mug', $pivot('Blaue Tasse, handbemalt', 'Red ceramic mug', true));

    expect($result['caption'])->toBe('Blaue Tasse, handbemalt')
        ->and($result['is_caption_reviewed'])->toBeTrue();
});

test('an untranslated caption is not mistaken for a translated one', function () use ($subject, $pivot) {
    $result = $subject->keep('Red ceramic mug', $pivot('Old caption', null));

    expect($result['caption'])->toBe('Red ceramic mug');
});
