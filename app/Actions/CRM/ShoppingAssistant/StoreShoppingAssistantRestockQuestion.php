<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Actions\Tasks\UpdateStaffTask;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Models\Catalogue\Product;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A customer asked the AI shopping assistant when a product is back in stock, our orders give no
 * reliable date (GetProductRestockEstimate), and they said yes to an answer from our purchasing team.
 * The question becomes a task for the organisation's products department, linked to the product,
 * with the customer, their question, what our orders show and the conversation. It goes to the
 * people set in the organisation settings (restock_questions.user_ids), else to its buyers (job
 * position buy), who are told on the channels they chose and reminded while nobody takes it.
 * The note written when the task is marked done is the answer: FollowUpShoppingAssistantRestockQuestion
 * sends it to the customer. Off when the organisation switched restock questions off (on by default).
 */
class StoreShoppingAssistantRestockQuestion
{
    use AsAction;

    public const string TASK_KIND = 'shopping_assistant_restock_question';

    public const string DEPARTMENT = 'products';

    public const string BUYER_POSITION = 'buy';

    /**
     * @return array{status: 'created'|'updated'|'off', task: ?StaffTask}
     */
    public function handle(ShoppingAssistantConversation $conversation, ?Product $product, string $question): array
    {
        $customer     = $conversation->customer;
        $organisation = $customer->organisation;
        $assistant    = GetAikuAssistant::run($customer->group_id);

        if (!self::isOn($organisation) || self::recipientsOf($organisation)->isEmpty()) {
            return ['status' => 'off', 'task' => null];
        }

        $question = mb_substr(trim($question), 0, 1000);
        $open     = StaffTask::open()
            ->where('data->kind', self::TASK_KIND)
            ->where('data->customer_id', $customer->id)
            ->where('data->product_id', $product?->id)
            ->latest('id')
            ->first();

        if ($open) {
            return ['status' => 'updated', 'task' => UpdateStaffTask::run($open, $assistant, ['note' => __('The customer asked again: :question', ['question' => $question])])];
        }

        $estimate = $product ? GetProductRestockEstimate::run($product) : null;
        $explicit = self::chosenRecipientsOf($organisation);

        $task = StoreStaffTask::run($assistant, [
            'subject'          => $product
                ? __('Restock question from :customer: when is :product back?', ['customer' => $customer->name, 'product' => $product->code])
                : __('Restock question from :customer', ['customer' => $customer->name]),
            'description'      => implode("\n", array_filter([
                __('A customer asked the AI shopping assistant when stock is coming and our orders give no reliable date. Write the answer for the customer in the note when you mark this task done: it is translated into their language and sent to them by email.'),
                __('Customer: :name (:reference)', ['name' => $customer->name, 'reference' => $customer->reference]).' '.route('grp.org.shops.show.crm.customers.show', [$organisation->slug, $customer->shop->slug, $customer->slug]),
                $product ? __('Product: :code :name', ['code' => $product->code, 'name' => $product->name]) : null,
                __('Question: :question', ['question' => $question]),
                $estimate ? __('What our orders show: :status', ['status' => $estimate['status'] === 'on_order_no_date' ? __('on order, with no expected date still ahead') : __('nothing on order in the last :months months', ['months' => GetProductRestockEstimate::RECENT_MONTHS])]) : null,
                $conversation->website ? __('Conversation on :website:', ['website' => $conversation->website->domain]).' '.route('grp.org.shops.show.web.analytics.shopping_assistant.conversations.show', [$organisation->slug, $customer->shop->slug, $conversation->website->slug, $conversation->ulid]) : null,
            ])),
            'department'       => self::DEPARTMENT,
            'collaborator_ids' => $explicit->pluck('id')->all(),
            'priority'         => ChatPriorityEnum::NORMAL->value,
            'model_type'       => $product ? 'Product' : 'Customer',
            'model_id'         => $product?->id ?? $customer->id,
            'notify'           => false,
            'data'             => [
                'kind'              => self::TASK_KIND,
                'organisation_id'   => $organisation->id,
                'shop_id'           => $customer->shop_id,
                'website_id'        => $conversation->website_id,
                'conversation_id'   => $conversation->id,
                'conversation_ulid' => $conversation->ulid,
                'customer_id'       => $customer->id,
                'customer_name'     => $customer->name,
                'product_id'        => $product?->id,
                'product_name'      => $product?->name,
                'question'          => $question,
                'estimate'          => $estimate['status'] ?? null,
            ],
        ]);

        AlertShoppingAssistantRestockQuestion::dispatch($task, false);

        return ['status' => 'created', 'task' => $task];
    }

    public static function isOn(Organisation $organisation): bool
    {
        return (bool) Arr::get($organisation->settings, 'restock_questions.enabled', true);
    }

    /**
     * @return Collection<int, User>
     */
    public static function recipientsOf(Organisation $organisation): Collection
    {
        $chosen = self::chosenRecipientsOf($organisation);
        if ($chosen->isNotEmpty()) {
            return $chosen;
        }

        $positions = DB::table('job_positions')
            ->where('group_id', $organisation->group_id)
            ->where('code', self::BUYER_POSITION)
            ->where(fn ($query) => $query->whereNull('organisation_id')->orWhere('organisation_id', $organisation->id))
            ->select('id');

        return User::where('group_id', $organisation->group_id)
            ->where('status', true)
            ->where(fn ($query) => $query
                ->whereIn('id', DB::table('user_has_pseudo_job_positions')->whereIn('job_position_id', $positions)->select('user_id'))
                ->orWhereIn('id', DB::table('user_has_models')->where('model_type', 'Employee')
                    ->whereIn('model_id', DB::table('employee_has_job_positions')->whereIn('job_position_id', $positions)->select('employee_id'))
                    ->select('user_id')))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private static function chosenRecipientsOf(Organisation $organisation): Collection
    {
        return User::whereIn('id', Arr::get($organisation->settings, 'restock_questions.user_ids', []))->where('status', true)->get();
    }
}
