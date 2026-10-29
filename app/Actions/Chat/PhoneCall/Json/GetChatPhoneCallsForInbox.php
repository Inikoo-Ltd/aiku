<?php

/*
 * Author: Yudhistira <artha@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\PhoneCall\Json;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatPhoneCallContactTypeEnum;
use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatPhoneCall;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The telephone beside the conversations in the inbox: the calls on the shops picked on the rail,
 * newest first, over the shops the person asking may look at chat on and no others.
 */
class GetChatPhoneCallsForInbox
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const array VIEWS = ['all', 'in_progress', 'today'];

    public function rules(): array
    {
        return [
            'shop_ids'     => ['required', 'array'],
            'shop_ids.*'   => ['integer'],
            'contact_type' => ['sometimes', 'string', 'in:'.implode(',', array_column(ChatPhoneCallContactTypeEnum::cases(), 'value'))],
            'view'         => ['sometimes', 'string', 'in:'.implode(',', self::VIEWS)],
            'page'         => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array<int, int>  $shopIds
     */
    public function handle(User $viewer, array $shopIds, ?string $contactType = null, string $view = 'all'): LengthAwarePaginator
    {
        $allowedShopIds = Shop::with('fulfilment')
            ->whereIn('id', $shopIds)
            ->get()
            ->filter(fn (Shop $shop) => $this->userCanViewChatOnShop($viewer, $shop))
            ->pluck('id')
            ->all();

        return ChatPhoneCall::query()
            ->with(['user:id,contact_name', 'shop:id,code,name,slug', 'chatSession:id,ulid'])
            ->whereIn('shop_id', $allowedShopIds)
            ->when($contactType, fn ($query) => $query->where('contact_type', $contactType))
            ->when($view === 'in_progress', fn ($query) => $query->where('status', ChatPhoneCallStatusEnum::IN_PROGRESS))
            ->when($view === 'today', fn ($query) => $query->where('started_at', '>=', today()))
            ->latest('started_at')
            ->paginate(30);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $validated = $request->validated();

        return $this->handle($request->user(), $validated['shop_ids'], $validated['contact_type'] ?? null, $validated['view'] ?? 'all');
    }

    public function jsonResponse(LengthAwarePaginator $calls): JsonResponse
    {
        return response()->json([
            'data'       => collect($calls->items())->map(fn (ChatPhoneCall $call) => [
                'id'                => $call->id,
                'status'            => $call->status?->value,
                'contact_type'      => $call->contact_type?->value,
                'contact_name'      => $call->contact_name,
                'customer_id'       => $call->customer_id,
                'notes'             => $call->notes,
                'started_at'        => $call->started_at?->toIso8601String(),
                'ended_at'          => $call->ended_at?->toIso8601String(),
                'duration_seconds'  => $call->duration_seconds,
                'agent_name'        => $call->user?->contact_name,
                'shop'              => $call->shop ? ['id' => $call->shop->id, 'code' => $call->shop->code, 'name' => $call->shop->name, 'slug' => $call->shop->slug] : null,
                'chat_session_ulid' => $call->chatSession?->ulid,
            ]),
            'pagination' => [
                'total'    => $calls->total(),
                'has_more' => $calls->hasMorePages(),
            ],
        ]);
    }
}
