<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeadingPublic.vue"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { useEchoRetinaPersonal } from "@/Stores/echo-retina-personal.js"

const props = defineProps<{
    pageHead: any
    title: string
    data: any
    ticket_badges?: {
        mine: Record<string, { label: string; count: number }>
        recent: { id: string; title: string; body: string; route: string; read: boolean; created_at: string }[]
    }
}>()

const echoPersonal = useEchoRetinaPersonal()

const badges = computed(() => echoPersonal.ticketBadges ?? props.ticket_badges ?? null)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div v-if="badges" class="mx-4 mt-3 flex flex-col gap-3 md:flex-row md:items-start">
        <div class="flex flex-wrap gap-2">
            <div v-for="(row, key) in badges.mine" :key="key" class="min-w-[7rem] rounded-lg border border-gray-200 bg-white px-3 py-2">
                <div class="text-xs text-gray-500">{{ row.label }}</div>
                <div class="text-lg font-semibold tabular-nums text-gray-800">{{ row.count }}</div>
            </div>
        </div>
        <div v-if="badges.recent.length" class="flex-1 rounded-lg border border-gray-200 bg-white p-3 text-sm">
            <div class="mb-1 text-xs text-gray-500">{{ trans("Recent") }}</div>
            <Link v-for="update in badges.recent" :key="update.id" :href="update.route" class="block rounded px-1 py-1 transition duration-200 hover:bg-gray-50">
                <div class="flex justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-1.5">
                        <span v-if="!update.read" class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-500" />
                        <span class="truncate" :class="update.read ? 'text-gray-500' : 'font-medium text-gray-900'">{{ update.title }}</span>
                    </span>
                    <span class="shrink-0 text-[10px] text-gray-400">{{ useFormatTime(update.created_at) }}</span>
                </div>
                <div class="truncate text-xs text-gray-500" :class="!update.read && 'pl-3'">{{ update.body }}</div>
            </Link>
        </div>
    </div>
    <Table :resource="data" class="mt-2">
        <template #cell(reference)="{ item }">
            <Link :href="route('retina.dropshipping.tickets.show', item.reference)" class="primaryLink">{{ item.reference }}</Link>
        </template>
        <template #cell(status)="{ item }">
            <Icon :data="item.status_icon" /> {{ item.status_label }}
        </template>
        <template #cell(updated_at)="{ item }">
            {{ useFormatTime(item.updated_at, { formatTime: "hm" }) }}
        </template>
    </Table>
</template>
