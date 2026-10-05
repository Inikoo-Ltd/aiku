<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import qs from "qs"
import Table from "@/Components/Table/Table.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlusCircle, faMinusCircle, faPeopleArrows } from "@fal"

library.add(faPlusCircle, faMinusCircle, faPeopleArrows)

interface IncomingLine {
    type: "purchase_order" | "stock_delivery" | "partner_request"
    reference: string
    supplier_name: string | null
    supplier_code: string | null
    supplier_type: string
    state_label: string
    quantity: number
    eta: string | null
    is_estimate: boolean
    organisation_slug: string
}

interface Product {
    slug: string
    code: string
    name: string
    state: string
    state_label: string
}

interface IncomingSko {
    key: string
    products: Product[]
    lines: IncomingLine[]
}

type FacetKey = "catalogue" | "product_state" | "arrival" | "organisation" | "source" | "supplier"

const props = defineProps<{
    data: {
        facets?: Partial<Record<FacetKey, { value: string; label: string; count: number }[]>>
        active_facets?: Partial<Record<FacetKey, string | null>>
    }
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

const isMaster = Boolean(route().params.masterShop)

const inlineFacets = computed<{ key: FacetKey; label: string }[]>(() => [
    { key: "catalogue", label: ctrans("Catalogue") },
    { key: "organisation", label: ctrans("AW company") },
    { key: "source", label: ctrans("Source") },
    { key: "arrival", label: ctrans("Arrival") },
    { key: "product_state", label: isMaster ? ctrans("Master product") : ctrans("Product state") },
])

const dropdownFacets = computed<{ key: FacetKey; label: string }[]>(() => [{ key: "supplier", label: ctrans("Supplier") }])

const facetOptions = (key: FacetKey) => props.data?.facets?.[key] ?? []

function selectFacet(key: FacetKey, value: string | null): void {
    const query = qs.parse(location.search.substring(1)) as Record<string, any>
    const facetsKey = `${props.tab}_facets`
    query[facetsKey] = { ...(query[facetsKey] ?? {}), [key]: value }
    delete query[`${props.tab}Page`]

    router.get(location.pathname + "?" + qs.stringify(query, { skipNulls: true, encodeValuesOnly: true }), {}, { preserveState: true, preserveScroll: true, replace: true })
}
const productsExpanded = ref<Record<string, boolean>>({})
const linesExpanded = ref<Record<string, boolean>>({})

const shownProducts = (item: IncomingSko) => {
    const products = [...item.products].sort((a, b) => Number(b.state === "active") - Number(a.state === "active"))

    return productsExpanded.value[item.key] ? products : products.slice(0, 1)
}

const shownLines = (item: IncomingSko) => (linesExpanded.value[item.key] ? item.lines : item.lines.slice(0, 1))

const lineTypeLabel = (type: IncomingLine["type"]) =>
    ({ purchase_order: ctrans("Purchase order"), stock_delivery: ctrans("Stock delivery") })[type] ?? ctrans("Partner request")

const formatEta = (eta: string | null, isEstimate: boolean) =>
    eta ? (isEstimate ? "~ " : "") + useFormatTime(eta, { formatTime: "mdy" }) : "—"

const productHref = (slug: string) =>
    isMaster
        ? route("grp.masters.master_shops.show.master_products.show", [route().params.masterShop, slug])
        : route("grp.org.shops.show.catalogue.products.all_products.show", [route().params.organisation, route().params.shop, slug])
</script>

<template>
  <div>
    <div class="mx-4 mt-4 flex flex-wrap items-center gap-2">
        <template v-for="facet in inlineFacets" :key="facet.key">
            <PillFilterBar
                v-if="facetOptions(facet.key).length > 1 || data?.active_facets?.[facet.key]"
                :label="facet.label"
                :options="facetOptions(facet.key)"
                :selected="data?.active_facets?.[facet.key] ?? null"
                @select="(value) => selectFacet(facet.key, value)" />
        </template>
        <template v-for="facet in dropdownFacets" :key="facet.key">
            <PillFilterBar
                v-if="facetOptions(facet.key).length > 1 || data?.active_facets?.[facet.key]"
                dropdown
                :label="facet.label"
                :options="facetOptions(facet.key)"
                :selected="data?.active_facets?.[facet.key] ?? null"
                @select="(value) => selectFacet(facet.key, value)" />
        </template>
    </div>
    <Table :resource="data" :name="tab" class="mt-3">
        <template #cell(code)="{ item }">
            <div class="font-medium">{{ item.code }}</div>
            <div class="text-gray-500">{{ item.name }}</div>
        </template>

        <template #cell(products)="{ item }">
            <div v-for="product in shownProducts(item)" :key="product.slug">
                <Link :href="productHref(product.slug)" class="primaryLink">{{ product.code }}</Link>
                <span class="ml-1 text-gray-500">{{ product.name }}</span>
                <span v-if="product.state !== 'active'" class="ml-1 text-xs text-gray-400">({{ product.state_label }})</span>
            </div>
            <button
                v-if="item.products.length > 1"
                type="button"
                class="mt-0.5 text-xs text-gray-500 hover:text-gray-700"
                @click="productsExpanded[item.key] = !productsExpanded[item.key]"
            >
                <FontAwesomeIcon :icon="productsExpanded[item.key] ? faMinusCircle : faPlusCircle" fixed-width />
                {{ productsExpanded[item.key] ? ctrans("Show less") : ctrans(":count other products", { count: String(item.products.length - 1) }) }}
            </button>
            <span v-if="!item.products.length" class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ ctrans("Not in catalogue yet") }}</span>
        </template>

        <template #cell(suppliers)="{ item }">
            <div v-for="supplier in item.supplier_list" :key="supplier.code" class="whitespace-nowrap" v-tooltip="supplier.is_agent ? ctrans('Agent: :name', { name: supplier.name }) : supplier.name">
                <FontAwesomeIcon v-if="supplier.is_agent" :icon="faPeopleArrows" class="mr-1 text-gray-400" fixed-width />{{ supplier.code }}
            </div>
        </template>

        <template #cell(lines)="{ item }">
            <div v-for="line in shownLines(item)" :key="line.type + line.reference + line.state_label" class="whitespace-nowrap text-sm">
                <span class="font-medium" v-tooltip="lineTypeLabel(line.type)">{{ line.reference }}</span>
                <span v-if="isMaster" class="ml-1 uppercase text-gray-400">{{ line.organisation_slug }}</span>
                <span v-if="item.lines.some((other) => other.supplier_code !== line.supplier_code)" class="ml-1 text-gray-400" v-tooltip="line.supplier_name"><FontAwesomeIcon v-if="line.supplier_type === 'OrgAgent'" :icon="faPeopleArrows" fixed-width />{{ line.supplier_code }}</span>
                <span class="ml-1 text-gray-500">{{ line.state_label }}</span>
                <span class="ml-1 tabular-nums">{{ locale.number(line.quantity) }}</span>
                <span class="ml-1 text-gray-500">{{ formatEta(line.eta, line.is_estimate) }}</span>
            </div>
            <button
                v-if="item.lines.length > 1"
                type="button"
                class="mt-0.5 text-xs text-gray-500 hover:text-gray-700"
                @click="linesExpanded[item.key] = !linesExpanded[item.key]"
            >
                <FontAwesomeIcon :icon="linesExpanded[item.key] ? faMinusCircle : faPlusCircle" fixed-width />
                {{ linesExpanded[item.key] ? ctrans("Show less") : ctrans(":count more", { count: String(item.lines.length - 1) }) }}
            </button>
        </template>

        <template #cell(quantity)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.quantity) }}</span>
        </template>

        <template #cell(eta)="{ item }">
            <span class="whitespace-nowrap" v-tooltip="item.is_estimate ? ctrans('Estimated from how far it got and past lead times') : undefined">{{ formatEta(item.eta, item.is_estimate) }}</span>
        </template>
    </Table>
  </div>
</template>
