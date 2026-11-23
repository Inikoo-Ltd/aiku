<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Actions\Chat\ChatSession\PlanChatClaim;
use App\Actions\Comms\Email\SendCustomerClaimEmail;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Actions\Tasks\UpdateStaffTask;
use App\Enums\CRM\CustomerClaim\CustomerClaimKindEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimSourceEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * A claim the customer makes themselves (HELP-4869). Everything the form sends is checked again
 * here: the order is theirs and inside the claim window, each quantity is still claimable, the
 * outcome is allowed for the order and the lines, and photos are there where the reason needs
 * them. A claim under the shop's auto-approve limits is settled at once with the Claim panel's
 * actions; anything else waits for customer services, who get a task.
 */
class StoreCustomerClaim
{
    use AsAction;

    public const string REVIEW_TASK_KIND = 'customer_claim_review';

    public const string REVIEW_DEPARTMENT = 'customer-services';

    /**
     * @param  array{resolution: string, comment?: string|null, lines: array<int, array{transaction_id: int, quantity: float|int|string, reason: string, photos?: array<int, UploadedFile>}>, box_photo?: UploadedFile|null}  $data
     *
     * @throws \Throwable
     */
    public function handle(Customer $customer, Order $order, array $data, CustomerClaimSourceEnum $source, ?ShoppingAssistantConversation $conversation = null): CustomerClaim
    {
        $lock = Cache::lock('customer-claim-customer-'.$customer->id, 30);
        if (!$lock->get()) {
            throw ValidationException::withMessages(['lines' => __('A claim of yours is being sent. Please wait a moment.')]);
        }

        try {
            $form = GetCustomerClaimForm::run($customer, $order);
            if (!$form) {
                throw ValidationException::withMessages(['order' => __('This order cannot be claimed on.')]);
            }
            if ($form['too_old']) {
                throw ValidationException::withMessages(['order' => __('Claims are taken for :days days after an order is dispatched.', ['days' => $form['max_age_days']])]);
            }
            if (!in_array($data['resolution'], $form['outcomes'], true)) {
                throw ValidationException::withMessages(['resolution' => __('This outcome is not available for this order.')]);
            }

            $formLines = collect($form['lines'])->keyBy('transaction_id');
            $items     = [];
            $needsBox  = false;

            foreach ($data['lines'] as $index => $line) {
                $formLine = $formLines->get((int) $line['transaction_id']);
                $reason   = DeliveryNoteItemReplacementReasonEnum::tryFrom((string) $line['reason']);

                if (!$formLine || !$reason || !in_array($reason, GetCustomerClaimForm::CUSTOMER_REASONS, true) || (float) $line['quantity'] < 1 || floor((float) $line['quantity']) != (float) $line['quantity']) {
                    throw ValidationException::withMessages(["lines.$index" => __('This line cannot be claimed.')]);
                }
                if ((float) $line['quantity'] > $formLine['claimable']) {
                    throw ValidationException::withMessages(["lines.$index" => __('Only :count of :code can be claimed.', ['count' => $formLine['claimable'], 'code' => $formLine['code']])]);
                }
                if ($data['resolution'] === 'replacement' && !$formLine['can_replace']) {
                    throw ValidationException::withMessages(["lines.$index" => __(':code is out of stock, so it cannot be replaced now. Please choose credit or your next order.', ['code' => $formLine['code']])]);
                }
                if (in_array($reason, GetCustomerClaimForm::PHOTO_REASONS, true)) {
                    $needsBox = true;
                    if (empty($line['photos'])) {
                        throw ValidationException::withMessages(["lines.$index.photos" => __('Please add a photo of :code.', ['code' => $formLine['code']])]);
                    }
                }

                $items[] = ['code' => $formLine['code'], 'quantity' => (float) $line['quantity'], 'reason' => $reason->value];
            }

            if ($needsBox && empty($data['box_photo'])) {
                throw ValidationException::withMessages(['box_photo' => __('Please add a photo of the outer box.')]);
            }

            $plan = PlanChatClaim::make()->planOrder($order, $data['resolution'], $items);
            if ($plan['error']) {
                throw ValidationException::withMessages(['lines' => __('Some of these items are no longer left to claim on this order.')]);
            }
            $value = $data['resolution'] === 'refund_to_balance' ? $plan['amount'] : PlanChatClaim::make()->planOrder($order, 'refund_to_balance', $items)['amount'];

            $claim = $this->createRecord($customer, $order, $plan, $data, $source, $conversation, $value);
        } finally {
            $lock->release();
        }

        if ($this->autoApproves($claim)) {
            $settled = null;
            try {
                $settled = SettleCustomerClaim::run($claim);
            } catch (Throwable $exception) {
                Log::warning('Customer claim '.$claim->reference.' could not be settled automatically, sent to review: '.$exception->getMessage());
            }
            if ($settled) {
                SendCustomerClaimEmail::run($settled);

                return $settled;
            }
        }

        try {
            $this->askCustomerServices($claim);
        } catch (Throwable $exception) {
            report($exception);
        }
        SendCustomerClaimEmail::run($claim);

        return $claim;
    }

