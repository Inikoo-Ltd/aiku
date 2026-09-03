<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\Comms\Mailshot\Filters\FilterByDepartment;
use App\Actions\Comms\Mailshot\Filters\FilterByFamily;
use App\Actions\Comms\Mailshot\Filters\FilterByFamilyNeverOrdered;
use App\Actions\Comms\Mailshot\Filters\FilterByInterest;
use App\Actions\Comms\Mailshot\Filters\FilterByLocation;
use App\Actions\Comms\Mailshot\Filters\FilterByOrderValue;
use App\Actions\Comms\Mailshot\Filters\FilterByShowroomOrders;
use App\Actions\Comms\Mailshot\Filters\FilterBySubdepartment;
use App\Actions\Comms\Mailshot\Filters\FilterGoldRewardStatus;
use App\Actions\Comms\Mailshot\Filters\FilterOrdersCollection;
use App\Actions\Comms\Mailshot\Filters\FilterOrdersInBasket;
use App\Actions\Comms\Mailshot\Filters\FilterRegisteredNeverOrdered;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * WhatsApp twin of GetCustomersQueryByRecipe.
 *
 * The filter pipeline is identical and deliberately shares the same Filter* classes,
 * only the two channel gates differ: consent is read from
 * customer_comms.is_subscribed_to_whatsapp_newsletter instead of is_subscribed_to_marketing,
 * and contactability requires a phone rather than an email. The email version is left
 * untouched because GetMailshotRecipientsQueryBuilder and IndexCustomers depend on it.
 */
class GetWhatsappCustomersQueryByRecipe
{
    use AsObject;

    /**
     * @throws \Exception
     */
    public function handle(?int $shopId, array $filters): Builder
    {
        $query = DB::table('customers');

        if ($shopId) {
            $query->where('customers.shop_id', $shopId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $query->whereNotNull('customers.phone');
        $query->whereNull('customers.deleted_at');

        (new FilterRegisteredNeverOrdered())->apply($query, $filters);
        (new FilterByFamilyNeverOrdered())->apply($query, $filters);
        (new FilterGoldRewardStatus())->apply($query, $filters);
        (new FilterOrdersInBasket())->apply($query, $filters);
        (new FilterByOrderValue())->apply($query, $filters);
        (new FilterBySubdepartment())->apply($query, $filters);
        (new FilterByDepartment())->apply($query, $filters);
        (new FilterByShowroomOrders())->apply($query, $filters);
        (new FilterByInterest())->apply($query, $filters);
        (new FilterOrdersCollection())->apply($query, $filters);
        (new FilterByFamily())->apply($query, $filters);
        (new FilterByLocation())->apply($query, $filters);

        return $query;
    }
}
