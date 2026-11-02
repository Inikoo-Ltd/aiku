<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-15h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\Recipe\ProductDevelopmentRecipes;
use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFiles;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentComponent;
use App\Models\Goods\ProductDevelopmentFile;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\HumanResources\Employee;
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

    private bool $canOpenJobOrders = false;

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, User $viewer): array
    {
        $access    = $this->productDevelopmentAccess();
        $canManage = $access->canManageSamples($viewer, $productDevelopment);
        $canReview = $access->canReviewSamples($viewer, $productDevelopment);
        $items     = $productDevelopment->items()
            ->with(['tradeUnit', 'artefact.production.organisation', 'artefact.branchedFrom', 'samples.madeBy', 'samples.reviewedBy', 'samples.photos.media', 'samples.jobOrder.employee', 'samples.jobOrder.jobOrderItems', 'components.tradeUnit', 'components.approvedBy'])
            ->orderBy('position')->orderBy('id')
            ->get();

        $this->commentCounts = $productDevelopment->comments()
            ->where('model_type', 'ProductDevelopmentSample')
            ->selectRaw('model_id, count(*) as total')
            ->groupBy('model_id')
            ->pluck('total', 'model_id')
            ->all();

        $canApproveComponents = $access->canApproveComponents($viewer, $productDevelopment);
        $production           = $productDevelopment->production;
        $this->canOpenJobOrders = $production && $viewer->authTo([
            'org-supervisor.'.$production->organisation_id,
            'productions-view.'.$production->organisation_id,
            "productions_operations.{$production->id}.view",
            "productions_rd.{$production->id}.edit",
        ]);
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
            'artisans'   => $production ? Employee::where('organisation_id', $production->organisation_id)
                ->where('state', EmployeeStateEnum::WORKING)
                ->orderBy('contact_name')
                ->get(['id', 'contact_name'])
                ->map(fn (Employee $employee) => ['value' => $employee->id, 'label' => $employee->contact_name])
                ->all() : [],
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
                'branched_from' => $artefact->branchedFrom?->code,
                'route' => ['name' => 'grp.org.productions.show.crafts.artefacts.show', 'parameters' => [$artefact->production->organisation->slug, $artefact->production->slug, $artefact->slug]],
            ] : null,
            'recipe'   => $recipe,
            'status'   => $latest ? $this->status($latest->status) : null,
            'final'    => $final?->reference(),
            'recipe_changed' => $final && $recipe && !self::sameIngredients($final->recipe['ingredients'] ?? [], $recipe['ingredients']),
            'can_start_sample' => $canManage && $artefact && !$open,
            'no_artefact_reason' => !$artefact ? $this->noArtefactReason($productDevelopment, $item) : null,
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
        $jobOrder = $sample->jobOrder;

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
            'job'            => $jobOrder ? [
                'reference' => $jobOrder->reference,
                'status'    => JobOrderStateEnum::labels()[$jobOrder->state->value] ?? $jobOrder->state->value,
                'assignee'  => $jobOrder->employee?->contact_name,
                'quantity'  => (float) $jobOrder->jobOrderItems->sum('quantity'),
                'received'  => (float) $jobOrder->jobOrderItems->sum('quantity_received'),
                'route'     => $this->canOpenJobOrders ? [
                    'name'       => 'grp.org.productions.show.operations.job-orders.show',
                    'parameters' => [$productDevelopment->production->organisation->slug, $productDevelopment->production->slug, $jobOrder->slug],
                ] : null,
            ] : null,
            'photos'         => $sample->photos->map(fn (ProductDevelopmentFile $photo) => UploadProductDevelopmentFiles::photoRow($photo, $canManage && $sample->status === ProductDevelopmentSampleStatusEnum::IN_PROGRESS))->all(),
            'can_edit'       => $canManage && $sample->status === ProductDevelopmentSampleStatusEnum::IN_PROGRESS,
            'can_review'     => $canReview && $sample->status === ProductDevelopmentSampleStatusEnum::READY_FOR_REVIEW,
            'routes'         => [
                'update'   => ['name' => 'grp.models.product_development.sample.update', 'parameters' => ['productDevelopmentSample' => $sample->id]],
                'submit'   => ['name' => 'grp.models.product_development.sample.submit', 'parameters' => ['productDevelopmentSample' => $sample->id]],
                'review'   => ['name' => 'grp.models.product_development.sample.review', 'parameters' => ['productDevelopmentSample' => $sample->id]],
                'photos'   => ['name' => 'grp.models.product_development.sample.photos', 'parameters' => ['productDevelopmentSample' => $sample->id]],
                'comments' => ['name' => 'grp.product_developments.json.sample_comments', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'sample' => $sample->id]],
            ],
        ];
    }

    /**
     * Why an SKU of an in-house development has no experiment to make trial runs of.
     */
    private function noArtefactReason(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item): ?string
    {
        if (!$productDevelopment->production) {
            return __('Choose the production unit that makes it');
        }

        $blocking = ProductDevelopmentRecipes::blockingArtefact($productDevelopment, $item);
        if (!$blocking) {
            return null;
        }

        return $blocking->trade_unit_id === $item->trade_unit_id
            ? __(':code is already a product of the factory (:state): make a new SKU for the experiment', ['code' => $blocking->code, 'state' => mb_strtolower(ArtefactStateEnum::labels()[$blocking->state->value])])
            : __('The code :code is already used by another product of the factory: give the SKU another code', ['code' => $blocking->code]);
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
