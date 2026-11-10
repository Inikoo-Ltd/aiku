<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\SendStaffTaskBadgeUpdateToUsers;
use App\Actions\Tasks\StoreStaffTask;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Events\BroadcastStaffTaskChanged;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4636: products of a family share their sessions, so they become ready together. The reviewers chosen for
 * the production get one open task with a line per product ready for its targets; a line ticks itself once the
 * product is decided or is no longer waiting (taken off development, targets set by hand), and the task closes
 * when every line is done.
 */
class AskToReviewArtefactDevelopment
{
    use AsAction;

    public const string TASK_KIND = 'artefact_development_review';

    public string $commandSignature = 'production:ask-to-review-development';

    public function handle(Production $production): int
    {
        $proposals = [];
        $waiting   = collect();
        foreach (Artefact::where('production_id', $production->id)->where('is_in_development', true)->orderBy('code')->get() as $artefact) {
            $proposals[$artefact->id] = GetArtefactDevelopmentProposal::run($artefact);
            if ($proposals[$artefact->id]['is_ready']) {
                $waiting->put($artefact->id, $artefact);
            }
        }

        $task = self::openTaskFor($production);
        if ($task) {
            foreach ($this->pendingArtefactIds($task) as $artefactId) {
                if (!$waiting->has($artefactId)) {
                    $task = $this->tick($task, $artefactId);
                }
            }
            if ($task->status === StaffTaskStatusEnum::DONE) {
                $task = null;
            }
        }

        $new = $waiting->reject(fn (Artefact $artefact) => $task && in_array($artefact->id, $this->pendingArtefactIds($task)));
        if ($new->isEmpty()) {
            return 0;
        }

        $assistant = GetAikuAssistant::run($production->group_id);
        $lines     = $new->map(fn (Artefact $artefact) => ['title' => self::lineTitle($artefact, $proposals[$artefact->id]), 'status' => 'todo', 'artefact_id' => $artefact->id])->values()->all();

        if ($task) {
            $task = $this->changeLines($task, fn (array $subtasks) => [...$subtasks, ...$lines]);
            SendStaffMessage::run($task->conversation, $assistant, [
                'body' => __('Ready for their targets too: :codes', ['codes' => $new->pluck('code')->implode(', ')]),
            ]);
            BroadcastStaffTaskChanged::dispatch($task);
            SendStaffTaskBadgeUpdateToUsers::run($task->involvedUserIds());

            return $new->count();
        }

        $reviewers = User::whereIn('id', $production->developmentReviewSettings()['reviewer_ids'])->where('status', true)->get()
            ->filter(fn (User $user) => StaffTask::canBeAssigned($user))
            ->values();
        if ($reviewers->isEmpty()) {
            return 0;
        }

        $task = StoreStaffTask::run($assistant, [
            'subject'          => __('Set targets for products paid as development in :production', ['production' => $production->name]),
            'description'      => __('These products have enough runs to set the targets of their steps. Accept, adjust or keep each one on development with a reason; its line ticks itself once decided:')
                .' '.self::reviewUrl($production),
            'assignee_id'      => $reviewers->first()->id,
            'collaborator_ids' => $reviewers->skip(1)->pluck('id')->all(),
            'data'             => ['kind' => self::TASK_KIND, 'production_id' => $production->id],
        ]);

        $this->changeLines($task, fn () => $lines);

        return $new->count();
    }

    public static function openTaskFor(Production $production): ?StaffTask
    {
        return StaffTask::open()
            ->where('data->kind', self::TASK_KIND)
            ->where('data->production_id', $production->id)
            ->first();
    }

    public function tickDecided(Artefact $artefact): void
    {
        $task = self::openTaskFor($artefact->production);
        if ($task && in_array($artefact->id, $this->pendingArtefactIds($task))) {
            $this->tick($task, $artefact->id);
        }
    }

    private function tick(StaffTask $task, int $artefactId): StaffTask
    {
        $task = $this->changeLines($task, fn (array $subtasks) => array_map(
            fn (array $subtask) => ($subtask['artefact_id'] ?? null) === $artefactId ? [...$subtask, 'status' => 'done'] : $subtask,
            $subtasks
        ));

        if (collect($task->data['subtasks'] ?? [])->every(fn (array $subtask) => $subtask['status'] === 'done')) {
            $task->update(['status' => StaffTaskStatusEnum::DONE, 'closed_at' => now()]);
        }

        BroadcastStaffTaskChanged::dispatch($task);
        SendStaffTaskBadgeUpdateToUsers::run($task->involvedUserIds());

        return $task;
    }

    /**
     * @return array<int, int>
     */
    private function pendingArtefactIds(StaffTask $task): array
    {
        return collect($task->data['subtasks'] ?? [])
            ->filter(fn (array $subtask) => $subtask['status'] !== 'done' && isset($subtask['artefact_id']))
            ->pluck('artefact_id')
            ->all();
    }

    private function changeLines(StaffTask $task, callable $change): StaffTask
    {
        return DB::transaction(function () use ($task, $change) {
            $lockedTask = StaffTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $lockedTask->update(['data' => [...($lockedTask->data ?? []), 'subtasks' => array_values($change($lockedTask->data['subtasks'] ?? []))]]);

            return $lockedTask;
        });
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    private static function lineTitle(Artefact $artefact, array $proposal): string
    {
        return collect($proposal['steps'])
            ->where('is_ready', true)
            ->map(fn (array $step) => __(':step :rate/h', ['step' => $step['name'], 'rate' => $step['proposed_target']]))
            ->prepend($artefact->code)
            ->implode(' · ');
    }

    public static function reviewUrl(Production $production, ?Artefact $artefact = null): string
    {
        return route('grp.org.productions.show.crafts.development', array_filter([
            'organisation' => $production->organisation->slug,
            'production'   => $production->slug,
            'tab'          => 'targets',
            'review'       => $artefact?->slug,
        ]));
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        Production::all()->each(fn (Production $production) => $command->info($production->code.': '.$this->handle($production).' asked'));

        return 0;
    }
}
