<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-21h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFactoryPhoto;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistLevelEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Goods\TradeUnit\TradeUnitBestBeforeEnum;
use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductDevelopmentChecklistEditor
{
    use AsAction;

    private ProductDevelopmentAccess $access;

    private User $viewer;

    private bool $canSeeCosts = true;

    private ?string $supplierCurrency = null;

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, User $viewer, bool $canSeeCosts, ?ProductDevelopmentMemberRoleEnum $onlyRole = null): array
    {
        $this->access      = new ProductDevelopmentAccess();
        $this->viewer      = $viewer;
        $this->canSeeCosts = $canSeeCosts;

        $productDevelopment->loadMissing(['items.tradeUnit', 'items.supplierProduct.currency', 'supplier.currency']);
        $this->supplierCurrency = $productDevelopment->supplier?->currency?->code;
        $checklistItems = $productDevelopment->checklistItems()
            ->with(['completedBy', 'productDevelopment'])
            ->orderBy('id')
            ->get()
            ->when($onlyRole, fn (Collection $rows) => $rows->where('role', $onlyRole))
            ->reject(fn (ProductDevelopmentChecklistItem $row) => !$this->canSeeCosts && ProductDevelopmentTargetFieldEnum::tryFrom((string) $row->target_field)?->isCost());

        $byItem    = $checklistItems->groupBy(fn (ProductDevelopmentChecklistItem $row) => $row->product_development_item_id ?? 0);
        $canLinkSp = $this->access->canEdit($viewer, $productDevelopment)
            || in_array(ProductDevelopmentMemberRoleEnum::AGENT, $this->access->memberRoles($viewer, $productDevelopment), true)
            || $this->access->isAgentOf($viewer, $productDevelopment);

        $needsCountries = $checklistItems->contains(fn (ProductDevelopmentChecklistItem $row) => $row->target_field === ProductDevelopmentTargetFieldEnum::TRADE_UNIT_ORIGIN_COUNTRY->value);

        return [
            'family'    => $byItem->get(0, collect())->where('level', ProductDevelopmentChecklistLevelEnum::FAMILY)->map(fn ($row) => $this->row($row, null))->values()->all(),
            'skus'      => $productDevelopment->items->map(fn (ProductDevelopmentItem $item) => [
                'id'                => $item->id,
                'code'              => $item->tradeUnit->code,
                'route'                     => ['name' => 'grp.trade_units.units.show', 'parameters' => ['tradeUnit' => $item->tradeUnit->slug]],
                'name'              => $item->tradeUnit->name,
                'supplier_product'  => $item->supplierProduct ? ['id' => $item->supplierProduct->id, 'code' => $item->supplierProduct->code] : null,
                'can_link_supplier_product' => $canLinkSp && (bool) $productDevelopment->supplier_id,
                'rows'              => $byItem->get($item->id, collect())->map(fn ($row) => $this->row($row, $item))->values()->all(),
                'label_content_route' => ['name' => 'grp.product_developments.json.label_content', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'item' => $item->id]],
            ])->filter(fn (array $sku) => !$onlyRole || count($sku['rows']))->values()->all(),
            'currency'  => $this->supplierCurrency,
            'options'   => $this->groupOptions($checklistItems),
            'countries' => $needsCountries ? Country::orderBy('name')->get(['id', 'name'])->map(fn (Country $country) => ['value' => $country->id, 'label' => $country->name])->all() : [],
            'routes'    => [
                'update'           => 'grp.models.product_development.checklist_item.update',
                'on_label'         => 'grp.models.product_development.checklist_item.on_label',
                'supplier_product' => 'grp.models.product_development.item.supplier_product',
                'supplier_products_search' => $productDevelopment->supplier_id ? ['name' => 'grp.product_developments.json.supplier_products', 'parameters' => ['productDevelopment' => $productDevelopment->slug]] : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ProductDevelopmentChecklistItem $row, ?ProductDevelopmentItem $item): array
    {
        $targetField = ProductDevelopmentTargetFieldEnum::tryFrom((string) $row->target_field);

        return [
            'id'           => $row->id,
            'label'        => $row->label,
            'role'         => [
                'value' => $row->role->value,
                'label' => ProductDevelopmentMemberRoleEnum::labels()[$row->role->value],
                'icon'  => $row->role->icon(),
                'color' => $row->role->color(),
            ],
            'required'     => $row->required,
            'state'        => $row->state->value,
            'input_type'   => $targetField?->inputType() ?? ($row->key === UploadProductDevelopmentFactoryPhoto::KEY ? 'photos' : 'textarea'),
            'placeholder'  => $targetField?->placeholder(),
            'information'  => $targetField?->information(),
            'unit'         => $targetField?->isCost() ? null : $targetField?->unit(),
            'currency'     => $targetField?->isCost() ? ($item?->supplierProduct?->currency?->code ?? $this->supplierCurrency) : null,
            'writes_to'    => $targetField?->writesTo(),
            'needs_supplier_product' => $targetField?->isOnSupplierProduct() && !$item?->supplierProduct,
            'value'        => $targetField ? $this->liveValue($targetField, $item) : ($row->key === UploadProductDevelopmentFactoryPhoto::KEY ? $this->factoryPhotos($row) : ($row->value['text'] ?? null)),
            'na_reason'    => $row->na_reason,
            'saved_by'     => $row->completedBy?->contact_name ?: $row->completedBy?->username,
            'saved_at'     => $row->completed_at?->toIso8601String(),
            'can_edit'     => $this->access->canEditChecklistItem($this->viewer, $row),
            'label_field'  => $item ? $targetField?->labelField() : null,
            'on_label'     => $item && $targetField?->labelField() && in_array($targetField->labelField(), GetProductDevelopmentLabelContent::onLabel($item), true),
            'can_reopen'   => (!$targetField && $row->key !== UploadProductDevelopmentFactoryPhoto::KEY) || $row->state->value === 'not_applicable',
        ];
    }

    /**
     * @return array{angles: array<int, array{key: string, label: string, url: string|null, preview_url: string|null, name: string|null, mime: string|null}>, upload_route: array<string, mixed>}
     */
    private function factoryPhotos(ProductDevelopmentChecklistItem $row): array
    {
        $fileIds = $row->value['angles'] ?? [];
        $files   = $row->productDevelopment->files()->with('media')->whereIn('id', array_values($fileIds))->get()->keyBy('id');

        return [
            'angles'       => collect(UploadProductDevelopmentFactoryPhoto::angleLabels())->map(function (string $label, string $key) use ($fileIds, $files) {
                $media = $files->get($fileIds[$key] ?? 0)?->media;

                return [
                    'key'         => $key,
                    'label'       => $label,
                    'url'         => $media ? route('grp.media.download', ['media' => $media->ulid]) : null,
                    'preview_url' => $media ? route('grp.media.download', ['media' => $media->ulid, 'inline' => 1]) : null,
                    'name'        => $media?->name,
                    'mime'        => $media?->mime_type,
                ];
            })->values()->all(),
            'upload_route' => ['name' => 'grp.models.product_development.checklist_item.factory_photo', 'parameters' => ['productDevelopmentChecklistItem' => $row->id]],
        ];
    }

    private function liveValue(ProductDevelopmentTargetFieldEnum $targetField, ?ProductDevelopmentItem $item): mixed
    {
        if ($item && $targetField->isConfirmedBySaving()) {
            return $targetField->groupValue($item->tradeUnit);
        }

        if ($item && $targetField === ProductDevelopmentTargetFieldEnum::TRADE_UNIT_INGREDIENTS) {
            return $item->tradeUnit->ingredients()->orderBy('name')->get(['ingredients.slug', 'ingredients.name'])
                ->map(fn ($ingredient) => ['slug' => $ingredient->slug, 'name' => $ingredient->name])
                ->all();
        }

        $model = $targetField->isOnSupplierProduct() ? $item?->supplierProduct : $item?->tradeUnit;

        return $model?->getAttribute($targetField->column());
    }

    /**
     * What the grouped fields need to be edited, only when the checklist has them.
     *
     * @return array<string, mixed>
     */
    private function groupOptions(Collection $checklistItems): array
    {
        $fields  = $checklistItems->pluck('target_field')->filter()->unique();
        $options = [];

        if ($fields->contains(ProductDevelopmentTargetFieldEnum::TRADE_UNIT_PICTOGRAMS->value)) {
            $options['pictograms'] = collect(ProductDevelopmentTargetFieldEnum::pictograms())->map(fn (array $pictogram, string $key) => ['key' => $key, ...$pictogram])->values()->all();
        }

        if ($fields->contains(ProductDevelopmentTargetFieldEnum::TRADE_UNIT_LABEL_MARKS->value)) {
            $options['label_marks'] = [
                'markets'     => collect(TradeUnitMarketEnum::labels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
                'languages'   => array_values(array_map(fn (array $language) => ['value' => $language['code'], 'label' => '('.strtoupper($language['code']).') '.$language['name']], GetLanguagesOptions::make()->all())),
                'best_before' => TradeUnitBestBeforeEnum::options(),
                'packaging'   => TradeUnitPackagingMaterialEnum::options(),
                'presence'    => collect(TradeUnitLabelPresenceEnum::labels())->map(fn (string $label, string $value) => ['key' => $value, 'label' => $label])->values()->all(),
            ];
        }

        if ($fields->contains(ProductDevelopmentTargetFieldEnum::TRADE_UNIT_INGREDIENTS->value)) {
            $options['ingredients'] = [
                'fetchRoute' => ['name' => 'grp.goods.ingredients.index', 'parameters' => []],
                'parseRoute' => ['name' => 'grp.json.ingredients.parse', 'parameters' => []],
                'valueProp'  => 'slug',
                'labelProp'  => 'name',
                'mode'       => 'tags',
            ];
        }

        return $options;
    }
}
