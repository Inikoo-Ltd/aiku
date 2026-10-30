<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-21h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
use App\Actions\Goods\ProductDevelopment\SeedProductDevelopmentChecklistTemplates;
use App\Actions\Goods\ProductDevelopment\SetProductDevelopmentComplianceTemplate;
use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFactoryPhoto;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistLevelEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Goods\TradeUnit\TradeUnitBestBeforeEnum;
use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentComponent;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
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
                'components'        => $onlyRole === ProductDevelopmentMemberRoleEnum::COMPLIANCE && $productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE ? $this->components($item) : [],
                'formulation'       => $onlyRole === ProductDevelopmentMemberRoleEnum::COMPLIANCE && $productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE ? $this->formulation($item) : null,
                'pack_info'         => $onlyRole === ProductDevelopmentMemberRoleEnum::COMPLIANCE ? $this->packInfo($item) : [],
            ])->filter(fn (array $sku) => !$onlyRole || count($sku['rows']))->values()->all(),
            'currency'  => $this->supplierCurrency,
            'in_house'  => $productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE,
            'template'  => $onlyRole === ProductDevelopmentMemberRoleEnum::COMPLIANCE ? [
                'value'      => $productDevelopment->checklist_category ?? SeedProductDevelopmentChecklistTemplates::DEFAULT_CATEGORY,
                'options'    => collect(SetProductDevelopmentComplianceTemplate::categories($productDevelopment->group_id))
                    ->map(fn (string $category) => ['value' => $category, 'label' => SetProductDevelopmentComplianceTemplate::categoryLabel($category)])->all(),
                'can_change' => $this->access->canEdit($viewer, $productDevelopment) || in_array(ProductDevelopmentMemberRoleEnum::COMPLIANCE, $this->access->memberRoles($viewer, $productDevelopment), true),
                'route'      => ['name' => 'grp.models.product_development.compliance_template', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
            ] : null,
            'options'   => $this->groupOptions($checklistItems),
            'countries' => $needsCountries ? Country::orderBy('name')->get(['id', 'name'])->map(fn (Country $country) => ['value' => $country->id, 'label' => $country->name])->all() : [],
            'routes'    => [
                'update'           => 'grp.models.product_development.checklist_item.update',
                'on_label'         => 'grp.models.product_development.item.on_label',
                'supplier_product' => 'grp.models.product_development.item.supplier_product',
                'supplier_products_search' => $productDevelopment->supplier_id ? ['name' => 'grp.product_developments.json.supplier_products', 'parameters' => ['productDevelopment' => $productDevelopment->slug]] : null,
            ],
        ];
    }

    /**
     * The pack text that is not a compliance field (filled in on the Products tab) but still goes on the
     * label, so compliance can leave it off too.
     *
     * @return array<int, array{key: string, label: string, value: string|null, on_label: bool}>
     */
    private function packInfo(ProductDevelopmentItem $item): array
    {
        $tradeUnit = $item->tradeUnit;
        $labels    = GetProductDevelopmentLabelContent::fieldLabels();
        $onLabel   = GetProductDevelopmentLabelContent::onLabel($item);

        return collect([
            'product_name' => $tradeUnit->name,
            'net_weight'   => $tradeUnit->net_weight ? $tradeUnit->net_weight.' g' : null,
            'net_volume'   => GetProductDevelopmentLabelContent::volumeLabel($tradeUnit->net_volume),
            'origin'       => $tradeUnit->countryOrigin?->name,
        ])->map(fn (?string $value, string $key) => [
            'key'      => $key,
            'label'    => $labels[$key],
            'value'    => $value,
            'on_label' => in_array($key, $onLabel, true),
        ])->values()->all();
    }

    /**
     * The approved formulation of an in-house SKU, for the compliance assessment.
     *
     * @return array{number: string, ingredients: array<int, array<string, mixed>>, approved_at: string|null}|null
     */
    private function formulation(ProductDevelopmentItem $item): ?array
    {
        $sample = $item->samples()->where('status', ProductDevelopmentSampleStatusEnum::APPROVED->value)->orderByDesc('number')->first();

        return $sample ? [
            'number'      => $sample->reference(),
            'ingredients' => $sample->recipe['ingredients'] ?? [],
            'approved_at' => $sample->reviewed_at?->toIso8601String(),
        ] : null;
    }

    /**
     * The components of an in-house SKU with what compliance needs from each one's own trade unit: its
     * materials, the material codes of its packaging and its documents, and what is missing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function components(ProductDevelopmentItem $item): array
    {
        $scopeLabels = TradeAttachmentScopeEnum::labels();

        return $item->components()->with(['tradeUnit.attachments', 'tradeUnit.packagingFamily.components'])->get()->map(function (ProductDevelopmentComponent $component) use ($scopeLabels) {
            $tradeUnit = $component->tradeUnit;
            $materials = collect(Arr::get($tradeUnit->compliance ?? [], 'material_composition.materials', []))
                ->map(fn (array $material) => trim(($material['material'] ?? '').' '.(isset($material['percentage']) ? $material['percentage'].'%' : '')))
                ->filter()->values()->all();
            $codes     = $tradeUnit->packagingFamily?->components
                ->map(fn ($packaging) => trim($packaging->material.($packaging->material_id_code ? ' ('.$packaging->material_id_code.')' : '')))
                ->filter()->unique()->values()->all() ?? [];
            $documents = $tradeUnit->attachmentsWithSharedDocuments()->map(fn ($media) => [
                'label' => $scopeLabels[$media->pivot->scope] ?? $media->pivot->scope,
                'name'  => $media->name,
                'url'   => route('grp.media.download', ['media' => $media->ulid]),
            ])->values()->all();

            return [
                'id'        => $component->id,
                'code'      => $tradeUnit->code,
                'name'      => $tradeUnit->name,
                'route'     => ['name' => 'grp.trade_units.units.show', 'parameters' => ['tradeUnit' => $tradeUnit->slug]],
                'status'    => ProductDevelopmentComponentStatusEnum::labels()[$component->status->value],
                'approved'  => $component->status === ProductDevelopmentComponentStatusEnum::APPROVED,
                'materials' => $materials,
                'material_codes' => $codes,
                'documents' => $documents,
                'missing'   => array_values(array_filter([
                    !$materials && !$codes ? __('Material') : null,
                    !$documents ? __('Documents') : null,
                ])),
            ];
        })->values()->all();
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

        if ($item && $targetField === ProductDevelopmentTargetFieldEnum::TRADE_UNIT_MATERIALS) {
            return data_get($item->tradeUnit->compliance, 'material_composition.materials') ?: null;
        }

        if ($item && $targetField === ProductDevelopmentTargetFieldEnum::TRADE_UNIT_INGREDIENTS) {
            return $item->tradeUnit->ingredients()->orderBy('trade_unit_has_ingredients.position')->orderBy('trade_unit_has_ingredients.id')->get(['ingredients.slug', 'ingredients.name'])
                ->map(fn ($ingredient) => ['slug' => $ingredient->slug, 'name' => $ingredient->name])
                ->all();
        }

        $model = $targetField->isOnSupplierProduct() ? $item?->supplierProduct : $item?->tradeUnit;

        $value = $model?->getAttribute($targetField->column());

        return $value !== null && $targetField->storedPerUnit() !== 1 ? $value / $targetField->storedPerUnit() : $value;
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
