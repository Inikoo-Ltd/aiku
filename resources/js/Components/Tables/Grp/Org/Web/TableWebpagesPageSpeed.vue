<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"
import { coreWebVitalRating, formatCoreWebVital, ratingStyles } from "@/Components/DataDisplay/coreWebVitals"

type WebpagePageSpeedRow = {
    code: string
    title: string | null
    typeIcon: object
    route: routeType
    samples: number
    lcp: number | null
    inp: number | null
    cls: number | null
    status: number | null
}

defineProps<{
    data: object
    tab: string
}>()

const locale = useLocaleStore()

const statusRating = (status: number | null) => status === null ? null : (["good", "needs_improvement", "poor"] as const)[status]
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(type)="{ item: webpage }: { item: WebpagePageSpeedRow }">
            <Icon :data="webpage.typeIcon" class="px-1" />
        </template>

        <template #cell(code)="{ item: webpage }: { item: WebpagePageSpeedRow }">
            <Link :href="route(webpage.route.name, webpage.route.parameters)" class="primaryLink">
                {{ webpage.code }}
            </Link>
        </template>

        <template #cell(status)="{ item: webpage }: { item: WebpagePageSpeedRow }">
            <span v-if="statusRating(webpage.status)" class="inline-flex items-center gap-1.5 whitespace-nowrap">
                <span class="size-2 rounded-full" :class="ratingStyles[statusRating(webpage.status)!].dot" aria-hidden="true" />
                <span :class="ratingStyles[statusRating(webpage.status)!].text">{{ ratingStyles[statusRating(webpage.status)!].label }}</span>
            </span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template v-for="metric in (['lcp', 'inp', 'cls'] as const)" :key="metric" #[`cell(${metric})`]="{ item: webpage }: { item: WebpagePageSpeedRow }">
            <span
                v-if="webpage[metric] !== null"
                class="tabular-nums"
                :class="ratingStyles[coreWebVitalRating(metric, webpage[metric])!].text">
                {{ formatCoreWebVital(metric, webpage[metric]) }}
            </span>
            <span v-else class="text-gray-400" :title="ctrans('Not measured: nobody interacted with the page, or the browser does not report it')">-</span>
        </template>

        <template #cell(samples)="{ item: webpage }: { item: WebpagePageSpeedRow }">
            <span class="tabular-nums">{{ locale.number(webpage.samples) }}</span>
        </template>
    </Table>
</template>
