<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierWechatConversation;

use App\Enums\Procurement\SupplierWechatConversation\SupplierWechatConversationKindEnum;
use App\Models\Procurement\SupplierWechatConversation;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchSupplierWechatConversationName
{
    use AsAction;

    private const string API = 'https://qyapi.weixin.qq.com/cgi-bin/';

    /**
     * The archive only carries ids. The customer contact API names them: the supplier's WeChat
     * name and company, and the remark the member of staff gave them, which is usually the
     * supplier's name in the way the team knows it.
     */
    public function handle(SupplierWechatConversation $conversation): void
    {
        $token = $this->accessToken($conversation->organisation);

        if (! $token) {
            return;
        }

        if ($conversation->kind === SupplierWechatConversationKindEnum::GROUP) {
            $response = Http::post(self::API.'externalcontact/groupchat/get?access_token='.$token, ['chat_id' => $conversation->room_id, 'need_name' => 0]);

            if ((int) $response->json('errcode') === 0) {
                $conversation->update(['name' => $response->json('group_chat.name') ?: null]);
            }

            return;
        }

        $response = Http::get(self::API.'externalcontact/get', ['access_token' => $token, 'external_userid' => $conversation->external_userid]);

        if ((int) $response->json('errcode') !== 0) {
            return;
        }

        $followedBy = collect($response->json('follow_user', []))->firstWhere('userid', $conversation->staff_wecom_userid);

        $conversation->update([
            'name'      => $response->json('external_contact.name') ?: null,
            'corp_name' => $response->json('external_contact.corp_full_name') ?: ($response->json('external_contact.corp_name') ?: null),
            'remark'    => Arr::get($followedBy, 'remark') ?: null,
        ]);
    }

    /**
     * WeCom allows only so many token requests a day, so the two hours each token lasts are
     * shared by every worker.
     */
    private function accessToken(Organisation $organisation): ?string
    {
        $corpId        = Arr::get($organisation->settings, 'procurement.wechat.corp_id');
        $contactSecret = Arr::get($organisation->settings, 'procurement.wechat.contact_secret');

        if (blank($corpId) || blank($contactSecret)) {
            return null;
        }

        $cacheKey = "wecom-contact-token:$organisation->id";
        $token    = Cache::get($cacheKey);

        if ($token) {
            return $token;
        }

        $response = Http::get(self::API.'gettoken', ['corpid' => $corpId, 'corpsecret' => Crypt::decryptString($contactSecret)]);

        if ((int) $response->json('errcode') !== 0 || blank($response->json('access_token'))) {
            return null;
        }

        Cache::put($cacheKey, $response->json('access_token'), max(60, (int) $response->json('expires_in', 7200) - 300));

        return $response->json('access_token');
    }
}
