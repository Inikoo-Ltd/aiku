<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Aug 2023 12:47:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Images;

use App\Helpers\ImgProxy\Exceptions\InvalidKey;
use App\Helpers\ImgProxy\Exceptions\InvalidSalt;
use App\Helpers\ImgProxy\Exceptions\MissingKey;
use App\Helpers\ImgProxy\Exceptions\MissingSalt;
use App\Helpers\ImgProxy\Image;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GetImgProxyUrl
{
    use AsAction;


    private Image $image;

    private ?int $signatureSize = null;

    public function __construct()
    {
        if (config('img-proxy.signature_size')) {
            $signature_size = config('img-proxy.signature_size');
            if (is_numeric($signature_size)) {
                $this->signatureSize = (int)$signature_size;
            }
        }
    }

    public function handle(Image $image): string
    {

        if (!config('img-proxy.base_url')) {
            return $image->getOriginalPictureUrl();
        }

        $this->image = $image;


        return

            join(
                '/',
                array_filter([
                config('img-proxy.base_url'),
                $this->getSignature(),
                $this->getParameters(),

            ])
            );
    }

    public function getEncodedSourceUrl(): string
    {
        $encodedSourceUrl = rtrim(strtr(base64_encode($this->image->getOriginalPictureUrl()), '+/', '-_'), '=');
        if ($extension = $this->image->getExtension()) {
            $encodedSourceUrl .= '.'.$extension;
        }
        return  $encodedSourceUrl;

    }

    public function getParameters(): string
    {
        return   join(
            '/',
            array_filter([
            $this->getProcessingOptions(),
            $this->getEncodedSourceUrl()

        ])
        );
    }



    public function getSignature(): string
    {



        if (app()->environment(['local']) && empty(config('img-proxy.key'))) {
            return 'signature';
        }

        $signature = hash_hmac(
            'sha256',
            $this->getBinarySalt() .'/'. $this->getParameters(),
            $this->getBinaryKey(),
            true
        );

        if ($this->signatureSize) {
            $signature = pack('A'.$this->signatureSize, $signature);
        }

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    }

    public function getProcessingOptions(Image $img = null): string
    {

        if (!$img) {
            $img = $this->image;
        }

        $processingOptions = '';

        if ($img->getSizeProcessOption() === 'resize') {
            $resize = $img->getResize();
            $processingOptions .= 'rs:'.join(':', $resize);
        }

        return $processingOptions;
    }

    public function getKey(): string
    {
        if (empty($key = config('img-proxy.key'))) {
            throw new MissingKey();
        }

        if (Str::length($key) < 32) {
            throw new InvalidKey();
        }
        return $key;
    }

    public function getSalt(): string
    {
        if (empty($salt = config('img-proxy.salt'))) {
            throw new MissingSalt();
        }

        if (Str::length($salt) < 32) {
            throw new InvalidSalt();
        }
        return $salt;
    }

    public function getBinaryKey(): string
    {
        if (empty($keyBin = pack("H*", $this->getKey()))) {
            throw new InvalidKey('Key expected to be hex-encoded string');
        }

        return $keyBin;
    }


    public function getBinarySalt(): string
    {
        if (empty($saltBin = pack("H*", $this->getSalt()))) {
            throw new InvalidSalt('Salt expected to be hex-encoded string');
        }

        return $saltBin;
    }


}
