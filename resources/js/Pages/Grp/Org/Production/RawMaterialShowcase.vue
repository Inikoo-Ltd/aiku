<script setup lang='ts'>
import { Link } from '@inertiajs/vue3'
import { computed, inject } from 'vue'
import Icon from '@/Components/Icon.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { ctrans } from '@/Composables/useTrans'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faSeedling,
    faCheckCircle,
    faGhost,
    faTimesCircle,
    faInfinity,
    faArrowCircleUp,
    faExclamationCircle,
    faExclamationTriangle,
    faQuestionCircle,
} from '@fal'

library.add(
    faSeedling,
    faCheckCircle,
    faGhost,
    faTimesCircle,
    faInfinity,
    faArrowCircleUp,
    faExclamationCircle,
    faExclamationTriangle,
    faQuestionCircle,
)

interface UsedIn {
    artefact_id: number
    artefact_slug: string
    artefact_code: string
    artefact_name: string
    task_code: string | null
    task_name: string | null
    position: number | null
    quantity_per_unit: string
}

interface RawMaterialShowcaseData {
    code: string
    description: string
    state_label: string | null
    state_icon: { tooltip?: string, icon?: string, class?: string } | null
    type_label: string | null
    unit_label: string | null
    unit_cost: string | null
    currency_code: string | null
    quantity_on_location: string | null
    stock_status_label: string | null
    stock_status_icon: { tooltip?: string, icon?: string, class?: string } | null
    production: { slug: string, code: string, name: string } | null
    trade_unit: { id: number, code: string, name: string } | null
    org_stock: { id: number, code: string, name: string, quantity_in_locations: number } | null
    artefact: { id: number, slug: string, code: string, name: string } | null
    number_artefacts: number
    used_in: UsedIn[]
}

const props = defineProps<{
    data: RawMaterialShowcaseData
}>()

const locale = inject('locale', aikuLocaleStructure)

const routeParams = route().params

const artefactRoute = (slug: string) => route(
    'grp.org.productions.show.crafts.artefacts.show',
    [routeParams['organisation'], routeParams['production'], slug]
)

const unitCost = computed(() => props.data.unit_cost == null
    ? '-'
    : locale.currencyFormat(props.data.currency_code, props.data.unit_cost))

const quantityOnLocation = computed(() => props.data.quantity_on_location == null
    ? '-'
    : locale.number(Number(props.data.quantity_on_location)))
</script>

<template>
    <div class="p-4 space-y-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ data.description }}</h2>
                    <p class="text-sm text-gray-500">{{ data.code }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span v-if="data.state_label" class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded border border-gray-200 bg-gray-50">
                        <Icon :data="data.state_icon" />
                        {{ data.state_label }}
                    </span>
                    <span v-if="data.stock_status_label" class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded border border-gray-200 bg-gray-50">
                        <Icon :data="data.stock_status_icon" />
                        {{ data.stock_status_label }}
                    </span>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans('Type') }}</div>
                    <div class="text-sm">{{ data.type_label || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans('Unit') }}</div>
                    <div class="text-sm">{{ data.unit_label || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">
                        {{ ctrans('Unit cost') }}
                        <InformationIcon :information="ctrans('Cost per unit. Refreshed from the supplier cost of the linked stock (SKU) when there is one.')" />
                    </div>
                    <div class="text-sm tabular-nums">{{ unitCost }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">
                        {{ ctrans('Quantity on location') }}
                        <InformationIcon :information="ctrans('The figure stored on the raw material itself. It is a copy of the stock quantity, refreshed in the background, so it can lag behind. Without a linked stock (SKU) it is the only figure there is.')" />
                    </div>
                    <div class="text-sm tabular-nums">{{ quantityOnLocation }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans('Factory') }}</div>
                    <div class="text-sm">{{ data.production ? `${data.production.code} - ${data.production.name}` : '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans('Trade unit') }}</div>
                    <div class="text-sm">{{ data.trade_unit ? `${data.trade_unit.code} - ${data.trade_unit.name}` : '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">
                        {{ ctrans('Stock (SKU)') }}
                        <InformationIcon :information="ctrans('The inventory SKU this raw material draws from. Stock figures and unit cost are refreshed from it.')" />
                    </div>
                    <div class="text-sm">{{ data.org_stock ? data.org_stock.code : '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">
                        {{ ctrans('Quantity in locations') }}
                        <InformationIcon :information="ctrans('The live total held across all warehouse locations, read straight from the linked stock (SKU). This is the figure production planning trusts.')" />
                    </div>
                    <div class="text-sm tabular-nums">{{ data.org_stock ? locale.number(data.org_stock.quantity_in_locations) : '-' }}</div>
                </div>
                <div v-if="data.artefact">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans('Made from artefact') }}</div>
                    <div class="text-sm">
                        <Link :href="artefactRoute(data.artefact.slug)" class="primaryLink">
                            {{ data.artefact.code }} - {{ data.artefact.name }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold">
                {{ ctrans('Used in artefacts') }}
                <span v-if="data.number_artefacts" class="ml-1 text-gray-400 font-normal">({{ data.number_artefacts }})</span>
            </h3>

            <div v-if="data.used_in.length" class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="pb-2 pr-4">{{ ctrans('Artefact') }}</th>
                            <th class="pb-2 pr-4">{{ ctrans('Recipe step') }}</th>
                            <th class="pb-2 text-right">{{ ctrans('Quantity per unit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(usage, index) in data.used_in" :key="index" class="border-t border-gray-100">
                            <td class="py-2 pr-4">
                                <Link :href="artefactRoute(usage.artefact_slug)" class="primaryLink">
                                    {{ usage.artefact_code }}
                                </Link>
                                <span class="text-gray-500"> - {{ usage.artefact_name }}</span>
                            </td>
                            <td class="py-2 pr-4 text-gray-600">
                                <span v-if="usage.task_code">{{ usage.position }}. {{ usage.task_code }} - {{ usage.task_name }}</span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="py-2 text-right tabular-nums">{{ locale.number(Number(usage.quantity_per_unit)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                :data="{
                    title: ctrans('Not used in any recipe'),
                    description: ctrans('This raw material is not consumed by any artefact yet.')
                }"
            />
        </div>
    </div>
</template>
