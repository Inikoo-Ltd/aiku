<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 23 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { inject } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBox, faDownload } from "@fal"

library.add(faBox, faDownload)

type StockCoverOrgStock = {
    slug: string
    code: string
    bucket_label: string
    bucket_tone: string
    quantity_available: number
    days_of_cover: number | null
    lead_time_days: number
    on_the_way: boolean
    supplier_code: string | null
    recommended_quantity: number | null
    stock_value: number
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    currency: string
    warehouseSlug: string | null
    exportRoute: routeType
    data: object
}>()

const locale = inject("locale", aikuLocaleStructure)

const toneDot: Record<string, string> = {
    "red-deep": "bg-red-700",
    red: "bg-red-500",
    orange: "bg-orange-500",
    amber: "bg-amber-400",
    yellow: "bg-yellow-300",
    green: "bg-green-500",
    blue: "bg-blue-500",
    gray: "bg-gray-400",
}

const orgStockRoute = (orgStock: StockCoverOrgStock) => route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", {
    organisation: route().params["organisation"],
    warehouse: props.warehouseSlug,
    orgStock: orgStock.slug,
})

const downloadHref = () => route(props.exportRoute.name, props.exportRoute.parameters) + window.location.search
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <a :href="downloadHref()" class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                {{ ctrans("Download") }}
            </a>
        </template>
    </PageHeading>

    <Table :resource="data" class="mt-5">
        <template #cell(code)="{ item: orgStock }">
            <Link v-if="warehouseSlug" :href="orgStockRoute(orgStock)" class="primaryLink">{{ orgStock.code }}</Link>
            <span v-else>{{ orgStock.code }}</span>
        </template>

        <template #cell(bucket_label)="{ item: orgStock }">
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                <span class="h-2 w-2 shrink-0 rounded-full" :class="toneDot[orgStock.bucket_tone]" />
                {{ orgStock.bucket_label }}
            </span>
        </template>

        <template #cell(quantity_available)="{ item: orgStock }">
            <span class="tabular-nums">{{ locale.number(Math.floor(orgStock.quantity_available)) }}</span>
        </template>

        <template #cell(days_of_cover)="{ item: orgStock }">
            <span class="tabular-nums">{{ orgStock.days_of_cover ?? "—" }}</span>
        </template>

        <template #cell(lead_time_days)="{ item: orgStock }">
            <span class="tabular-nums text-gray-500">{{ orgStock.lead_time_days }} {{ ctrans("days") }}</span>
        </template>

        <template #cell(supplier_code)="{ item: orgStock }">
            {{ orgStock.supplier_code ?? "—" }}
            <span v-if="orgStock.on_the_way" class="ml-1 text-xs text-indigo-600">{{ ctrans("on the way") }}</span>
        </template>

        <template #cell(recommended_quantity)="{ item: orgStock }">
            <span class="tabular-nums">{{ orgStock.recommended_quantity !== null ? locale.number(orgStock.recommended_quantity) : "—" }}</span>
        </template>

        <template #cell(stock_value)="{ item: orgStock }">
            <span class="tabular-nums">{{ locale.currencyFormat(currency, orgStock.stock_value) }}</span>
        </template>
    </Table>
</template>