    /**
     * The customer-services task asking to review the claim is done once a person approved or
     * rejected it.
     */
    public static function closeReviewTasks(CustomerClaim $claim, User $reviewer): void
    {
        try {
            StaffTask::where('model_type', 'CustomerClaim')->where('model_id', $claim->id)
                ->whereIn('status', [StaffTaskStatusEnum::TODO, StaffTaskStatusEnum::IN_PROGRESS])
                ->get()
                ->each(fn (StaffTask $task) => UpdateStaffTask::make()->handle($task, $reviewer, ['status' => StaffTaskStatusEnum::DONE->value]));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The claim, its lines and photos, in one transaction, waiting for review. Customers' and staff's
     * claims are both made here.
     *
     * @param  array<string, mixed>  $plan  from PlanChatClaim::planOrder
     * @param  array<string, mixed>  $data
     */
    public function createRecord(Customer $customer, Order $order, array $plan, array $data, CustomerClaimSourceEnum $source, ?ShoppingAssistantConversation $conversation, float $value, CustomerClaimKindEnum $kind = CustomerClaimKindEnum::ITEMS): CustomerClaim
    {
        return DB::transaction(function () use ($customer, $order, $data, $source, $conversation, $plan, $value, $kind) {
            $claim = CustomerClaim::create([
                'ulid'                               => (string) Str::ulid(),
                'group_id'                           => $order->group_id,
                'organisation_id'                    => $order->organisation_id,
                'shop_id'                            => $order->shop_id,
                'customer_id'                        => $customer->id,
                'order_id'                           => $order->id,
                'shopping_assistant_conversation_id' => $conversation?->id,
                'reference'                          => self::reference($customer),
                'kind'                               => $kind,
                'source'                             => $source,
                'state'                              => CustomerClaimStateEnum::IN_REVIEW,
                'resolution'                         => $data['resolution'],
                'amount'                             => $data['resolution'] === 'refund_to_balance' ? $plan['amount'] : 0,
                'value'                              => $value,
                'currency_code'                      => $plan['currency'],
                'comment'                            => isset($data['comment']) ? mb_substr(trim((string) $data['comment']), 0, 2000) : null,
                'result'                             => isset($data['percentage']) && (float) $data['percentage'] < 100 ? ['percentage' => (float) $data['percentage']] : null,
            ]);

            foreach ($plan['claimed'] as $claimed) {
                $claim->lines()->create([
                    'transaction_id'      => $claimed['transaction_id'],
                    'code'                => $claimed['code'],
                    'name'                => $claimed['name'],
                    'quantity'            => $claimed['quantity'],
                    'reason'              => $claimed['reason'],
                    'delivery_note_items' => $claimed['items'],
                ]);
            }

            $this->storePhotos($claim, $data);

            return $claim;
        });
    }

    /**
     * Shop code, customer reference and the customer's claim number, counting up with each claim
     * (UK-754401-3). Made under the customer's claim lock.
     */
    public static function reference(Customer $customer): string
    {
        return mb_strtoupper((string) $customer->shop->code).'-'.$customer->reference.'-'.(CustomerClaim::where('customer_id', $customer->id)->count() + 1);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storePhotos(CustomerClaim $claim, array $data): void
    {
        $photos = collect($data['lines'] ?? [])
            ->flatMap(fn (array $line) => collect(array_slice($line['photos'] ?? [], 0, GetCustomerClaimForm::MAX_PHOTOS_PER_LINE))->map(fn (UploadedFile $photo) => [$photo, 'transaction:'.(int) $line['transaction_id']]))
            ->when(!empty($data['box_photo']), fn ($photos) => $photos->push([$data['box_photo'], 'box']));

        foreach ($photos as [$photo, $scope]) {
            StoreMediaFromFile::run($claim, [
                'path'         => $photo->getPathname(),
                'originalName' => $photo->getClientOriginalName(),
                'extension'    => $photo->getClientOriginalExtension() ?: $photo->guessExtension(),
                'checksum'     => md5_file($photo->getPathname()),
                'scope'        => $scope,
            ], CustomerClaim::PHOTOS);
        }
    }

    /**
     * The claim is settled with no person only when the shop has auto-approve on and it is within
     * the customer's allowance (GetCustomerClaimSettings): the claim is small enough, the customer
     * has not made too many claims in the window, and what was auto-approved for them in the window
     * plus this claim stays within the larger of the floor and their share of what they were
     * invoiced. Claims a person approved do not use the allowance. Lost parcels always go to a person
     * until carrier tracking is checked.
     */
    public function autoApproves(CustomerClaim $claim): bool
    {
        $settings = GetCustomerClaimSettings::for($claim->shop);

        if (!$settings['auto_approve'] || $claim->lines->contains(fn ($line) => $line->reason === DeliveryNoteItemReplacementReasonEnum::LOST_IN_TRANSIT)) {
            return false;
        }

        if ((float) $claim->value > $settings['max_claim_amount']) {
            return false;
        }

        $since  = now()->subDays($settings['window_days']);
        $others = CustomerClaim::where('customer_id', $claim->customer_id)->where('id', '!=', $claim->id)->where('kind', CustomerClaimKindEnum::ITEMS)->where('created_at', '>=', $since);

        if ((clone $others)->count() >= $settings['max_claims_in_window']) {
            return false;
        }

        $autoApproved = (float) (clone $others)->where('state', CustomerClaimStateEnum::SETTLED)->where('source', '!=', CustomerClaimSourceEnum::STAFF)->whereNull('reviewed_by_user_id')->sum('value');
        $invoiced     = (float) Invoice::where('customer_id', $claim->customer_id)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->where('date', '>=', $since)
            ->sum('total_amount');
        $allowance    = max($settings['allowance_floor'], $invoiced * $settings['allowance_share'] / 100);

        return $autoApproved + (float) $claim->value <= $allowance;
    }

    private function askCustomerServices(CustomerClaim $claim): void
    {
        if (!in_array(self::REVIEW_DEPARTMENT, array_column(StaffTask::departments($claim->group_id), 'value'), true)) {
            Log::warning('Customer claim review task not raised: no '.self::REVIEW_DEPARTMENT.' department in group '.$claim->group_id);

            return;
        }

        StoreStaffTask::run(GetAikuAssistant::run($claim->group_id), [
            'subject'     => mb_substr('Claim '.$claim->reference.' to review', 0, 255),
            'description' => implode("\n\n", array_filter([
                'The customer made this claim themselves'.($claim->source === CustomerClaimSourceEnum::MERLIN ? ' in Merlin' : '').'. Approve or reject it on the claim: '.route('grp.org.shops.show.claims.show', [$claim->shop->organisation->slug, $claim->shop->slug, $claim->ulid]),
                'Customer: '.$claim->customer->name.' ('.$claim->customer->reference.')',
                'Asked for: '.str_replace('_', ' ', $claim->resolution),
                $claim->lines->map(fn ($line) => $line->code.' x'.(float) $line->quantity.' · '.$line->reason->label())->implode("\n"),
                $claim->comment ? 'Customer comment: '.$claim->comment : null,
            ])),
            'department'  => self::REVIEW_DEPARTMENT,
            'priority'    => ChatPriorityEnum::NORMAL->value,
            'model_type'  => 'CustomerClaim',
            'model_id'    => $claim->id,
            'data'        => [
                'kind'              => self::REVIEW_TASK_KIND,
                'organisation_id'   => $claim->organisation_id,
                'shop_id'           => $claim->shop_id,
                'customer_id'       => $claim->customer_id,
                'order_id'          => $claim->order_id,
                'customer_claim_id' => $claim->id,
                'conversation_id'   => $claim->shopping_assistant_conversation_id,
            ],
        ]);
    }
}
