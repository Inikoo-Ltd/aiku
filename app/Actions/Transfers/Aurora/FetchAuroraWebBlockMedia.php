<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Oct 2024 16:18:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Helpers\Media\SaveModelImages;
use App\Actions\OrgAction;
use App\Models\Helpers\Media;
use App\Models\Transfers\FetchDownloadImage;
use App\Models\Web\WebBlock;
use App\Models\Web\Webpage;
use App\Transfers\Aurora\WithAuroraImages;
use Exception;
use Illuminate\Support\Facades\DB;

class FetchAuroraWebBlockMedia extends OrgAction
{
    use WithAuroraImages;

    public function handle(WebBlock $webBlock, Webpage $webpage, string $auroraImage): Media|null
    {
        if ($auroraImage == '/') {
            return null;
        }

        if ($auroraImage == '/art/nopic_trimmed.jpg') {
            return $this->getStaticImages($webBlock, 'nopic_trimmed.jpg');
        }
        if ($auroraImage == '/https://via.placeholder.com/300x250.png') {
            return $this->getStaticImages($webBlock, '300x250.png');
        }

        $this->organisation = $webpage->website->organisation;
        $auroraImageId      = null;


        if (preg_match('/wi\/(\d+)\.([a-zA-Z]+)/', $auroraImage, $matches)) {
            $auroraImageId = $matches[1];
        }
        if (!$auroraImageId) {
            if (preg_match('/^\/wi.php\?.*id=(\d+)/', $auroraImage, $matches)) {
                $auroraImageId = $matches[1];
            }
        }

        if (!$auroraImageId) {
            if (preg_match('/^\/image_root.php\?.*id=(\d+)/', $auroraImage, $matches)) {
                $auroraImageId = $matches[1];
            }
        }


        if ($auroraImageId) {
            $auroraImageData = DB::connection('aurora')->table('Image Dimension')
                ->where('Image Key', $auroraImageId)->first();


            if ($auroraImageData) {
                $imageData = $this->fetchImage($auroraImageData);


                if (isset($imageData['image_path']) && file_exists($imageData['image_path'])) {
                    return SaveModelImages::run($webBlock, [
                        "path"         => $imageData['image_path'],
                        "originalName" => $imageData['filename'],
                    ]);
                }
            }
        }

        return $this->downloadMediaFromWebpage($webBlock, $webpage, $auroraImage);
    }


    private function getStaticImages($webBlock, $imageName)
    {
        return SaveModelImages::run($webBlock, [
            "path"         => resource_path('aurora/'.$imageName),
            "originalName" => $imageName,
        ]);
    }

    public function downloadMediaFromWebpage(WebBlock $webBlock, Webpage $webpage, string $auroraImage): Media|null
    {
        $urlToFile = "https://www.".$webpage->website->domain.$auroraImage;

        if (!$fetchNotFoundImage = FetchDownloadImage::where('url', $urlToFile)->first()) {
            $fetchNotFoundImage = FetchDownloadImage::create([
                'domain' => $webpage->website->domain,
                'path'   => $auroraImage,
                'url'    => $urlToFile
            ]);
        }

        try {
            $content  = file_get_contents($urlToFile);
            $tempPath = tempnam(sys_get_temp_dir(), "img_");

            $headers  = get_headers($urlToFile, 1);
            $mimeType = $headers["Content-Type"];

            if ($mimeType == "image/jpeg") {
                $extension = ".jpg";
            } elseif ($mimeType == "image/png") {
                $extension = ".png";
            } else {
                $extension = ".jpg";
            }

            $tempFile = $tempPath.$extension;

            file_put_contents($tempFile, $content);
            $fetchNotFoundImage->update(['status' => 'found']);

            return SaveModelImages::run($webBlock, [
                "path"         => $tempFile,
                "originalName" => "aurora_image",
            ]);
        } catch (Exception) {
            $fetchNotFoundImage->update(['status' => 'failed']);

            return null;
        }
    }


}
