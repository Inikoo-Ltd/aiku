<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 13 September 2024 11:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Order } from "@/types/order"
import type { Links, Meta, Table as TableTS } from "@/types/Table"
import Icon from "@/Components/Icon.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import { debounce, get, set } from "lodash"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
// import { useFormatTime } from '@/Composables/useFormatTime'

defineProps<{
    data: TableTS
    tab?: string
}>()


function deliveryNoteRoute(deliveryNote: Order) {
    // console.log(route().current())
    switch (route().current()) {
        case "grp.org.warehouses.show.dispatching.delivery-notes.show":
            // return route(
            //     "grp.org.shops.show.discounts.campaigns.show",
            //     [route().params["organisation"], , route().params["shop"], route().params["customer"], deliveryNote.slug])
        default:
            return ''
    }
}


const onPickingQuantity = (pick_route: routeType, quantity: number) => {
    router[pick_route.method || 'post'](
        route(pick_route.name, pick_route.parameters),
        {
            quantity: quantity,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: "",
                    type: "error",
                })
            },
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: "",
                    type: "error",
                })
            }
        }
    )
}
const debounceOnPickingQuantity = debounce(onPickingQuantity, 500)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">

        <!-- Column: state -->
        <template #cell(state)="{ item }">
            <Icon :data="item.state_icon" />
        </template>

        <!-- Column: Reference -->
        <template #cell(org_stock_code)="{ item: deliveryNote }">
            <Link :href="deliveryNoteRoute(deliveryNote)" class="primaryLink">
                {{ deliveryNote.org_stock_code }}
            </Link>
            <!-- <pre>{{ deliveryNote }}</pre> -->
        </template>

        <!-- Column: Reference -->
        <template #cell(quantity_required)="{ item }">
            {{ item.quantity_required }}
        </template>

        <!-- Column: Reference -->
        <template #cell(quantity_picked)="{ proxyItem }">
            <!-- ff{{ proxyItem.quantity_picked }} -->

            <NumberWithButtonSave
                :modelValue="get(proxyItem, 'quantity_required', 1)"
                :bindToTarget="{ min: 1 }"
                @update:modelValue="(e: number) => (set(proxyItem, 'quantity_picked', e), debounceOnPickingQuantity(proxyItem.picking_route, e))"
                noUndoButton
                noSaveButton
                parentClass="w-min"
            />
        </template>


        <template #cell(action)="{ item: deliveryNote }">
            <!-- <pre>
                {{ data.data[0].pickings }}
            </pre> -->
        </template>

    </Table>
</template>
