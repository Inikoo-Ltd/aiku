<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Order } from "@/types/order";
import type { Links, Meta } from "@/types/Table";
import { useFormatTime } from "@/Composables/useFormatTime";
import Icon from "@/Components/Icon.vue";
import { useLocaleStore } from "@/Stores/locale";

import { faSeedling, faPaperPlane, faWarehouse, faHandsHelping, faBox, faTasks, faShippingFast, faTimesCircle } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { RouteParams } from "@/types/route-params";

library.add(faSeedling, faPaperPlane, faWarehouse, faHandsHelping, faBox, faTasks, faShippingFast, faTimesCircle);

defineProps<{
  data: {
    data: {}[]
    links: Links
    meta: Meta
  },
  tab?: string
}>();

const locale = useLocaleStore();

function orderRoute(order: Order) {
  switch (route().current()) {
    case "grp.org.shops.show.crm.show.orders.index":
      return route(
        "grp.org.shops.show.crm.show.orders.show",
        [(route().params as RouteParams).organisation, (route().params as RouteParams).shop,  (route().params as RouteParams).customer, order.slug]);
    case "grp.org.overview.orders_in_basket.index":
    case "grp.overview.ordering.orders_in_basket.index":
        return route(
            "grp.org.shops.show.ordering.orders.show",
            [order.organisation_slug, order.shop_slug, order.slug]);  
    case "grp.org.shops.show.ordering.orders.index":
      return route(
        "grp.org.shops.show.ordering.orders.show",
        [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, order.slug]);
    case "grp.org.shops.show.crm.customers.show.orders.index":
      return route(
        "grp.org.shops.show.crm.customers.show.orders.show",
        [(route().params as RouteParams).organisation, (route().params as RouteParams).shop,  (route().params as RouteParams).customer, order.slug]);
    case "grp.org.shops.show.crm.customers.show.customer-clients.orders.index":
      return route(
        "grp.org.shops.show.crm.customers.show.customer-clients.orders.show",
        [(route().params as RouteParams).organisation, (route().params as RouteParams).shop,  (route().params as RouteParams).customer,  (route().params as RouteParams).customerClient, order.slug]);
    default:
      return null;
  }
}

function shopRoute(order: Order) {
  return route(
    "grp.org.shops.show.ordering.backlog",
    [order.organisation_slug,order.shop_slug]);
}

function organisationRoute(order: Order) {
  return route(
    "grp.org.overview.orders_in_basket.index",
    [order.organisation_slug]);
}


function customerRoute(order: Order) {
    let routeCurr = route().current();
    console.log(routeCurr)
    switch (routeCurr) {
        case "grp.overview.ordering.orders.index":
        case "grp.org.overview.orders_in_basket.index":
        case 'grp.org.overview.orders.index':
        case "grp.overview.ordering.orders_in_basket.index":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [order.organisation_slug, order.shop_slug, order.customer_slug]
            );
        default:
            return route(
                "grp.org.shops.show.crm.customers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    order.customer_slug
                ]
            );
    }
}


</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">

    <template #cell(organisation_code)="{ item: order }" >
      <Link :href="organisationRoute(order)" class="secondaryLink">
        {{ order["organisation_code"] }}
      </Link>
    </template>

    <template #cell(shop_code)="{ item: order }">
      <Link :href="shopRoute(order)" class="secondaryLink">
        {{ order["shop_code"] }}
      </Link>
    </template>

    <template #cell(state)="{ item: order }">
      <Icon :data="order.state_icon" />
    </template>

    <template #cell(reference)="{ item: order }">
      <Link :href="orderRoute(order) as unknown as string" class="primaryLink">
        {{ order["reference"] }}
      </Link>
    </template>

    <template #cell(customer_name)="{ item: order }">
      <Link :href="customerRoute(order)" class="secondaryLink">
        {{ order["customer_name"] }}
      </Link>
    </template>



    <template #cell(date)="{ item: order }">
      <div class="text-right">
        {{ useFormatTime(order.date, { localeCode: locale.language.code, formatTime: "aiku" }) }}
      </div>
    </template>

  </Table>
</template>
