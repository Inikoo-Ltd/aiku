<?php

use App\Actions\Dispatching\DeliveryNoteLeaflet\WithPrintDeliveryNoteLeaflet;
use App\Actions\Traits\WithPrintNode;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use Illuminate\Validation\ValidationException;

function leafletImagePdf(string $bytes): string
{
    return (new class () {
        use WithPrintNode;
        use WithPrintDeliveryNoteLeaflet;

        public function build(string $bytes): string
        {
            return $this->imageLeafletPdf($bytes);
        }
    })->build($bytes);
}

function noisyLeafletPng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            imagesetpixel($image, $x, $y, random_int(0, 0xFFFFFF));
        }
    }
    ob_start();
    imagepng($image);

    return ob_get_clean();
}

test('an image leaflet becomes one A6 page in its own orientation', function (int $width, int $height, string $mediaBox) {
    $png = noisyLeafletPng($width, $height);
    $pdf = leafletImagePdf($png);

    expect($pdf)->toStartWith('%PDF')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBe(1)
        ->and($pdf)->toMatch($mediaBox);
})->with([
    'portrait'  => [700, 987, '/MediaBox\s*\[0 0 297\.6\d* 419\.5\d*\]/'],
    'landscape' => [987, 700, '/MediaBox\s*\[0 0 419\.5\d* 297\.6\d*\]/'],
]);

test('an unreadable leaflet image is refused with a message', function () {
    leafletImagePdf('not an image');
})->throws(ValidationException::class);

test('the leaflet job names the A6 paper of the printer', function (array $papers, ?string $expected) {
    $printer = new class () {
        use WithPrintNode;
        use WithPrintDeliveryNoteLeaflet;

        public function paper(array $papers): ?string
        {
            return $this->a6PaperName($papers);
        }
    };

    expect($printer->paper($papers))->toBe($expected);
})->with([
    'epson'        => [['A4 210 x 297 mm' => [2100, 2970], 'A6 105 x 148 mm' => [1050, 1480]], 'A6 105 x 148 mm'],
    'no A6'        => [['A4' => [2100, 2970], 'Custom' => [null, null]], null],
    'sizes not reported' => [['A4' => [null, null], 'A6' => [null, null]], 'A6'],
    'no papers'    => [[], null],
]);

test('a printer without A6 gets its nearest paper', function (array $papers, ?string $expected) {
    $printer = new class () {
        use WithPrintNode;
        use WithPrintDeliveryNoteLeaflet;

        public function paper(array $papers): ?string
        {
            return $this->nearA6PaperName($papers);
        }
    };

    expect($printer->paper($papers))->toBe($expected);
})->with([
    'postcard'     => [['102 x 152 mm (4 x 6 in)' => [1016, 1524], 'A4' => [2100, 2970], 'Postcard' => [999, 1480]], 'Postcard'],
    'only 4 x 6'   => [['102 x 152 mm (4 x 6 in)' => [1016, 1524], 'A4' => [2100, 2970]], '102 x 152 mm (4 x 6 in)'],
    'nothing near' => [['A4' => [2100, 2970], 'A5' => [1480, 2100], 'Custom' => [null, null]], null],
]);

function leafletPdfOf(DeliveryNoteLeaflet $deliveryNoteLeaflet): ?string
{
    return (new class () {
        use WithPrintDeliveryNoteLeaflet;

        public function build(DeliveryNoteLeaflet $deliveryNoteLeaflet): ?string
        {
            return $this->leafletPdf($deliveryNoteLeaflet);
        }
    })->build($deliveryNoteLeaflet);
}

test('a personalised message leaflet is written on one A6 page', function () {
    $pdf = leafletPdfOf(new DeliveryNoteLeaflet(['message' => "Thank you for your order\nSee you soon"]));

    expect($pdf)->toStartWith('%PDF')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBe(1)
        ->and($pdf)->toMatch('/\/MediaBox\s*\[0 0 297\.6\d* 419\.5\d*\]/');
});

test('a leaflet with neither file nor message has nothing to print', function () {
    expect(leafletPdfOf(new DeliveryNoteLeaflet()))->toBeNull();
});

test('a personalised message prints its emoji as colour pictures', function () {
    $pdf = leafletPdfOf(new DeliveryNoteLeaflet(['message' => "Thank you for shopping 🌿 please leave feedback 💚"]));

    expect($pdf)->toStartWith('%PDF')
        ->and(preg_match_all('/\/Subtype\s*\/Image/', $pdf))->toBeGreaterThanOrEqual(2);
});

test('flags, skin tones, joined emoji and keycaps each become one colour picture', function () {
    $html = (new class () {
        use WithPrintDeliveryNoteLeaflet;

        public function html(string $message): string
        {
            return $this->personalisedMessageHtml($message);
        }
    })->html("From 🇬🇧 🏴󠁧󠁢󠁳󠁣󠁴󠁿 👍🏽 👩‍💻 ❤️‍🔥 1️⃣ ❤ 💚💚 © 2026\nSee you <soon>");

    preg_match_all('/<img src="([^"]+)"/', $html, $pictures);

    expect(array_map('basename', $pictures[1]))->toBe([
        'emoji_u1f1ec_1f1e7.png',
        'emoji_u1f3f4_e0067_e0062_e0073_e0063_e0074_e007f.png',
        'emoji_u1f44d_1f3fd.png',
        'emoji_u1f469_200d_1f4bb.png',
        'emoji_u2764_200d_1f525.png',
        'emoji_u0031_20e3.png',
        'emoji_u2764.png',
        'emoji_u1f49a.png',
        'emoji_u1f49a.png',
    ])
        ->and($pictures[1])->each->toBeFile()
        ->and($html)->toContain('<span style="font-family: notoemoji;">©</span>')
        ->and($html)->toContain('<br />')
        ->and($html)->toContain('&lt;soon&gt;');
});
