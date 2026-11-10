<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerShopTypeSourceEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Per open B2B shop: customers invoiced within --months, and how many of them have a shop type
 * (split by who set it) and a country.
 */
class ReportCustomerShopTypeCoverage
{
    use AsAction;

    public string $commandSignature = 'customers:shop-type-coverage {--months=24}';

    public string $commandDescription = 'Shop type and country coverage of active customers per open B2B shop';

    public function handle(int $months): array
    {
        $rows = DB::select(
            "select shops.code as shop,
                count(*) as active,
                count(*) filter (where customers.shop_type is not null) as with_shop_type,
                count(*) filter (where coalesce(customers.location->>0, '') <> '') as with_country,
                count(*) filter (where customers.shop_type is not null and customers.shop_type_source = ?) as source_customer,
                count(*) filter (where customers.shop_type is not null and customers.shop_type_source = ?) as source_staff,
                count(*) filter (where customers.shop_type is not null and customers.shop_type_source = ?) as source_inferred
            from customers
            join shops on shops.id = customers.shop_id
            where shops.type = ? and shops.state = ?
              and customers.deleted_at is null
              and customers.last_invoiced_at >= now() - make_interval(months => ?)
            group by shops.code
            order by active desc",
            [
                CustomerShopTypeSourceEnum::CUSTOMER->value,
                CustomerShopTypeSourceEnum::STAFF->value,
                CustomerShopTypeSourceEnum::INFERRED->value,
                ShopTypeEnum::B2B->value,
                ShopStateEnum::OPEN->value,
                $months,
            ]
        );

        $total = (object) ['shop' => 'All'];
        foreach (['active', 'with_shop_type', 'with_country', 'source_customer', 'source_staff', 'source_inferred'] as $column) {
            $total->{$column} = array_sum(array_column($rows, $column));
        }
        $rows[] = $total;

        return $rows;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $rows = $this->handle((int) $command->option('months'));

        $percentOf = fn (int $count, int $active): string => $count.' ('.($active ? round(100 * $count / $active, 1) : 0).'%)';

        $command->table(
            ['Shop', 'Active', 'With shop type', 'With country', 'Told us', 'Set by staff', 'Inferred'],
            array_map(fn ($row) => [
                $row->shop,
                $row->active,
                $percentOf($row->with_shop_type, $row->active),
                $percentOf($row->with_country, $row->active),
                $percentOf($row->source_customer, $row->with_shop_type),
                $percentOf($row->source_staff, $row->with_shop_type),
                $percentOf($row->source_inferred, $row->with_shop_type),
            ], $rows)
        );

        return 0;
    }
}
