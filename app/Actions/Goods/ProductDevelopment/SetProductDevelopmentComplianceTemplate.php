<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-20h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Hydrators\ProductDevelopmentHydrateChecklist;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistLevelEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentChecklistTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Picks the compliance template (product type) of a development, which decides what compliance has to fill
 * in. Compliance items the new template shares keep their answers; the others are replaced. Values already
 * saved on the trade units stay where they are.
 */
class SetProductDevelopmentComplianceTemplate extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopment $productDevelopment;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $access = $this->productDevelopmentAccess();

        return $this->canViewProductDevelopment($request->user(), $this->productDevelopment)
            && ($access->canEdit($request->user(), $this->productDevelopment) || in_array(ProductDevelopmentMemberRoleEnum::COMPLIANCE, $access->memberRoles($request->user(), $this->productDevelopment), true));
    }

    public function handle(ProductDevelopment $productDevelopment, array $modelData): ProductDevelopment
    {
        $category = $modelData['checklist_category'];

        DB::transaction(function () use ($productDevelopment, $category) {
            $productDevelopment->update(['checklist_category' => $category]);

            $templates = ProductDevelopmentChecklistTemplate::where('group_id', $productDevelopment->group_id)
                ->where('category', $category)
                ->where('role', ProductDevelopmentMemberRoleEnum::COMPLIANCE->value)
                ->where('is_active', true)
                ->orderBy('position')
                ->get()
                ->keyBy('key');

            $existing = $productDevelopment->checklistItems()->where('role', ProductDevelopmentMemberRoleEnum::COMPLIANCE->value)->get();
            $existing->reject(fn (ProductDevelopmentChecklistItem $checklistItem) => $templates->has($checklistItem->key))->each->delete();

            $items = $productDevelopment->items()->get();
            foreach ($templates as $template) {
                $targets = $template->level === ProductDevelopmentChecklistLevelEnum::FAMILY ? [null] : $items->pluck('id')->all();
                foreach ($targets as $itemId) {
                    $productDevelopment->checklistItems()->updateOrCreate(
                        ['product_development_item_id' => $itemId, 'role' => $template->role, 'key' => $template->key],
                        [
                            'template_id'  => $template->id,
                            'level'        => $template->level,
                            'label'        => $template->label,
                            'required'     => $template->required,
                            'target_field' => $template->target_field,
                        ]
                    );
                }
            }
        });

        ProductDevelopmentHydrateChecklist::run($productDevelopment->refresh());

        return $productDevelopment;
    }

    /**
     * The templates a group can pick from, as their category keys.
     *
     * @return array<int, string>
     */
    public static function categories(int $groupId): array
    {
        return ProductDevelopmentChecklistTemplate::where('group_id', $groupId)->where('is_active', true)->distinct()->orderBy('category')->pluck('category')->all();
    }

    public static function categoryLabel(string $category): string
    {
        return $category === SeedProductDevelopmentChecklistTemplates::DEFAULT_CATEGORY ? __('General merchandise') : ucfirst(str_replace(['_', '-'], ' ', $category));
    }

    public function rules(): array
    {
        return [
            'checklist_category' => ['required', 'string', Rule::in(self::categories($this->productDevelopment->group_id))],
        ];
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): ProductDevelopment
    {
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $request);

        return $this->handle($productDevelopment, $this->validatedData);
    }

    public function action(ProductDevelopment $productDevelopment, array $modelData): ProductDevelopment
    {
        $this->asAction           = true;
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $modelData);

        return $this->handle($productDevelopment, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
