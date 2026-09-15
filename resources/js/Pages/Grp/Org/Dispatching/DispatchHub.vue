<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 Feb 2023 14:32:57 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, reactive, ref, watch } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import { trans } from "laravel-vue-i18n"
import { faHandsHelping, faBan, faCheckCircle, faList, faCheck, faPersonCarry, faChartLine, faDolly, faIndustry } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import DispatchDashboard from "@/Components/Warehouse/DispatchDashboard.vue"
import Table from "@/Components/Table/Table.vue"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faMinus, faSpinnerThird } from "@far"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faHandsHelping, faBan, faCheckCircle, faList, faCheck, faPersonCarry, faChartLine, faDolly, faIndustry)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    delivery_note?: object
    picking_session?: object
    pickers_current?: {}
    packers_current?: {}
    partner_staging?: {
        org_partner_id: number
        org_stock_id: number
        stock_code: string
        stock_name: string
        partner_code: string
        to_location: string
        quantity_staged: number
        quantity_to_move: number
        from_locations: { location_org_stock_id: number, code: string, quantity: number }[]
    }[]
    stage_route?: { name: string; parameters: Record<string, string> }
    production_output?: {
        destination: { type: 'partner' | 'stock', label: string, location_code: string | null }
        job_order_ids: number[]
        jobs: { reference: string, artisan: string | null, items: { id: number, location_code: string | null, code: string, name: string, quantity: number }[] }[]
    }[] | null
    can_edit?: boolean
    put_away_route?: { name: string; parameters: Record<string, string> } | null
    reports_route?: { name: string; parameters: Record<string, string> }
    gate_route?: { name: string; parameters: Record<string, string> } | null
    intervals: any
    settings: any
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const isPersonnelTab = computed(() => currentTab.value === "pickers" || currentTab.value === "packers")
const isStagingTab = computed(() => currentTab.value === "partner_staging")
const isProductionTab = computed(() => currentTab.value === "production_output")
const tripKey = (trip: { destination: { location_code: string | null } }) => trip.destination.location_code ?? 'stock'
const putAwayLocation = reactive<Record<string, string>>({})
const itemLocation = reactive<Record<number, string>>({})
watch(() => props.production_output, trips => {
    trips?.forEach(trip => {
        putAwayLocation[tripKey(trip)] ??= trip.destination.location_code ?? ''
        trip.jobs.forEach(job => job.items.forEach(item => itemLocation[item.id] ??= item.location_code ?? ''))
    })
}, { immediate: true })

const page = usePage()
const actionError = computed(() => Object.values((page.props.errors ?? {}) as Record<string, string>)[0])

type ProductionTrip = NonNullable<typeof props.production_output>[number]
const tripItems = (trip: ProductionTrip) => trip.jobs.flatMap(job => job.items)
const canPutAway = (trip: ProductionTrip) => trip.destination.type === 'stock'
    ? tripItems(trip).every(item => itemLocation[item.id])
    : !!putAwayLocation[tripKey(trip)]

function putAway(trip: ProductionTrip, allowNewLocations = false) {
    if (!props.put_away_route || !canPutAway(trip)) return
    const locations = trip.destination.type === 'stock'
        ? { item_locations: Object.fromEntries(tripItems(trip).map(item => [item.id, itemLocation[item.id]])) }
        : { location_code: putAwayLocation[tripKey(trip)] }
    router.post(route(props.put_away_route.name, props.put_away_route.parameters), {
        ...locations,
        job_order_ids: trip.job_order_ids,
        allow_new_locations: allowNewLocations,
    }, {
        preserveScroll: true,
        onError: errors => {
            if (errors.new_location && window.confirm(errors.new_location + '. ' + trans('Add this location to the stock?'))) {
                putAway(trip, true)
            }
        },
    })
}

const stagingKey = (task: { org_partner_id: number, org_stock_id: number }) => task.org_partner_id + '-' + task.org_stock_id
const stagingSource = reactive<Record<string, number>>({})
const stagingQuantity = reactive<Record<string, number>>({})
const stagingInProgress = ref<string | null>(null)
watch(() => props.partner_staging, tasks => {
    tasks?.forEach(task => {
        const key = stagingKey(task)
        const source = task.from_locations[0]
        stagingSource[key] = source?.location_org_stock_id
        stagingQuantity[key] = source ? Math.min(task.quantity_to_move, source.quantity) : 0
    })
}, { immediate: true })

