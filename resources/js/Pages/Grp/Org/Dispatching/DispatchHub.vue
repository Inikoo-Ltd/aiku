<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 Feb 2023 14:32:57 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Deferred, Head, Link, router, usePage } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, reactive, ref, watch } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import { ctrans } from "@/Composables/useTrans"
import { faHandsHelping, faBan, faCheckCircle, faList, faCheck, faPersonCarry, faChartLine, faDolly, faIndustry, faClipboardListCheck, faSave, faLock, faChevronDown } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import DispatchDashboard from "@/Components/Warehouse/DispatchDashboard.vue"
import DispatchCapacityPanel from "@/Components/Warehouse/DispatchCapacityPanel.vue"
import Table from "@/Components/Table/Table.vue"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faMinus } from "@far"
import { PageHeadingTypes } from "@/types/PageHeading"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useSessionStorage } from "@vueuse/core"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"

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
        is_cosmetic: boolean
        is_gb: boolean
        partner_code: string
        to_location: string
        quantity_staged: number
        quantity_to_move: number
        from_locations: { location_org_stock_id: number, code: string, quantity: number }[]
    }[]
    stage_route?: { name: string; parameters: Record<string, string> }
    release_route?: { name: string; parameters: Record<string, string> }
    can_edit?: boolean
    dispatch_capacity?: InstanceType<typeof DispatchCapacityPanel>["$props"]["rows"]
    current_warehouse_id?: number
    reports_route?: { name: string; parameters: Record<string, string> }
    gate_route?: { name: string; parameters: Record<string, string> } | null
    intervals: any
    settings: any
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const isPersonnelTab = computed(() => currentTab.value === "pickers" || currentTab.value === "packers")
const isStagingTab = computed(() => currentTab.value === "partner_staging")

const page = usePage()
const actionError = computed(() => Object.values((page.props.errors ?? {}) as Record<string, string>)[0])

const stagingKey = (task: { org_partner_id: number, org_stock_id: number }) => task.org_partner_id + '-' + task.org_stock_id
const stagingSource = reactive<Record<string, number | undefined>>({})
const stagingQuantity = reactive<Record<string, number>>({})
const stagingInProgress = ref<string | null>(null)
type StagingTask = NonNullable<typeof props.partner_staging>[number]
const stagingSourceQuantity = (task: StagingTask) =>
    task.from_locations.find(location => location.location_org_stock_id === stagingSource[stagingKey(task)])?.quantity ?? 0
function selectStagingSource(task: StagingTask, locationOrgStockId: number | undefined) {
    const key = stagingKey(task)
    stagingSource[key] = locationOrgStockId
    stagingQuantity[key] = Math.min(task.quantity_to_move, stagingSourceQuantity(task))
}
watch(() => props.partner_staging, tasks => {
    tasks?.forEach(task => selectStagingSource(task, task.from_locations[0]?.location_org_stock_id))
}, { immediate: true })

