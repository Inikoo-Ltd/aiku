<?php

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Actions\Web\WebBlock\Iris\GetIrisWebBlockCtaImageBackground;
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

test('cta image background asks for its avif at the banner quality, flat or per screen', function () use ($media) {
    config(['img-proxy.formats' => ['png', 'avif']]);
    GetImgProxyUrl::clearFake();
    GetImgProxyUrl::mock()->shouldReceive('handle')->andReturnUsing(fn (Image $image) => ($image->getExtension() ?: 'png').'-q'.($image->getQuality() ?? 'default'));

    $subject = new class ($media(3600, 1200)) extends GetIrisWebBlockCtaImageBackground {
        public function __construct(private readonly Media $media)
        {
        }

        protected function findMediaFromImgProxyUrl(?string $url): ?Media
        {
            return $url ? $this->media : null;
        }
    };

    $block = fn (array $source) => ['web_block' => ['layout' => ['data' => ['fieldValue' => ['image' => ['source' => $source]]]]]];
    $saved = ['original' => 'saved-original', 'avif' => 'saved-avif', 'webp' => 'saved-webp'];

    expect($subject->handle($block($saved)))->toBe($block(['original' => 'saved-original', 'avif' => 'avif-q70', 'webp' => 'saved-webp']))
        ->and($subject->handle($block(['desktop' => $saved, 'mobile' => null])))->toBe($block(['desktop' => ['original' => 'saved-original', 'avif' => 'avif-q70', 'webp' => 'saved-webp'], 'mobile' => null]));
});

test('website image uploads refuse an animated gif over the size cap and keep small or still ones', function () {
    $uploader = new class () {
        use App\Actions\Web\WithUploadWebImage;
    };
    $gif = function (int $frames, int $kb): Illuminate\Http\UploadedFile {
        $bytes = 'GIF89a'.str_repeat("\x00\x21\xF9\x04\x00\x00\x00\x00\x00\x2C", $frames);
        $path  = tempnam(sys_get_temp_dir(), 'gif');
        file_put_contents($path, str_pad($bytes, $kb * 1024, "\x00"));

        return new Illuminate\Http\UploadedFile($path, 'banner.gif', 'image/gif', null, true);
    };
    $passes = fn (Illuminate\Http\UploadedFile $file) => Illuminate\Support\Facades\Validator::make(['images' => [$file]], $uploader->rules())->passes();

    expect($passes($gif(231, 460)))->toBeFalse()
        ->and($passes($gif(20, 200)))->toBeTrue()
        ->and($passes($gif(1, 460)))->toBeTrue();
});
