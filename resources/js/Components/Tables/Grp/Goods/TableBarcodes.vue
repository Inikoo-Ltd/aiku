<!-- 
 Author Louis Perez
 Created on 19-06-2026-09h-46m
 GitHub: https://github.com/louis-perez
 Copyright 2026 
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { faSeedling, faScarecrow, faPencil, faSave, faTimes, faSpinnerThird } from "@fal"
import { faCheckCircle, faSkull, faTriangle, faEquals, faMinus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Icon from '@/Components/Icon.vue';
import { inject, computed, ref } from "vue"
import { TradeUnit } from "@/types/trade-unit"

interface Barcode {
    number:         string
    slug:           string
    type:           string
    status:         string
    status_icon:    {}
    note:           string
    trade_units:    TradeUnit[]
}

library.add(faCheckCircle, faSeedling, faSkull, faScarecrow, faTriangle, faEquals, faMinus, faPencil, faSave, faTimes, faSpinnerThird)

const locale = inject("locale", aikuLocaleStructure);

const props = defineProps<{
    data: {}
    tab?: string
}>()

function tradeUnitRoute(tradeUnit: TradeUnit) {
    return route("grp.trade_units.units.show", [tradeUnit.slug])
}

function barcodeRoute(barcode: Barcode) {
    return route("grp.trade_units.barcodes.show", [barcode.slug])
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(number)="{ item }">
            <Link :href="barcodeRoute(item)" class="primaryLink">
                {{ item.number }}
            </Link>
        </template>

        <template #cell(status)="{ item }">
            <Icon 
                :data="item.status_icon"
            />
        </template>
        
        <template #cell(trade_units)="{ item }">
            <div class="flex flex-row">
                <div v-for="tradeUnit in item.trade_units">
                    <Link :href="tradeUnitRoute(tradeUnit)" class="primaryLink">
                        {{ tradeUnit.code }}
                    </Link>
                </div>
            </div>
        </template>
    </Table>
</template>
