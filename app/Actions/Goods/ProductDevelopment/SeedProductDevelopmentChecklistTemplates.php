<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-16h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Recipe\ProductDevelopmentRecipes;
use App\Actions\Goods\ProductDevelopment\Hydrators\ProductDevelopmentHydrateChecklist;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopment;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistLevelEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Models\Goods\ProductDevelopmentChecklistTemplate;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedProductDevelopmentChecklistTemplates
{
    use AsAction;

    public const string DEFAULT_CATEGORY = 'default';

    public string $commandSignature = 'product_development:seed_checklist_templates';

    public function handle(Group $group): void
    {
        $this->replaceFreeTextIngredients($group);

        foreach ($this->starterSet() as $position => $template) {
            ProductDevelopmentChecklistTemplate::firstOrCreate(
                [
                    'group_id' => $group->id,
                    'category' => self::DEFAULT_CATEGORY,
                    'key'      => $template['key'],
                ],
                [
                    'role'         => $template['role'],
                    'level'        => $template['level'],
                    'label'        => $template['label'],
                    'required'     => $template['required'] ?? true,
                    'target_field' => $template['target_field'] ?? null,
                    'position'     => $position,
                ]
            );
        }
    }

    /**
     * The ingredients item used to save the free-text marketing ingredients; it now links the trade
     * unit's ingredient records, like the trade unit page does.
     */
    private function replaceFreeTextIngredients(Group $group): void
    {
        $old = ProductDevelopmentTargetFieldEnum::TRADE_UNIT_MARKETING_INGREDIENTS->value;
        $new = ProductDevelopmentTargetFieldEnum::TRADE_UNIT_INGREDIENTS->value;

        ProductDevelopmentChecklistTemplate::where('group_id', $group->id)->where('key', $old)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('product_development_checklist_templates as existing')->whereColumn('existing.group_id', 'product_development_checklist_templates.group_id')->whereColumn('existing.category', 'product_development_checklist_templates.category')->where('existing.key', $new))
            ->update(['key' => $new, 'target_field' => $new]);

        ProductDevelopmentChecklistItem::whereHas('productDevelopment', fn ($query) => $query->where('group_id', $group->id))
            ->where('key', $old)
            ->update(['key' => $new, 'target_field' => $new]);
    }

    /**
     * Adds the items of templates created after a development started, so open developments ask for them too.
     */
    public function backfill(Group $group): int
    {
        $added = 0;

        ProductDevelopment::where('group_id', $group->id)
            ->where('status', ProductDevelopmentStatusEnum::ACTIVE->value)
            ->with(['items', 'checklistItems'])
            ->each(function (ProductDevelopment $productDevelopment) use (&$added) {
                $templates = ProductDevelopmentChecklistTemplate::where('group_id', $productDevelopment->group_id)
                    ->where('category', $productDevelopment->checklist_category)
                    ->where('is_active', true)
                    ->get();

                foreach ($templates as $template) {
                    if (!ProductDevelopmentRecipes::templateApplies($template, $productDevelopment->kind)) {
                        continue;
                    }
                    $targets = $template->level === ProductDevelopmentChecklistLevelEnum::FAMILY ? [null] : $productDevelopment->items->all();
                    foreach ($targets as $item) {
                        $exists = $productDevelopment->checklistItems->contains(fn ($checklistItem) => $checklistItem->key === $template->key && $checklistItem->product_development_item_id === $item?->id);
                        if ($exists) {
                            continue;
                        }
                        $productDevelopment->checklistItems()->create([
                            'product_development_item_id' => $item?->id,
                            'template_id'                 => $template->id,
                            'role'                        => ProductDevelopmentRecipes::roleFor($template->role, $productDevelopment->kind),
                            'level'                       => $template->level,
                            'key'                         => $template->key,
                            'label'                       => $template->label,
                            'required'                    => $template->required,
                            'target_field'                => $template->target_field,
                        ]);
                        $added++;
                    }
                }

                ProductDevelopmentHydrateChecklist::run($productDevelopment);
            });

        return $added;
    }

    /**
     * @return array<int, array{key: string, role: string, level: string, label: string, required?: bool, target_field?: string}>
     */
    private function starterSet(): array
    {
        $family = ProductDevelopmentChecklistLevelEnum::FAMILY->value;
        $sku    = ProductDevelopmentChecklistLevelEnum::SKU->value;

        $fieldItem = fn (ProductDevelopmentMemberRoleEnum $role, ProductDevelopmentTargetFieldEnum $field, bool $required = true) => [
            'key'          => $field->value,
            'role'         => $role->value,
            'level'        => $sku,
            'label'        => ProductDevelopmentTargetFieldEnum::labels()[$field->value],
            'required'     => $required,
            'target_field' => $field->value,
        ];

        return [
            ['key' => 'brief_target_customer', 'role' => ProductDevelopmentMemberRoleEnum::BUYER->value, 'level' => $family, 'label' => __('Target customer and price point')],
            ['key' => 'brief_references', 'role' => ProductDevelopmentMemberRoleEnum::BUYER->value, 'level' => $family, 'label' => __('Reference images and mood')],
            $fieldItem(ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_NET_WEIGHT),
            $fieldItem(ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_NET_VOLUME, false),
            $fieldItem(ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_GROSS_WEIGHT),
            $fieldItem(ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_MARKETING_DIMENSIONS),
            $fieldItem(ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_ORIGIN_COUNTRY),
            $fieldItem(ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_MATERIALS, false),
            ['key' => UploadProductDevelopmentFactoryPhoto::KEY, 'role' => ProductDevelopmentMemberRoleEnum::AGENT->value, 'level' => $sku, 'label' => __('Factory photos (6 angles)')],
            $fieldItem(ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentTargetFieldEnum::SUPPLIER_PRODUCT_UNITS_PER_PACK),
            $fieldItem(ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentTargetFieldEnum::SUPPLIER_PRODUCT_UNITS_PER_CARTON),
            $fieldItem(ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentTargetFieldEnum::SUPPLIER_PRODUCT_CBM),
            $fieldItem(ProductDevelopmentMemberRoleEnum::AGENT, ProductDevelopmentTargetFieldEnum::SUPPLIER_PRODUCT_CARTON_WEIGHT),
            $fieldItem(ProductDevelopmentMemberRoleEnum::PURCHASING, ProductDevelopmentTargetFieldEnum::SUPPLIER_PRODUCT_COST),
            $fieldItem(ProductDevelopmentMemberRoleEnum::PURCHASING, ProductDevelopmentTargetFieldEnum::SUPPLIER_PRODUCT_LEAD_TIME),
            ['key' => 'charge_label_print', 'role' => ProductDevelopmentMemberRoleEnum::PURCHASING->value, 'level' => $family, 'label' => __('Agreed label print charge'), 'required' => false],
            $fieldItem(ProductDevelopmentMemberRoleEnum::COMPLIANCE, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_TARIFF_CODE),
            $fieldItem(ProductDevelopmentMemberRoleEnum::COMPLIANCE, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_INGREDIENTS),
            $fieldItem(ProductDevelopmentMemberRoleEnum::COMPLIANCE, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_GPSR_MANUFACTURER),
            $fieldItem(ProductDevelopmentMemberRoleEnum::COMPLIANCE, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_GPSR_EU_RESPONSIBLE, false),
            $fieldItem(ProductDevelopmentMemberRoleEnum::COMPLIANCE, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_GPSR_WARNINGS),
            $fieldItem(ProductDevelopmentMemberRoleEnum::COMPLIANCE, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_UFI_NUMBER, false),
            $fieldItem(ProductDevelopmentMemberRoleEnum::COMPLIANCE, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_UN_NUMBER, false),
            $fieldItem(ProductDevelopmentMemberRoleEnum::COMPLIANCE, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_PICTOGRAMS),
            $fieldItem(ProductDevelopmentMemberRoleEnum::COMPLIANCE, ProductDevelopmentTargetFieldEnum::TRADE_UNIT_LABEL_MARKS),
        ];
    }

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $this->handle($group);
            $added = $this->backfill($group);
            $command->info("Checklist templates seeded for {$group->slug}, {$added} checklist items added to open developments");
        }

        return 0;
    }
}
