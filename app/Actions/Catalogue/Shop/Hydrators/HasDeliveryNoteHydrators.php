<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Nov 2025 14:53:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Accounting\Reports\IntrastatExportTimeSeries\ProcessIntrastatExportTimeSeriesRecords;
use App\Actions\Accounting\Reports\IntrastatImportTimeSeries\ProcessIntrastatImportTimeSeriesRecords;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateDeliveryNotes;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotes;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotesState;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotesState;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotesState;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Helpers\Country;
use App\Models\Dispatching\DeliveryNote;

trait HasDeliveryNoteHydrators
{
    public function storeDeliveryNoteHydrators(DeliveryNote $deliveryNote): void
    {
        GroupHydrateDeliveryNotes::dispatch($deliveryNote->group_id, $deliveryNote->type)->delay(60);
        OrganisationHydrateDeliveryNotes::dispatch($deliveryNote->organisation_id, $deliveryNote->type)->delay($this->hydratorsDelay);
        ShopHydrateDeliveryNotes::dispatch($deliveryNote->shop_id, $deliveryNote->type)->delay($this->hydratorsDelay);
        CustomerHydrateDeliveryNotes::dispatch($deliveryNote->customer_id, $deliveryNote->type)->delay($this->hydratorsDelay);
    }

    public function deliveryNoteHandlingHydrators(DeliveryNote $deliveryNote, DeliveryNoteStateEnum $deliveryNoteStateEnum): void
    {
        GroupHydrateDeliveryNotesState::dispatch($deliveryNote->group_id, $deliveryNoteStateEnum)->delay($this->hydratorsDelay);
        OrganisationHydrateDeliveryNotesState::dispatch($deliveryNote->organisation_id, $deliveryNoteStateEnum)->delay($this->hydratorsDelay);
        ShopHydrateDeliveryNotesState::dispatch($deliveryNote->shop_id, $deliveryNoteStateEnum)->delay($this->hydratorsDelay);
        // Get directly from shop.type because some deliveryNote has no shop_type somehow (null), probably old order_data
        OrganisationHydrateShopTypeDeliveryNotesState::dispatch($deliveryNote->organisation_id, $deliveryNote->shop_type ?? $deliveryNote->shop->type, $deliveryNoteStateEnum);
    }

    public function intrastatHydrators(DeliveryNote $deliveryNote): void
    {
        if (!$deliveryNote->delivery_country_id || $deliveryNote->delivery_country_id === $deliveryNote->organisation->country_id) {
            return;
        }

        $deliveryCountry = Country::find($deliveryNote->delivery_country_id);

        if (!$deliveryCountry || !Country::isInEU($deliveryCountry->code)) {
            return;
        }

        $dispatchedDate = ($deliveryNote->dispatched_at ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            ProcessIntrastatExportTimeSeriesRecords::dispatch($deliveryNote->organisation_id, $frequency, $dispatchedDate, $dispatchedDate)->delay($this->hydratorsDelay);
            ProcessIntrastatImportTimeSeriesRecords::dispatch($deliveryNote->organisation_id, $frequency, $dispatchedDate, $dispatchedDate)->delay($this->hydratorsDelay);
        }
    }

}
