<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject } from "vue"
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
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
    { label: trans("Unit cost"), value: money(props.data.costs.unit_cost), strong: true },
    { label: trans("Extra costs"), value: `${locale.number(props.data.costs.extra_costs_percentage)} %` },
    { label: trans("Delivered unit cost"), value: money(props.data.costs.delivered_unit_cost), strong: true },
    { label: trans("Delivered pack cost"), value: money(props.data.costs.pack_cost) },
    { label: trans("Delivered carton cost"), value: money(props.data.costs.carton_cost) },
])

const packagingRows = computed(() => [
    {
        label: trans("Units per pack"),
        value: props.data.packaging.units_per_pack ? locale.number(props.data.packaging.units_per_pack) : "-",
    },
    {
        label: trans("Units per carton"),
        value: props.data.packaging.units_per_carton ? locale.number(props.data.packaging.units_per_carton) : "-",
    },
    {
        label: trans("Carton volume"),
        value: props.data.packaging.cbm ? `${locale.number(props.data.packaging.cbm)} m³` : "-",
    },
])

const availabilityBadge = (isAvailable: boolean) =>
    isAvailable
        ? { label: trans("Available"), class: "bg-green-50 text-green-700 ring-green-600/20" }
        : { label: trans("Not available"), class: "bg-red-50 text-red-700 ring-red-600/20" }
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
                    {{ trans("In") }} {{ data.organisation.name }}
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
                {{ trans("Supplier product in supply chain") }}
            </Link>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-money-bill' }" />
                {{ trans("Costs") }}
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
                {{ trans("Packaging") }}
            </h3>
            <dl class="divide-y divide-gray-100">
                <div v-for="row in packagingRows" :key="row.label" class="flex items-baseline justify-between py-2">
                    <dt class="text-sm text-gray-500">{{ row.label }}</dt>
                    <dd class="text-sm tabular-nums text-gray-700">{{ row.value }}</dd>
                </div>
            </dl>

            <template v-if="data.parties.length">
                <h3 class="mb-3 mt-5 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <Icon :data="{ icon: 'fal fa-person-dolly' }" />
                    {{ trans("Provided by") }}
                </h3>
                <div class="flex flex-col gap-2">
                    <Link v-for="party in data.parties" :key="party.label"
                        :href="route(party.route.name, party.route.parameters)"
                        class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:border-gray-300 hover:bg-gray-50">
                        <Image v-if="party.image" :src="party.image" class="h-8 w-8 shrink-0" />
                        <Icon v-else :data="{ icon: party.icon }" class="w-8 shrink-0 text-center text-gray-400" />
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wide text-gray-400">{{ party.label }}</div>
                            <div class="truncate text-sm font-medium text-gray-700">{{ party.name }}</div>
                        </div>
                        <span class="ml-auto text-xs tabular-nums text-gray-400">{{ party.code }}</span>
                    </Link>
                </div>
            </template>
        </section>

        <section v-if="data.trade_units.length" class="md:col-span-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-cubes' }" />
                {{ trans("Trade units") }}
            </h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <Link v-for="tradeUnit in data.trade_units" :key="tradeUnit.slug"
                    :href="route(tradeUnit.route.name, tradeUnit.route.parameters)"
                    class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:border-gray-300 hover:bg-gray-50">
                    <Image v-if="tradeUnit.image" :src="tradeUnit.image" class="h-12 w-12 shrink-0" />
                    <Icon v-else :data="{ icon: 'fal fa-cube' }" class="w-12 shrink-0 text-center text-gray-300" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-gray-700">{{ tradeUnit.name }}</div>
                        <div class="truncate text-xs text-gray-400">{{ tradeUnit.code }}</div>
                    </div>
                    <ProductUnitLabel :units="tradeUnit.units" :unit="tradeUnit.unit ?? undefined" />
                </Link>
            </div>
        </section>

        <section v-if="data.stocks.length" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <Icon :data="{ icon: 'fal fa-box-open' }" />
                {{ trans("SKUs") }}
            </h3>
            <div class="flex flex-col divide-y divide-gray-100">
                <Link v-for="stock in data.stocks" :key="stock.slug"
                    :href="route(stock.route.name, stock.route.parameters)"
                    class="flex items-baseline justify-between gap-3 py-2 hover:text-indigo-600">
                    <span class="truncate text-sm text-gray-700">{{ stock.name }}</span>
                    <span class="shrink-0 text-xs tabular-nums text-gray-400">{{ stock.code }}</span>
                </Link>
            </div>
        </section>

        <section v-if="data.stats.length" class="md:col-span-3">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                {{ trans("Statistics") }}
            </h3>
            <BoxDisplay :data="data.stats" />
        </section>
    </div>
</template>
