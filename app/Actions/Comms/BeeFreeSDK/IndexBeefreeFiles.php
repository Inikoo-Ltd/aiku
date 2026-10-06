<?php

/*
 * Author: eka yudinata <ekayudintha@gmail.com>
 * Created: Tue, 06 Oct 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\BeeFreeSDK;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingAuthorisation;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IndexBeefreeFiles extends OrgAction
{
    use WithMarketingAuthorisation;

    public const string BEEFREE_UID = 'CmsUserName';

    public function handle(Shop $shop, string $path): array
    {
        $directory = $this->encodePath($path);
        $response  = $this->client($shop)->get('https://api.getbee.io/v1/file/'.($directory === '' ? '' : $directory.'/'));

        if ($response->failed()) {
            throw new HttpException($response->status(), Arr::get($response->json() ?? [], 'message', __('Failed to load Beefree files')));
        }

        return $response->json('data');
    }

    public function client(Shop $shop): PendingRequest
    {
        $apiKey = Arr::get($shop->group->settings, 'beefree.file_manager_api_key');

        if (!$apiKey) {
            throw new HttpException(422, __('Beefree File Manager API key not configured'));
        }

        return Http::withToken($apiKey)->withHeaders(['X-BEE-Uid' => self::BEEFREE_UID]);
    }

    public function encodePath(string $path): string
    {
        return collect(explode('/', trim($path, '/')))
            ->filter(fn (string $segment) => $segment !== '' && $segment !== '.' && $segment !== '..')
            ->map(fn (string $segment) => rawurlencode($segment))
            ->implode('/');
    }

    public function rules(): array
    {
        return [
            'path' => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, (string) Arr::get($this->validatedData, 'path', ''));
    }
}
