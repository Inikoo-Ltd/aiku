<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 10 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"

defineProps<{
    data: object
    tab?: string
}>()

function resolve(item: { id: number }, accept: boolean) {
    router.post(
        route("grp.org.procurement.shopping_list.resolve_dismiss", [route().params["organisation"], item.id]),
        { accept },
        { preserveScroll: true }
    )
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(quantity_units)="{ item }">
            {{ item.quantity_units }} u
            <span v-if="item.units_per_carton_snapshot"> ({{ Math.round((item.quantity_units / item.units_per_carton_snapshot) * 100) / 100 }} ctn)</span>
        </template>
        <template #cell(needed_by)="{ item }">
            {{ item.needed_by ? useFormatTime(item.needed_by) : "-" }}
        </template>
        <template #cell(created_at)="{ item }">
            {{ useFormatTime(item.created_at) }} <span v-if="item.added_by_name" class="text-gray-400">· {{ item.added_by_name }}</span>
        </template>
        <template #cell(state)="{ item }">
            <div v-if="item.state === 'dismiss_proposed'">
                <div class="text-xs text-warning-600">{{ trans("Dismissal proposed") }}: {{ item.dismiss_reason }}</div>
                <div class="mt-1 flex gap-2">
                    <button type="button" class="primaryLink" @click="resolve(item, true)">{{ trans("Accept") }}</button>
                    <button type="button" class="secondaryLink" @click="resolve(item, false)">{{ trans("Reinstate") }}</button>
                </div>
            </div>
            <span v-else>{{ item.state }}</span>
        </template>
    </Table>
</template>
