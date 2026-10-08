<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { Link } from "@inertiajs/vue3"
import { RouteParams } from "@/types/route-params"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"

library.add(faPencil)

defineProps<{
    data: object
    tab?: string
    allowEdit?: boolean
}>()

const routeParams = route().params as RouteParams
const locale = useLocaleStore()

function showRoute(batchCode: { id: number }) {
    return route("grp.org.warehouses.show.inventory.batch_codes.show", {
        organisation: routeParams.organisation,
        warehouse: routeParams.warehouse,
        batchCode: batchCode.id,
    })
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item }">
            <Link :href="showRoute(item)" class="primaryLink">
                {{ item.code }}
            </Link>
        </template>

        <template #cell(expiry_date)="{ item }">
            <div class="flex items-center gap-x-2">
                <span>{{ item.expiry_date ? useFormatTime(item.expiry_date) : '—' }}</span>
                <span
                    v-if="item.days_left !== null && item.quantity_on_hand"
                    class="text-xs px-1.5 rounded"
                    :class="item.days_left < 0 ? 'bg-red-100 text-red-700' : item.days_left <= 30 ? 'bg-amber-100 text-amber-700' : item.days_left <= 90 ? 'bg-amber-50 text-amber-600' : 'text-gray-400'">
                    {{ item.days_left < 0 ? ctrans('expired :days days ago', { days: -item.days_left }) : ctrans(':days days left', { days: item.days_left }) }}
                </span>
                <span v-else-if="item.days_left === null && item.quantity_on_hand" class="text-xs text-amber-600">{{ ctrans('Best-before missing') }}</span>
            </div>
        </template>

        <template #cell(quantity_on_hand)="{ item }">
            <span :class="item.quantity_on_hand ? '' : 'text-gray-400'">{{ locale.number(Number(item.quantity_on_hand ?? 0)) }}</span>
        </template>

        <template #cell(number_locations)="{ item }">
            <span :class="item.number_locations ? '' : 'text-gray-400'">{{ item.number_locations ?? 0 }}</span>
        </template>

        <template #cell(org_stock_code)="{ item }">
            <span v-if="item.org_stock_code">{{ item.org_stock_code }}</span>
            <span v-else class="text-gray-400">—</span>
        </template>

        <template #cell(number_delivery_notes)="{ item }">
            <Link
                :href="route('grp.org.warehouses.show.inventory.batch_codes.show', {
                    organisation: routeParams.organisation,
                    warehouse: routeParams.warehouse,
                    batchCode: item.id,
                    tab: 'delivery_notes',
                })"
                class="primaryLink"
            >
                {{ item.number_delivery_notes }}
            </Link>
        </template>

        <template #cell(actions)="{ item }">
            <div class="flex gap-2 justify-end">
                <Link
                    v-if="allowEdit"
                    :href="route('grp.org.warehouses.show.inventory.batch_codes.edit', {
                        organisation: routeParams.organisation,
                        warehouse: routeParams.warehouse,
                        batchCode: item.id,
                    })"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                >
                    <FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
                </Link>
            </div>
        </template>
    </Table>
</template>
