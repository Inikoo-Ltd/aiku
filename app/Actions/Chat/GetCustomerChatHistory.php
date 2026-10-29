<?php

namespace App\Actions\Chat;

use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\MetaChatSession\UI\GetMetaChatSessions;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;
use App\Http\Resources\CRM\Livechat\MetaChatSessionListResource;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Everything else the person in a conversation has written to us, on website chat, email and
 * WhatsApp, as one list beside the conversation. A customer record belongs to one shop and a
 * guest has none, so the sender is recognised by what they wrote from as well: the address,
 * the WhatsApp number, and every customer record in the group carrying that address. The
 * list only reaches the shops whose chat the person asking may look at.
 */
class GetCustomerChatHistory
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * @param  array{customer_id?: int, web_user_id?: int, page?: int, limit?: int}  $filters
     * @return array{rows: Collection, page: int, limit: int, has_more: bool}
     */
    public function handle(array $filters = []): array
    {
        $customerId = $filters['customer_id'] ?? null;
        $webUserId  = $filters['web_user_id'] ?? null;

        if (!$customerId && $webUserId) {
            $customerId = WebUser::where('id', $webUserId)->value('customer_id');
        }

        // A customer can own several web user accounts; all of their website threads
        // belong in the same history.
        $webUserIds = $customerId
            ? WebUser::where('customer_id', $customerId)->pluck('id')->all()
            : array_filter([$webUserId]);

        return $this->listed(
            ['web_user_ids' => $webUserIds, 'emails' => []],
            ['customer_ids' => array_filter([$customerId]), 'phones' => [], 'whatsapp_user_ids' => []],
            [],
            $filters
        );
    }

    /**
     * @param  array<int, int>  $shopIds
     * @param  array{page?: int, limit?: int}  $filters
     * @return array{rows: Collection, page: int, limit: int, has_more: bool}
     */
    public function forConversation(ChatSession|MetaChatSession $chatSession, array $shopIds, array $filters = []): array
    {
        $customer = $chatSession instanceof MetaChatSession ? $chatSession->customer : $chatSession->webUser?->customer;

        $emails = collect([
            $chatSession instanceof ChatSession ? data_get($chatSession->metadata, 'email') : null,
            $customer?->email,
        ])->map(fn ($email) => mb_strtolower(trim((string) $email)))->filter()->unique()->values();

        $customers = collect([$customer])->filter();

        if ($emails->isNotEmpty() && $chatSession->shop) {
            $customers = $customers->concat(
                Customer::where('group_id', $chatSession->shop->group_id)
                    ->whereNotNull('email')
                    ->whereRaw('lower(trim(email)) in ('.$emails->map(fn () => '?')->implode(',').')', $emails->all())
                    ->limit(50)
                    ->get(['id', 'phone'])
            );
        }

        $customerIds = $customers->pluck('id')->unique()->values()->all();

        $phones = $customers->pluck('phone')
            ->push($chatSession instanceof MetaChatSession ? $chatSession->phone_number : null)
            ->map(fn ($phone) => preg_replace('/\D/', '', (string) $phone))
            ->filter(fn ($digits) => strlen($digits) >= 8)
            ->unique()
            ->flatMap(fn ($digits) => ['+'.$digits, $digits])
            ->values()
            ->all();

        return $this->listed(
            [
                'web_user_ids' => $customerIds ? WebUser::whereIn('customer_id', $customerIds)->pluck('id')->all() : [],
                'emails'       => $emails->all(),
            ],
            [
                'customer_ids'      => $customerIds,
                'phones'            => $phones,
                'whatsapp_user_ids' => array_filter([$chatSession instanceof MetaChatSession ? $chatSession->whatsapp_user_id : null]),
            ],
            ['allowed_shop_ids' => $shopIds, 'except_ulid' => $chatSession->ulid],
            $filters
        );
    }

    /**
     * Both sources are asked for everything up to the requested page, then merged and sliced,
     * as one chronological order cannot come out of paging two tables apart.
     *
     * @return array{rows: Collection, page: int, limit: int, has_more: bool}
     */
    private function listed(array $websiteContact, array $whatsappContact, array $scope, array $filters): array
    {
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(50, max(1, (int) ($filters['limit'] ?? 20)));
        $take  = min(200, $page * $limit);

        $knowsWebsite  = $websiteContact['web_user_ids'] || $websiteContact['emails'];
        $knowsWhatsapp = $whatsappContact['customer_ids'] || $whatsappContact['phones'] || $whatsappContact['whatsapp_user_ids'];

        $website  = $knowsWebsite ? GetChatSessions::make()->handle([...$scope, 'contact' => $websiteContact, 'page' => 1, 'limit' => $take]) : null;
        $whatsapp = $knowsWhatsapp ? GetMetaChatSessions::make()->handle([...$scope, 'contact' => $whatsappContact, 'page' => 1, 'limit' => $take]) : null;

        $rows = collect($website?->items() ?? [])
            ->map(fn ($s) => ['channel' => $s->channel?->value ?? 'website', 'session' => $s])
            ->concat(collect($whatsapp?->items() ?? [])->map(fn ($s) => ['channel' => 'whatsapp', 'session' => $s]))
            ->sortByDesc(fn (array $row) => $row['session']->last_visitor_message_at
                ?? $row['session']->last_agent_message_at
                ?? $row['session']->created_at)
            ->values();

        return [
            'rows'     => $rows->slice(($page - 1) * $limit, $limit)->values(),
            'page'     => $page,
            'limit'    => $limit,
            'has_more' => $rows->count() > $page * $limit
                || (bool) $website?->hasMorePages()
                || (bool) $whatsapp?->hasMorePages(),
        ];
    }

    public function rules(): array
    {
        return [
            'page'  => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        return $this->respond($chatSession, $request);
    }

    public function inMetaChatSession(MetaChatSession $metaChatSession, ActionRequest $request): JsonResponse
    {
        return $this->respond($metaChatSession, $request);
    }

    private function respond(ChatSession|MetaChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User || !$chatSession->shop || !$this->userCanViewChatOnShop($user, $chatSession->shop)) {
            abort(403);
        }

        $result = $this->forConversation(
            $chatSession,
            $this->chatFiltersScopedTo($user, [])['allowed_shop_ids'],
            $request->only(['page', 'limit'])
        );

        $sessions = $result['rows']->map(function (array $row) {
            $resource = $row['session'] instanceof MetaChatSession
                ? MetaChatSessionListResource::make($row['session'])
                : ChatSessionListResource::make($row['session']);

            return array_merge($resource->resolve(), ['channel' => $row['channel']]);
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'sessions'   => $sessions,
                'pagination' => [
                    'current_page' => $result['page'],
                    'per_page'     => $result['limit'],
                    'has_more'     => $result['has_more'],
                ],
            ],
        ]);
    }
}
