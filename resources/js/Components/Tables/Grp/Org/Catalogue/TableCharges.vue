<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Links, Meta } from "@/types/Table"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { inject } from "vue"
import Icon from "@/Components/Icon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTriangle, faEquals, faMinus } from "@fas"

library.add(faTriangle, faEquals, faMinus)

defineProps<{
    data: {
        data: {}
        links: Links
        meta: Meta
    },
    tab?: string
}>()


function shopRoute(charge: {}) {
    switch (route().current()) {
        case "grp.org.shops.show.billables.charges.index":
            return route(
                "grp.org.shops.show.billables.charges.show",
                [route().params["organisation"], route().params["shop"], charge.slug])
        default:
            return null
    }
}

function customersRoute(charge: {}) {
    switch (route().current()) {
        case "grp.org.shops.show.billables.charges.index":
            return route(
                "grp.org.shops.show.billables.charges.show.customers.index",
                [route().params["organisation"], route().params["shop"], charge.slug])
        default:
            return null
    }
}

const locale = inject('locale', aikuLocaleStructure)

const getIntervalChangesIcon = (isPositive: boolean) => {
    if (isPositive) {
        return { icon: faTriangle }
    } else {
        return { icon: faTriangle, class: 'rotate-180' }
    }
}

const getIntervalStateColor = (isPositive: boolean) => {
    return isPositive ? 'text-green-500' : 'text-red-500'
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: charge }">
            <Icon :data="charge['state_icon']" />
        </template>
        <template #cell(code)="{ item: charge }">
            <Link :href="shopRoute(charge)" class="primaryLink">
            {{ charge["code"] }}
            </Link>
        </template>
        <template #cell(customers_invoiced)="{ item: charge }">
            <Link :href="customersRoute(charge)" class="secondaryLink">
            {{ charge["customers_invoiced"] }}
            </Link>
        </template>
        <template #cell(sales_grp_currency_external)="{ item: charge }">
            {{ locale.currencyFormat(charge.currency_code, charge.sales_grp_currency_external) }}
        </template>
        <template #cell(sales_grp_currency_external_delta)="{ item: charge }">
            <div v-if="charge.sales_grp_currency_external_delta">
                <span>{{ charge.sales_grp_currency_external_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(charge.sales_grp_currency_external_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(charge.sales_grp_currency_external_delta.is_positive).class,
                        getIntervalStateColor(charge.sales_grp_currency_external_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faEquals" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
            </div>
        </template>
    </Table>
</template>
