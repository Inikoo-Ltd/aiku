<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-15h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFiles;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFileScopeEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentFile;
use App\Models\Goods\ProductDevelopmentItem;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductDevelopmentFiles
{
    use AsAction;

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, bool $canUpload): array
    {
        $files = $productDevelopment->files()
            ->where('scope', '!=', ProductDevelopmentFileScopeEnum::DESIGN_IMAGE->value)
            ->with(['media', 'uploadedBy', 'item.tradeUnit'])
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->get();

        $labels = ProductDevelopmentFileScopeEnum::labels();

        $groups = $files
            ->groupBy(fn (ProductDevelopmentFile $file) => $file->scope->value.'|'.($file->product_development_item_id ?? 0))
            ->map(function (Collection $groupFiles) use ($labels) {
                /** @var ProductDevelopmentFile $first */
                $first = $groupFiles->first();

                return [
                    'key'       => $first->scope->value.'-'.($first->product_development_item_id ?? 0),
                    'scope'     => $first->scope->value,
                    'label'     => $labels[$first->scope->value],
                    'icon'      => $first->scope->icon(),
                    'versioned' => $first->scope->isVersioned(),
                    'sku'       => $first->item?->tradeUnit?->code,
                    'current'   => $groupFiles->whereNull('superseded_at')->map(fn (ProductDevelopmentFile $file) => $this->file($file))->values()->all(),
                    'earlier'   => $groupFiles->whereNotNull('superseded_at')->map(fn (ProductDevelopmentFile $file) => $this->file($file))->values()->all(),
                ];
            })
            ->sortBy(fn (array $group) => array_search($group['scope'], ProductDevelopmentFileScopeEnum::values(), true).'|'.$group['sku'])
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'upload' => $canUpload ? [
                'route'      => ['name' => 'grp.models.product_development.files.upload', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
                'accept'     => collect(explode(',', UploadProductDevelopmentFiles::ACCEPTED_EXTENSIONS))->map(fn (string $extension) => '.'.$extension)->implode(','),
                'max_files'  => UploadProductDevelopmentFiles::MAX_FILES,
                'max_mb'     => (int) (UploadProductDevelopmentFiles::MAX_KILOBYTES / 1024),
                'scopes'     => collect(ProductDevelopmentFileScopeEnum::cases())->reject(fn (ProductDevelopmentFileScopeEnum $scope) => $scope === ProductDevelopmentFileScopeEnum::DESIGN_IMAGE || $scope->isModelPhoto())->map(fn (ProductDevelopmentFileScopeEnum $scope) => [
                    'value'      => $scope->value,
                    'label'      => $labels[$scope->value],
                    'icon'       => $scope->icon(),
                    'per_sku'    => $scope->isPerSku(),
                    'versioned'  => $scope->isVersioned(),
                ])->all(),
                'items'      => $productDevelopment->items()->with('tradeUnit')->get()->map(fn (ProductDevelopmentItem $item) => [
                    'value' => $item->id,
                    'label' => $item->tradeUnit->code.' · '.$item->tradeUnit->name,
                ])->all(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function file(ProductDevelopmentFile $file): array
    {
        return [
            'id'          => $file->id,
            'name'        => $file->media?->name ?? __('Missing file'),
            'mime'        => $file->media?->mime_type,
            'size'        => $file->media?->size,
            'version'     => $file->version,
            'uploaded_by' => $file->uploadedBy?->contact_name ?: $file->uploadedBy?->username,
            'uploaded_at' => $file->created_at?->toIso8601String(),
            'url'         => $file->media ? route('grp.media.download', ['media' => $file->media->ulid]) : null,
            'preview_url' => $file->media && str_starts_with((string) $file->media->mime_type, 'image/') ? route('grp.media.download', ['media' => $file->media->ulid, 'inline' => 1]) : null,
        ];
    }
}
