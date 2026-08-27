<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 27 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { reactive } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
}>()

const selected = reactive<Record<number, number>>({})

function toggle(item: { id: number, quantity: number }) {
    if (item.id in selected) {
        delete selected[item.id]
    } else {
        selected[item.id] = Number(item.quantity)
    }
}

function submitCherryPick() {
    const lines = Object.entries(selected).map(([id, quantity]) => ({ id: Number(id), quantity }))
    router.post(
        route("grp.org.procurement.org_partners.shipping_list.cherry_pick", [route().params["organisation"]]),
        { lines },
        { preserveScroll: true, onSuccess: () => { for (const k in selected) delete selected[k] } }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div v-if="Object.keys(selected).length" class="sticky top-0 z-10 mx-4 mt-4 flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-2 text-white">
        <span>{{ Object.keys(selected).length }} {{ trans("lines selected") }}</span>
        <button type="button" class="rounded bg-white px-3 py-1 text-indigo-600" @click="submitCherryPick">
            {{ trans("Pick into order") }}
        </button>
    </div>

    <Table :resource="data" class="mt-5">
        <template #cell(pick)="{ item }">
            <div class="flex items-center gap-2">
                <input v-if="item.state === 'open'" type="checkbox" :checked="item.id in selected" @change="toggle(item)" />
                <input
                    v-if="item.id in selected"
                    v-model.number="selected[item.id]"
                    type="number"
                    step="0.001"
                    :max="item.quantity"
                    min="0.001"
                    class="w-24 rounded border-gray-300"
                />
            </div>
        </template>
        <template #cell(quantity)="{ item }">
            <span class="tabular-nums">{{ useLocaleStore().number(Number(item.quantity)) }}</span>
        </template>
        <template #cell(needed_by)="{ item }">
            {{ item.needed_by ? useFormatTime(item.needed_by, { formatTime: "mdy" }) : "-" }}
        </template>
        <template #cell(created_at)="{ item }">
            {{ useFormatTime(item.created_at, { formatTime: "mdy" }) }}
        </template>
    </Table>
</template>
