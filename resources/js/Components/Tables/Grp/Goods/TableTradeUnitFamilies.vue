<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { TradeUnit } from "@/types/trade-unit"

defineProps<{
    data: {}
    tab?: string
}>()

function tradeUniFamilyRoute(tradeUnitFamily: {}) {
     switch (route().current()) {
        case "grp.masters.trade-unit-families.index": 
            return route(
                "grp.masters.trade-unit-families.show",
                [tradeUnitFamily.slug]
            )
        default: 
            return route(
                "grp.trade_units.families.show",
                [tradeUnitFamily.slug]
            )
     }
    
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: tradeUnitFamily }">
            <Link :href="tradeUniFamilyRoute(tradeUnitFamily) as string" class="primaryLink">
                {{ tradeUnitFamily["code"] }}
            </Link>
        </template>
    </Table>
</template>


