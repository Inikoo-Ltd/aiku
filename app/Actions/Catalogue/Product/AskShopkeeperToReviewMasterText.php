<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Masters\MasterAsset\PropagateMasterContentToProducts;
use App\Actions\Tasks\SendStaffTaskBadgeUpdateToUsers;
use App\Actions\Tasks\StoreStaffTask;
use App\Events\BroadcastStaffTaskChanged;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A master text change leaves a shop in another language with products to re-read. Its shopkeeper
 * gets one open task per shop with a sub task per product, later changes pile onto that task, and
 * a product's sub task ticks itself once someone has written its text.
 */
class AskShopkeeperToReviewMasterText
{
    use AsAction;

    public const string TASK_KIND = 'master_text_review';

    /**
     * @param Collection<int, Product> $products
     * @param array<int, string> $changedFields
     */
    public function handle(Shop $shop, Collection $products, array $changedFields, User $requester): StaffTask
    {
        $fieldsLabel = $this->fieldsLabel($changedFields);
        $lines       = $products->map(fn (Product $product) => $this->subtaskTitle($product, $fieldsLabel))->values();

        $openTask = $this->openTaskFor($shop);

        if ($openTask) {
            $openTask = $this->changeSubtasks($openTask, function (array $subtasks) use ($products, $fieldsLabel, $changedFields) {
                foreach ($products as $product) {
                    $pendingIndex = collect($subtasks)->search(fn (array $subtask) => $subtask['status'] !== 'done' && $this->isSubtaskOf($subtask, $product));

                    if ($pendingIndex === false) {
                        $subtasks[] = ['title' => $this->subtaskTitle($product, $fieldsLabel), 'status' => 'todo'];
                    } else {
                        $subtasks[$pendingIndex]['title'] = $this->subtaskTitle($product, $this->mergedFieldsLabel($subtasks[$pendingIndex]['title'], $changedFields));
                    }
                }

                return $subtasks;
            });

            SendStaffMessage::run($openTask->conversation, $requester, ['body' => __('The master text changed again:')."\n".$lines->implode("\n")]);
            BroadcastStaffTaskChanged::dispatch($openTask);
            SendStaffTaskBadgeUpdateToUsers::run($openTask->involvedUserIds());

            return $openTask;
        }

        $shopkeeper = User::where('id', data_get($shop->settings, 'catalog.shopkeeper_in_charge_id'))->where('status', true)->first();
        $assignment = $shopkeeper && StaffTask::canBeAssigned($shopkeeper) ? ['assignee_id' => $shopkeeper->id] : ['department' => 'products'];

        $task = StoreStaffTask::make()->action($requester, [
            'subject'     => __('Review master text changes in :shop', ['shop' => $shop->name]),
            'description' => __('The master changed the text of these products. This shop writes its own text, so please read the new master text and update yours:')
                ."\n\n".$lines->implode("\n")
                ."\n\n".$this->reviewListUrl($shop),
            'model_type'  => 'Product',
            'model_id'    => $products->first()->id,
            'subtasks'    => $lines->map(fn (string $title) => ['title' => $title, 'status' => 'todo'])->all(),
            ...$assignment,
        ]);
        $task->update(['data' => array_merge($task->data ?? [], ['kind' => self::TASK_KIND, 'shop_id' => $shop->id])]);

        return $task;
    }

    public function tickReviewed(Product $product): void
    {
        foreach (PropagateMasterContentToProducts::REVIEW_FLAGS as $reviewFlag) {
            if ($product->{$reviewFlag} === false) {
                return;
            }
        }

        $task = $this->openTaskFor($product->shop);
        if (!$task) {
            return;
        }

        $isPendingFor = fn (array $subtask) => $subtask['status'] !== 'done' && $this->isSubtaskOf($subtask, $product);

        if (!collect($task->data['subtasks'] ?? [])->contains($isPendingFor)) {
            return;
        }

        $task = $this->changeSubtasks($task, fn (array $subtasks) => collect($subtasks)
            ->map(fn (array $subtask) => $isPendingFor($subtask) ? [...$subtask, 'status' => 'done'] : $subtask)
            ->all());

        BroadcastStaffTaskChanged::dispatch($task);
        SendStaffTaskBadgeUpdateToUsers::run($task->involvedUserIds());
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

    private function openTaskFor(Shop $shop): ?StaffTask
    {
        return StaffTask::open()
            ->where('data->kind', self::TASK_KIND)
            ->where('data->shop_id', $shop->id)
            ->first();
    }

    /**
     * @param array{title: string, status: string} $subtask
     */
    private function isSubtaskOf(array $subtask, Product $product): bool
    {
        return str_starts_with($subtask['title'], $product->code.' · ');
    }

    private function subtaskTitle(Product $product, string $fieldsLabel): string
    {
        return mb_substr($product->code.' · '.$fieldsLabel, 0, 255);
    }

    /**
     * @param array<int, string> $changedFields
     */
    private function fieldsLabel(array $changedFields): string
    {
        $labels = [
            'name'              => __('Name'),
            'description_title' => __('Description title'),
            'description'       => __('Description'),
            'description_extra' => __('Extra description'),
        ];

        return collect($labels)->only($changedFields)->implode(', ');
    }

    /**
     * @param array<int, string> $changedFields
     */
    private function mergedFieldsLabel(string $existingTitle, array $changedFields): string
    {
        $existingLabels = explode(', ', explode(' · ', $existingTitle, 2)[1] ?? '');

        return collect($existingLabels)->merge(explode(', ', $this->fieldsLabel($changedFields)))->filter()->unique()->implode(', ');
    }

    private function reviewListUrl(Shop $shop): string
    {
        return route('grp.org.shops.show.catalogue.products.all_products.index', [
            'organisation'   => $shop->organisation->slug,
            'shop'           => $shop->slug,
            'index_elements' => ['state' => 'needs_content_review'],
        ]);
    }
}
