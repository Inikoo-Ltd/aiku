<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ManufacturePayBand;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\ManufactureStepTier;
use App\Models\Production\ManufactureTask;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Pay band thresholds typed by hand for a recipe step, on the artefact or on every artefact of its family (HELP-4465).
 * They apply from the start of the day they are saved: today's sessions on the step are paid again, earlier days stay
 * as they were paid. No thresholds puts the step back on the ones calculated from the band multipliers.
 */
class SetManufactureStepTiers extends OrgAction
{
    private Artefact $artefact;

    private ManufactureTask $manufactureTask;

    /**
     * @param  array<string, float|int|string>|null  $thresholds
     */
    public function handle(Artefact $artefact, ManufactureTask $manufactureTask, string $applyTo, ?array $thresholds): ?ManufactureStepTier
    {
        $source     = $applyTo == 'family' ? 'family' : 'artefact';
        $parentId   = $source == 'family' ? $artefact->artefact_family_id : $artefact->id;
        $thresholds = $thresholds === null ? null : array_map(fn ($threshold) => round((float) $threshold, 4), $thresholds);
        $previous   = ManufactureStepTier::lastFor($source, $parentId, $manufactureTask->id)?->thresholds;

        if ($previous == $thresholds) {
            return null;
        }

        $effectiveFrom = now($artefact->organisation->timezone?->name ?? config('app.timezone'))->startOfDay()->utc();

        return DB::transaction(function () use ($artefact, $manufactureTask, $source, $parentId, $thresholds, $previous, $effectiveFrom) {
            $tier = ManufactureStepTier::create([
                'artefact_id'         => $source == 'artefact' ? $parentId : null,
                'artefact_family_id'  => $source == 'family' ? $parentId : null,
                'manufacture_task_id' => $manufactureTask->id,
                'thresholds'          => $thresholds,
                'previous_thresholds' => $previous,
                'effective_from'      => $effectiveFrom,
                'user_id'             => auth()->id(),
            ]);

            ManufactureTaskSession::query()
                ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
                ->where('activity_type', ManufactureTaskSessionActivityTypeEnum::PRODUCTION)
                ->where('manufacture_task_id', $manufactureTask->id)
                ->where('is_combined', false)
                ->whereNotNull('ended_at')
                ->where('started_at', '>=', $effectiveFrom->copy()->max(ManufacturePayrollAdjustment::lockedBefore($artefact->production)))
                ->whereHas('jobOrderItemTask.jobOrderItem.artefact', fn ($query) => $source == 'family'
                    ? $query->where('artefact_family_id', $parentId)
                    : $query->where('artefacts.id', $parentId))
                ->each(fn (ManufactureTaskSession $session) => CalculateManufactureTaskSessionPay::run($session));

            return $tier;
        });
    }

    /**
     * Band codes above band 0 measured against the target, lowest first.
     *
     * @return Collection<int, string>
     */
    public static function measuredBandCodes(int $productionId): Collection
    {
        return ManufacturePayBand::effectiveAt($productionId, now())
            ->whereNotNull('target_multiplier')
            ->where('code', '!=', '0')
            ->get()
            ->unique('code')
            ->sortBy('target_multiplier')
            ->pluck('code')
            ->values();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            "org-supervisor.{$this->organisation->id}",
            "accounting.{$this->organisation->id}.edit",
        ]);
    }

    public function rules(): array
    {
        return [
            'apply_to'     => ['required', Rule::in(['artefact', 'family'])],
            'thresholds'   => ['present', 'nullable', 'array'],
            'thresholds.*' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($this->get('apply_to') == 'family' && !$this->artefact->artefact_family_id) {
            $validator->errors()->add('apply_to', __('This artefact is not in a family'));

            return;
        }

        if (!$this->manufactureTask->is_piece_rate) {
            $validator->errors()->add('thresholds', __(':step is paid by the hour, so it has no pay band thresholds', ['step' => $this->manufactureTask->name]));

            return;
        }

        $thresholds = $this->get('thresholds');
        if ($thresholds === null) {
            return;
        }

        $codes = self::measuredBandCodes($this->artefact->production_id);
        if (collect(array_keys($thresholds))->map(fn ($code) => (string) $code)->sort()->values()->all() != $codes->sort()->values()->all()) {
            $validator->errors()->add('thresholds', __('Give a threshold for each band: :bands', ['bands' => $codes->implode(', ')]));

            return;
        }

        $target = $this->highestTarget();
        if ($target === null) {
            $validator->errors()->add('thresholds', __('Set a target per hour on :step first', ['step' => $this->manufactureTask->name]));

            return;
        }

        $previous = $target;
        foreach ($codes as $code) {
            if ((float) $thresholds[$code] <= $previous) {
                $validator->errors()->add('thresholds', $previous == $target
                    ? __('Band :band must be higher than the target of :target per hour', ['band' => $code, 'target' => round($target, 2)])
                    : __('Band :band must be higher than the band below it', ['band' => $code]));

                return;
            }
            $previous = (float) $thresholds[$code];
        }
    }

    /**
     * The target the thresholds have to start above: the artefact's, or for a family the highest of its artefacts on this
     * step, so every band stays above every artefact's own target.
     */
    private function highestTarget(): ?float
    {
        $rates = ArtefactManufactureTask::query()
            ->where('manufacture_task_id', $this->manufactureTask->id)
            ->when(
                $this->get('apply_to') == 'family',
                fn ($query) => $query->whereHas('artefact', fn ($query) => $query->where('artefact_family_id', $this->artefact->artefact_family_id)),
                fn ($query) => $query->where('artefact_id', $this->artefact->id)
            )
            ->pluck('standard_rate')
            ->map(fn ($rate) => $rate ?? $this->manufactureTask->standard_rate)
            ->filter(fn ($rate) => $rate !== null);

        return $rates->isEmpty() ? null : (float) $rates->max();
    }

    public function action(Artefact $artefact, ManufactureTask $manufactureTask, array $modelData): ?ManufactureStepTier
    {
        $this->asAction        = true;
        $this->artefact        = $artefact;
        $this->manufactureTask = $manufactureTask;
        $this->initialisationFromProduction($artefact->production, $modelData);

        return $this->handle($artefact, $manufactureTask, $this->validatedData['apply_to'], $this->validatedData['thresholds']);
    }

    public function asController(Artefact $artefact, ManufactureTask $manufactureTask, ActionRequest $request): ?ManufactureStepTier
    {
        $this->artefact        = $artefact;
        $this->manufactureTask = $manufactureTask;
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $manufactureTask, $this->validatedData['apply_to'], $this->validatedData['thresholds']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
