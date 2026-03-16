<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { TradeUnit } from "@/types/trade-unit"
import Icon from "@/Components/Icon.vue"
import { faSeedling, faScarecrow } from "@fal"
import { faCheckCircle, faSkull, faTriangle, faEquals, faMinus } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(faCheckCircle, faSeedling, faSkull, faScarecrow, faTriangle, faEquals, faMinus)

const locale = inject("locale", aikuLocaleStructure)

defineProps<{
    data: {}
    tab?: string
}>()

function tradeUnitRoute(tradeUnit: TradeUnit) {
    return route(
        "grp.trade_units.units.show",
        [tradeUnit.slug])
}

const getIntervalChangesIcon = (isPositive: boolean) => {
    if (isPositive) {
        return { icon: faTriangle }
    } else {
        return { icon: faTriangle, class: "rotate-180" }
    }
}

const getIntervalStateColor = (isPositive: boolean) => {
    return isPositive ? "text-green-500" : "text-red-500"
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item: tradeUnit }">
            <Icon :data="tradeUnit.status_icon" />
        </template>
        <template #cell(code)="{ item: tradeUnit }">
            <Link :href="tradeUnitRoute(tradeUnit) as string" class="primaryLink">
                {{ tradeUnit["code"] }}
            </Link>
        </template>
        <template #cell(name)="{ item: tradeUnit }">
            {{ tradeUnit["name"] }}
        </template>
        <template #cell(marketing_weight)="{ item: tradeUnit }">
            {{ tradeUnit["marketing_weight"] }}
        </template>
        <template #cell(type)="{ item: tradeUnit }">
            <div class="capitalize">{{ tradeUnit["type"] }}</div>
        </template>
        <template #cell(units)="{ item: tradeUnit }">
            {{ tradeUnit["units"] }}
        </template>

        <template #cell(sales_grp_currency_external)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat('GBP', item.sales_grp_currency_external) }}</span>
        </template>

        <template #cell(sales_grp_currency_external_delta)="{ item }">
            <div v-if="item.sales_grp_currency_external_delta">
                <span>{{ item.sales_grp_currency_external_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive).class,
                        getIntervalStateColor(item.sales_grp_currency_external_delta.is_positive),
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

        <template #cell(invoices)="{ item }">
            <span class="tabular-nums">{{ item.invoices }}</span>
        </template>

        <template #cell(invoices_delta)="{ item }">
            <div v-if="item.invoices_delta">
                <span>{{ item.invoices_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.invoices_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.invoices_delta.is_positive).class,
                        getIntervalStateColor(item.invoices_delta.is_positive),
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
