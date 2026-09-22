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
import { faHandsHelping, faBan, faCheckCircle, faList, faCheck, faPersonCarry, faChartLine, faDolly, faIndustry, faClipboardListCheck, faSave, faLock, faChevronDown } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import DispatchDashboard from "@/Components/Warehouse/DispatchDashboard.vue"
import Table from "@/Components/Table/Table.vue"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faMinus, faSpinnerThird } from "@far"
import { PageHeadingTypes } from "@/types/PageHeading"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Popover from "@/Components/Popover.vue"

library.add(faHandsHelping, faBan, faCheckCircle, faList, faCheck, faPersonCarry, faChartLine, faDolly, faIndustry, faClipboardListCheck, faSave, faLock, faChevronDown)

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
    release_route?: { name: string; parameters: Record<string, string> }
    production_output?: {
        data: {
            id: number
            job_order_id: number
            job_order_reference: string
            artisan: string | null
            code: string
            name: string
            destinations: { type: 'partner' | 'stock', label: string, quantity: number, location_code: string | null, locations: { code: string, quantity: number }[] }[]
        }[]
    } | null
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
type ProductionItem = NonNullable<typeof props.production_output>['data'][number]
type ProductionDestination = ProductionItem['destinations'][number]
const destinationKey = (item: ProductionItem, destination: ProductionDestination) => item.id + '-' + (destination.type === 'stock' ? 'stock' : destination.location_code)
const putAwayLocation = reactive<Record<string, string>>({})
watch(() => props.production_output, output => {
    output?.data.forEach(item => item.destinations.forEach(destination => putAwayLocation[destinationKey(item, destination)] ??= destination.location_code ?? ''))
}, { immediate: true })

const page = usePage()
const actionError = computed(() => Object.values((page.props.errors ?? {}) as Record<string, string>)[0])

const stockInLocation = (item: ProductionItem, destination: ProductionDestination) => destination.locations.find(location => location.code === putAwayLocation[destinationKey(item, destination)]?.toUpperCase())?.quantity
const putAwayQuantity = reactive<Record<string, number>>({})
const isPuttingAway = ref(false)

