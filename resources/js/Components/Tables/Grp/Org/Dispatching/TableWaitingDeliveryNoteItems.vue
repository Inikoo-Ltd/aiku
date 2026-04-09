<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckSquare, faSquare } from "@fal"
import { faHeadset } from "@fas"
import { Link } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faCheckSquare, faSquare, faHeadset)

const props = defineProps<{
    data: TableTS
    tab?: string
}>()

const selectedDeliveryNoteIds = defineModel<number[]>('selectedDeliveryNoteIds')

const emits = defineEmits<{
    (e: 'update:selectedDeliveryNoteIds', value: number[]): void
}>()

const onCheckedAll = ({ data, allChecked }: { data: any[], allChecked: boolean }) => {
    if (!selectedDeliveryNoteIds.value) return

    if (allChecked) {
        const newIds = data.map(row => row.delivery_note_id).filter(Boolean)
        selectedDeliveryNoteIds.value = Array.from(new Set([...selectedDeliveryNoteIds.value, ...newIds]))
    } else {
        const uncheckIds = data.map(row => row.delivery_note_id).filter(Boolean)
        selectedDeliveryNoteIds.value = selectedDeliveryNoteIds.value.filter(id => !uncheckIds.includes(id))
    }
}

const routeToDeliveryNoteItem = (id: number) => {
    return route('grp.helpers.redirect_org_stock', { orgStock: id })
}

const routeToDeliveryNote = (slug: string) => {
    return route('grp.org.warehouses.show.dispatching.delivery_notes.show', [
        route().params['organisation'],
        route().params['warehouse'],
        slug,
    ])
}

const onReportToCs = (item: any) => {
    console.log('item', item)
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="true" @onCheckedAll="(data) => onCheckedAll(data)"
        checkboxKey="delivery_note_id">
        <template #checkbox="data">
            <FontAwesomeIcon
                v-if="selectedDeliveryNoteIds?.includes(data.data.delivery_note_id)"
                icon="fas fa-check-square"
                class="text-green-500 p-2 cursor-pointer text-lg mx-auto block"
                fixed-width
                aria-hidden="true"
                @click="() => emits('update:selectedDeliveryNoteIds', selectedDeliveryNoteIds!.filter(id => id !== data.data.delivery_note_id))"
            />
            <FontAwesomeIcon
                v-else
                icon="fal fa-square"
                class="text-gray-500 hover:text-gray-700 p-2 cursor-pointer text-lg mx-auto block"
                fixed-width
                aria-hidden="true"
                @click="() => emits('update:selectedDeliveryNoteIds', [...(selectedDeliveryNoteIds ?? []), data.data.delivery_note_id])"
            />
        </template>

        <template #cell(org_stock_name)="{ item }">
            <div class="flex items-center xgap-2">
                <span class="text-xs opacity-75 tabular-nums">({{ item.org_stock_code }})</span>
                <Link :href="routeToDeliveryNoteItem(item.org_stock_id)" class="primaryLink">{{ item.org_stock_name }}</Link>
            </div>
            
            <Link :href="routeToDeliveryNote(item.delivery_note_slug)" class="secondaryLink text-xs flex gap-x-2 w-fit">
                <FontAwesomeIcon icon="fal fa-truck" class="opacity-75" fixed-width aria-hidden="true" />
                {{ item.delivery_note_reference }}
            </Link>
        </template>

        <template #cell(action)="{ item }">
            <Button
                @click="() => onReportToCs(item)"
                icon="fas fa-headset"
                label="Report to CS"
                size="m"
                type="tertiary"
            />
        </template>
    </Table>
</template>
