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
    bucket: string
    bucket_label: string
    bucket_tone: string
    quantity_available: number
    days_of_cover: number | null
    lead_time_days: number
    on_the_way: boolean
    supplier_code: string | null
    supplier_id?: number | null
    org_supplier_slug?: string | null
    org_agent_slug?: string | null
    recommended_quantity: number | null
    stock_value: number
    projected_lost_revenue: number | null
    organisation_slug: string | null
    organisation_code: string | null
    organisation_name: string | null
    currency_code: string | null
    warehouse_slug: string | null
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    currency: string | null
    warehouseSlug: string | null
    exportRoute: routeType | null
    data: object
}>()

const locale = inject("locale", aikuLocaleStructure)

const toneDot: Record<string, string> = {
    "red-deep": "bg-red-700",
    red: "bg-red-500",
    orange: "bg-orange-500",
    "orange-light": "bg-orange-400",
    amber: "bg-amber-400",
    yellow: "bg-yellow-300",
    green: "bg-green-500",
    "red-light": "bg-red-400",
    blue: "bg-blue-500",
    gray: "bg-gray-400",
}

const organisationOf = (orgStock: StockCoverOrgStock) => orgStock.organisation_slug ?? route().params["organisation"]
const warehouseOf = (orgStock: StockCoverOrgStock) => orgStock.warehouse_slug ?? props.warehouseSlug
const currencyOf = (orgStock: StockCoverOrgStock) => orgStock.currency_code ?? props.currency ?? ""

const orgStockRoute = (orgStock: StockCoverOrgStock) => route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", {
    organisation: organisationOf(orgStock),
    warehouse: warehouseOf(orgStock),
    orgStock: orgStock.slug,
})

const organisationStockLevelRoute = (orgStock: StockCoverOrgStock) => route("grp.org.procurement.stock_cover.index", {
    organisation: orgStock.organisation_slug ?? "",
    _query: { elements: { cover: orgStock.bucket } },
})

const shoppingCover = (orgStock: StockCoverOrgStock) => orgStock.bucket === "excess" ? "ok" : orgStock.bucket

const supplierShoppingRoute = (orgStock: StockCoverOrgStock) => orgStock.org_agent_slug
    ? route("grp.org.procurement.org_agents.show.shopping.items.index", {
        organisation: organisationOf(orgStock),
        orgAgent: orgStock.org_agent_slug,
        _query: { cover: shoppingCover(orgStock), supplier: orgStock.supplier_id },
    })
    : route("grp.org.procurement.org_suppliers.show.shopping.items.index", {
        organisation: organisationOf(orgStock),
        orgSupplier: orgStock.org_supplier_slug,
        _query: { cover: shoppingCover(orgStock) },
    })

const downloadHref = () => props.exportRoute ? route(props.exportRoute.name, props.exportRoute.parameters) + window.location.search : null
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <a v-if="exportRoute" :href="downloadHref()!" class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                {{ ctrans("Download") }}
            </a>
        </template>
    </PageHeading>

    <Table :resource="data" class="mt-5">
        <template #cell(organisation_code)="{ item: orgStock }">
            <Link v-if="orgStock.organisation_slug" :href="organisationStockLevelRoute(orgStock)" class="primaryLink" v-tooltip="orgStock.organisation_name">{{ orgStock.organisation_code }}</Link>
        </template>

        <template #cell(code)="{ item: orgStock }">
            <Link v-if="warehouseOf(orgStock)" :href="orgStockRoute(orgStock)" class="primaryLink">{{ orgStock.code }}</Link>
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
            <Link v-if="orgStock.org_supplier_slug" :href="supplierShoppingRoute(orgStock)" class="primaryLink whitespace-nowrap" v-tooltip="ctrans('Order from this supplier')">{{ orgStock.supplier_code }}</Link>
            <span v-else class="whitespace-nowrap">{{ orgStock.supplier_code ?? "—" }}</span>
            <span v-if="orgStock.on_the_way" class="ml-1 whitespace-nowrap text-xs text-[var(--app-accent)]">{{ ctrans("on the way") }}</span>
        </template>

        <template #cell(recommended_quantity)="{ item: orgStock }">
            <span class="tabular-nums">{{ orgStock.recommended_quantity !== null ? locale.number(orgStock.recommended_quantity) : "—" }}</span>
        </template>

        <template #cell(projected_lost_revenue)="{ item: orgStock }">
            <span v-if="orgStock.projected_lost_revenue" class="tabular-nums" :class="orgStock.projected_lost_revenue >= 100 ? 'font-medium text-red-600' : ''">{{ locale.currencyFormat(currencyOf(orgStock), orgStock.projected_lost_revenue) }}</span>
            <span v-else class="text-gray-300">-</span>
        </template>

        <template #cell(stock_value)="{ item: orgStock }">
            <span class="tabular-nums">{{ locale.currencyFormat(currencyOf(orgStock), orgStock.stock_value) }}</span>
        </template>
    </Table>
</template>
