<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Http\Resources\Procurement\SupplierWechatMessageResource;
use App\Models\Procurement\SupplierMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Goes to everyone who can see the organisation's procurement, not to named people: who may
 * read supplier chats is a permission, and the channel checks it when they subscribe.
 */
class SupplierWechatMessageStored implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public SupplierMessage $supplierMessage)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.procurement-wechat.'.$this->supplierMessage->organisation_id)];
    }

    public function broadcastAs(): string
    {
        return 'supplier-wechat-message';
    }

    public function broadcastWith(): array
    {
        return SupplierWechatMessageResource::make($this->supplierMessage)->resolve();
    }
}
