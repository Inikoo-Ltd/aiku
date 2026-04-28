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

library.add(faPencil)

defineProps<{
    data: object
    tab?: string
    allowEdit?: boolean
}>()

function showRoute(batchCode: { id: number }) {
    return route("grp.org.warehouses.show.inventory.batch_codes.show", {
        organisation: (route().params as RouteParams).organisation,
        warehouse: (route().params as RouteParams).warehouse,
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
            <span>{{ item.expiry_date ? useFormatTime(item.expiry_date) : '—' }}</span>
        </template>

        <template #cell(org_stock_code)="{ item }">
            <span v-if="item.org_stock_code">{{ item.org_stock_code }}</span>
            <span v-else class="text-gray-400">—</span>
        </template>

        <template #cell(number_delivery_notes)="{ item }">
            <Link
                :href="route('grp.org.warehouses.show.inventory.batch_codes.show', {
                    organisation: (route().params as RouteParams).organisation,
                    warehouse: (route().params as RouteParams).warehouse,
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
                        organisation: (route().params as RouteParams).organisation,
                        warehouse: (route().params as RouteParams).warehouse,
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
