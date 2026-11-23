<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Oct 2026 23:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatSession;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * The buttons an assistant puts on its draft: open the staff claim form prefilled with the claim
 * she proposes (staff check it and create the claim there), ask a department to do something as a staff task, put a chat that is probably spam in spam, never
 * blocking the sender, close as done an email that only thanks us, or link the chat to its customer. The assistant only proposes them; each runs once,
 * when a staff member who may do it clicks, as that staff member, and the draft keeps what came of it.
 */
class RunChatAiDraftAction
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function asController(ChatAiDraft $chatAiDraft, int $index, Request $request): JsonResponse
    {
        $session = $chatAiDraft->session();
        $agent   = $session instanceof ChatSession ? $this->getAuthorisedChatAgent($session) : null;

        if (!$agent) {
            return response()->json(['success' => false], 403);
        }

        if (!$this->userCanDisposeOfChat($request->user(), $session)) {
            return response()->json(['success' => false, 'message' => $this->chatHeldByAnotherAgentMessage($session)], 403);
        }

        try {
            $view = DB::transaction(function () use ($chatAiDraft, $index, $session, $agent, $request) {
                $draft  = ChatAiDraft::lockForUpdate()->findOrFail($chatAiDraft->id);
                $action = data_get($draft->facts, "actions.$index");

                abort_unless(is_array($action), 404);
                abort_if(isset($action['done']), 409, __('This was already done'));
                abort_unless(in_array($draft->status, [ChatAiDraftStatusEnum::PENDING, ChatAiDraftStatusEnum::USED, ChatAiDraftStatusEnum::EDITED], true) && !$draft->flagged_wrong_at, 422, __('This draft was discarded or replaced, so its actions can no longer be used'));

                $action['done'] = $this->run($session, $request->user(), $agent, $action) + ['at' => now()->toIso8601String(), 'by' => $request->user()->id];
                $draft->update(['facts' => array_replace_recursive($draft->facts, ['actions' => [$index => $action]])]);

                return self::view($session, $action, $index);
            });
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'message' => implode(' ', $exception->validator->errors()->all())], 422);
        } catch (HttpException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], $exception->getStatusCode());
        }

        return response()->json(['success' => true, 'data' => $view]);
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array{label: string, reference: string|null}
     *
     * @throws Throwable
     */
    private function run(ChatSession $session, User $user, ChatAgent $agent, array $action): array
    {
        if ($action['type'] === 'spam') {
            $refused = MarkChatSessionAsSpam::refusedForAssistant($session, untouchedOnly: false);
            abort_if((bool) $refused, 422, (string) $refused);

            MarkChatSessionAsSpam::make()->handle($session, $agent, blockSender: false);

            return ['label' => __('Moved to spam'), 'reference' => null];
        }

        if ($action['type'] === 'close') {
            CloseChatAfterThanks::closeForAssistant($session, untouchedOnly: false);

            return ['label' => __('Closed as done'), 'reference' => null];
        }

        if ($action['type'] === 'link') {
            $customer = self::customerToLink($session, $action['customer']);
            abort_unless($customer, 422, __('No customer :customer with a login in :shop', ['customer' => $action['customer'], 'shop' => $session->shop->name]));

            self::link($session, $customer, $agent);

            return ['label' => __('Linked to :customer', ['customer' => $customer->reference]), 'reference' => $customer->reference];
        }

        if ($action['type'] === 'task') {
            $assigneeId = isset($action['assignee']) ? $user->group->users()->where('username', $action['assignee'])->value('id') : null;
            if (isset($action['assignee']) && !$assigneeId) {
                abort(422, __('There is no user :username to give this task to', ['username' => $action['assignee']]));
            }

            $task = StoreStaffTaskFromChatSession::make()->handle($session, $user, array_filter([
                'subject'     => $action['subject'],
                'description' => $action['description'] ?? null,
                'department'  => $action['department'] ?? null,
                'assignee_id' => $assigneeId,
                'priority'    => $action['priority'] ?? null,
            ]));

            return ['label' => __(':reference sent', ['reference' => $task->reference]), 'reference' => $task->reference];
        }

        abort(422, __('Claims are made with Create claim, on the claim form'));
    }

    /**
     * What the draft card shows for each proposed action, worked out now: an action that can no
     * longer be done says why, and a claim links to the claim form.
     *
     * @return array<int, array{index: int, type: string, label: string, done: bool, available: bool, why: string|null, url?: string|null}>
     */
    public static function views(ChatSession $session, ChatAiDraft $draft): array
    {
        return collect(data_get($draft->facts, 'actions', []))
            ->map(fn (array $action, int $index) => self::view($session, $action, $index))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array{index: int, type: string, label: string, done: bool, available: bool, why: string|null, url?: string|null}
     */
    public static function view(ChatSession $session, array $action, int $index): array
    {
        $view = ['index' => $index, 'type' => $action['type'], 'done' => isset($action['done']), 'available' => true, 'why' => null];

        if (isset($action['done'])) {
            return $view + ['label' => $action['done']['label']];
        }

        if ($action['type'] === 'task') {
            return $view + ['label' => $action['label'] ?? $action['subject']];
        }

        if ($action['type'] === 'link') {
            $customer = self::customerToLink($session, $action['customer']);
            $linked   = $customer && $session->webUser?->customer_id === $customer->id;

            return array_replace($view, [
                'available' => $customer && !$linked,
                'why'       => $customer ? ($linked ? __('Already linked') : null) : __('Customer not found'),
                'label'     => __('Link to :customer', ['customer' => $customer?->reference ?? $action['customer']]),
            ]);
        }

        if ($action['type'] === 'spam') {
            $refused = MarkChatSessionAsSpam::refusedForAssistant($session, untouchedOnly: false);

            return array_replace($view, ['available' => !$refused, 'why' => $refused, 'label' => __('This looks like spam. Send to bin?')]);
        }

        if ($action['type'] === 'close') {
            $refused = CloseChatAfterThanks::thanksToClose($session, untouchedOnly: false)[1];

            return array_replace($view, ['available' => !$refused, 'why' => $refused, 'label' => __('The customer only said thanks. Close as done?')]);
        }

        $order = self::order($session, $action['order']);
        $items = collect($action['items'])->map(fn (array $item) => (float) $item['quantity'].' × '.$item['code'])->implode(', ');

        $plan  = $order ? PlanChatClaim::run($session, $order, $action['resolution'], $action['items'], $action['reason'] ?? null, $action['percentage'] ?? null) : ['error' => __('Order not found'), 'reason' => null];

        return array_replace($view, [
            'available' => !$plan['error'],
            'why'       => $plan['error'],
            'label'     => __('Create claim: :items', ['items' => $items]),
            'url'       => !$plan['error'] ? route('grp.org.shops.show.claims.create', [$session->shop->organisation->slug, $session->shop->slug, $order->slug]).'?'.http_build_query(array_filter([
                'resolution' => $action['resolution'],
                'reason'     => $action['reason'] ?? $plan['reason'],
                'percentage' => $action['percentage'] ?? null,
                'items'      => collect($action['items'])->mapWithKeys(fn (array $item) => [trim($item['code']) => (float) $item['quantity']])->all(),
            ])) : null,
        ]);
    }

    public static function customerToLink(ChatSession $session, string $identifier): ?Customer
    {
        $identifier = strtolower(trim($identifier));

        return Customer::where('shop_id', $session->shop_id)
            ->whereHas('webUsers')
            ->where(fn ($query) => $query->whereRaw('lower(reference) = ?', [$identifier])
                ->orWhereRaw('lower(slug) = ?', [$identifier])
                ->orWhereRaw('lower(email) = ?', [$identifier]))
            ->first();
    }

    public static function link(ChatSession $session, Customer $customer, ChatAgent $agent): void
    {
        $session->update([
            'suggested_customer_id'  => $customer->id,
            'suggestion_basis'       => SuggestChatSessionCustomer::BASIS_MANUAL,
            'suggestion_rejected_at' => null,
        ]);

        ConfirmSuggestedChatCustomer::make()->handle($session, $agent, true);
    }

    public static function order(ChatSession $session, string $reference): ?Order
    {
        return $session->webUser?->customer?->orders()->whereRaw('lower(reference) = ?', [strtolower(trim($reference))])->first();
    }
}
