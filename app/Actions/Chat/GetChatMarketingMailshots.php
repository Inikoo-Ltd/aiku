<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * What the shops sent their customers lately, for customer service to read: a customer asking
 * about an offer in this morning's newsletter is answered by somebody who has seen it. Read from
 * the mailshots themselves, so nothing is copied into the chat queue.
 */
class GetChatMarketingMailshots extends OrgAction
{
    use WithChatAgentAuthorisation;

    public const int DAYS = 30;

    /**
     * @param  array<int, int>  $shopIds
     * @return array<int, array{id: int, subject: string, type: string, shop: string, sent_at: ?string}>
     */
    public function handle(User $user, Organisation $organisation, array $shopIds): array
    {
        $shops = $organisation->shops()
            ->when($shopIds, fn ($query) => $query->whereIn('shops.id', $shopIds))
            ->get()
            ->filter(fn (Shop $shop) => $this->userCanViewChatOnShop($user, $shop))
            ->keyBy('id');

        return Mailshot::query()
            ->whereIn('shop_id', $shops->keys())
            ->whereIn('type', [MailshotTypeEnum::NEWSLETTER, MailshotTypeEnum::MARKETING])
            ->where('state', MailshotStateEnum::SENT)
            ->where('sent_at', '>=', now()->subDays(self::DAYS))
            ->orderByDesc('sent_at')
            ->limit(300)
            ->get(['id', 'shop_id', 'subject', 'type', 'sent_at'])
            ->map(fn (Mailshot $mailshot) => [
                'id'      => $mailshot->id,
                'subject' => $mailshot->subject,
                'type'    => $mailshot->type->value,
                'shop'    => $shops->get($mailshot->shop_id)?->name,
                'sent_at' => $mailshot->sent_at?->toISOString(),
            ])
            ->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($mailshot = $request->route('mailshot')) {
            return $mailshot->shop && $mailshot->shop->organisation_id === $this->organisation->id
                && $this->userCanViewChatOnShop($request->user(), $mailshot->shop);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'shop_ids'   => ['sometimes', 'array'],
            'shop_ids.*' => ['integer'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): JsonResponse
    {
        $this->initialisation($organisation, $request);

        return response()->json($this->handle($request->user(), $organisation, $this->validatedData['shop_ids'] ?? []));
    }

    public function show(Organisation $organisation, Mailshot $mailshot, ActionRequest $request): JsonResponse
    {
        $this->initialisation($organisation, $request);

        return response()->json([
            'html' => $mailshot->email?->liveSnapshot?->compiled_layout,
        ]);
    }
}
