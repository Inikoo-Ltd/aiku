<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\OrgAction;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\SendStaffTaskBadgeUpdateToUsers;
use App\Actions\Tasks\StoreStaffTask;
use App\Events\BroadcastStaffTaskChanged;
use App\Models\Production\Artefact;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * HELP-4636 (points 6-7): an artisan tells the Production Manager from the tablet that the steps of a product in
 * development are not right. Every feedback goes to one open task per product for the first reviewer chosen in
 * the development settings, as a line and a message. While that task is open the targets cannot be accepted
 * (DecideArtefactDevelopmentReview); the Production Manager closes it once the recipe is right.
 */
class StoreArtefactDevelopmentFeedback extends OrgAction
{
    public const string TASK_KIND = 'artefact_development_feedback';

    public const array REASONS = ['missing_step', 'step_not_needed', 'wrong_order', 'material_wrong'];

    private Artefact $artefact;

    /**
     * @return array<string, string>
     */
    public static function reasonLabels(): array
    {
        return [
            'missing_step'    => __('A step is missing'),
            'step_not_needed' => __('A step is not needed'),
            'wrong_order'     => __('Steps in the wrong order'),
            'material_wrong'  => __('A material or packaging is missing or wrong'),
        ];
    }

    /**
     * @param  array{reasons?: array<int, string>, comment?: string|null}  $modelData
     */
    public function handle(Artefact $artefact, User $user, array $modelData): StaffTask
    {
        $line = Str::limit(trim($user->chatName().': '.collect($modelData['reasons'] ?? [])->map(fn (string $reason) => self::reasonLabels()[$reason])
            ->push(trim($modelData['comment'] ?? ''))->filter()->implode(' · ')), 255);

        $task = DB::transaction(function () use ($artefact, $user, $line) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', [self::TASK_KIND.':'.$artefact->id]);

            $task = self::openTaskFor($artefact);
            if (!$task) {
                return StoreStaffTask::run($user, [
                    'subject'     => __('Feedback from the floor on :code, a product in development', ['code' => $artefact->code]),
                    'description' => __('Artisans say the steps of :code are not right yet. Fix the recipe and close this task: its targets cannot be accepted while it is open.', ['code' => $artefact->code])
                        .' '.AskToReviewArtefactDevelopment::reviewUrl($artefact->production, $artefact),
                    'assignee_id' => self::managerOf($artefact)->id,
                    'subtasks'    => [['title' => $line]],
                    'data'        => ['kind' => self::TASK_KIND, 'artefact_id' => $artefact->id, 'production_id' => $artefact->production_id],
                ]);
            }

            $task = StaffTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $task->update(['data' => [...$task->data, 'subtasks' => [...($task->data['subtasks'] ?? []), ['title' => $line, 'status' => 'todo']]]]);

            return $task;
        });

        if (!$task->wasRecentlyCreated) {
            SendStaffMessage::run($task->conversation, $user, ['body' => $line]);
            BroadcastStaffTaskChanged::dispatch($task);
            SendStaffTaskBadgeUpdateToUsers::run($task->involvedUserIds());
        }

        $reviewTask = AskToReviewArtefactDevelopment::openTaskFor($artefact->production);
        if ($reviewTask && collect($reviewTask->data['subtasks'] ?? [])->contains(fn (array $subtask) => ($subtask['artefact_id'] ?? null) === $artefact->id && $subtask['status'] !== 'done')) {
            SendStaffMessage::run($reviewTask->conversation, GetAikuAssistant::run($artefact->group_id), [
                'body' => __('Feedback from the floor on :code (:reference): :line. Its targets cannot be saved until :reference is closed.', ['code' => $artefact->code, 'reference' => $task->reference, 'line' => $line]),
            ]);
        }

        return $task;
    }

    public static function openTaskFor(Artefact $artefact): ?StaffTask
    {
        return StaffTask::open()
            ->where('data->kind', self::TASK_KIND)
            ->where('data->artefact_id', $artefact->id)
            ->first();
    }

    private static function managerOf(Artefact $artefact): ?User
    {
        $reviewerIds = $artefact->production->developmentReviewSettings()['reviewer_ids'];

        return User::whereIn('id', $reviewerIds)->where('status', true)->get()
            ->sortBy(fn (User $user) => array_search($user->id, $reviewerIds))
            ->first(fn (User $user) => StaffTask::canBeAssigned($user));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
        ]);
    }

    public function rules(): array
    {
        return [
            'reasons'   => ['sometimes', 'array'],
            'reasons.*' => ['string', Rule::in(self::REASONS)],
            'comment'   => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->artefact->is_in_development) {
            $validator->errors()->add('reasons', __(':code is no longer in development', ['code' => $this->artefact->code]));
        } elseif (!$this->get('reasons') && !trim((string) $this->get('comment'))) {
            $validator->errors()->add('reasons', __('Pick what is wrong or write a comment'));
        } elseif (!self::openTaskFor($this->artefact) && !self::managerOf($this->artefact)) {
            $validator->errors()->add('reasons', __('Nobody is set to receive this feedback yet, please tell your manager'));
        }
    }

    public function action(Artefact $artefact, User $user, array $modelData): StaffTask
    {
        $this->asAction = true;
        $this->artefact = $artefact;
        $this->initialisationFromProduction($artefact->production, $modelData);

        return $this->handle($artefact, $user, $this->validatedData);
    }

    public function asController(Artefact $artefact, ActionRequest $request): StaffTask
    {
        $this->artefact = $artefact;
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $request->user(), $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
