<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle } from "@fal"

library.add(faInfoCircle)

type TrafficSourceRow = {
    label: string
    group: string
    arrivals: number
    share: number
    first_arrivals: number
    returning_arrivals: number
    customers: number
    last_arrival_at: string | null
}

defineProps<{
    data: {
        summary?: {
            window_days: number
            total_arrivals: number
        }
    }
    tab: string
}>()

const locale = useLocaleStore()
</script>

<template>
    <div>
    <div v-if="data.summary" class="flex items-center gap-x-2 px-4 pt-4 sm:px-6">
        <h3 class="text-sm font-medium text-gray-800">
            {{ ctrans(":arrivals arrivals in the last :days days", { arrivals: locale.number(data.summary.total_arrivals), days: data.summary.window_days }) }}
        </h3>
        <button
            type="button"
            class="inline-flex size-6 items-center justify-center rounded text-gray-500 hover:text-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[--app-accent]"
            :aria-label="ctrans('About these numbers')"
            v-tooltip="ctrans('Visitors who landed on this page, by the source that brought them. Bots are left out. Visits typed in or opened from a bookmark have no source, so they are not counted here.')">
            <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
        </button>
    </div>

    <Table :resource="data" :name="tab">
        <template #cell(type)="{ item: source }: { item: TrafficSourceRow }">
            <span class="font-medium text-gray-900">{{ source.label }}</span>
        </template>

        <template #cell(arrivals)="{ item: source }: { item: TrafficSourceRow }">
            <span class="tabular-nums">{{ locale.number(source.arrivals) }}</span>
        </template>

        <template #cell(share)="{ item: source }: { item: TrafficSourceRow }">
            <span class="tabular-nums">{{ source.share.toFixed(1) }}%</span>
        </template>

        <template #cell(first_arrivals)="{ item: source }: { item: TrafficSourceRow }">
            <span class="tabular-nums">{{ locale.number(source.first_arrivals) }}</span>
        </template>

        <template #cell(returning_arrivals)="{ item: source }: { item: TrafficSourceRow }">
            <span class="tabular-nums">{{ locale.number(source.returning_arrivals) }}</span>
        </template>

        <template #cell(customers)="{ item: source }: { item: TrafficSourceRow }">
            <span class="tabular-nums">{{ locale.number(source.customers) }}</span>
        </template>

        <template #cell(last_arrival_at)="{ item: source }: { item: TrafficSourceRow }">
            <span v-if="source.last_arrival_at" class="whitespace-nowrap" :title="useFormatTime(source.last_arrival_at, { formatTime: 'hm' })">
                {{ useRangeFromNow(source.last_arrival_at) }}
            </span>
        </template>
    </Table>
    </div>
</template>
