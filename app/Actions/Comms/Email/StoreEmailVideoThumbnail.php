<?php

namespace App\Actions\Comms\Email;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Email;
use App\Models\Comms\EmailTemplate;
use App\Models\Helpers\Media;
use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class StoreEmailVideoThumbnail extends OrgAction
{
    use WithAttachMediaToModel;

    public const int OUTPUT_WIDTH = 1200;

    public const int DESIGN_WIDTH = 600;

    private const int SUPERSAMPLING = 4;

    private const array THUMBNAIL_HOSTS = ['img.youtube.com', 'i.ytimg.com', 'i.vimeocdn.com'];

    /**
     * Gmail swaps text play symbols for its own emoji and does not reliably show background images,
     * so the email gets one plain image: the video's sharpest thumbnail cropped to the chosen ratio
     * with the play button drawn into it.
     *
     * @param  array{video_url: string, thumbnail_url?: string|null, ratio: string, show_play_button: bool, play_button_size: int, play_button_color: string, play_icon_color: string}  $modelData
     */
    public function handle(Shop $shop, array $modelData): Media
    {
        $source = $this->loadSourceImage($modelData['video_url'], $modelData['thumbnail_url'] ?? null);

        [$ratioWidth, $ratioHeight] = array_map('intval', explode('-', $modelData['ratio']));
        $width  = self::OUTPUT_WIDTH;
        $height = (int) round($width * $ratioHeight / $ratioWidth);

        $canvas = imagecreatetruecolor($width, $height);
        $this->drawCover($canvas, $source, $width, $height);

        if ($modelData['show_play_button']) {
            $diameter = (int) round($modelData['play_button_size'] * $width / self::DESIGN_WIDTH);
            $this->drawPlayButton($canvas, $diameter, $modelData['play_button_color'], $modelData['play_icon_color']);
        }

        $path = tempnam(sys_get_temp_dir(), 'email-video-thumbnail-');

        try {
            imagejpeg($canvas, $path, 88);
            $checksum = md5_file($path);

            $media = Media::where('group_id', $shop->group_id)
                ->where('collection_name', UploadImagesToEmail::MEDIA_SCOPE)
                ->where('checksum', $checksum)
                ->first()
                ?? StoreMediaFromFile::run(
                    $shop,
                    [
                        'path'         => $path,
                        'originalName' => 'video-thumbnail.jpg',
                        'extension'    => 'jpg',
                        'checksum'     => $checksum,
                    ],
                    UploadImagesToEmail::MEDIA_SCOPE
                );
        } finally {
            File::delete($path);
        }

        if (!$shop->images()->where('media.id', $media->id)->wherePivot('scope', UploadImagesToEmail::MEDIA_SCOPE)->exists()) {
            $this->attachMediaToModel($shop, $media, UploadImagesToEmail::MEDIA_SCOPE);
        }

        return $media;
    }

    private function loadSourceImage(string $videoUrl, ?string $thumbnailUrl): GdImage
    {
        $candidates = $thumbnailUrl && !$this->isVideoPageUrl($thumbnailUrl)
            ? [$thumbnailUrl]
            : $this->videoThumbnailCandidates($videoUrl);

        foreach ($candidates as $candidate) {
            if (!$this->isAllowedThumbnailHost($candidate)) {
                continue;
            }
            $image = $this->downloadImage($candidate);
            if ($image && imagesx($image) > 120) {
                return $image;
            }
        }

        throw ValidationException::withMessages([
            'video_url' => __('The video thumbnail could not be loaded. Use a YouTube or Vimeo link, or upload a thumbnail.'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function videoThumbnailCandidates(string $videoUrl): array
    {
        if ($youtubeId = $this->youtubeVideoId($videoUrl)) {
            return array_map(
                fn (string $name) => "https://i.ytimg.com/vi/$youtubeId/$name.jpg",
                ['maxresdefault', 'sddefault', 'hqdefault']
            );
        }

        if ($vimeoId = $this->vimeoVideoId($videoUrl)) {
            try {
                $thumbnail = Http::timeout(10)
                    ->get('https://vimeo.com/api/oembed.json', ['url' => "https://vimeo.com/$vimeoId", 'width' => self::OUTPUT_WIDTH])
                    ->json('thumbnail_url');
            } catch (Throwable) {
                $thumbnail = null;
            }

            return $thumbnail ? [$thumbnail] : [];
        }

        return [];
    }

    private function downloadImage(string $url): ?GdImage
    {
        try {
            $response = Http::timeout(15)->withOptions(['allow_redirects' => false])->get($url);
            if (!$response->successful()) {
                return null;
            }

            return @imagecreatefromstring($response->body()) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function drawCover(GdImage $canvas, GdImage $source, int $width, int $height): void
    {
        $sourceWidth  = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale        = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth    = (int) round($width / $scale);
        $cropHeight   = (int) round($height / $scale);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            (int) round(($sourceWidth - $cropWidth) / 2),
            (int) round(($sourceHeight - $cropHeight) / 2),
            $width,
            $height,
            $cropWidth,
            $cropHeight
        );
    }

    private function drawPlayButton(GdImage $canvas, int $diameter, string $buttonColor, string $iconColor): void
    {
        $size   = $diameter * self::SUPERSAMPLING;
        $button = imagecreatetruecolor($size, $size);
        imagealphablending($button, false);
        imagesavealpha($button, true);
        imagefill($button, 0, 0, imagecolorallocatealpha($button, 0, 0, 0, 127));
        imagealphablending($button, true);

        imagefilledellipse($button, intdiv($size, 2), intdiv($size, 2), $size, $size, $this->allocateHex($button, $buttonColor));

        $triangleHeight = $size * 0.38;
        $triangleWidth  = $triangleHeight * 0.88;
        $left           = ($size - $triangleWidth) / 2 + $size * 0.04;
        $top            = ($size - $triangleHeight) / 2;
        imagefilledpolygon($button, [
            (int) round($left), (int) round($top),
            (int) round($left), (int) round($top + $triangleHeight),
            (int) round($left + $triangleWidth), (int) round($top + $triangleHeight / 2),
        ], $this->allocateHex($button, $iconColor));

        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas,
            $button,
            intdiv(imagesx($canvas) - $diameter, 2),
            intdiv(imagesy($canvas) - $diameter, 2),
            0,
            0,
            $diameter,
            $diameter,
            $size,
            $size
        );
    }

    private function allocateHex(GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return imagecolorallocate($image, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    private function isAllowedThumbnailHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && $host
            && (in_array($host, self::THUMBNAIL_HOSTS, true) || $host === parse_url((string) config('img-proxy.base_url'), PHP_URL_HOST));
    }

    private function isVideoPageUrl(string $url): bool
    {
        return $this->youtubeVideoId($url) !== null || $this->vimeoVideoId($url) !== null;
    }

    private function youtubeVideoId(string $url): ?string
    {
        return preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~', $url, $matches) ? $matches[1] : null;
    }

    private function vimeoVideoId(string $url): ?string
    {
        return preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $matches) ? $matches[1] : null;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $shopId = $this->shop->id;

        return $request->user()->authTo([
            "shop-admin.$shopId",
            "marketing.$shopId.edit",
            "supervisor-marketing.$shopId",
            "crm.$shopId.edit",
            "crm.$shopId.prospects.edit",
            "web.$shopId.edit",
        ]);
    }

    public function rules(): array
    {
        return [
            'video_url'         => ['required', 'string', 'max:2048'],
            'thumbnail_url'     => ['sometimes', 'nullable', 'string', 'max:2048'],
            'ratio'             => ['required', 'in:16-9,4-3,1-1'],
            'show_play_button'  => ['required', 'boolean'],
            'play_button_size'  => ['required', 'integer', 'min:24', 'max:160'],
            'play_button_color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'play_icon_color'   => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ];
    }

    public function asController(Email $email, ActionRequest $request): Media
    {
        abort_unless($email->shop, 404);

        $this->initialisationFromShop($email->shop, $request);

        return $this->handle($email->shop, $this->validatedData);
    }

    public function inEmailTemplate(EmailTemplate $emailTemplate, ActionRequest $request): Media
    {
        abort_unless($emailTemplate->shop, 404);

        $this->initialisationFromShop($emailTemplate->shop, $request);

        return $this->handle($emailTemplate->shop, $this->validatedData);
    }

    public function jsonResponse(Media $media): ImageResource
    {
        return ImageResource::make($media);
    }
}
