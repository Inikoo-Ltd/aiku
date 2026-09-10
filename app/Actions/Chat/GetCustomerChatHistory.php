<?php

namespace App\Actions\Chat;

use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\MetaChatSession\UI\GetMetaChatSessions;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;
use App\Http\Resources\CRM\Livechat\MetaChatSessionListResource;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\WebUser;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCustomerChatHistory
{
    use AsAction;

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'integer'],
            'web_user_id' => ['sometimes', 'integer'],
            'page'        => ['sometimes', 'integer', 'min:1'],
            'limit'       => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

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

        if (!$customerId && !$webUserIds) {
            return ['rows' => collect(), 'page' => 1, 'limit' => 20, 'has_more' => false];
        }

        $page  = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(50, max(1, (int) ($filters['limit'] ?? 20)));
        $take  = min(200, $page * $limit);

        $websiteSessions = collect();
        if ($webUserIds) {
            $paginator       = GetChatSessions::make()->handle(['web_user_id' => $webUserIds, 'page' => 1, 'limit' => $take]);
            $websiteSessions = collect($paginator->items())
                ->map(fn ($s) => ['channel' => 'website', 'session' => $s]);
        }

        $whatsappSessions = collect();
        if ($customerId) {
            $paginator        = GetMetaChatSessions::make()->handle(['customer_id' => $customerId, 'page' => 1, 'limit' => $take]);
            $whatsappSessions = collect($paginator->items())
                ->map(fn ($s) => ['channel' => 'whatsapp', 'session' => $s]);
        }

        $rows = $websiteSessions
            ->concat($whatsappSessions)
            ->sortByDesc(fn (array $row) => $row['session']->last_visitor_message_at
                ?? $row['session']->last_agent_message_at
                ?? $row['session']->created_at)
            ->values();

        return [
            'rows'     => $rows->slice(($page - 1) * $limit, $limit)->values(),
            'page'     => $page,
            'limit'    => $limit,
            'has_more' => $rows->count() > $page * $limit,
        ];
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->validated());
    }

    public function jsonResponse(array $result): JsonResponse
    {
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
