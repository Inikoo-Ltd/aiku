<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-15h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\Recipe\ProductDevelopmentRecipes;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentComponent;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The Formulation & samples tab of an in-house development: each SKU with its brief, its recipe as it is in
 * Production now, and its samples, newest first, each with the recipe it was made from.
 */
class GetProductDevelopmentFormulation
{
    use AsAction;
    use WithProductDevelopmentAuthorisation;

    /**
     * @var array<int, int> comment count by sample id
     */
    private array $commentCounts = [];

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, User $viewer): array
    {
        $access    = $this->productDevelopmentAccess();
        $canManage = $access->canManageSamples($viewer, $productDevelopment);
        $canReview = $access->canReviewSamples($viewer, $productDevelopment);
        $items     = $productDevelopment->items()
            ->with(['tradeUnit', 'artefact.production.organisation', 'samples.madeBy', 'samples.reviewedBy', 'samples.staffTask.assignee', 'components.tradeUnit', 'components.approvedBy'])
            ->orderBy('position')->orderBy('id')
            ->get();

        $this->commentCounts = $productDevelopment->comments()
            ->where('model_type', 'ProductDevelopmentSample')
            ->selectRaw('model_id, count(*) as total')
            ->groupBy('model_id')
            ->pluck('total', 'model_id')
            ->all();

        $canApproveComponents = $access->canApproveComponents($viewer, $productDevelopment);
        $skus     = $items->map(fn (ProductDevelopmentItem $item) => $this->sku($productDevelopment, $item, $canManage, $canReview, $canApproveComponents))->values();
        $approved = $skus->filter(fn (array $sku) => $sku['final'] !== null)->count();

        return [
            'production' => $productDevelopment->production ? [
                'name'         => $productDevelopment->production->name,
                'organisation' => $productDevelopment->production->organisation?->name,
            ] : null,
            'skus'       => $skus->all(),
            'progress'   => ['done' => $approved, 'total' => $skus->count()],
            'can_manage' => $canManage,
            'can_review' => $canReview,
            'can_create_job' => $access->canEdit($viewer, $productDevelopment),
            'can_approve_components' => $canApproveComponents,
            'component_search' => ['name' => 'grp.product_developments.json.search', 'parameters' => ['scope' => 'trade_units', 'product_development' => $productDevelopment->id]],
            'people'     => User::where('group_id', $productDevelopment->group_id)->where('status', true)->orderBy('contact_name')
                ->get(['id', 'contact_name', 'username', 'image_id'])
                ->map(fn (User $user) => [
                    'value'  => $user->id,
                    'label'  => $user->contact_name ?: $user->username,
                    'avatar' => $user->image_id ? $user->imageSources(48, 48) : null,
                ])->all(),
            'priorities' => collect(ChatPriorityEnum::cases())->map(fn (ChatPriorityEnum $priority) => ['value' => $priority->value, 'label' => ucfirst($priority->value)])->all(),
            'routes'     => [
                'sample_job' => ['name' => 'grp.models.product_development.sample_job.store', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
                'comments'   => ['name' => 'grp.models.product_development.comments.store', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sku(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item, bool $canManage, bool $canReview, bool $canApproveComponents): array
    {
        $tradeUnit = $item->tradeUnit;
        $artefact  = $item->artefact;
        $recipe    = $artefact ? ProductDevelopmentRecipes::recipe($artefact) : null;
        $samples   = $item->samples->sortByDesc('number')->values();
        $final     = $samples->first(fn (ProductDevelopmentSample $sample) => $sample->status === ProductDevelopmentSampleStatusEnum::APPROVED);
        $open      = $samples->first(fn (ProductDevelopmentSample $sample) => $sample->status->isOpen());
        $latest    = $samples->first();

        return [
            'id'       => $item->id,
            'code'     => $tradeUnit->code,
            'name'     => $tradeUnit->name,
            'image'    => $tradeUnit->image_id ? $tradeUnit->imageSources(96, 96) : null,
            'brief'    => $item->brief,
            'artefact' => $artefact ? [
                'code'  => $artefact->code,
                'name'  => $artefact->name,
                'state' => ArtefactStateEnum::labels()[$artefact->state->value] ?? $artefact->state->value,
                'route' => ['name' => 'grp.org.productions.show.crafts.artefacts.show', 'parameters' => [$artefact->production->organisation->slug, $artefact->production->slug, $artefact->slug]],
            ] : null,
            'recipe'   => $recipe,
            'status'   => $latest ? $this->status($latest->status) : null,
            'final'    => $final?->reference(),
            'recipe_changed' => $final && $recipe && !self::sameIngredients($final->recipe['ingredients'] ?? [], $recipe['ingredients']),
            'can_start_sample' => $canManage && $artefact && !$open,
            'samples'  => $samples->map(fn (ProductDevelopmentSample $sample) => $this->sample($productDevelopment, $sample, $canManage, $canReview))->all(),
            'components' => $item->components->map(fn (ProductDevelopmentComponent $component) => [
                'id'            => $component->id,
                'trade_unit_id' => $component->trade_unit_id,
                'code'          => $component->tradeUnit->code,
                'name'          => $component->tradeUnit->name,
                'route'         => ['name' => 'grp.trade_units.units.show', 'parameters' => ['tradeUnit' => $component->tradeUnit->slug]],
                'quantity'      => (float) $component->quantity,
                'note'          => $component->note,
                'status'        => [
                    'value' => $component->status->value,
                    'label' => ProductDevelopmentComponentStatusEnum::labels()[$component->status->value],
                    'color' => $component->status->color(),
                    'icon'  => $component->status->icon(),
                ],
                'approved_by'   => $component->approvedBy?->contact_name ?: $component->approvedBy?->username,
                'approved_at'   => $component->approved_at?->toIso8601String(),
                'can_edit'      => $canManage,
                'can_approve'   => $canApproveComponents,
                'routes'        => [
                    'update'   => ['name' => 'grp.models.product_development.component.update', 'parameters' => ['productDevelopmentComponent' => $component->id]],
                    'delete'   => ['name' => 'grp.models.product_development.component.delete', 'parameters' => ['productDevelopmentComponent' => $component->id]],
                    'approval' => ['name' => 'grp.models.product_development.component.approval', 'parameters' => ['productDevelopmentComponent' => $component->id]],
                ],
            ])->values()->all(),
            'can_add_component' => $canManage,
            'routes'   => [
                'sample_store'    => ['name' => 'grp.models.product_development.item.sample.store', 'parameters' => ['productDevelopmentItem' => $item->id]],
                'component_store' => ['name' => 'grp.models.product_development.item.component.store', 'parameters' => ['productDevelopmentItem' => $item->id]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sample(ProductDevelopment $productDevelopment, ProductDevelopmentSample $sample, bool $canManage, bool $canReview): array
    {
        $task = $sample->staffTask;

        return [
            'id'             => $sample->id,
            'number'         => $sample->reference(),
            'status'         => $this->status($sample->status),
            'recipe'         => $sample->recipe,
            'batch_size'     => $sample->batch_size !== null ? (float) $sample->batch_size : null,
            'batch_unit'     => $sample->batch_unit,
            'notes'          => $sample->notes,
            'made_by'        => $sample->madeBy?->contact_name ?: $sample->madeBy?->username,
            'created_at'     => $sample->created_at?->toIso8601String(),
            'ready_at'       => $sample->ready_at?->toIso8601String(),
            'review_comment' => $sample->review_comment,
            'reviewed_by'    => $sample->reviewedBy?->contact_name ?: $sample->reviewedBy?->username,
            'reviewed_at'    => $sample->reviewed_at?->toIso8601String(),
            'comments'       => (int) ($this->commentCounts[$sample->id] ?? 0),
            'job'            => $task ? [
                'reference' => $task->reference,
                'status'    => $task->status->value,
                'assignee'  => $task->assignee?->contact_name ?: $task->assignee?->username,
                'due_at'    => $task->due_at?->toDateString(),
                'route'     => ['name' => 'grp.tasks.show', 'parameters' => [$task->reference]],
            ] : null,
            'can_edit'       => $canManage && $sample->status === ProductDevelopmentSampleStatusEnum::IN_PROGRESS,
            'can_review'     => $canReview && $sample->status === ProductDevelopmentSampleStatusEnum::READY_FOR_REVIEW,
            'routes'         => [
                'update'   => ['name' => 'grp.models.product_development.sample.update', 'parameters' => ['productDevelopmentSample' => $sample->id]],
                'submit'   => ['name' => 'grp.models.product_development.sample.submit', 'parameters' => ['productDevelopmentSample' => $sample->id]],
                'review'   => ['name' => 'grp.models.product_development.sample.review', 'parameters' => ['productDevelopmentSample' => $sample->id]],
                'comments' => ['name' => 'grp.product_developments.json.sample_comments', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'sample' => $sample->id]],
            ],
        ];
    }

    /**
     * @return array{value: string, label: string, color: string, icon: string}
     */
    private function status(ProductDevelopmentSampleStatusEnum $status): array
    {
        return [
            'value' => $status->value,
            'label' => ProductDevelopmentSampleStatusEnum::labels()[$status->value],
            'color' => $status->color(),
            'icon'  => $status->icon(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $approved
     * @param array<int, array<string, mixed>> $current
     */
    public static function sameIngredients(array $approved, array $current): bool
    {
        $key = fn (array $lines) => collect($lines)->map(fn (array $line) => $line['code'].'|'.$line['unit'].'|'.round((float) $line['quantity'], 6))->sort()->values()->all();

        return $key($approved) === $key($current);
    }
}
