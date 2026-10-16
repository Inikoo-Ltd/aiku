<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierWechatConversation\Json;

use App\Actions\Procurement\SupplierWechatConversation\MarkSupplierWechatConversationRead;
use App\Http\Resources\Procurement\SupplierWechatMessageResource;
use App\Models\Procurement\SupplierWechatConversation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSupplierWechatMessages
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('supplierWechatConversation')->canBeViewedBy($request->user());
    }

    public function rules(): array
    {
        return ['before_id' => ['sometimes', 'integer']];
    }

    public function asController(SupplierWechatConversation $supplierWechatConversation, ActionRequest $request): AnonymousResourceCollection
    {
        $messages = $supplierWechatConversation->messages()
            ->with('supplierWechatConversation')
            ->when($request->validated('before_id'), fn ($query, $beforeId) => $query->where('id', '<', $beforeId))
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        MarkSupplierWechatConversationRead::run($supplierWechatConversation, $request->user());

        return SupplierWechatMessageResource::collection($messages);
    }
}
