<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Models\Catalogue\Shop;
use App\Models\CRM\GoogleAdsMediaAsset;
use App\Models\Helpers\Media;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Puts one of Aiku's own images into a shop's Google Ads account as a reusable image asset.
 *
 * Google copies the bytes into its own library and hands back a resource name; it never recognises an
 * image it already holds. Uploading the same product shot for a second campaign would therefore leave
 * two identical assets with the performance figures split between them, so every upload is recorded
 * against the shop and the media row and the second request returns the first upload.
 *
 * The image goes up at its own aspect ratio, never cropped to fit a slot. Which slot it can fill is
 * decided afterwards from the dimensions recorded here, because cropping somebody's product photo to
 * a shape they did not choose is the sort of thing that is only noticed once the ad is running.
 */
class UploadGoogleAdsImageAsset
{
    use AsAction;
    use WithGoogleAdsWriteErrors;

    /**
     * Google refuses an image asset over 5MB and anything under 128 pixels on a side. The long edge is
     * capped well inside that: the largest slot Google renders is 1200 across, and a 1600 pixel JPEG
     * of a product is comfortably under a megabyte.
     */
    private const int MAX_EDGE = 1600;

    private const int MIN_EDGE = 128;

    private const int MAX_BYTES = 5_242_880;

    /**
     * @return array{resource_name: string, width: int|null, height: int|null, reused: bool}
     * @throws GoogleAdsException
     */
    public function handle(Shop $shop, Media $media): array
    {
        $existing = GoogleAdsMediaAsset::where('shop_id', $shop->id)->where('media_id', $media->id)->first();

        if ($existing) {
            return [
                'resource_name' => $existing->asset_resource_name,
                'width'         => $existing->width,
                'height'        => $existing->height,
                'reused'        => true,
            ];
        }

        $client = GoogleAdsClient::forShop($shop);

        if (!$client) {
            throw ValidationException::withMessages([
                'media' => GoogleAdsClient::unreachableReason($shop) ?? __('Google Ads is not configured for this shop.'),
            ]);
        }

        [$bytes, $width, $height] = $this->render($media);

        try {
            $results = $client->mutate('assets', [[
                'create' => [
                    /* Named for the image it came from, so somebody looking at the asset library in
                       Google Ads can find the same picture in Aiku. Google requires the name to be
                       unique within the account, hence the media id. */
                    'name'       => mb_substr($media->name ?: $media->file_name ?: __('Aiku image'), 0, 120).' #'.$media->id,
                    'type'       => 'IMAGE',
                    'imageAsset' => ['data' => base64_encode($bytes)],
                ],
            ]]);
        } catch (GoogleAdsException $exception) {
            $this->refuse($exception, 'media');
        }

        $resourceName = (string) Arr::get($results, '0.resourceName');

        if ($resourceName === '') {
            throw new RuntimeException('Google accepted the image but returned no asset resource name.');
        }

        GoogleAdsMediaAsset::create([
            'shop_id'             => $shop->id,
            'media_id'            => $media->id,
            'asset_resource_name' => $resourceName,
            'width'               => $width,
            'height'              => $height,
        ]);

        return ['resource_name' => $resourceName, 'width' => $width, 'height' => $height, 'reused' => false];
    }

    /**
     * The image as bytes, fitted inside the size cap without cropping or stretching, with the size it
     * actually came out at rather than the size that was asked for.
     *
     * Read back through imgproxy rather than off the media disk, as Media::getBase64Image already
     * does: it is the one path that works whether the original sits on local disk or in object
     * storage, and it hands back a web format at a sane size instead of a 40 megapixel original.
     * It does mean this needs `IMGPROXY_URL` to be reachable from the application itself, which on a
     * developer machine pointing at `localhost` it is not.
     *
     * @return array{0: string, 1: int|null, 2: int|null}
     */
    private function render(Media $media): array
    {
        $url = GetImgProxyUrl::run($media->getImage()->resize(self::MAX_EDGE, self::MAX_EDGE));

        try {
            $response = Http::timeout(30)->get($url);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'media' => __('That image could not be fetched for upload: :reason', ['reason' => $exception->getMessage()]),
            ]);
        }

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'media' => __('That image could not be fetched for upload, the image server answered :status.', [
                    'status' => $response->status(),
                ]),
            ]);
        }

        $bytes = $response->body();

        if ($bytes === '') {
            throw ValidationException::withMessages([
                'media' => __('That image came back empty, so it was not sent to Google.'),
            ]);
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'media' => __('That image is larger than the 5MB Google accepts.'),
            ]);
        }

        $size   = @getimagesizefromstring($bytes);
        $width  = $size ? (int) $size[0] : null;
        $height = $size ? (int) $size[1] : null;

        if ($width !== null && ($width < self::MIN_EDGE || $height < self::MIN_EDGE)) {
            throw ValidationException::withMessages([
                'media' => __('Google needs at least 128 pixels on each side; that image is :width by :height.', [
                    'width'  => $width,
                    'height' => $height,
                ]),
            ]);
        }

        return [$bytes, $width, $height];
    }

    /**
     * Which of Google's image slots an uploaded image can fill, from its shape. Google allows a small
     * tolerance either side of each ratio rather than demanding the exact pixel count.
     *
     * @return array<int, string>
     */
    public static function fieldTypesFor(?int $width, ?int $height): array
    {
        if (!$width || !$height) {
            return [];
        }

        $ratio = $width / $height;
        $near  = fn (float $target) => abs($ratio - $target) <= $target * 0.02;

        return array_values(array_filter([
            $near(1.91) ? 'MARKETING_IMAGE' : null,
            $near(1.0) ? 'SQUARE_MARKETING_IMAGE' : null,
            $near(1.0) ? 'LOGO' : null,
            $near(0.8) ? 'PORTRAIT_MARKETING_IMAGE' : null,
            $near(4.0) ? 'LANDSCAPE_LOGO' : null,
        ]));
    }
}