function putAway(item: ProductionItem, destination: ProductionDestination, quantity: number | null = null, allowNewLocations = false) {
    const key = destinationKey(item, destination)
    if (!props.put_away_route || isPuttingAway.value || !putAwayLocation[key]) return
    router.post(route(props.put_away_route.name, props.put_away_route.parameters), {
        item_locations: { [item.id]: putAwayLocation[key] },
        job_order_ids: [item.job_order_id],
        ...(quantity ? { item_quantities: { [item.id]: quantity } } : {}),
        allow_new_locations: allowNewLocations,
    }, {
        preserveScroll: true,
        onStart: () => isPuttingAway.value = true,
        onFinish: () => isPuttingAway.value = false,
        onSuccess: () => delete putAwayQuantity[key],
        onError: errors => {
            if (errors.new_location && window.confirm(errors.new_location + '. ' + trans('Add this location to the stock?'))) {
                isPuttingAway.value = false
                putAway(item, destination, quantity, true)
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
function release(task: NonNullable<typeof props.partner_staging>[number]) {
    if (!props.release_route || !window.confirm(trans("Send what is left to move back to production?"))) return
    const key = stagingKey(task)
    router.post(route(props.release_route.name, props.release_route.parameters), {
        org_partner_id: task.org_partner_id,
        org_stock_id: task.org_stock_id,
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
                                    <FontAwesomeIcon :icon="faPlus" fixed-width />
                                </template>
                                <template #decrementbuttonicon>
                                    <FontAwesomeIcon :icon="faMinus" fixed-width />
                                </template>
                            </InputNumber>
                        </template>
                        <button v-if="can_edit && task.from_locations.length" type="button" class="relative rounded bg-indigo-600 px-3 py-1 text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-80" :disabled="stagingInProgress !== null" @click="stage(task)">
                            <span :class="{ invisible: stagingInProgress === stagingKey(task) }">{{ trans("Set as Moved") }}</span>
                            <FontAwesomeIcon v-if="stagingInProgress === stagingKey(task)" :icon="faSpinnerThird" spin class="absolute inset-0 m-auto" fixed-width />
                        </button>
                        <button v-if="can_edit && task.org_stock_id" type="button" class="ml-2 rounded border border-gray-300 px-3 py-1 text-gray-700 hover:bg-gray-50 disabled:cursor-wait disabled:opacity-80 dark:border-gray-600 dark:text-gray-200" :disabled="stagingInProgress !== null" @click="release(task)">
                            {{ trans("Back to production") }}
                        </button>
                    </td>
                </tr>
                <tr v-if="!partner_staging?.length">
                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">{{ trans("Nothing to stage") }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-else-if="isProductionTab && production_output">
        <Table :resource="production_output" name="production_output">
            <template #cell(code)="{ item }">
                <span class="font-medium">{{ item.code }}</span>
            </template>
            <template #cell(actions)="{ item }">
                <div v-for="destination in item.destinations" :key="destinationKey(item, destination)" class="flex items-center justify-between gap-x-6 py-0.5">
                    <div>
                        <div v-if="destination.type === 'partner'" class="flex items-center gap-x-2 font-mono text-base" v-tooltip="trans('Goods out bay of partner :partner', { partner: destination.label })">
                            <span class="inline-flex w-6 shrink-0 justify-center"><FontAwesomeIcon icon="fal fa-hands-helping" class="text-indigo-500" fixed-width aria-hidden="true" /></span>
                            {{ putAwayLocation[destinationKey(item, destination)] }}
                        </div>
                        <div v-else-if="destination.locations.length === 1" class="flex items-center gap-x-2 font-mono text-base" v-tooltip="trans('The only location of this stock')">
                            <span class="inline-flex w-6 shrink-0 justify-center"><FontAwesomeIcon icon="fal fa-lock" class="text-gray-400" fixed-width aria-hidden="true" /></span>
                            {{ putAwayLocation[destinationKey(item, destination)] }}
                        </div>
                        <div v-else-if="destination.locations.length > 1" class="relative w-fit">
                            <Popover position="left-0" width="w-64">
                                <template #button>
                                    <div class="flex items-center gap-x-2 font-mono text-base hover:text-indigo-700" v-tooltip="trans('This stock has :count locations, choose one', { count: String(destination.locations.length) })">
                                        <span class="inline-flex w-6 shrink-0 justify-center"><FontAwesomeIcon icon="fal fa-chevron-down" class="text-gray-400" fixed-width aria-hidden="true" /></span>
                                        {{ putAwayLocation[destinationKey(item, destination)] }}
                                    </div>
                                </template>
                                <template #content="{ close }">
                                    <div class="-mx-4 -my-3 divide-y divide-gray-100">
                                        <button v-for="location in destination.locations" :key="location.code" type="button"
                                            class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-indigo-50 focus:outline-none"
                                            :class="putAwayLocation[destinationKey(item, destination)] === location.code ? 'bg-indigo-50' : ''"
                                            @click="putAwayLocation[destinationKey(item, destination)] = location.code; close()">
                                            <span class="flex items-center gap-x-2">
                                                <FontAwesomeIcon icon="fal fa-check" fixed-width :class="putAwayLocation[destinationKey(item, destination)] === location.code ? 'text-indigo-600' : 'text-transparent'" aria-hidden="true" />
                                                <span class="font-mono text-base font-semibold">{{ location.code }}</span>
                                            </span>
                                            <span class="text-xs tabular-nums text-gray-500">{{ trans("stock in location") }}: {{ location.quantity }}</span>
                                        </button>
                                    </div>
                                </template>
                            </Popover>
                        </div>
                        <input v-else v-model.trim="putAwayLocation[destinationKey(item, destination)]" type="text" :placeholder="trans('Location code')" :disabled="!can_edit"
                            class="w-32 rounded py-0.5 font-mono text-base uppercase" :class="putAwayLocation[destinationKey(item, destination)] ? 'border-gray-300' : 'border-amber-400 bg-amber-50'" />
                        <div class="pl-8 text-xs tabular-nums" :class="destination.type === 'partner' ? 'text-indigo-700' : putAwayLocation[destinationKey(item, destination)] ? 'text-gray-500' : 'text-amber-700'">
                            <template v-if="destination.type === 'partner'">{{ trans("Partner bay") }} · {{ destination.label }}</template>
                            <template v-else-if="!putAwayLocation[destinationKey(item, destination)]">{{ trans("No location yet, type one") }}</template>
                            <template v-else-if="stockInLocation(item, destination) !== undefined">({{ trans("stock in location") }}: {{ stockInLocation(item, destination) }})</template>
                            <template v-else>({{ trans("new location for this stock") }})</template>
                        </div>
                    </div>
                    <NumberWithButtonSave v-if="can_edit" :key="destinationKey(item, destination) + '-' + destination.quantity" :modelValue="0" noUndoButton noSaveButton
                        :readonly="!putAwayLocation[destinationKey(item, destination)] || isPuttingAway" :bindToTarget="{ step: 1, min: 0, max: destination.quantity }"
                        @update:modelValue="(quantity: number) => putAwayQuantity[destinationKey(item, destination)] = quantity">
                        <template #suffix>
                            <div class="ml-1 flex items-center gap-x-1">
                                <Button v-if="putAwayQuantity[destinationKey(item, destination)] > 0" type="primary" size="xs" icon="fal fa-save" :loading="isPuttingAway"
                                    v-tooltip="trans('Put away :quantity', { quantity: String(putAwayQuantity[destinationKey(item, destination)]) })"
                                    @click="putAway(item, destination, putAwayQuantity[destinationKey(item, destination)])" />
                                <Button type="secondary" size="xs" icon="fal fa-clipboard-list-check" :label="String(destination.quantity)" :loading="isPuttingAway" :disabled="!putAwayLocation[destinationKey(item, destination)]"
                                    v-tooltip="trans('Put away all :quantity in :location', { quantity: String(destination.quantity), location: putAwayLocation[destinationKey(item, destination)] || '-' })"
                                    @click="putAway(item, destination)" />
                            </div>
                        </template>
                    </NumberWithButtonSave>
                    <span v-else class="font-semibold tabular-nums">{{ destination.quantity }}</span>
                </div>
            </template>
        </Table>
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
