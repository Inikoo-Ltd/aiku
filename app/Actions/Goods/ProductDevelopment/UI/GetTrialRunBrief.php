<?php

/*
 * Author Louis Perez
 * Created on 09-11-2026-11h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignRounds;
use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
use App\Actions\Goods\ProductDevelopment\Recipe\RecordTrialRunFormulation;
use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFactoryPhoto;
use App\Actions\OrgAction;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistItemStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFileScopeEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentFile;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Helpers\Country;
use App\Models\Helpers\Media;
use App\Models\Production\JobOrder;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

/**
 * What the artisan making a trial run needs to know, read from the product development that asked for it: the
 * brief, the concept, the SKU's specs and compliance information, the reference files, and the formulation
 * recorded for it. Costs are never shown.
 */
class GetTrialRunBrief extends OrgAction
{
    private JobOrder $jobOrder;

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();
        if ($user->authTo([
            'org-supervisor.'.$this->jobOrder->organisation_id,
            'productions-view.'.$this->jobOrder->organisation_id,
            "productions_operations.{$this->jobOrder->production_id}.view",
            "productions_rd.{$this->jobOrder->production_id}.view",
        ])) {
            return true;
        }

        $productDevelopment = RecordTrialRunFormulation::sampleOf($this->jobOrder)?->productDevelopment;