function stage(task: StagingTask) {
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
function release(task: StagingTask) {
    if (!props.release_route || !window.confirm(ctrans("Send what is left to move back to production?"))) return
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
const stagingPartner = useSessionStorage<string>("dispatch_hub_staging_partner", "")
const stagingPartners = computed(() => {
    const counts: Record<string, number> = {}
    props.partner_staging?.forEach(task => counts[task.partner_code] = (counts[task.partner_code] ?? 0) + 1)
    return Object.entries(counts).sort(([a], [b]) => a.localeCompare(b))
})
const activeStagingPartner = computed(() => stagingPartners.value.some(([code]) => code === stagingPartner.value) ? stagingPartner.value : "")
const stagingPartnerOptions = computed(() => [["", props.partner_staging?.length ?? 0] as [string, number], ...stagingPartners.value]
    .map(([code, count]) => ({ value: code, label: `${code || ctrans("All")} ${count}` })))
const stagingPallet = useSessionStorage<"all" | "gb" | "other">("dispatch_hub_staging_pallet", "all")
const stagingGbCount = computed(() => props.partner_staging?.filter(task => task.is_gb).length ?? 0)
const stagingPalletOptions = computed(() => [
    { value: "all" as const, label: ctrans("All") },
    { value: "gb" as const, label: `${ctrans("GB pallet")} ${stagingGbCount.value}` },
    { value: "other" as const, label: ctrans("Other") },
])
const stagingTasks = computed(() => props.partner_staging
    ?.filter(task => !activeStagingPartner.value || task.partner_code === activeStagingPartner.value)
    .filter(task => !stagingGbCount.value || stagingPallet.value === "all" || task.is_gb === (stagingPallet.value === "gb")))
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
    <div v-if="actionError && isStagingTab" class="mx-4 mt-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
        {{ actionError }}
    </div>

    <div v-if="isStagingTab && (stagingPartners.length > 1 || stagingGbCount)" class="mx-4 mt-4 flex flex-wrap items-center gap-4 text-sm">
        <div v-if="stagingPartners.length > 1" class="flex items-center gap-2">
            <span class="text-gray-500">{{ ctrans("Partner") }}:</span>
            <SegmentedToggle v-model="stagingPartner" :options="stagingPartnerOptions" :ariaLabel="ctrans('Partner')" />
        </div>
        <div v-if="stagingGbCount" class="flex items-center gap-2">
            <span class="text-gray-500">{{ ctrans("Pallet") }}:</span>
            <SegmentedToggle v-model="stagingPallet" :options="stagingPalletOptions" :ariaLabel="ctrans('Pallet')" />
        </div>
    </div>
    <div v-if="isStagingTab" class="mx-4 mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                <tr>
                    <th class="px-2 py-2 2xl:px-4">{{ ctrans("For") }}</th>
                    <th class="px-2 py-2 2xl:px-4">{{ ctrans("SKO") }}</th>
                    <th class="px-2 py-2 2xl:px-4">{{ ctrans("From") }}</th>
                    <th class="px-2 py-2 2xl:px-4">{{ ctrans("To") }}</th>
                    <th class="px-2 py-2 2xl:px-4 text-right">{{ ctrans("Moved") }}</th>
                    <th class="px-2 py-2 2xl:px-4 text-right">{{ ctrans("To move") }}</th>
                    <th class="px-2 py-2 2xl:px-4"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="task in stagingTasks" :key="stagingKey(task)" class="border-t border-gray-100 dark:border-gray-800">
                    <td class="px-2 py-2 2xl:px-4 font-medium">{{ task.partner_code }}</td>
                    <td class="px-2 py-2 2xl:px-4">
                        <div class="flex items-center gap-1.5 font-medium">
                            {{ task.stock_code }}
                            <span v-if="task.is_cosmetic" class="rounded-full bg-pink-100 px-1.5 text-xs font-normal text-pink-700">{{ ctrans("Cosmetic") }}</span>
                            <span v-if="task.is_gb" v-tooltip="ctrans('GB-origin, travels on the separate GB pallet')" class="rounded-full bg-slate-200 px-1.5 text-xs font-normal text-slate-700">GB</span>
                        </div>
                        <div class="text-gray-500">{{ task.stock_name }}</div>
                    </td>
                    <td class="px-2 py-2 2xl:px-4">
                        <Select v-if="task.from_locations.length" :modelValue="stagingSource[stagingKey(task)]" :options="task.from_locations" :optionLabel="location => `${location.code} (${location.quantity})`" optionValue="location_org_stock_id" size="small" class="font-mono text-sm" @update:modelValue="locationOrgStockId => selectStagingSource(task, locationOrgStockId)" />
                        <span v-else class="text-red-600">{{ ctrans("Nowhere to take it from") }}</span>
                    </td>
                    <td class="px-2 py-2 2xl:px-4 font-mono">{{ task.to_location }}</td>
                    <td class="px-2 py-2 2xl:px-4 text-right tabular-nums text-gray-500">{{ task.quantity_staged }}</td>
                    <td class="px-2 py-2 2xl:px-4 text-right font-semibold tabular-nums">{{ task.quantity_to_move }}</td>
                    <td class="px-2 py-2 2xl:px-4">
                        <div class="flex flex-wrap items-center justify-end gap-2 2xl:flex-nowrap">
                        <template v-if="can_edit && task.from_locations.length">
                            <span class="inline-block w-12 text-right font-medium tabular-nums" :class="stagingQuantity[stagingKey(task)] > task.quantity_to_move ? 'text-amber-600' : 'text-red-600'">
                                <template v-if="stagingQuantity[stagingKey(task)] !== task.quantity_to_move">
                                    {{ stagingQuantity[stagingKey(task)] > task.quantity_to_move ? '+' : '' }}{{ Math.round((stagingQuantity[stagingKey(task)] - task.quantity_to_move) * 1000) / 1000 }}
                                </template>
                            </span>
                            <InputNumber v-model="stagingQuantity[stagingKey(task)]" :min="0" :max="stagingSourceQuantity(task)" :maxFractionDigits="3" showButtons buttonLayout="horizontal" inputClass="w-16 text-center" class="shrink-0">
                                <template #incrementbuttonicon>
                                    <FontAwesomeIcon :icon="faPlus" fixed-width />
                                </template>
                                <template #decrementbuttonicon>
                                    <FontAwesomeIcon :icon="faMinus" fixed-width />
                                </template>
                            </InputNumber>
                        </template>
                        <Button v-if="can_edit && task.from_locations.length" type="tertiary" size="s" class="shrink-0 whitespace-nowrap" :label="ctrans('Set as Moved')" :loading="stagingInProgress === stagingKey(task)" :disabled="stagingInProgress !== null" @click="stage(task)">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button v-if="can_edit && task.org_stock_id" type="tertiary" size="s" class="shrink-0 whitespace-nowrap" icon="fal fa-industry" :label="ctrans('Back to production')" :disabled="stagingInProgress !== null" @click="release(task)" />
                        </div>
                    </td>
                </tr>
                <tr v-if="!partner_staging?.length">
                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">{{ ctrans("Nothing to stage") }}</td>
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
                    class="text-sm font-medium text-gray-700 hover:underline"
                >
                    {{ ctrans("The gate") }} →
                </Link>
                <Link
                    v-if="reports_route"
                    :href="route(reports_route.name, reports_route.parameters)"
                    class="text-sm font-medium text-gray-700 hover:underline"
                >
                    {{ ctrans("Performance reports") }} →
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

    <template v-else>
        <component
            :is="DispatchDashboard"
            :tab="currentTab"
            :data="currentTab === 'picking_session' ? picking_session : delivery_note"
        ></component>
        <Deferred data="dispatch_capacity">
            <template #fallback>
                <div class="skeleton mx-4 mt-6 h-32 rounded-lg" />
            </template>
            <DispatchCapacityPanel v-if="dispatch_capacity" :rows="dispatch_capacity" :currentWarehouseId="current_warehouse_id" />
        </Deferred>
    </template>
</template>
