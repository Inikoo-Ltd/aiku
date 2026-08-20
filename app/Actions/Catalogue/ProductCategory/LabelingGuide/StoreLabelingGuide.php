<?php

/*
 * Author Louis Perez
 * Created on 20-08-2026-14h-03m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\LabelingGuide;

use App\Actions\OrgAction;
use App\Models\Catalogue\LabelingGuide;
use App\Models\Catalogue\ProductCategory;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class StoreLabelingGuide extends OrgAction
{
    private ?User $user = null;

    public function handle(ProductCategory $productCategory, ?UploadedFile $file = null): LabelingGuide
    {
        $labelingGuide = LabelingGuide::where('product_category_id', $productCategory->id)
            ->first();

        if ($labelingGuide) {
            Storage::disk('media')->delete($labelingGuide->path);
        }

        if ($file) {
            $content = $file->get();

            $folderName = $productCategory->type->value;
            $storagePath = "labeling_guide/{$productCategory->group_id}/{$productCategory->shop_id}/{$folderName}";
            $storedFilename = 'labeling_guide_'. $productCategory->id . '.pdf';
            Storage::disk('media')->put("{$storagePath}/{$storedFilename}", $content);

            $storeData = [
                'filename'              => $file->getClientOriginalName(),
                'path'                  => "{$storagePath}/{$storedFilename}",
                'file_size'             => $file->getSize(),
                'checksum'              => md5($content),
                'uploaded_by'           => $this->user?->id,
                'uploaded_at'           => now(),
            ];

            if ($labelingGuide) {
                $labelingGuide->update($storeData);
            } else {
                data_set($storeData, 'product_category_id', $productCategory->id);
                $labelingGuide = LabelingGuide::create($storeData);
            }
        } elseif ($labelingGuide) {
            $labelingGuide->delete();
        }

        return $labelingGuide;
    }

    public function action(ProductCategory $productCategory, ?UploadedFile $file = null, ?User $user = null): LabelingGuide
    {
        $this->asAction = true;
        $this->productCategory = $productCategory;
        $this->user = $user;
        $this->initialisation($productCategory->organisation, []);

        return $this->handle($productCategory, $file);
    }
}