function stage(task: NonNullable<typeof props.partner_staging>[number]) {
    const key = stagingKey(task)
    if (!stagingSource[key] || !(stagingQuantity[key] > 0) || !props.stage_route) return
    router.post(route(props.stage_route.name, props.stage_route.parameters), {
        location_org_stock_id: stagingSource[key],
        org_partner_id: task.org_partner_id,
        quantity: stagingQuantity[key],
    }, {
        preserveScroll: true,
        onStart: () => { stagingInProgress.value = key },
        onFinish: () => { stagingInProgress.value = null },
    })
}
const currentWorkData = computed(() => currentTab.value === "pickers" ? props.pickers_current : props.packers_current)

const orderRoute = (order: { slug: string }) =>
    route("grp.org.warehouses.show.dispatching.delivery_notes.show", {
        organisation: route().params["organisation"],
        warehouse: route().params["warehouse"],
        deliveryNote: order.slug,
    })

const trolleyRoute = (trolley: { slug: string }) =>
    route("grp.org.warehouses.show.dispatching.trolleys.show", {
        organisation: route().params["organisation"],
        warehouse: route().params["warehouse"],
        trolley: trolley.slug,
    })
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <div v-if="actionError && (isStagingTab || isProductionTab)" class="mx-4 mt-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
        {{ actionError }}
    </div>

    <div v-if="isStagingTab" class="mx-4 mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2">{{ trans("For") }}</th>
                    <th class="px-4 py-2">{{ trans("SKO") }}</th>
                    <th class="px-4 py-2">{{ trans("From") }}</th>
                    <th class="px-4 py-2">{{ trans("To") }}</th>
                    <th class="px-4 py-2 text-right">{{ trans("Moved") }}</th>
                    <th class="px-4 py-2 text-right">{{ trans("To move") }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="task in partner_staging" :key="stagingKey(task)" class="border-t border-gray-100 dark:border-gray-800">
                    <td class="px-4 py-2 font-medium">{{ task.partner_code }}</td>
                    <td class="px-4 py-2">
                        <div class="font-medium">{{ task.stock_code }}</div>
                        <div class="text-gray-500">{{ task.stock_name }}</div>
                    </td>
                    <td class="px-4 py-2">
                        <select v-if="task.from_locations.length" v-model="stagingSource[stagingKey(task)]" class="rounded border-gray-300 py-1 font-mono text-sm">
                            <option v-for="location in task.from_locations" :key="location.location_org_stock_id" :value="location.location_org_stock_id">
                                {{ location.code }} ({{ location.quantity }})
                            </option>
                        </select>
                        <span v-else class="text-red-600">{{ trans("Nowhere to take it from") }}</span>
                    </td>
                    <td class="px-4 py-2 font-mono">{{ task.to_location }}</td>
                    <td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ task.quantity_staged }}</td>
                    <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ task.quantity_to_move }}</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <template v-if="can_edit && task.from_locations.length">
                            <span class="mr-2 inline-block w-12 text-right font-medium tabular-nums" :class="stagingQuantity[stagingKey(task)] > task.quantity_to_move ? 'text-amber-600' : 'text-red-600'">
                                <template v-if="stagingQuantity[stagingKey(task)] !== task.quantity_to_move">
                                    {{ stagingQuantity[stagingKey(task)] > task.quantity_to_move ? '+' : '' }}{{ Math.round((stagingQuantity[stagingKey(task)] - task.quantity_to_move) * 1000) / 1000 }}
                                </template>
                            </span>
                            <InputNumber v-model="stagingQuantity[stagingKey(task)]" :min="0" :maxFractionDigits="3" showButtons buttonLayout="horizontal" inputClass="w-16 text-center" class="mr-2">
                                <template #incrementbuttonicon>
                                    <FontAwesomeIcon :icon="faPlus" />
                                </template>
                                <template #decrementbuttonicon>
                                    <FontAwesomeIcon :icon="faMinus" />
                                </template>
                            </InputNumber>
                        </template>
                        <button v-if="can_edit && task.from_locations.length" type="button" class="relative rounded bg-indigo-600 px-3 py-1 text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-80" :disabled="stagingInProgress !== null" @click="stage(task)">
                            <span :class="{ invisible: stagingInProgress === stagingKey(task) }">{{ trans("Set as Moved") }}</span>
                            <FontAwesomeIcon v-if="stagingInProgress === stagingKey(task)" :icon="faSpinnerThird" spin class="absolute inset-0 m-auto" />
                        </button>
                    </td>
                </tr>
                <tr v-if="!partner_staging?.length">
                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">{{ trans("Nothing to stage") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-else-if="isProductionTab" class="mx-4 mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2">{{ trans("For") }}</th>
                    <th class="px-4 py-2">{{ trans("Carry") }}</th>
                    <th class="px-4 py-2">{{ trans("To location") }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="trip in production_output" :key="tripKey(trip)" class="border-t border-gray-100 dark:border-gray-800 align-top">
                    <td class="px-4 py-2" :class="trip.destination.type === 'partner' ? 'font-semibold text-indigo-700' : 'text-gray-500'">
                        {{ trip.destination.label }}
                    </td>
                    <td class="px-4 py-2">
                        <div v-for="job in trip.jobs" :key="job.reference" class="mb-1">
                            <div v-for="item in job.items" :key="item.id" class="flex items-center gap-2">
                                <input v-if="can_edit && trip.destination.type === 'stock'" v-model.trim="itemLocation[item.id]" type="text" :placeholder="trans('Location code')"
                                    class="w-36 rounded border-gray-300 py-0.5 font-mono text-sm uppercase" @keyup.enter="putAway(trip)" />
                                <span>
                                    <span class="font-semibold tabular-nums">{{ item.quantity }}</span> × <span class="font-medium">{{ item.code }}</span>
                                    <span class="text-gray-500">{{ item.name }}</span>
                                </span>
                            </div>
                            <div class="text-xs text-gray-500">{{ job.reference }}<span v-if="job.artisan"> · {{ job.artisan }}</span></div>
                        </div>
                    </td>
                    <td class="px-4 py-2">
                        <span v-if="trip.destination.type === 'stock'" class="text-gray-500">{{ trans("Per item") }}</span>
                        <input v-else-if="can_edit" v-model.trim="putAwayLocation[tripKey(trip)]" type="text" :placeholder="trans('Location code')"
                            class="w-36 rounded border-gray-300 font-mono text-sm uppercase" @keyup.enter="putAway(trip)" />
                        <span v-else class="font-mono">{{ trip.destination.location_code ?? '—' }}</span>
                    </td>
                    <td class="px-4 py-2 text-right">
                        <button v-if="can_edit" type="button" class="rounded bg-indigo-600 px-3 py-1 text-white hover:bg-indigo-700 disabled:opacity-40"
                            :disabled="!canPutAway(trip)" @click="putAway(trip)">
                            {{ trans("Put away") }}
                        </button>
                    </td>
                </tr>
                <tr v-if="!production_output?.length">
                    <td colspan="4" class="px-4 py-6 text-center text-gray-400">{{ trans("Nothing finished waiting for the warehouse") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <template v-else-if="isPersonnelTab">
        <div class="px-4 pt-2">
            <div class="flex justify-end gap-4">
                <Link
                    v-if="gate_route"
                    :href="route(gate_route.name, gate_route.parameters)"
                    class="text-sm text-indigo-600 hover:underline"
                >
                    {{ trans("The gate") }} →
                </Link>
                <Link
                    v-if="reports_route"
                    :href="route(reports_route.name, reports_route.parameters)"
                    class="text-sm text-indigo-600 hover:underline"
                >
                    {{ trans("Performance reports") }} →
                </Link>
            </div>
            <Table :resource="currentWorkData" :name="currentTab + '_current'">
                <template #cell(orders)="{ item }">
                    <div v-if="item.orders?.length" class="flex flex-wrap gap-1">
                        <Link
                            v-for="order in item.orders"
                            :key="order.slug"
                            :href="orderRoute(order)"
                            class="inline-block px-1.5 py-0.5 rounded text-xs font-mono bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors"
                        >
                            {{ order.reference }}
                        </Link>
                    </div>
                    <span v-else class="text-xs text-gray-300 italic">—</span>
                </template>
                <template #cell(trolleys)="{ item }">
                    <div v-if="item.trolleys?.length" class="flex flex-wrap gap-1">
                        <Link
                            v-for="trolley in item.trolleys"
                            :key="trolley.slug"
                            :href="trolleyRoute(trolley)"
                            class="inline-block px-1.5 py-0.5 rounded text-xs font-mono bg-orange-50 text-orange-700 hover:bg-orange-100 transition-colors"
                        >
                            {{ trolley.name }}
                        </Link>
                    </div>
                    <span v-else class="text-xs text-gray-300 italic">—</span>
                </template>
            </Table>
        </div>
    </template>

    <component
        v-else
        :is="DispatchDashboard"
        :tab="currentTab"
        :data="currentTab === 'picking_session' ? picking_session : delivery_note"
    ></component>
</template>
