<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faBoxOpen,
    faChair,
    faCube,
    faCubes,
    faExclamationTriangle,
    faExternalLink,
    faLaugh,
    faMoneyBill,
    faPallet,
    faPeopleArrows,
    faPersonDolly,
    faSeedling,
} from "@fal"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { routeType } from "@/types/route"
import Image from "@common/Components/Image.vue"
import Icon from "@/Components/Icon.vue"
import BoxDisplay from "@/Components/DataDisplay/BoxDisplay.vue"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"

library.add(
    faBoxOpen,
    faChair,
    faCube,
    faCubes,
    faExclamationTriangle,
    faExternalLink,
    faLaugh,
    faMoneyBill,
    faPallet,
    faPeopleArrows,
    faPersonDolly,
    faSeedling,
)

const props = defineProps<{
    data: {
        product: {
            code: string
            name: string | null
            description: string | null
            image: object | null
            state: {
                label: string
                icon: { icon: string; class?: string; tooltip?: string }
            }
            is_available: boolean
            composition: string | null
            route: routeType | null
        }
        costs: {
            currency_code: string | null
            unit_cost: number
            extra_costs_percentage: number
            delivered_unit_cost: number
            pack_cost: number | null
            carton_cost: number | null
        }
        packaging: {
            units_per_pack: number | null
            units_per_carton: number | null
            cbm: number | null
        }
        trade_units: {
            slug: string
            code: string
            name: string | null
            unit: string | null
            units: number | string
            image: object | null
            route: routeType
        }[]
        stocks: {
            slug: string
            code: string
            name: string | null
            route: routeType
        }[]
        composition: {
            code: string
            name: string | null
            slug: string
            quantity: number
            route: routeType
            org_stocks: {
                code: string
                slug: string
                quantity: number
                organisation: { code: string; slug: string }
                route: routeType
            }[]
        }[]
        parties: {
            label: string
            icon: string
            name: string | null
            code: string | null
            image: object | null
            route: routeType
        }[]
        organisation?: {
            name: string
            code: string
            state: string
            is_available: boolean
        }
        supplierProductInfo?: {
            minimum_carton_order?: number
            delivery_time?: number
            unit_expense?: number
            extra_costs?: number
            barcode?: string
            net_weight?: number
            gross_weight?: number
            marketing_dimensions?: Record<string, any>
        }
        stats: {
            label: string
            count: number
            description?: string
            full?: boolean
        }[]
    }
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (value: number | null) =>
    value === null || value === undefined ? "-" : locale.currencyFormat(props.data.costs.currency_code, value)

const costRows = computed(() => [
    { label: ctrans("Unit cost"), value: money(props.data.costs.unit_cost), strong: true },
    { label: ctrans("Extra costs"), value: `${locale.number(props.data.costs.extra_costs_percentage)} %` },
    { label: ctrans("Delivered unit cost"), value: money(props.data.costs.delivered_unit_cost), strong: true },
    { label: ctrans("Delivered pack cost"), value: money(props.data.costs.pack_cost) },
    { label: ctrans("Delivered carton cost"), value: money(props.data.costs.carton_cost) },
])

const packagingRows = computed(() => [
    {
        label: ctrans("Units per pack"),
        value: props.data.packaging.units_per_pack ? locale.number(props.data.packaging.units_per_pack) : "-",
    },
    {
        label: ctrans("Units per carton"),
        value: props.data.packaging.units_per_carton ? locale.number(props.data.packaging.units_per_carton) : "-",
    },
    {
        label: ctrans("Carton volume"),
        value: props.data.packaging.cbm ? `${locale.number(props.data.packaging.cbm)} m³` : "-",
    },
])

const supplyingRows = computed(() => {
    const info = props.data.supplierProductInfo

    if (!info) {
        return []
    }

    return [
        {
            key: "minimum_carton_order",
            label: ctrans("Minimum order"),
            value: info.minimum_carton_order ? ctrans(":count cartons", { count: info.minimum_carton_order }) : null,
        },
        {
            key: "delivery_time",
            label: ctrans("Delivery time"),
            value: info.delivery_time ? ctrans(":days days", { days: info.delivery_time }) : null,
        },
        {
            key: "unit_expense",
            label: ctrans("Unit expense"),
            value: info.unit_expense ? money(info.unit_expense) : null,
        },
        {
            key: "barcode",
            label: ctrans("Barcode"),
            value: info.barcode,
        },
        {
            key: "net_weight",
            label: ctrans("Net weight"),
            value: info.net_weight ? `${locale.number(info.net_weight)} g` : null,
        },
        {
            key: "gross_weight",
            label: ctrans("Gross weight"),
            value: info.gross_weight ? `${locale.number(info.gross_weight)} g` : null,
        },
    ].filter((row) => row.value)
})

const availabilityBadge = (isAvailable: boolean) =>
    isAvailable
        ? { label: ctrans("Available"), class: "bg-green-50 text-green-700 ring-green-600/20" }
        : { label: ctrans("Not available"), class: "bg-red-50 text-red-700 ring-red-600/20" }
</script>

<template>
    <div class="grid gap-6 p-6 md:grid-cols-3">
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex gap-4">
                <Image v-if="data.product.image" :src="data.product.image" class="h-28 w-28 shrink-0" />
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <Icon :data="data.product.state.icon" v-tooltip="data.product.state.label" />
                        <span class="truncate text-lg font-semibold text-gray-800">{{ data.product.code }}</span>
                    </div>
                    <div class="mt-1 text-sm text-gray-600">{{ data.product.name }}</div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                            :class="availabilityBadge(data.product.is_available).class">
                            {{ availabilityBadge(data.product.is_available).label }}
                        </span>
                        <span v-if="data.product.composition"
                            class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-600">
                            {{ data.product.composition.replace("_", " ") }}
                        </span>
                    </div>
                </div>
            </div>

            <p v-if="data.product.description" class="mt-4 whitespace-pre-wrap text-sm text-gray-500">
                {{ data.product.description }}
            </p>

            <div v-if="data.organisation" class="mt-4 border-t border-gray-100 pt-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    {{ ctrans("In") }} {{ data.organisation.name }}
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                        :class="availabilityBadge(data.organisation.is_available).class">
                        {{ availabilityBadge(data.organisation.is_available).label }}
                    </span>
                    <span
                        class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-600">
                        {{ data.organisation.state.replace("_", " ") }}
                    </span>
                </div>
            </div>

            <Link v-if="data.product.route" :href="route(data.product.route.name, data.product.route.parameters)"
                class="mt-4 inline-flex items-center gap-1.5 text-xs font-medium text-indigo-500 hover:text-indigo-700">
                <Icon :data="{ icon: 'fal fa-external-link' }" />
                {{ ctrans("Supplier product in supply chain") }}
            </Link>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-money-bill' }" />
                {{ ctrans("Costs") }}
            </h3>
            <dl class="divide-y divide-gray-100">
                <div v-for="row in costRows" :key="row.label" class="flex items-baseline justify-between py-2">
                    <dt class="text-sm text-gray-500">{{ row.label }}</dt>
                    <dd class="tabular-nums" :class="row.strong ? 'text-base font-semibold text-gray-800' : 'text-sm text-gray-700'">
                        {{ row.value }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-pallet' }" />
                {{ ctrans("Packaging") }}
            </h3>
            <dl class="divide-y divide-gray-100">
                <div v-for="row in packagingRows" :key="row.label" class="flex items-baseline justify-between py-2">
                    <dt class="text-sm text-gray-500">{{ row.label }}</dt>
                    <dd class="text-sm tabular-nums text-gray-700">{{ row.value }}</dd>
                </div>
            </dl>

            <template v-if="supplyingRows.length">
                <h3 class="mb-3 mt-5 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <Icon :data="{ icon: 'fal fa-truck-container' }" />
                    {{ ctrans("Supplying") }}
                </h3>
                <dl class="divide-y divide-gray-100">
                    <div v-for="row in supplyingRows" :key="row.key" class="flex items-baseline justify-between py-2">
                        <dt class="text-sm text-gray-500">{{ row.label }}</dt>
                        <dd class="text-sm tabular-nums text-gray-700">{{ row.value }}</dd>
                    </div>
                </dl>
            </template>

            <template v-if="data.parties.length">
                <h3 class="mb-3 mt-5 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <Icon :data="{ icon: 'fal fa-person-dolly' }" />
                    {{ ctrans("Provided by") }}
                </h3>
                <div class="flex flex-col gap-2">
                    <component :is="party.route ? Link : 'div'" v-for="party in data.parties" :key="party.label"
                        :href="party.route ? route(party.route.name, party.route.parameters) : undefined"
                        class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:border-gray-300 hover:bg-gray-50">
                        <Image v-if="party.image" :src="party.image" class="h-8 w-8 shrink-0" />
                        <Icon v-else :data="{ icon: party.icon }" class="w-8 shrink-0 text-center text-gray-400" />
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wide text-gray-400">{{ party.label }}</div>
                            <div class="truncate text-sm font-medium text-gray-700">{{ party.name }}</div>
                        </div>
                        <span class="ml-auto text-xs tabular-nums text-gray-400">{{ party.code }}</span>
                    </component>
                </div>
            </template>
        </section>

        <section v-if="data.trade_units.length" class="md:col-span-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-cubes' }" />
                {{ ctrans("Trade units") }}
            </h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <component :is="tradeUnit.route ? Link : 'div'" v-for="tradeUnit in data.trade_units" :key="tradeUnit.slug"
                    :href="tradeUnit.route ? route(tradeUnit.route.name, tradeUnit.route.parameters) : undefined"
                    class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:border-gray-300 hover:bg-gray-50">
                    <Image v-if="tradeUnit.image" :src="tradeUnit.image" class="h-12 w-12 shrink-0" />
                    <Icon v-else :data="{ icon: 'fal fa-cube' }" class="w-12 shrink-0 text-center text-gray-300" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-gray-700">{{ tradeUnit.name }}</div>
                        <div class="truncate text-xs text-gray-400">{{ tradeUnit.code }}</div>
                    </div>
                    <ProductUnitLabel :units="tradeUnit.units" :unit="tradeUnit.unit ?? undefined" />
                </component>
            </div>
        </section>

        <!-- Same triangle as the master product composition page, read-only here -->
        <section v-if="data.composition.length" class="md:col-span-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-cubes' }" />
                {{ ctrans("Composition") }}
            </h3>
            <div class="flex flex-col gap-4">
                <div v-for="tradeUnit in data.composition" :key="tradeUnit.slug">
                    <component :is="tradeUnit.route ? Link : 'div'" :href="tradeUnit.route ? route(tradeUnit.route.name, tradeUnit.route.parameters) : undefined"
                        class="text-sm font-medium text-gray-700 hover:text-indigo-600">
                        {{ tradeUnit.code }} <span class="text-gray-400 font-normal">{{ tradeUnit.name }}</span>
                    </component>
                    <div v-if="tradeUnit.org_stocks.length" class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <component :is="orgStock.route ? Link : 'div'" v-for="orgStock in tradeUnit.org_stocks" :key="orgStock.slug"
                            :href="orgStock.route ? route(orgStock.route.name, orgStock.route.parameters) : undefined"
                            class="flex items-baseline justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 text-xs transition hover:border-gray-300 hover:bg-gray-50">
                            <span class="truncate text-gray-700">{{ orgStock.code }}</span>
                            <span class="shrink-0 text-gray-400">{{ orgStock.organisation.code }}</span>
                        </component>
                    </div>
                    <p v-else class="mt-1 text-xs text-gray-400">{{ ctrans("No org stock linked") }}</p>
                </div>
            </div>
        </section>

        <section v-if="data.stocks.length" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-box-open' }" />
                {{ ctrans("SKUs") }}
            </h3>
            <div class="flex flex-col divide-y divide-gray-100">
                <component :is="stock.route ? Link : 'div'" v-for="stock in data.stocks" :key="stock.slug"
                    :href="stock.route ? route(stock.route.name, stock.route.parameters) : undefined"
                    class="flex items-baseline justify-between gap-3 py-2 hover:text-indigo-600">
                    <span class="truncate text-sm text-gray-700">{{ stock.name }}</span>
                    <span class="shrink-0 text-xs tabular-nums text-gray-400">{{ stock.code }}</span>
                </component>
            </div>
        </section>

        <section v-if="data.stats.length" class="md:col-span-3">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                {{ ctrans("Statistics") }}
            </h3>
            <BoxDisplay :data="data.stats" />
        </section>
    </div>
</template>
