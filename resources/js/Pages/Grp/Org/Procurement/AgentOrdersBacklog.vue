<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 29 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import DispatchDashboard from "@/Components/Warehouse/DispatchDashboard.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTasks, faPaperPlane, faWarehouseAlt, faIndustryAlt, faBoxOpen, faPallet, faShip, faTruckLoading, faPlane, faShippingFast, faHourglassStart, faHandHoldingUsd, faCogs, faClipboardCheck } from "@fal"

library.add(faTasks, faPaperPlane, faWarehouseAlt, faIndustryAlt, faBoxOpen, faPallet, faShip, faTruckLoading, faPlane, faShippingFast, faHourglassStart, faHandHoldingUsd, faCogs, faClipboardCheck)

interface RouteTarget {
    name: string
    parameters?: Record<string, unknown>
}

interface Cell {
    value: number
    shared?: number
    shared_tooltip?: string
    lines?: number
    lines_route?: RouteTarget
    route_target?: RouteTarget
    breakdown?: { value: number; tooltip: string; route_target?: RouteTarget }[]
}

interface Stage {
    key: string
    label: string
    icon: string
    tooltip?: string
    breakdown_icons?: { icon: string; tooltip: string }[]
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: {
        dimension: { items: { key: string; label: string; route_target?: RouteTarget }[] }
        metrics: { key: string; caption?: string; items: Stage[] }[]
        data: Record<string, Record<string, Cell>>
        totals: Record<string, Cell>
    }
}>()

const href = (target?: RouteTarget) => (target ? route(target.name, target.parameters as never) : undefined)

const cellOf = (clientKey: string, stageKey: string): Cell | undefined =>
    clientKey === "_total" ? props.data.totals[stageKey] : props.data.data[clientKey]?.[stageKey]
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="hidden md:block">
        <DispatchDashboard tab="orders" :data="data" />
    </div>

    <!-- Phones: one card per client, the stages one under the other -->
    <div class="md:hidden space-y-3 px-3 py-3">
        <div
            v-for="client in [...data.dimension.items, { key: '_total', label: ctrans('Total'), route_target: undefined }]"
            :key="client.key"
            class="rounded-lg border border-gray-200 bg-white"
        >
            <component
                :is="client.route_target ? Link : 'div'"
                :href="href(client.route_target)"
                class="block border-b border-gray-200 px-3 py-2 text-base font-semibold"
            >{{ client.label }}</component>

            <template v-for="block in data.metrics" :key="block.key">
                <div class="px-3 pt-2 text-[10px] uppercase tracking-wide text-gray-400">{{ block.caption }}</div>
                <div v-for="stage in block.items" :key="stage.key" class="flex items-center gap-2 px-3 py-1.5 text-sm">
                    <FontAwesomeIcon :icon="stage.icon" fixed-width class="text-gray-500" aria-hidden="true" />
                    <span class="min-w-0 flex-1 truncate">{{ stage.label }}</span>

                    <span v-if="cellOf(client.key, stage.key)?.breakdown?.length" class="inline-flex rounded border border-gray-200 bg-gray-50 text-[11px] tabular-nums divide-x divide-gray-200">
                        <component
                            v-for="(part, partIndex) in cellOf(client.key, stage.key)?.breakdown"
                            :key="partIndex"
                            :is="part.route_target ? Link : 'span'"
                            :href="href(part.route_target)"
                            :class="['w-7 py-0.5 text-center leading-tight', part.value ? 'text-gray-600' : 'text-gray-300']"
                        >
                            <FontAwesomeIcon v-if="stage.breakdown_icons?.[partIndex]" :icon="stage.breakdown_icons[partIndex].icon" class="block mx-auto text-[9px] text-gray-400" aria-hidden="true" />
                            {{ part.value }}
                        </component>
                    </span>

                    <component
                        :is="cellOf(client.key, stage.key)?.route_target ? Link : 'span'"
                        :href="href(cellOf(client.key, stage.key)?.route_target)"
                        :class="['w-8 text-right font-medium tabular-nums', cellOf(client.key, stage.key)?.value ? '' : 'text-gray-300']"
                    >{{ cellOf(client.key, stage.key)?.value ?? 0 }}</component>
                    <span class="w-7 text-xs tabular-nums text-violet-600">{{ cellOf(client.key, stage.key)?.shared ? `(${cellOf(client.key, stage.key)?.shared})` : "" }}</span>
                    <component
                        :is="cellOf(client.key, stage.key)?.lines_route ? Link : 'span'"
                        :href="href(cellOf(client.key, stage.key)?.lines_route)"
                        :class="['w-10 rounded border text-center text-[10px] tabular-nums', cellOf(client.key, stage.key)?.lines ? 'border-teal-200 bg-teal-50 text-teal-700' : 'border-gray-200 bg-gray-50 text-gray-300']"
                    >{{ cellOf(client.key, stage.key)?.lines ?? 0 }}</component>
                </div>
            </template>
        </div>
    </div>
</template>
