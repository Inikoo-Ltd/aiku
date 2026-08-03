<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Masters\MasterProductCategory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MasterProductCategoryFaqCascadeProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param array{state: string, done: int, total: int} $progress */
    public function __construct(
        public MasterProductCategory $masterProductCategory,
        public array $progress
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.master-product-category.'.$this->masterProductCategory->id)
        ];
    }

    public function broadcastWith(): array
    {
        return $this->progress;
    }

    public function broadcastAs(): string
    {
        return 'faq-cascade-progress';
    }
}
