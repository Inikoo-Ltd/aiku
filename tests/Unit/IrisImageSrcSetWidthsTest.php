<?php

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Helpers\ImgProxy\Image;
use App\Models\Helpers\Media;

$subject = new class () {
    use WithIrisImageVariants;

    public function srcSets(Media $media): array
    {
        return $this->getWidthSrcSets($media, [360, 720, 1440]);
    }
};

$media = function (?int $width, ?int $height): Media {
    $media = new class () extends Media {
        public function getImage(): Image
        {
            return new Image()->make('media/a.png');
        }
    };
    $media->width       = $width;
    $media->height      = $height;
    $media->is_animated = false;

    return $media;
};

beforeEach(function () {
    config(['img-proxy.formats' => ['png']]);
    GetImgProxyUrl::mock()->shouldReceive('handle')->andReturnUsing(fn (Image $image) => 'box-'.$image->getResize()['width']);
});

test('srcset advertises the real width of an original smaller than the boxes, once', function () use ($subject, $media) {
    expect($subject->srcSets($media(330, 330)))->toBe(['original' => 'box-360 330w']);
});

test('srcset advertises the fitted width of a tall original', function () use ($subject, $media) {
    expect($subject->srcSets($media(1000, 2000)))->toBe(['original' => 'box-360 180w, box-720 360w, box-1440 720w']);
});

test('srcset keeps the box widths for a large square original', function () use ($subject, $media) {
    expect($subject->srcSets($media(2000, 2000)))->toBe(['original' => 'box-360 360w, box-720 720w, box-1440 1440w']);
});

test('srcset keeps the box widths when the media has no dimensions', function () use ($subject, $media) {
    expect($subject->srcSets($media(null, null)))->toBe(['original' => 'box-360 360w, box-720 720w, box-1440 1440w']);
});

test('avif srcset entries carry the requested quality, the original ones do not', function () use ($media) {
    config(['img-proxy.formats' => ['png', 'avif']]);
    GetImgProxyUrl::clearFake();
    GetImgProxyUrl::mock()->shouldReceive('handle')->andReturnUsing(fn (Image $image) => ($image->getExtension() ?: 'png').'-q'.($image->getQuality() ?? 'default'));

    $subject = new class () {
        use WithIrisImageVariants;

        public function srcSets(Media $media): array
        {
            return $this->getWidthSrcSets($media, [1440], 70);
        }
    };

    expect($subject->srcSets($media(2000, 2000)))->toBe([
        'original' => 'png-qdefault 1440w',
        'avif'     => 'avif-q70 1440w',
    ]);
});

test('imgproxy url adds the quality option after the resize option', function () {
    config(['img-proxy.formats' => ['png', 'avif']]);

    expect(new GetImgProxyUrl()->getProcessingOptions(new Image()->make('media/a.png')->resize(1440, 1440)->extension('avif')->quality(70)))->toBe('rs::1440:1440::/q:70')
        ->and(new GetImgProxyUrl()->getProcessingOptions(new Image()->make('media/a.png')->resize(1440, 1440)))->toBe('rs::1440:1440::');
});
