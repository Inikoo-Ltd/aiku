<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\ShippingEvent;

use App\Actions\OrgAction;
use App\Models\Dispatching\Shipper;
use App\Models\Dispatching\ShippingEvent;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreShippingEvent extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Shipper $parent, array $modelData): ShippingEvent
    {
        data_set($modelData, 'organisation_id', $this->organisation->id);
        data_set($modelData, 'sent_at', now());

        /** @var ShippingEvent $shippingEvent */
        $shippingEvent = $parent->shippingEvents()->create($modelData);

        return $shippingEvent;
    }

    public function rules(): array
    {
        return [
            'events' => ['required',  'array']
        ];
    }

    public function action(Shipper $shipper, array $modelData): ShippingEvent
    {
        $this->initialisation($shipper->organisation, $modelData);

        return $this->handle($shipper, $this->validatedData);
    }
}
