<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSignIn, faHome, faNewspaper, faBrowser, faUfoBeam, faShapes } from "@fal"

library.add(faSignIn, faHome, faNewspaper, faBrowser, faUfoBeam, faShapes)

type WebpagePerformanceRow = {
    code: string
    title: string | null
    typeIcon: object
    route: routeType
    visitors: number
    page_views: number
    avg_time_on_page: number
    add_to_baskets: number
    conversion_rate: number
}

defineProps<{
    data: object
    tab: string
}>()

const locale = useLocaleStore()

const formatDuration = (totalSeconds: number) => {
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60

    if (minutes > 0) {
        return `${minutes}m ${String(seconds).padStart(2, "0")}s`
    }

    return `${seconds}s`
}
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(type)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <Icon :data="webpage.typeIcon" class="px-1" />
        </template>

        <template #cell(code)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <Link :href="route(webpage.route.name, webpage.route.parameters)" class="primaryLink">
                {{ webpage.code }}
            </Link>
        </template>

        <template #cell(visitors)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums">{{ locale.number(webpage.visitors) }}</span>
        </template>

        <template #cell(page_views)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums">{{ locale.number(webpage.page_views) }}</span>
        </template>

        <template #cell(avg_time_on_page)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums">{{ formatDuration(webpage.avg_time_on_page) }}</span>
        </template>

        <template #cell(add_to_baskets)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums">{{ locale.number(webpage.add_to_baskets) }}</span>
        </template>

        <template #cell(conversion_rate)="{ item: webpage }: { item: WebpagePerformanceRow }">
            <span class="tabular-nums">{{ locale.number(webpage.conversion_rate) }}%</span>
        </template>
    </Table>
</template>
