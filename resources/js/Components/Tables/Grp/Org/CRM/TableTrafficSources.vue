<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale"



const props = defineProps<{
    data: {}
    tab?: string
}>()

function trafficRoute(trafficSource: { slug: string }) {
  switch (route().current()) {
    case "grp.org.shops.show.marketing.traffic_sources.index":
      return route(
        "grp.org.shops.show.marketing.traffic_sources.show",
        [
          (route().params as RouteParams).organisation,
          (route().params as RouteParams).shop,
          trafficSource.slug
        ])
    default:
      return "#"
  }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
      <template #cell(name)="{ item }">
            <Link :href="trafficRoute(item)" class="primaryLink">
              {{ item.name }}
            </Link>
      </template>
      <template #cell(total_customer_revenue)="{ item }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(item.currency_code, item.total_customer_revenue) }}</div>
        </template>
    </Table>
</template>
