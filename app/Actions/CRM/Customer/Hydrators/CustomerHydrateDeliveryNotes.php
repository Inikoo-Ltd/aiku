<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Sept 2024 21:29:12 Malaysia Time, Taipei, Taiwan
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateDeliveryNotes;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateDeliveryNotes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateDeliveryNotes;

    public function getJobUniqueId(int|null $customerId, DeliveryNoteTypeEnum $type): string
    {
        return $customerId ?? 'empty'.'-'.$type->value;
    }

    public function handle(int|null $customerId, DeliveryNoteTypeEnum $type): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);
        if (!$customer) {
            return;
        }
        if ($type == DeliveryNoteTypeEnum::ORDER) {
            $stats = $this->getStoreDeliveryNotesStats($customer);
        } else {
            $stats = $this->getStoreReplacementsStats($customer);
        }

        $customer->stats()->update($stats);
    }

}
