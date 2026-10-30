<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-18h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Tasks\StoreStaffTask;
use App\Actions\Tasks\SyncStaffTaskCollaborators;
use App\Actions\Tasks\UpdateStaffTask;
use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentArtworks;
use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignRounds;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateProductDevelopmentTask
{
    use AsAction;

    public const string SPECS            = 'complete_specs';
    public const string COMPLIANCE_SCOPE = 'confirm_compliance_scope';
    public const string SEND_ARTWORK     = 'send_approved_artwork';
    public const string CONFIRM_ARTWORK  = 'confirm_artwork_received';
    public const string DESIGN_CONCEPT   = 'design_concept';
    public const string DESIGN_ARTWORK   = 'design_artwork';
    public const string SIGN_OFF_BUYER   = 'sign_off_buyer';
    public const string SIGN_OFF_COMPLIANCE = 'sign_off_compliance';

    public const array SIGN_OFF_TASKS = [
        'buyer'      => self::SIGN_OFF_BUYER,
        'compliance' => self::SIGN_OFF_COMPLIANCE,
    ];

    public const array DESIGN_TASKS = [
        'concept'   => self::DESIGN_CONCEPT,
    ];

    private static bool $syncingProgress = false;

    public function handle(ProductDevelopment $productDevelopment, string $key, ?User $requester = null): ?StaffTask
    {
        $definition = $this->definition($productDevelopment, $key);
        $holders    = NotifyProductDevelopmentUsers::make()->usersWithRole($productDevelopment, $definition['role']);

        if ($holders->isEmpty() || $this->existing($productDevelopment, $key)) {
            return null;
        }

        $assignee  = $holders->first();
        $requester ??= $productDevelopment->owner ?? $assignee;

        $task = StoreStaffTask::run($requester, [
            'subject'     => $definition['subject'],
            'description' => $definition['description'],
            'assignee_id' => $assignee->id,
            'due_at'      => $definition['due_at']?->toDateString(),
            'model_type'  => 'ProductDevelopment',
            'model_id'    => $productDevelopment->id,
            'notify'      => false,
            'data'        => [
                'product_development_event' => $key,
                'product_development_role'  => $definition['role']->value,
            ],
        ]);

        $this->syncCollaborators($task, $holders, $requester);

        NotifyProductDevelopmentUsers::run(
            $productDevelopment,
            $holders,
            UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK,
            __(':reference is for you', ['reference' => $task->reference]),
            $definition['subject'],
            url: route('grp.tasks.show', $task->reference),
            except: $requester,
        );

        return $task;
    }

    /**
     * Creates the tasks a development is due now, moves an open task to someone holding its role
     * when it sits with someone who does not, and keeps every other role holder as a collaborator.
     */
    public function ensureForDevelopment(ProductDevelopment $productDevelopment, User $actor): void
    {
        $notifier = NotifyProductDevelopmentUsers::make();

        foreach ([self::SPECS, self::COMPLIANCE_SCOPE, self::SEND_ARTWORK, self::CONFIRM_ARTWORK, ...array_values(self::DESIGN_TASKS), self::DESIGN_ARTWORK, ...array_values(self::SIGN_OFF_TASKS)] as $key) {
            if (!$this->isDue($productDevelopment, $key)) {
                continue;
            }

            $task = $this->existing($productDevelopment, $key);
            if (!$task) {
                $this->handle($productDevelopment, $key, $actor);
                continue;
            }

            if (!$task->status->isOpen()) {
                continue;
            }

            $holders = $notifier->usersWithRole($productDevelopment, $this->definition($productDevelopment, $key)['role']);
            if ($holders->isEmpty()) {
                continue;
            }

            if (!$holders->contains('id', $task->assignee_id)) {
                $task = UpdateStaffTask::run($task, $actor, ['assignee_id' => $holders->first()->id]);
            }

            $this->syncCollaborators($task, $holders, $actor);
        }

        $this->syncProgress($productDevelopment, $actor);
    }

    /**
     * Moves the development's own tasks along with the work done on the development page: a role task
     * starts with the role's first saved item and is done once every required item is filled, a design
     * task follows its round, the artwork tasks close when the development moves past artwork to supplier,
     * and every open task closes with the development. A task closed here reopens if its items go missing
     * again; one closed by hand is left alone.
     */
    public function syncProgress(ProductDevelopment $productDevelopment, User $actor): void
    {
        if (self::$syncingProgress) {
            return;
        }
        self::$syncingProgress = true;

        try {
            $tasks = StaffTask::query()
                ->where('model_type', 'ProductDevelopment')
                ->where('model_id', $productDevelopment->id)
                ->whereNotNull('data->product_development_event')
                ->where('status', '!=', StaffTaskStatusEnum::CANCELLED->value)
                ->get();

            foreach ($tasks as $task) {
                $task->refresh();
                [$status, $note] = $this->progressFor($productDevelopment, $task);
                if (!$status || $status === $task->status) {
                    continue;
                }

                $autoClosed = (bool) ($task->data['closed_by_development'] ?? false);
                if ($task->status === StaffTaskStatusEnum::DONE && !$autoClosed) {
                    continue;
                }

                UpdateStaffTask::run($task, $actor, [
                    'status' => $status->value,
                    'note'   => $note,
                    'data'   => [...($task->data ?? []), 'closed_by_development' => !$status->isOpen()],
                ]);
            }
        } finally {
            self::$syncingProgress = false;
        }
    }

    /**
     * @return array{0: StaffTaskStatusEnum|null, 1: string|null}
     */
    private function progressFor(ProductDevelopment $productDevelopment, StaffTask $task): array
    {
        if ($productDevelopment->status === ProductDevelopmentStatusEnum::COMPLETED) {
            return $task->status->isOpen() ? [StaffTaskStatusEnum::DONE, __('The development is completed')] : [null, null];
        }
        if ($productDevelopment->status === ProductDevelopmentStatusEnum::CANCELLED) {
            return $task->status->isOpen() ? [StaffTaskStatusEnum::CANCELLED, __('The development was cancelled')] : [null, null];
        }

        $key = $task->data['product_development_event'] ?? null;

        if ($key === self::SPECS || $key === self::COMPLIANCE_SCOPE) {
            $role     = $key === self::SPECS ? ProductDevelopmentStageEnum::PRODUCT_SPECS->roleFor($productDevelopment->kind) : ProductDevelopmentMemberRoleEnum::COMPLIANCE;
            $required = $productDevelopment->checklistItems()->where('role', $role->value)->where('required', true)->get();
            $missing  = $required->filter(fn ($checklistItem) => $checklistItem->isMissing())->count();

            if ($required->isEmpty()) {
                return [null, null];
            }
            if ($missing === 0) {
                return [StaffTaskStatusEnum::DONE, $key === self::SPECS ? __('Every required product spec is filled in') : __('Every required compliance item is filled in')];
            }

            return $missing < $required->count() || $task->status === StaffTaskStatusEnum::DONE
                ? [StaffTaskStatusEnum::IN_PROGRESS, $task->status === StaffTaskStatusEnum::DONE ? __(':count required items are missing again', ['count' => $missing]) : null]
                : [null, null];
        }

        if ($round = array_search($key, self::DESIGN_TASKS, true)) {
            $round = ProductDevelopmentDesignRoundEnum::from($round);
            if (ProductDevelopmentDesignRounds::isApproved($productDevelopment, $round)) {
                return [StaffTaskStatusEnum::DONE, __('The :round design is approved', ['round' => mb_strtolower(ProductDevelopmentDesignRoundEnum::labels()[$round->value])])];
            }

            return ProductDevelopmentDesignRounds::hasAnyVersion($productDevelopment, $round) && $task->status === StaffTaskStatusEnum::TODO
                ? [StaffTaskStatusEnum::IN_PROGRESS, null]
                : [null, null];
        }

        if ($key === self::DESIGN_ARTWORK) {
            if (ProductDevelopmentArtworks::isAllApproved($productDevelopment)) {
                return $task->status->isOpen() ? [StaffTaskStatusEnum::DONE, __('Every artwork is approved')] : [null, null];
            }

            if ($task->status === StaffTaskStatusEnum::DONE) {
                return [StaffTaskStatusEnum::IN_PROGRESS, __('An artwork needs approving again')];
            }

            return ProductDevelopmentArtworks::hasAnyArtwork($productDevelopment) && $task->status === StaffTaskStatusEnum::TODO
                ? [StaffTaskStatusEnum::IN_PROGRESS, null]
                : [null, null];
        }

        if ($role = array_search($key, self::SIGN_OFF_TASKS, true)) {
            $signed = !empty(SignOffProductDevelopment::signOffs($productDevelopment)[$role] ?? null);
            if ($signed) {
                return [StaffTaskStatusEnum::DONE, __('Signed off')];
            }

            return $task->status === StaffTaskStatusEnum::DONE && SignOffProductDevelopment::isOpenForSignOff($productDevelopment)
                ? [StaffTaskStatusEnum::IN_PROGRESS, __('The sign-off was withdrawn')]
                : [null, null];
        }

        if (($key === self::SEND_ARTWORK || $key === self::CONFIRM_ARTWORK) && $productDevelopment->current_position > ProductDevelopmentStageEnum::ARTWORK_TO_SUPPLIER->position()) {
            return $task->status->isOpen() ? [StaffTaskStatusEnum::DONE, __('The development moved past artwork to supplier')] : [null, null];
        }

        return [null, null];
    }

    private function isDue(ProductDevelopment $productDevelopment, string $key): bool
    {
        if ($productDevelopment->status !== ProductDevelopmentStatusEnum::ACTIVE) {
            return false;
        }

        $artworkPosition = ProductDevelopmentStageEnum::ARTWORK_TO_SUPPLIER->position();

        return match ($key) {
            self::SPECS, self::COMPLIANCE_SCOPE => $productDevelopment->current_position <= ProductDevelopmentStageEnum::PACK_CONTENT->position(),
            self::SEND_ARTWORK => $productDevelopment->current_position === $artworkPosition,
            self::CONFIRM_ARTWORK => $productDevelopment->kind !== ProductDevelopmentKindEnum::RECIPE
                && $productDevelopment->current_position === $artworkPosition
                && $this->existing($productDevelopment, self::SEND_ARTWORK)?->status === StaffTaskStatusEnum::DONE,
            self::SIGN_OFF_BUYER, self::SIGN_OFF_COMPLIANCE => SignOffProductDevelopment::isOpenForSignOff($productDevelopment),
            self::DESIGN_CONCEPT => !ProductDevelopmentDesignRounds::isSettled($productDevelopment, ProductDevelopmentDesignRoundEnum::CONCEPT),
            self::DESIGN_ARTWORK => !ProductDevelopmentArtworks::isAllApproved($productDevelopment),
            default => false,
        };
    }

    /**
     * Closes the open design task of a round once one of its versions is approved.
     */
    public function completeDesignTask(ProductDevelopment $productDevelopment, ProductDevelopmentDesignRoundEnum $round, User $actor): void
    {
        if (!ProductDevelopmentDesignRounds::isApproved($productDevelopment, $round)) {
            return;
        }

        $task = $this->existing($productDevelopment, self::DESIGN_TASKS[$round->value]);
        if ($task && $task->status->isOpen()) {
            UpdateStaffTask::run($task, $actor, [
                'status' => StaffTaskStatusEnum::DONE->value,
                'note'   => __('The :round design is approved', ['round' => mb_strtolower(ProductDevelopmentDesignRoundEnum::labels()[$round->value])]),
                'data'   => [...($task->data ?? []), 'closed_by_development' => true],
            ]);
        }
    }

    private function existing(ProductDevelopment $productDevelopment, string $key): ?StaffTask
    {
        return StaffTask::query()
            ->where('model_type', 'ProductDevelopment')
            ->where('model_id', $productDevelopment->id)
            ->where('data->product_development_event', $key)
            ->where('status', '!=', StaffTaskStatusEnum::CANCELLED->value)
            ->latest('id')
            ->first();
    }

    /**
     * @param Collection<int, User> $holders
     */
    private function syncCollaborators(StaffTask $task, Collection $holders, User $actor): void
    {
        $others = $holders->pluck('id')->reject(fn (int $id) => $id === $task->assignee_id)->values();
        $wanted = $task->collaborators()->pluck('users.id')->map(fn ($id) => (int) $id)->merge($others)->unique()->values()->all();

        if (array_diff($wanted, $task->collaborators()->pluck('users.id')->map(fn ($id) => (int) $id)->all())) {
            SyncStaffTaskCollaborators::run($task, $wanted, $actor);
        }
    }

    /**
     * @return array{role: ProductDevelopmentMemberRoleEnum, subject: string, description: string, due_at: Carbon|null}
     */
    private function definition(ProductDevelopment $productDevelopment, string $key): array
    {
        $stageDue = function (ProductDevelopmentStageEnum $stage) use ($productDevelopment): ?Carbon {
            $date = $productDevelopment->stages()->where('stage', $stage->value)->value('due_at');

            return $date ? Carbon::parse($date) : null;
        };
        $family = $productDevelopment->name;
        $code   = $productDevelopment->code;

        return match ($key) {
            self::SPECS => [
                'role'        => ProductDevelopmentStageEnum::PRODUCT_SPECS->roleFor($productDevelopment->kind),
                'subject'     => __('Complete specs for :family', ['family' => $family]),
                'description' => __('Fill in the product specs of every SKU in :code (sizes, weights, cartons, country of origin) and upload the factory photos.', ['code' => $code]),
                'due_at'      => $stageDue(ProductDevelopmentStageEnum::PRODUCT_SPECS),
            ],
            self::COMPLIANCE_SCOPE => [
                'role'        => ProductDevelopmentMemberRoleEnum::COMPLIANCE,
                'subject'     => __('Confirm category and required documents for :family', ['family' => $family]),
                'description' => __('Check the product category of :code and which documents (SDS, IFRA, CPSR, test reports) each SKU needs.', ['code' => $code]),
                'due_at'      => $stageDue(ProductDevelopmentStageEnum::PACK_CONTENT),
            ],
            self::SEND_ARTWORK => [
                'role'        => ProductDevelopmentMemberRoleEnum::PURCHASING,
                'subject'     => __('Send approved artwork for :family', ['family' => $family]),
                'description' => __('The artwork of :code is approved and locked: send it to the supplier, then mark this task done.', ['code' => $code]),
                'due_at'      => $stageDue(ProductDevelopmentStageEnum::ARTWORK_TO_SUPPLIER),
            ],
            self::DESIGN_CONCEPT => $this->designDefinition($productDevelopment, $key, $stageDue),
            self::DESIGN_ARTWORK => [
                'role'        => ProductDevelopmentMemberRoleEnum::DESIGNER,
                'subject'     => __('Make the artwork of :family', ['family' => $family]),
                'description' => __('In the Design tab of :code, upload or build the artwork of every SKU in each design section and send it for review.', ['code' => $code]),
                'due_at'      => $stageDue(ProductDevelopmentStageEnum::ARTWORK),
            ],
            self::SIGN_OFF_BUYER, self::SIGN_OFF_COMPLIANCE => [
                'role'        => ProductDevelopmentMemberRoleEnum::from(array_search($key, self::SIGN_OFF_TASKS, true)),
                'subject'     => __('Sign off the final artwork of :family', ['family' => $family]),
                'description' => __('Check the approved concept and artwork of :code and sign them off on the development page. Once the buyer and compliance have both signed, the artwork is locked.', ['code' => $code]),
                'due_at'      => $stageDue(ProductDevelopmentStageEnum::FINAL_APPROVAL),
            ],
            self::CONFIRM_ARTWORK => [
                'role'        => ProductDevelopmentMemberRoleEnum::AGENT,
                'subject'     => __('Confirm factory received artwork for :family', ['family' => $family]),
                'description' => __('Purchasing sent the approved artwork of :code to the supplier: confirm the factory has received it.', ['code' => $code]),
                'due_at'      => Carbon::today()->addDays(2),
            ],
        };
    }

    /**
     * @return array{role: ProductDevelopmentMemberRoleEnum, subject: string, description: string, due_at: Carbon|null}
     */
    private function designDefinition(ProductDevelopment $productDevelopment, string $key, callable $stageDue): array
    {
        $round = ProductDevelopmentDesignRoundEnum::from(array_search($key, self::DESIGN_TASKS, true));
        $label = ProductDevelopmentDesignRoundEnum::labels()[$round->value];

        return [
            'role'        => ProductDevelopmentMemberRoleEnum::DESIGNER,
            'subject'     => __('Design the :round of :family', ['round' => mb_strtolower($label), 'family' => $productDevelopment->name]),
            'description' => __('Open the design of :code, upload the :round and send it for review.', ['code' => $productDevelopment->code, 'round' => mb_strtolower($label)]),
            'due_at'      => $stageDue($round->stage()),
        ];
    }
}
