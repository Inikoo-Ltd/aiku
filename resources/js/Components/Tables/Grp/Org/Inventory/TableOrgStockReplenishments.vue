<!--
  -  Author: stewicca <stewicalf@gmail.com>
  -  Created: Fri, 17 Jul 2026, Bali, Indonesia
  -  Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFlag } from "@fas"
import { RouteParams } from "@/types/route-params"

library.add(faFlag)

defineProps<{
    data: object
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)
const routeParams = route().params as RouteParams

interface OtherLocation {
    code: string
    slug: string
    quantity: number
}

interface Replenishment {
    id: number
    slug: string
    code: string
    stock: number
    ordered: number
    eventual_stock: number
    location: { code: string; slug: string; status: string } | null
    other_locations: OtherLocation[]
    recommended: { min: number | null; max: number | null }
}

function orgStockRoute(replenishment: Replenishment) {
    return route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", [
        routeParams.organisation,
        routeParams.warehouse,
        replenishment.slug,
    ])
}

function locationRoute(slug: string) {
    return route("grp.org.warehouses.show.infrastructure.locations.show", [
        routeParams.organisation,
        routeParams.warehouse,
        slug,
    ])
}

function recommendedLabel(recommended: { min: number | null; max: number | null }) {
    const values = [recommended.min, recommended.max].filter((value) => value !== null && value !== undefined)

    if (values.length === 0) {
        return ""
    }

    return `( ${values.map((value) => locale.number(Number(value))).join(" , ")} )`
}
</script>

<template>
    <Table :resource="data" :name="tab ?? 'replenishments'">
        <template #cell(code)="{ item: replenishment }">
            <Link :href="orgStockRoute(replenishment) as string" class="primaryLink">
                {{ replenishment.code }}
            </Link>
        </template>

        <template #cell(other_locations)="{ item: replenishment }">
            <div v-if="replenishment.other_locations?.length" class="flex flex-col gap-y-0.5">
                <div
                    v-for="(otherLocation, index) in replenishment.other_locations"
                    :key="index"
                    class="flex justify-between gap-x-6 tabular-nums"
                >
                    <Link :href="locationRoute(otherLocation.slug) as string" class="primaryLink">
                        {{ otherLocation.code }}
                    </Link>
                    <span>{{ locale.number(Number(otherLocation.quantity)) }}</span>
                </div>
            </div>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(location)="{ item: replenishment }">
            <div v-if="replenishment.location" class="flex items-center gap-x-1.5">
                <FontAwesomeIcon
                    :icon="faFlag"
                    :class="replenishment.location.status === 'operational' ? 'text-green-500' : 'text-red-500'"
                    fixed-width
                    aria-hidden="true"
                />
                <Link :href="locationRoute(replenishment.location.slug) as string" class="primaryLink">
                    {{ replenishment.location.code }}
                </Link>
            </div>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(stock)="{ item: replenishment }">
            <span class="tabular-nums">{{ locale.number(Number(replenishment.stock)) }}</span>
        </template>

        <template #cell(ordered)="{ item: replenishment }">
            <span class="tabular-nums">{{ locale.number(Number(replenishment.ordered)) }}</span>
        </template>

        <template #cell(eventual_stock)="{ item: replenishment }">
            <span class="tabular-nums">{{ locale.number(Number(replenishment.eventual_stock)) }}</span>
        </template>

        <template #cell(recommended)="{ item: replenishment }">
            <span class="tabular-nums text-gray-500">{{ recommendedLabel(replenishment.recommended) }}</span>
        </template>
    </Table>
</template>
