<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { ref } from "vue"
import draggable from "vuedraggable"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Icon from "@/Components/Icon.vue"

const props = defineProps<{
    pageHead: any
    title: string
    columns: { status: string; label: string; icon: any; tickets: any[] }[]
    updateRoute: string
}>()

const columns = ref(props.columns)

const onMoved = (status: string, event: { added?: { element: { id: number } } }) => {
    if (!event.added) return
    router.patch(route(props.updateRoute, { ticket: event.added.element.id }), { status }, { preserveScroll: true, preserveState: true })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 overflow-x-auto">
        <div class="flex gap-3 min-w-max">
            <div v-for="column in columns" :key="column.status" class="w-72 bg-gray-100 rounded-lg p-2 flex flex-col">
                <div class="flex items-center gap-2 px-1 pb-2 text-sm font-semibold">
                    <Icon :data="column.icon" /> {{ column.label }}
                    <span class="ml-auto text-xs font-normal text-gray-500">{{ column.tickets.length }}</span>
                </div>
                <draggable v-model="column.tickets" item-key="id" group="tickets" class="flex-1 space-y-2 min-h-24" @change="onMoved(column.status, $event)">
                    <template #item="{ element }">
                        <div class="bg-white rounded-md border border-gray-300 p-2.5 cursor-grab active:cursor-grabbing">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <Link :href="route('grp.tickets.show', element.reference)" class="primaryLink font-medium">{{ element.reference }}</Link>
                                <Icon :data="element.priority_icon" />
                            </div>
                            <p class="text-sm leading-snug">{{ element.subject }}</p>
                            <p class="text-xs text-gray-500 mt-1.5">{{ element.assignee || element.customer || element.reporter || "" }}</p>
                        </div>
                    </template>
                </draggable>
            </div>
        </div>
    </div>
</template>
