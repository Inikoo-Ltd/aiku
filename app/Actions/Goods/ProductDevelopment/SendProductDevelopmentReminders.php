<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-19h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentStage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class SendProductDevelopmentReminders
{
    use AsAction;

    public string $commandSignature = 'product-developments:remind';

    public const int REMIND_DAYS_BEFORE = 3;

    /**
     * @return array{reminders: int, digests: int}
     */
    public function handle(?Carbon $today = null): array
    {
        $today        = ($today ?? Carbon::today())->copy()->startOfDay();
        $developments = ProductDevelopment::query()
            ->where('status', ProductDevelopmentStatusEnum::ACTIVE->value)
            ->with(['stages', 'stats', 'owner'])
            ->get();

        $reminders = 0;
        foreach ($developments as $productDevelopment) {
            $reminders += $this->remindDueStages($productDevelopment, $today);
        }

        $digests = 0;
        foreach ($developments->filter(fn (ProductDevelopment $productDevelopment) => $productDevelopment->owner?->status)->groupBy('owner_id') as $owned) {
            $digests += $this->sendDigest($owned, $today);
        }

        return ['reminders' => $reminders, 'digests' => $digests];
    }

    private function remindDueStages(ProductDevelopment $productDevelopment, Carbon $today): int
    {
        $sent     = 0;
        $notifier = NotifyProductDevelopmentUsers::make();

        $dueStages = $productDevelopment->stages->filter(fn (ProductDevelopmentStage $stage) => $stage->state === ProductDevelopmentStageStateEnum::IN_PROGRESS
            && $stage->due_at
            && ($stage->due_at->isSameDay($today->copy()->addDays(self::REMIND_DAYS_BEFORE)) || $stage->due_at->isSameDay($today)));

        foreach ($dueStages as $stage) {
            $missing = (int) ($productDevelopment->stats?->{$stage->role->statsColumn()} ?? 0);
            if ($missing === 0) {
                continue;
            }

            $recipients = $notifier->usersWithRole($productDevelopment, $stage->role);
            if ($recipients->isEmpty()) {
                continue;
            }

            $when = $stage->due_at->isSameDay($today) ? __('today') : $stage->due_at->format('j M');
            $notifier->handle(
                $productDevelopment,
                $recipients,
                UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK,
                __('Reminder: :stage on :code is due :when', ['stage' => ProductDevelopmentStageEnum::labels()[$stage->stage->value], 'code' => $productDevelopment->code, 'when' => $when]),
                trans_choice(':count required item is still missing|:count required items are still missing', $missing, ['count' => $missing]),
            );
            $sent += $recipients->count();
        }

        return $sent;
    }

    /**
     * @param Collection<int, ProductDevelopment> $owned
     */
    private function sendDigest(Collection $owned, Carbon $today): int
    {
        $owner      = $owned->first()->owner;
        $roleLabels = ProductDevelopmentMemberRoleEnum::labels();

        $lines = $owned->map(function (ProductDevelopment $productDevelopment) use ($today, $roleLabels) {
            $currentStages = $productDevelopment->stages->where('position', $productDevelopment->current_position);
            $dueAt         = $currentStages->pluck('due_at')->filter()->min();
            $missingByRole = collect(ProductDevelopmentMemberRoleEnum::cases())
                ->mapWithKeys(fn (ProductDevelopmentMemberRoleEnum $role) => [$roleLabels[$role->value] => (int) ($productDevelopment->stats?->{$role->statsColumn()} ?? 0)])
                ->filter();

            $parts = [
                $productDevelopment->code.' '.$productDevelopment->name,
                ProductDevelopmentStageEnum::positionLabel($productDevelopment->current_position, $productDevelopment->kind),
                $missingByRole->isEmpty() ? __('nothing missing') : $missingByRole->map(fn (int $count, string $role) => "$role $count")->implode(', '),
            ];
            if ($dueAt) {
                $parts[] = $dueAt->lt($today) ? __('OVERDUE since :date', ['date' => $dueAt->format('j M')]) : __('due :date', ['date' => $dueAt->format('j M')]);
            }

            return implode(' · ', $parts);
        });

        NotifyProductDevelopmentUsers::run(
            null,
            [$owner],
            UserNotificationEnum::PRODUCT_DEVELOPMENT_DIGEST,
            trans_choice('Your :count product development today|Your :count product developments today', $owned->count(), ['count' => $owned->count()]),
            $lines->implode("\n"),
        );

        return 1;
    }

    public function asCommand(Command $command): int
    {
        $result = $this->handle();
        $command->info("Reminders sent: {$result['reminders']}, digests sent: {$result['digests']}");

        return 0;
    }
}
