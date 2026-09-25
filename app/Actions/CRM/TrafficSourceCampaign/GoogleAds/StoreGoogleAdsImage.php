<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * Takes an image uploaded on the campaign form into Aiku's own media, so it can be chosen for an ad.
 *
 * It lands in Aiku first rather than going straight to Google because an ad image is worth keeping:
 * the next campaign can reuse it, the upload to Google is recorded against it so it is only sent once,
 * and an image that only ever existed inside Google is one nobody here can find again.
 *
 * Nothing reaches Google at this point. That happens when the campaign is created, for the images
 * actually chosen, which is what stops a browse-and-discard from filling the ad account with strays.
 */
class StoreGoogleAdsImage extends OrgAction
{
    /**
     * Google refuses an image asset over 5MB, so anything larger is turned away here where it can be
     * said plainly rather than at the end of building a campaign.
     */
    private const int MAX_KILOBYTES = 5120;

    /**
     * @return array{id: int, name: string, thumbnail: string}
     */
    public function handle(Shop $shop, array $modelData): array
    {
        /** @var UploadedFile $file */
        $file = Arr::get($modelData, 'image');

        $media = StoreMediaFromFile::run(
            $shop,
            [
                'path'         => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'extension'    => $file->getClientOriginalExtension(),
                'checksum'     => md5_file($file->getPathName()),
            ],
            'google_ads'
        );

        return $this->shape($media);
    }

    /**
     * @return array{id: int, name: string, thumbnail: string}
     */
    public static function shape(Media $media): array
    {
        return [
            'id'        => $media->id,
            'name'      => $media->name ?: $media->file_name,
            'thumbnail' => GetImgProxyUrl::run($media->getImage()->resize(160, 160)),
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function jsonResponse(array $image): array
    {
        return $image;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES, 'dimensions:min_width=128,min_height=128'],
        ];
    }
}