        return $productDevelopment && (new ProductDevelopmentAccess())->canView($user, $productDevelopment);
    }

    /**
     * The floor team of an in-house development's production unit sees the files its trial runs show: the
     * brief's references and images and the concept, not the rest of the development's files. A file can be
     * shared by several developments, so every development using it counts.
     */
    public static function canSeeFile(User $user, Media $media): bool
    {
        return ProductDevelopmentFile::where('media_id', $media->id)
            ->with(['productDevelopment.production', 'designRound'])
            ->get()
            ->contains(function (ProductDevelopmentFile $file) use ($user) {
                $production = $file->productDevelopment?->production;
                $isShown    = in_array($file->scope, [ProductDevelopmentFileScopeEnum::BRIEF_REFERENCE, ProductDevelopmentFileScopeEnum::BRIEF_IMAGE], true)
                    || $file->designRound?->round === ProductDevelopmentDesignRoundEnum::CONCEPT;

                return $isShown && $production && $user->authTo([
                    'org-supervisor.'.$production->organisation_id,
                    'productions-view.'.$production->organisation_id,
                    "productions_operations.$production->id.view",
                    "productions_rd.$production->id.view",
                ]);
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function handle(JobOrder $jobOrder, ?User $viewer = null): ?array
    {
        $sample = RecordTrialRunFormulation::sampleOf($jobOrder);
        if (!$sample) {
            return null;
        }

        $productDevelopment = $sample->productDevelopment;
        $item               = $sample->item;
        $tradeUnit          = $item->tradeUnit;
        $checklist          = $productDevelopment->checklistItems()
            ->where(fn ($query) => $query->whereNull('product_development_item_id')->orWhere('product_development_item_id', $item->id))
            ->where('state', '!=', ProductDevelopmentChecklistItemStateEnum::NOT_APPLICABLE->value)
            ->orderBy('id')
            ->get()
            ->reject(fn (ProductDevelopmentChecklistItem $row) => ProductDevelopmentTargetFieldEnum::tryFrom((string) $row->target_field)?->isCost() || $row->key === UploadProductDevelopmentFactoryPhoto::KEY);
        $labelContent       = GetProductDevelopmentLabelContent::run($productDevelopment, $item);
        $formulation        = $jobOrder->data['formulation'] ?? null;

        return [
            'development' => [
                'code' => $productDevelopment->code,
                'name' => $productDevelopment->name,
            ],
            'sku'         => [
                'code'  => $tradeUnit->code,
                'name'  => $tradeUnit->name,
                'image' => $tradeUnit->image_id ? $tradeUnit->imageSources(160, 160) : null,
            ],
            'trial_run'   => $sample->reference(),
            'brief'       => [
                'development' => $productDevelopment->brief,
                'sku'         => $item->brief,
            ],
            'references'  => $this->references($productDevelopment, $item),
            'concept'     => $this->concept($productDevelopment),
            'specs'       => $this->answers($checklist->reject(fn (ProductDevelopmentChecklistItem $row) => $row->role === ProductDevelopmentMemberRoleEnum::COMPLIANCE), $item),
            'compliance'  => [
                'label_text' => $labelContent['text'],
                'pictograms' => $labelContent['pictograms'],
                'marks'      => $labelContent['marks'],
                'notes'      => $this->answers($checklist->filter(fn (ProductDevelopmentChecklistItem $row) => $row->role === ProductDevelopmentMemberRoleEnum::COMPLIANCE && !$row->target_field), $item),
            ],
            'formulation' => $formulation ? [
                'recipe'      => $formulation['recipe'],
                'notes'       => $formulation['notes'] ?? null,
                'recorded_by' => ($recordedBy = User::find($formulation['recorded_by_id'] ?? 0)) ? ($recordedBy->contact_name ?: $recordedBy->username) : null,
                'recorded_at' => $formulation['recorded_at'] ?? null,
            ] : null,
            'form'        => $viewer && $item->artefact && RecordTrialRunFormulation::isChangeable($sample) && RecordTrialRunFormulation::canRecord($viewer, $jobOrder) ? [
                'steps'                => RecordTrialRunFormulation::formSteps($item->artefact),
                'notes'                => $formulation['notes'] ?? null,
                'raw_material_options' => ['name' => 'grp.json.production.raw_materials.index', 'parameters' => ['production' => $jobOrder->production_id]],
                'save_route'           => ['name' => 'grp.models.job-order.trial_run_formulation', 'parameters' => ['jobOrder' => $jobOrder->id], 'method' => 'post'],
            ] : null,
        ];
    }

    /**
     * @return array<int, array{name: string|null, mime: string|null, url: string, preview_url: string}>
     */
    private function references(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item): array
    {
        return $productDevelopment->files()
            ->whereNull('superseded_at')
            ->where(fn ($query) => $query->where('scope', ProductDevelopmentFileScopeEnum::BRIEF_REFERENCE->value)
                ->orWhere(fn ($query) => $query->where('scope', ProductDevelopmentFileScopeEnum::BRIEF_IMAGE->value)->where('product_development_item_id', $item->id)))
            ->with('media')
            ->orderBy('id')
            ->get()
            ->filter(fn (ProductDevelopmentFile $file) => $file->media)
            ->map(fn (ProductDevelopmentFile $file) => [
                'name'        => $file->media->name,
                'mime'        => $file->media->mime_type,
                'url'         => route('grp.media.download', ['media' => $file->media->ulid]),
                'preview_url' => route('grp.media.download', ['media' => $file->media->ulid, 'inline' => 1]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function concept(ProductDevelopment $productDevelopment): ?array
    {
        $concept = ProductDevelopmentDesignRounds::scope($productDevelopment, ProductDevelopmentDesignRoundEnum::CONCEPT)
            ->whereNot('status', ProductDevelopmentDesignRoundStatusEnum::DRAFT->value)
            ->orderByDesc('version')
            ->first();

        if (!$concept) {
            return null;
        }

        return [
            'version' => $concept->version,
            'status'  => ProductDevelopmentDesignRoundStatusEnum::labels()[$concept->status->value],
            'notes'   => $concept->notes,
            'upload'  => ShowProductDevelopmentDesign::content($concept)['upload'],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function answers(iterable $rows, ProductDevelopmentItem $item): array
    {
        $answers = [];
        foreach ($rows as $row) {
            $targetField = ProductDevelopmentTargetFieldEnum::tryFrom((string) $row->target_field);
            $value       = $targetField ? self::displayValue($targetField, GetProductDevelopmentChecklistEditor::liveValue($targetField, $item)) : ($row->value['text'] ?? null);
            if (filled($value)) {
                $answers[] = ['label' => $row->label, 'value' => (string) $value];
            }
        }

        return $answers;
    }

    private static function displayValue(ProductDevelopmentTargetFieldEnum $targetField, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($targetField->inputType()) {
            'pictograms', 'label_marks' => null,
            'ingredients'               => collect($value)->pluck('name')->filter()->implode(', '),
            'country'                   => Country::find($value)?->name,
            'dimensions'                => self::dimensions($value),
            'materials'                 => collect($value)->map(fn ($material) => is_array($material) ? implode(' ', array_filter($material, 'is_scalar')) : (string) $material)->implode(', '),
            default                     => is_scalar($value) ? trim($value.' '.($targetField->unit() ?? '')) : null,
        };
    }

    /**
     * @param array<string, mixed> $dimensions
     */
    private static function dimensions(array $dimensions): ?string
    {
        $sizes = array_filter([$dimensions['l'] ?? null, $dimensions['w'] ?? null, $dimensions['h'] ?? null], fn ($size) => $size !== null && $size !== '');

        return $sizes ? implode(' × ', $sizes).' '.($dimensions['units'] ?? '') : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(JobOrder $jobOrder, ActionRequest $request): array
    {
        $this->jobOrder = $jobOrder;
        $this->initialisationFromProduction($jobOrder->production, $request);

        return ['brief' => $this->handle($jobOrder, $request->user())];
    }
}
