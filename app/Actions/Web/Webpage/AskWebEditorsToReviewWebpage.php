<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\SendStaffTaskBadgeUpdateToUsers;
use App\Actions\Tasks\StoreStaffTask;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Events\BroadcastStaffTaskChanged;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A change to a live webpage that is not published yet becomes a line on its shop's one open
 * "To review & publish" task, so it gets checked and published instead of sitting there. The task
 * goes to the shop's shopkeeper, or the webmasters, never to whoever made the change, and publishing
 * the page ticks its line; the task closes once every line is published.
 */
class AskWebEditorsToReviewWebpage
{
    use AsAction;

    public const string TASK_KIND = 'webpage_review';

    public function handle(Webpage $webpage, User $changer): ?StaffTask
    {
        if ($webpage->state !== WebpageStateEnum::LIVE || !$webpage->shop_id) {
            return null;
        }

        $openTask = $this->openTaskFor($webpage->shop_id);

        if ($openTask && $this->hasPendingLine($openTask, $webpage)) {
            return $openTask;
        }

        if ($openTask) {
            $openTask = $this->changeSubtasks($openTask, fn (array $subtasks) => [...$subtasks, ['title' => $this->lineTitle($webpage), 'status' => 'todo']]);
            SendStaffMessage::run($openTask->conversation, GetAikuAssistant::run($webpage->group_id), [
                'body' => __(':user changed :page, it is waiting to be published', ['user' => $changer->chatName(), 'page' => $this->lineTitle($webpage)]),
            ]);
            BroadcastStaffTaskChanged::dispatch($openTask);
            SendStaffTaskBadgeUpdateToUsers::run($openTask->involvedUserIds());

            return $openTask;
        }

        $shop = $webpage->shop;

        return StoreStaffTask::run(GetAikuAssistant::run($webpage->group_id), [
            'subject'     => __('Review & publish page changes in :shop', ['shop' => $shop->name]),
            'description' => __('These pages were changed and the changes are not live yet. Check each one and publish it, its line ticks itself once it is published.')
                ."\n\n".__(':user changed :page', ['user' => $changer->chatName(), 'page' => $this->lineTitle($webpage)]),
            'subtasks'    => [['title' => $this->lineTitle($webpage), 'status' => 'todo']],
            ...$this->assignment($shop, $changer),
            'data'        => ['kind' => self::TASK_KIND, 'shop_id' => $shop->id],
        ]);
    }

    public function tickPublished(Webpage $webpage): void
    {
        if (!$webpage->shop_id) {
            return;
        }

        $task = $this->openTaskFor($webpage->shop_id);

        if (!$task || !$this->hasPendingLine($task, $webpage)) {
            return;
        }

        $task = $this->changeSubtasks($task, fn (array $subtasks) => collect($subtasks)
            ->map(fn (array $subtask) => $subtask['status'] !== 'done' && $this->isLineOf($subtask, $webpage) ? [...$subtask, 'status' => 'done'] : $subtask)
            ->all());

        if (collect($task->data['subtasks'] ?? [])->every(fn (array $subtask) => $subtask['status'] === 'done')) {
            $task->update(['status' => StaffTaskStatusEnum::DONE, 'closed_at' => now()]);
        }

        BroadcastStaffTaskChanged::dispatch($task);
        SendStaffTaskBadgeUpdateToUsers::run($task->involvedUserIds());
    }

    /**
     * The shop's shopkeeper in charge checks and publishes, unless they made the change themselves;
     * then, or when the shop has no shopkeeper, the webmasters do.
     *
     * @return array{assignee_id?: int, department?: string}
     */
    private function assignment(Shop $shop, User $changer): array
    {
        $shopkeeper = User::where('id', data_get($shop->settings, 'catalog.shopkeeper_in_charge_id'))->where('status', true)->first();

        if ($shopkeeper && $shopkeeper->id !== $changer->id && StaffTask::canBeAssigned($shopkeeper)) {
            return ['assignee_id' => $shopkeeper->id];
        }

        return ['department' => 'webmaster'];
    }

    private function openTaskFor(int $shopId): ?StaffTask
    {
        return StaffTask::open()
            ->where('data->kind', self::TASK_KIND)
            ->where('data->shop_id', $shopId)
            ->first();
    }

    private function hasPendingLine(StaffTask $task, Webpage $webpage): bool
    {
        return collect($task->data['subtasks'] ?? [])->contains(fn (array $subtask) => $subtask['status'] !== 'done' && $this->isLineOf($subtask, $webpage));
    }

    /**
     * @param array{title: string, status: string} $subtask
     */
    private function isLineOf(array $subtask, Webpage $webpage): bool
    {
        return str_starts_with($subtask['title'], $webpage->code.' · ');
    }

    private function lineTitle(Webpage $webpage): string
    {
        return mb_substr($webpage->code.' · '.($webpage->title ?: $webpage->url), 0, 255);
    }

    /**
     * @param callable(array<int, array{title: string, status: string}>): array<int, array{title: string, status: string}> $change
     */
    private function changeSubtasks(StaffTask $task, callable $change): StaffTask
    {
        return DB::transaction(function () use ($task, $change) {
            $lockedTask = StaffTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            $lockedTask->update([
                'data' => [
                    ...($lockedTask->data ?? []),
                    'subtasks' => array_values($change($lockedTask->data['subtasks'] ?? [])),
                ],
            ]);

            return $lockedTask;
        });
    }
}
