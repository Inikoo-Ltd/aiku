<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall;

use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatPhoneCallGuests
{
    use AsAction;
    use WithChatPhoneCall;

    /**
     * The guests somebody can say they spoke to are the ones who have written in: a guest is only
     * a name and an email left in a conversation, so there is no other list of them to draw on.
     *
     * @return array<int, array{id: int, name: string, email: string|null, phone: string|null, shop_id: int|null, last_seen_at: string|null}>
     */
    public function handle(User $user, ?int $shopId = null, ?string $search = null): array
    {
        $shopIds = $this->workableShopIdsFor($user);

        if ($shopIds === []) {
            return [];
        }

        if ($shopId && in_array($shopId, $shopIds, true)) {
            $shopIds = [$shopId];
        }

        $sessions = ChatSession::query()
            ->whereNull('web_user_id')
            ->whereNotNull('guest_identifier')
            ->whereIn('shop_id', $shopIds)
            ->with([
                'chatEvents' => fn ($q) => $q->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)->latest()->limit(1),
            ])
            ->latest('created_at')
            ->limit(200)
            ->get();

        return $sessions
            ->map(fn (ChatSession $session) => [
                'id'           => $session->id,
                'name'         => $this->nameFor($session),
                'email'        => Arr::get($session->chatEvents->first()?->payload ?? [], 'email'),
                'phone'        => Arr::get($session->chatEvents->first()?->payload ?? [], 'phone'),
                'shop_id'      => $session->shop_id,
                'last_seen_at' => $session->created_at?->toIso8601String(),
            ])
            ->filter(function (array $guest) use ($search) {
                if (!$search) {
                    return true;
                }

                $haystack = strtolower($guest['name'].' '.$guest['email'].' '.$guest['phone']);

                return str_contains($haystack, strtolower($search));
            })
            ->take(50)
            ->values()
            ->all();
    }

    public function nameFor(ChatSession $session): string
    {
        $payload = $session->relationLoaded('chatEvents')
            ? ($session->chatEvents->first()?->payload ?? [])
            : ($session->chatEvents()
                ->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)
                ->latest()
                ->first()?->payload ?? []);

        $name = trim((string) Arr::get($payload, 'name'));

        return $name !== '' ? $name : __('Guest').' '.substr((string) $session->guest_identifier, 0, 8);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->handle(
                $request->user(),
                $request->validated('shop_id'),
                $request->validated('q')
            ),
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->workableShopIdsFor($request->user()) !== [];
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['sometimes', 'nullable', 'integer'],
            'q'       => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
