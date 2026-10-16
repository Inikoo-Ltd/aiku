<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierWechatConversation;

use App\Models\Procurement\SupplierWechatConversation;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkSupplierWechatConversationRead
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('supplierWechatConversation')->canBeViewedBy($request->user());
    }

    public function handle(SupplierWechatConversation $supplierWechatConversation, User $user): void
    {
        DB::table('supplier_wechat_conversation_reads')->upsert(
            [['supplier_wechat_conversation_id' => $supplierWechatConversation->id, 'user_id' => $user->id, 'last_read_at' => now()]],
            ['supplier_wechat_conversation_id', 'user_id'],
            ['last_read_at']
        );
    }

    public function asController(SupplierWechatConversation $supplierWechatConversation, ActionRequest $request): JsonResponse
    {
        $this->handle($supplierWechatConversation, $request->user());

        return response()->json(['read' => true]);
    }
}
