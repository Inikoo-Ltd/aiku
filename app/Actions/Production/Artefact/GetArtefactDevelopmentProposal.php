<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 22:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4636: how long the work of a product in development takes, step by step. A step's sessions are pooled with
 * the same step (task, label and units per artefact) of the other products of its family, counting only work done
 * since each product's recipe last gained or lost a step. Each artisan's first session is a learning run, very
 * short sessions and outliers (beyond 1.5 times the interquartile range) are left out too. Once enough is left the
 * median per hour is proposed as the step's target.
 */
class GetArtefactDevelopmentProposal
{
    use AsAction;

    /**
     * @return array{
     *     artefact_id: int, code: string, development_started_at: ?string, recipe_changed_at: ?string, last_run_at: ?string,
     *     is_idle: bool, is_ready: bool, is_overdue: bool, kept: ?array<string, mixed>, steps: array<int, array<string, mixed>>
     * }
     */
    public function handle(Artefact $artefact, bool $withSessions = false): array
    {
        $settings = $artefact->production->developmentReviewSettings();
        $kept     = data_get($artefact->data, 'development_review.decision') === 'kept' ? $artefact->data['development_review'] : null;

        $steps = $artefact->recipeSteps()->with('manufactureTask')->get()
            ->map(fn (ArtefactManufactureTask $step) => $this->step($artefact, $step, $settings, $kept, $withSessions))
            ->values();

        $lastRunAt = DB::table('manufacture_task_sessions')
            ->join('job_order_item_tasks', 'job_order_item_tasks.id', 'manufacture_task_sessions.job_order_item_task_id')
            ->join('job_order_items', 'job_order_items.id', 'job_order_item_tasks.job_order_item_id')
            ->where('job_order_items.artefact_id', $artefact->id)
            ->where('manufacture_task_sessions.state', ManufactureTaskSessionStateEnum::CLOSED->value)
            ->max('manufacture_task_sessions.ended_at');

        $isIdle  = !$lastRunAt || Carbon::parse($lastRunAt)->lt(now()->subWeeks($settings['idle_weeks']));
        $isReady = $artefact->is_in_development && $steps->contains('is_ready', true);

        return [
            'artefact_id'            => $artefact->id,
            'code'                   => $artefact->code,
            'development_started_at' => $artefact->development_started_at?->toIso8601String(),
            'recipe_changed_at'      => $artefact->recipe_changed_at?->toIso8601String(),
            'last_run_at'            => $lastRunAt ? Carbon::parse($lastRunAt)->toIso8601String() : null,
            'is_idle'                => $isIdle,
            'is_ready'               => $isReady,
            'is_overdue'             => $isReady && !$isIdle,
            'kept'                   => $kept,
            'steps'                  => $steps->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>|null  $kept
     *
     * @return array<string, mixed>
     */
    private function step(Artefact $artefact, ArtefactManufactureTask $step, array $settings, ?array $kept, bool $withSessions): array
    {
        $sessions = $this->classify($this->pooledSessions($artefact, $step), $settings);
        $counted  = $sessions->whereNull('excluded');
        $rates    = $counted->pluck('rate')->sort()->values();
        $artisans = $counted->pluck('employee_id')->unique()->count();
        $hours    = round($counted->sum('hours'), 2);
        $target   = $step->standard_rate === null ? null : (float) $step->standard_rate;

        $hasEnough = $counted->count() >= $settings['min_sessions'] && $artisans >= $settings['min_artisans'] && $hours >= $settings['min_hours'];
        if ($hasEnough && $kept) {
            $hasEnough = $counted->filter(fn (array $session) => Carbon::parse($session['ended_at'])->gt(Carbon::parse($kept['at'])))->count() >= $settings['min_sessions'];
        }

        return [
            'id'                 => $step->id,
            'name'               => ArtefactManufactureTask::stepName($step->manufactureTask->name, $step->label),
            'manufacture_task_id' => $step->manufacture_task_id,
            'label'              => $step->label,
            'units_per_artefact' => (float) $step->units_per_artefact,
            'target'             => $target,
            'is_piece_rate'      => (bool) $step->manufactureTask->is_piece_rate,
            'sessions_count'     => $counted->count(),
            'excluded_count'     => $sessions->count() - $counted->count(),
            'artisans_count'     => $artisans,
            'hours'              => $hours,
            'proposed_target'    => $rates->isEmpty() ? null : round(self::quantile($rates, 0.5), 1),
            'min_rate'           => $rates->isEmpty() ? null : round($rates->first(), 1),
            'max_rate'           => $rates->isEmpty() ? null : round($rates->last(), 1),
            'q1_rate'            => $rates->isEmpty() ? null : round(self::quantile($rates, 0.25), 1),
            'q3_rate'            => $rates->isEmpty() ? null : round(self::quantile($rates, 0.75), 1),
            'is_ready'           => $target === null && $step->manufactureTask->is_piece_rate && $hasEnough,
            'missing'            => [
                'sessions' => max(0, $settings['min_sessions'] - $counted->count()),
                'artisans' => max(0, $settings['min_artisans'] - $artisans),
                'hours'    => round(max(0, $settings['min_hours'] - $hours), 2),
            ],
            'sessions'           => $withSessions ? $sessions->values()->all() : null,
            'similar'            => $withSessions ? $this->similarTargets($artefact, $step) : null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pooledSessions(Artefact $artefact, ArtefactManufactureTask $step): Collection
    {
        return DB::table('manufacture_task_sessions')
            ->join('job_order_item_tasks', 'job_order_item_tasks.id', 'manufacture_task_sessions.job_order_item_task_id')
            ->join('artefacts_manufacture_tasks', 'artefacts_manufacture_tasks.id', 'job_order_item_tasks.artefact_manufacture_task_id')
            ->join('artefacts', 'artefacts.id', 'artefacts_manufacture_tasks.artefact_id')
            ->leftJoin('employees', 'employees.id', 'manufacture_task_sessions.employee_id')
            ->where('manufacture_task_sessions.state', ManufactureTaskSessionStateEnum::CLOSED->value)
            ->whereIn('manufacture_task_sessions.activity_type', [ManufactureTaskSessionActivityTypeEnum::PRODUCTION->value, ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT->value])
            ->where('manufacture_task_sessions.is_combined', false)
            ->whereNotNull('manufacture_task_sessions.employee_id')
            ->where('manufacture_task_sessions.quantity_made', '>', 0)
            ->where(fn ($query) => $query->whereNull('manufacture_task_sessions.left_open_after_shift_at')->orWhereNotNull('manufacture_task_sessions.shift_end_resolved_at'))
            ->where('artefacts_manufacture_tasks.manufacture_task_id', $step->manufacture_task_id)
            ->whereRaw("lower(coalesce(artefacts_manufacture_tasks.label, '')) = ?", [mb_strtolower(trim((string) $step->label))])
            ->where('artefacts_manufacture_tasks.units_per_artefact', $step->units_per_artefact)
            ->when(
                $artefact->artefact_family_id,
                fn ($query) => $query->where('artefacts.artefact_family_id', $artefact->artefact_family_id),
                fn ($query) => $query->where('artefacts.id', $artefact->id)
            )
            ->whereRaw('manufacture_task_sessions.started_at >= coalesce(artefacts.recipe_changed_at, manufacture_task_sessions.started_at)')
            ->orderBy('manufacture_task_sessions.started_at')
            ->get([
                'manufacture_task_sessions.id',
                'manufacture_task_sessions.employee_id',
                'manufacture_task_sessions.started_at',
                'manufacture_task_sessions.ended_at',
                'manufacture_task_sessions.quantity_made',
                'manufacture_task_sessions.break_minutes',
                'artefacts.code as artefact_code',
                'employees.contact_name as artisan',
            ])
            ->map(function (object $session) {
                $hours = max(0, Carbon::parse($session->started_at)->diffInSeconds(Carbon::parse($session->ended_at)) / 3600 - $session->break_minutes / 60);

                return [
                    'id'            => $session->id,
                    'employee_id'   => $session->employee_id,
                    'artisan'       => $session->artisan,
                    'artefact_code' => $session->artefact_code,
                    'started_at'    => Carbon::parse($session->started_at)->toIso8601String(),
                    'ended_at'      => Carbon::parse($session->ended_at)->toIso8601String(),
                    'quantity'      => (float) $session->quantity_made,
                    'hours'         => round($hours, 2),
                    'rate'          => $hours > 0 ? round((float) $session->quantity_made / $hours, 1) : null,
                    'excluded'      => null,
                ];
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sessions  oldest first
     * @param  array<string, mixed>  $settings
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function classify(Collection $sessions, array $settings): Collection
    {
        $seen     = [];
        $sessions = $sessions->map(function (array $session) use (&$seen, $settings) {
            if (!isset($seen[$session['employee_id']])) {
                $seen[$session['employee_id']] = true;
                $session['excluded'] = 'first_run';
            } elseif ($session['rate'] === null || $session['hours'] * 60 < $settings['min_session_minutes']) {
                $session['excluded'] = 'too_short';
            }

            return $session;
        });

        $rates = $sessions->whereNull('excluded')->pluck('rate')->sort()->values();
        if ($rates->count() < 4) {
            return $sessions;
        }

        $q1    = self::quantile($rates, 0.25);
        $q3    = self::quantile($rates, 0.75);
        $fence = 1.5 * ($q3 - $q1);

        return $sessions->map(function (array $session) use ($q1, $q3, $fence) {
            if ($session['excluded'] === null && ($session['rate'] < $q1 - $fence || $session['rate'] > $q3 + $fence)) {
                $session['excluded'] = 'outlier';
            }

            return $session;
        });
    }

    /**
     * Linear interpolation between the closest ranks.
     *
     * @param  Collection<int, float>  $sorted
     */
    public static function quantile(Collection $sorted, float $quantile): float
    {
        $position = ($sorted->count() - 1) * $quantile;
        $lower    = (int) floor($position);
        $upper    = (int) ceil($position);

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($position - $lower);
    }

    /**
     * Targets already set on the same task in this production, the family's first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function similarTargets(Artefact $artefact, ArtefactManufactureTask $step): array
    {
        return DB::table('artefacts_manufacture_tasks')
            ->join('artefacts', 'artefacts.id', 'artefacts_manufacture_tasks.artefact_id')
            ->where('artefacts.production_id', $artefact->production_id)
            ->whereNull('artefacts.deleted_at')
            ->where('artefacts.id', '!=', $artefact->id)
            ->where('artefacts_manufacture_tasks.manufacture_task_id', $step->manufacture_task_id)
            ->whereNotNull('artefacts_manufacture_tasks.standard_rate')
            ->orderByRaw('artefacts.artefact_family_id is not distinct from ? desc', [$artefact->artefact_family_id])
            ->orderByRaw('abs(artefacts_manufacture_tasks.units_per_artefact - ?)', [$step->units_per_artefact])
            ->orderBy('artefacts.code')
            ->limit(6)
            ->get(['artefacts.code', 'artefacts_manufacture_tasks.label', 'artefacts_manufacture_tasks.units_per_artefact', 'artefacts_manufacture_tasks.standard_rate'])
            ->map(fn (object $row) => [
                'code'               => $row->code,
                'label'              => $row->label,
                'units_per_artefact' => (float) $row->units_per_artefact,
                'target'             => (float) $row->standard_rate,
            ])
            ->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        $artefact = $request->route('artefact');

        return $request->user()->authTo([
            "org-supervisor.$artefact->organisation_id",
            "accounting.$artefact->organisation_id.edit",
            "productions_rd.$artefact->production_id.view",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Artefact $artefact): array
    {
        return $this->handle($artefact, true);
    }
}
