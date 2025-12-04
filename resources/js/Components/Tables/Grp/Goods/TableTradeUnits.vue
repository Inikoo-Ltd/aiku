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
import {faSeedling, faScarecrow } from "@fal"
import { faCheckCircle,faSkull } from "@fas"

import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faCheckCircle, faSeedling, faSkull, faScarecrow)


defineProps<{
    data: {}
    tab?: string
}>()

function tradeUnitRoute(tradeUnit: TradeUnit) {
    return route(
        "grp.trade_units.units.show",
        [tradeUnit.slug])
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
        <template #cell(net_weight)="{ item: tradeUnit }">
            {{ tradeUnit["weight"] }}
        </template>
        <template #cell(type)="{ item: tradeUnit }">
            {{ tradeUnit["type"] }}
        </template>
        <template #cell(units)="{ item: tradeUnit }">
            {{ tradeUnit["units"] }}
        </template>
    </Table>
</template>


