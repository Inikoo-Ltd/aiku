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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DownloadBeefreeFile extends OrgAction
{
    use WithMarketingAuthorisation;

    public function handle(Shop $shop, string $path): StreamedResponse
    {
        $filePath  = trim($path, '/');
        $directory = dirname($filePath);

        $file = collect(Arr::get(IndexBeefreeFiles::make()->handle($shop, $directory === '.' ? '' : $directory), 'items', []))
            ->first(fn (array $item) => trim(Arr::get($item, 'path', ''), '/') === $filePath && Arr::get($item, 'mime-type') !== 'application/directory');

        if (!$file || !Arr::get($file, 'public-url')) {
            throw new HttpException(404, __('File not found'));
        }

        $response = Http::get($file['public-url']);

        if ($response->failed()) {
            throw new HttpException(502, __('Failed to download file from Beefree'));
        }

        return response()->streamDownload(
            fn () => print($response->body()),
            $file['name'],
            ['Content-Type' => Arr::get($file, 'mime-type', 'application/octet-stream')]
        );
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:1024'],
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): StreamedResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData['path']);
    }
}
