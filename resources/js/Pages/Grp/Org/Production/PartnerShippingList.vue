<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 27 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, reactive, ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUserHardHat } from "@fal"

library.add(faUserHardHat)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    groupBy: string | null
    mixes: { artefact_id: number, code: string, name: string, unit: string, needed: number, on_hand: number, in_progress: number, shortfall: number, artisan: string | null, needed_for: string[] }[] | null
    artisanWorkload: { id: number, name: string, open_job_orders: number, hidden: boolean }[] | null
    groups: { label: string, items: { id: number, quantity: number, state: string, stock_code: string, stock_name: string, family: string | null, maker: string | null, buyer_code: string | null, customer_name: string | null, order_reference: string | null, job_order_reference: string | null, job_order_slug: string | null, priority: string, needed_by: string | null }[] }[] | null
    pickedOrders: {
        id: number
        reference: string
        net_amount: string
        currency_code: string
        buyer_name: string
        transactions_count: number
    }[]
}>()

const selected = reactive<Record<number, number>>({})

const hiddenGroupsKey = `to-produce-hidden-${props.groupBy}`
const hiddenGroups = ref<string[]>(JSON.parse(localStorage.getItem(hiddenGroupsKey) || "[]"))
watch(hiddenGroups, (value) => localStorage.setItem(hiddenGroupsKey, JSON.stringify(value)), { deep: true })

function toggleGroup(label: string) {
    const index = hiddenGroups.value.indexOf(label)
    index === -1 ? hiddenGroups.value.push(label) : hiddenGroups.value.splice(index, 1)
}

function toggle(item: { id: number, quantity: number }) {
    if (item.id in selected) {
        delete selected[item.id]
    } else {
        selected[item.id] = Number(item.quantity)
    }
}

function sendToWarehouse(orderId: number) {
    router.post(
        route("grp.org.productions.show.to_produce.send_to_warehouse", [route().params["organisation"], route().params["production"], orderId]),
        {},
        { preserveScroll: true }
    )
}

function createJobOrders(ids: number[] = Object.keys(selected).map(Number), employeeId: number | null = null, quantity: number | null = null) {
    router.post(
        route("grp.org.productions.show.to_produce.job_orders.store", [route().params["organisation"], route().params["production"]]),
        { ids, employee_id: employeeId, quantity },
        { preserveScroll: true, onSuccess: () => { for (const k in selected) delete selected[k] } }
    )
}

type BoardItem = { id: number, stock_code: string, stock_name: string, state: string, quantity: number, quantity_to_produce: number | null, maker: string | null, maker_id: number | null, preparing_at: string | null }
const LANE_BACKLOG = 0
const LANE_PREPARING = 1
const LANE_ASSIGNED = 2

function dropTarget(laneIndex: number): boolean {
    if (!dragging.value) return false
    if (laneIndex === LANE_BACKLOG) return !!dragging.value.preparing_at
    if (laneIndex === LANE_PREPARING) return !dragging.value.preparing_at
    if (laneIndex === LANE_ASSIGNED) return !!dragging.value.preparing_at
    return false
}

function setPreparing(item: BoardItem, preparing: boolean, quantity: number | null = null) {
    router.post(
        route("grp.org.productions.show.to_produce.items.preparing", [route().params["organisation"], route().params["production"], item.id]),
        { preparing, quantity },
        { preserveScroll: true }
    )
}

function onDrop(laneIndex: number, event: DragEvent) {
    if (!dragging.value || !dropTarget(laneIndex)) return
    if (laneIndex === LANE_ASSIGNED) {
        openPicker("assign", event)
    } else if (laneIndex === LANE_PREPARING) {
        openPicker("prepare", event)
    } else {
        setPreparing(dragging.value, false)
        dragging.value = null
    }
}
const dragging = ref<BoardItem | null>(null)
const pendingAssign = ref<BoardItem | null>(null)
const pickerMode = ref<"prepare" | "assign">("prepare")
const assignQuantity = ref(1)
const pickerPosition = ref({ x: 0, y: 0 })

function openPicker(mode: "prepare" | "assign", event: DragEvent) {
    if (!dragging.value) return
    pickerMode.value = mode
    pendingAssign.value = dragging.value
    assignQuantity.value = Math.ceil(Number(dragging.value.quantity_to_produce ?? dragging.value.quantity))
    const laneLeft = (event.currentTarget as HTMLElement).getBoundingClientRect().left
    pickerPosition.value = {
        x: Math.max(8, laneLeft - 144),
        y: Math.max(8, Math.min(event.clientY - 40, window.innerHeight - 380)),
    }
    dragging.value = null
}

function confirmPrepare() {
    if (pendingAssign.value) {
        setPreparing(pendingAssign.value, true, assignQuantity.value)
        pendingAssign.value = null
    }
}

function assign(employeeId: number) {
    if (pendingAssign.value) {
        createJobOrders([pendingAssign.value.id], employeeId, Math.ceil(Number(pendingAssign.value.quantity_to_produce ?? pendingAssign.value.quantity)))
        pendingAssign.value = null
    }
}

type BoardFilterKey = "family" | "requester" | "priority" | "artisan"
const boardFilters = reactive<Record<BoardFilterKey, string[]>>({ family: [], requester: [], priority: [], artisan: [] })

function requesterOf(item: { buyer_code: string | null, customer_name: string | null }): string {
    return item.buyer_code ?? item.customer_name ?? ""
}

const boardFilterOptions = computed(() => {
    const items = (props.groups ?? []).flatMap(group => group.items)
    const counted = (values: string[]) => {
        const counts: Record<string, number> = {}
        values.filter(Boolean).forEach(value => counts[value] = (counts[value] ?? 0) + 1)
        return Object.entries(counts).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0])).map(([value, count]) => ({ value, count }))
    }
    return {
        family: counted(items.map(item => item.family ?? "")),
        requester: counted(items.map(requesterOf)),
        priority: counted(items.map(item => item.priority)),
        artisan: counted(items.map(item => item.job_order_artisan ?? "")),
    }
})

function toggleBoardFilter(key: BoardFilterKey, value: string) {
    const index = boardFilters[key].indexOf(value)
    index === -1 ? boardFilters[key].push(value) : boardFilters[key].splice(index, 1)
}

const filteredGroups = computed(() =>
    (props.groups ?? []).map((lane, laneIndex) => ({
        ...lane,
        items: lane.items.filter(item =>
            (!boardFilters.family.length || boardFilters.family.includes(item.family ?? ""))
            && (!boardFilters.requester.length || boardFilters.requester.includes(requesterOf(item)))
            && (!boardFilters.priority.length || boardFilters.priority.includes(item.priority))
            && (laneIndex < LANE_ASSIGNED || !boardFilters.artisan.length || boardFilters.artisan.includes(item.job_order_artisan ?? ""))
        ),
    }))
)

const artisanChoices = computed(() => {
    const list = (props.artisanWorkload ?? []).filter(artisan => !artisan.hidden)
    const defaultId = pendingAssign.value?.maker_id
    return defaultId ? [...list.filter(a => a.id === defaultId), ...list.filter(a => a.id !== defaultId)] : list
})

const showHiddenArtisans = ref(false)

function toggleArtisan(artisan: { id: number, hidden: boolean }) {
    router.post(
        route(artisan.hidden ? "grp.org.productions.show.to_produce.artisans.show" : "grp.org.productions.show.to_produce.artisans.hide", [route().params["organisation"], route().params["production"], artisan.id]),
        {},
        { preserveScroll: true }
    )
}

const mixQuantities = reactive<Record<number, number>>({})

function toggleMix(mix: { artefact_id: number, shortfall: number }) {
    if (mix.artefact_id in mixQuantities) {
        delete mixQuantities[mix.artefact_id]
    } else {
        mixQuantities[mix.artefact_id] = Math.ceil(mix.shortfall) || 1
    }
}

function createMixJobOrders() {
    router.post(
        route("grp.org.productions.show.to_produce.mixes.job_orders.store", [route().params["organisation"], route().params["production"]]),
        { lines: Object.entries(mixQuantities).map(([artefact_id, quantity]) => ({ artefact_id: Number(artefact_id), quantity })) },
        { preserveScroll: true, onSuccess: () => { for (const k in mixQuantities) delete mixQuantities[k] } }
    )
}

function jobOrderHref(item: { job_order_slug: string }) {
    return route("grp.org.productions.show.operations.job-orders.show", [route().params["organisation"], route().params["production"], item.job_order_slug])
}

function submitCherryPick() {
    const lines = Object.entries(selected).map(([id, quantity]) => ({ id: Number(id), quantity }))
    router.post(
        route("grp.org.productions.show.to_produce.cherry_pick", [route().params["organisation"], route().params["production"]]),
        { lines },
        { preserveScroll: true, onSuccess: () => { for (const k in selected) delete selected[k] } }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div v-if="Object.keys(selected).length" class="sticky top-0 z-10 mx-4 mt-4 flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-2 text-white">
        <span>{{ Object.keys(selected).length }} {{ trans("lines selected") }}</span>
        <div class="flex gap-2">
            <button type="button" class="rounded bg-white px-3 py-1 text-indigo-600" @click="createJobOrders">
                {{ trans("Create job orders") }}
            </button>
            <button type="button" class="rounded bg-white px-3 py-1 text-indigo-600" @click="submitCherryPick">
                {{ trans("Pick into order") }}
            </button>
        </div>
    </div>

    <div v-if="pickedOrders?.length" class="mx-4 mt-4 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="border-b border-gray-200 px-4 py-2 font-medium dark:border-gray-700">
            {{ trans("Picked orders waiting to be sent to warehouse") }}
        </div>
        <div v-for="order in pickedOrders" :key="order.id" class="flex items-center justify-between px-4 py-2">
            <div class="flex items-center gap-4">
                <span class="font-medium">{{ order.buyer_name }}</span>
                <span class="text-gray-500">{{ order.reference }}</span>
                <span class="text-gray-500">{{ order.transactions_count }} {{ trans("lines") }}</span>
                <span class="tabular-nums">{{ useLocaleStore().currencyFormat(order.currency_code, Number(order.net_amount)) }}</span>
            </div>
            <button type="button" class="rounded bg-indigo-600 px-3 py-1 text-white" @click="sendToWarehouse(order.id)">
                {{ trans("Send to warehouse") }}
            </button>
        </div>
    </div>

    <div v-if="mixes" class="mx-4 mt-5">
        <div v-if="Object.keys(mixQuantities).length" class="sticky top-0 z-10 mb-4 flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-2 text-white">
            <span>{{ Object.keys(mixQuantities).length }} {{ trans("mixes selected") }}</span>
            <button type="button" class="rounded bg-white px-3 py-1 text-indigo-600" @click="createMixJobOrders">{{ trans("Create job orders") }}</button>
        </div>
        <div v-if="!mixes.length" class="rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center text-gray-400">
            {{ trans("No mixes needed. Mixes appear here when an open job order uses a raw material that is made in-house.") }}
        </div>
        <table v-else class="w-full text-sm">
            <thead class="text-left text-gray-500">
                <tr>
                    <th class="w-36 px-4 py-2"></th>
                    <th class="px-2 py-2">{{ trans("Mix") }}</th>
                    <th class="px-2 py-2">{{ trans("Prepared by") }}</th>
                    <th class="px-2 py-2">{{ trans("Needed for") }}</th>
                    <th class="px-2 py-2 text-right">{{ trans("Needed") }}</th>
                    <th class="px-2 py-2 text-right">{{ trans("On hand") }}</th>
                    <th class="px-2 py-2 text-right">{{ trans("Being made") }}</th>
                    <th class="px-4 py-2 text-right">{{ trans("Short") }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="mix in mixes" :key="mix.artefact_id" class="border-t border-gray-100 dark:border-gray-800">
                    <td class="px-4 py-1.5">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" :checked="mix.artefact_id in mixQuantities" @change="toggleMix(mix)" />
                            <input v-if="mix.artefact_id in mixQuantities" v-model.number="mixQuantities[mix.artefact_id]" type="number" min="1" class="w-24 rounded border-gray-300" />
                        </div>
                    </td>
                    <td class="px-2 py-1.5"><span class="font-medium">{{ mix.code }}</span> <span class="text-gray-500">{{ mix.name }}</span></td>
                    <td class="px-2 py-1.5 text-gray-500">{{ mix.artisan ?? '-' }}</td>
                    <td class="px-2 py-1.5 text-gray-500">{{ mix.needed_for.join(', ') }}</td>
                    <td class="px-2 py-1.5 text-right tabular-nums">{{ useLocaleStore().number(mix.needed) }} {{ mix.unit }}</td>
                    <td class="px-2 py-1.5 text-right tabular-nums">{{ useLocaleStore().number(mix.on_hand) }}</td>
                    <td class="px-2 py-1.5 text-right tabular-nums">{{ useLocaleStore().number(mix.in_progress) }}</td>
                    <td class="px-4 py-1.5 text-right tabular-nums" :class="mix.shortfall > 0 ? 'font-semibold text-red-600' : 'text-gray-400'">{{ useLocaleStore().number(mix.shortfall) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-if="artisanWorkload && groupBy === 'maker'" class="mx-4 mt-5 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
        <div class="mb-2 flex items-center gap-2 text-sm text-gray-500">
            <span>{{ trans("Open job orders per artisan") }} <span class="text-gray-400">· {{ trans("everyone should have at least two") }}</span></span>
            <button v-if="artisanWorkload.some(artisan => artisan.hidden)" type="button" class="ml-auto text-xs text-gray-400 hover:text-indigo-600" @click="showHiddenArtisans = !showHiddenArtisans">
                {{ artisanWorkload.filter(artisan => artisan.hidden).length }} {{ trans("not artisans") }} · {{ showHiddenArtisans ? trans("hide") : trans("show") }}
            </button>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <span
                v-for="artisan in artisanWorkload.filter(artisan => !artisan.hidden)"
                :key="artisan.id"
                class="flex items-center gap-1 rounded-full border px-2.5 py-px text-xs"
                :class="artisan.open_job_orders === 0 ? 'border-red-300 bg-red-50 text-red-700' : artisan.open_job_orders === 1 ? 'border-amber-300 bg-amber-50 text-amber-700' : 'border-gray-200 text-gray-600'">
                {{ artisan.name }} · {{ artisan.open_job_orders }}
                <button type="button" class="opacity-40 hover:opacity-100" :title="trans('Not an artisan')" @click="toggleArtisan(artisan)">×</button>
            </span>
        </div>
        <div v-if="showHiddenArtisans" class="mt-2 flex flex-wrap gap-1.5">
            <span v-for="artisan in artisanWorkload.filter(artisan => artisan.hidden)" :key="artisan.id" class="flex items-center gap-1 rounded-full border border-dashed border-gray-300 px-2.5 py-px text-xs text-gray-400">
                {{ artisan.name }}
                <button type="button" class="hover:text-indigo-600" :title="trans('Is an artisan')" @click="toggleArtisan(artisan)">+</button>
            </span>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="pendingAssign" class="fixed inset-0 z-40" @click="pendingAssign = null" />
        <div v-if="pendingAssign" class="fixed z-50 w-72 rounded-lg border border-indigo-300 bg-white p-3 text-xs shadow-xl dark:bg-gray-900" :style="{ left: pickerPosition.x + 'px', top: pickerPosition.y + 'px' }">
            <div class="mb-1.5 font-medium">{{ pendingAssign.stock_code }} <span class="font-normal text-gray-500">{{ pendingAssign.stock_name }}</span></div>
            <template v-if="pickerMode === 'prepare'">
                <div class="mb-1 text-gray-500">{{ trans("How many to make? (labels)") }}</div>
                <form class="flex items-center gap-2" @submit.prevent="confirmPrepare">
                    <input v-model.number="assignQuantity" type="number" min="1" step="1" autofocus class="w-20 rounded border-gray-300 py-0.5 text-xs tabular-nums" />
                    <span v-if="assignQuantity > Math.ceil(Number(pendingAssign.quantity))" class="text-gray-400">+{{ assignQuantity - Math.ceil(Number(pendingAssign.quantity)) }} {{ trans("for stock") }}</span>
                    <button type="submit" class="ml-auto rounded bg-indigo-600 px-3 py-1 font-medium text-white hover:bg-indigo-500">{{ trans("Prepare") }}</button>
                </form>
            </template>
            <div v-else class="mb-1 text-gray-500">{{ trans("Who makes :count?", { count: Math.ceil(Number(pendingAssign.quantity_to_produce ?? pendingAssign.quantity)) }) }}</div>
            <div v-if="pickerMode === 'assign'" class="flex max-h-64 flex-col gap-0.5 overflow-y-auto">
                <button
                    v-for="artisan in artisanChoices"
                    :key="artisan.id"
                    type="button"
                    class="flex items-center gap-1.5 rounded px-2 py-1 text-left hover:bg-indigo-50"
                    :class="artisan.id === pendingAssign.maker_id ? 'bg-indigo-50 font-medium text-indigo-700' : ''"
                    @click="assign(artisan.id)">
                    <FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width class="text-gray-400" />
                    <span class="truncate">{{ artisan.name }}</span>
                    <span v-if="artisan.id === pendingAssign.maker_id" class="ml-auto text-[10px] uppercase tracking-wide">{{ trans("default") }}</span>
                    <span v-else class="ml-auto text-gray-400">{{ artisan.open_job_orders }}</span>
                </button>
            </div>
        </div>
    </Teleport>

    <div v-if="groupBy === 'board' && groups" class="mx-4 mt-4 flex items-stretch gap-3 text-sm">
        <div class="flex flex-1 flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900">
            <div v-for="(label, key) in { family: trans('Category'), requester: trans('Requester'), priority: trans('Urgency') }" :key="key" class="flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ label }}</span>
                <button
                    v-for="option in boardFilterOptions[key]"
                    :key="option.value"
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                    :class="boardFilters[key].includes(option.value)
                        ? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
                        : option.value === 'urgent' ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                    @click="toggleBoardFilter(key, option.value)">
                    <span class="capitalize">{{ option.value }}</span>
                    <span class="rounded-full px-1.5 text-xs tabular-nums" :class="boardFilters[key].includes(option.value) ? 'bg-white/20' : 'bg-white text-gray-500'">{{ option.count }}</span>
                </button>
            </div>
            <button v-if="boardFilters.family.length || boardFilters.requester.length || boardFilters.priority.length" type="button" class="ml-auto text-xs text-gray-400 hover:text-gray-600" @click="boardFilters.family = []; boardFilters.requester = []; boardFilters.priority = []">× {{ trans("Clear") }}</button>
        </div>
        <div v-if="boardFilterOptions.artisan.length" class="flex flex-wrap items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-2.5 dark:border-emerald-900 dark:bg-emerald-950/40">
            <span class="mr-1 text-xs font-medium uppercase tracking-wide text-emerald-700/70">{{ trans("Artisan") }}</span>
            <button
                v-for="option in boardFilterOptions.artisan"
                :key="option.value"
                type="button"
                class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                :class="boardFilters.artisan.includes(option.value) ? 'border-emerald-600 bg-emerald-600 text-white shadow-sm' : 'border-emerald-200 bg-white text-emerald-800 hover:border-emerald-400'"
                @click="toggleBoardFilter('artisan', option.value)">
                <FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width :class="boardFilters.artisan.includes(option.value) ? 'text-white/70' : 'text-emerald-500'" />
                {{ option.value }}
                <span class="rounded-full px-1.5 text-xs tabular-nums" :class="boardFilters.artisan.includes(option.value) ? 'bg-white/20' : 'bg-emerald-100 text-emerald-700'">{{ option.count }}</span>
            </button>
            <button v-if="boardFilters.artisan.length" type="button" class="ml-1 text-xs text-emerald-700/60 hover:text-emerald-800" @click="boardFilters.artisan = []">×</button>
        </div>
    </div>

    <div v-if="groupBy === 'board' && groups" class="mx-4 mt-3 flex gap-3">
        <div
            v-for="(lane, laneIndex) in filteredGroups"
            :key="lane.label"
            class="flex min-w-0 flex-1 flex-col rounded-lg border bg-gray-50 transition dark:bg-gray-800"
            :class="dropTarget(laneIndex) ? 'border-indigo-400 ring-2 ring-indigo-200' : 'border-gray-200 dark:border-gray-700'"
            @dragover="dropTarget(laneIndex) ? $event.preventDefault() : null"
            @drop.prevent="onDrop(laneIndex, $event)">
            <div class="flex items-center gap-2 px-3 py-2 font-medium">
                <span>{{ lane.label }}</span>
                <span class="ml-auto rounded-full bg-white px-2 text-xs text-gray-500 dark:bg-gray-900">{{ lane.items.length }}</span>
            </div>
            <div class="flex max-h-[70vh] flex-col gap-1.5 overflow-y-auto px-2 pb-2">
                <div
                    v-for="item in lane.items"
                    :key="item.id"
                    class="rounded border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900"
                    :class="laneIndex <= LANE_PREPARING && item.state === 'open' ? 'cursor-grab active:cursor-grabbing' : ''"
                    :draggable="laneIndex <= LANE_PREPARING && item.state === 'open'"
                    @dragstart="dragging = item"
                    @dragend="dragging = null">
                    <div class="flex items-center gap-1.5">
                        <span class="font-medium">{{ item.stock_code }}</span>
                        <span class="ml-auto tabular-nums" :class="item.priority === 'urgent' ? 'text-red-600 font-semibold' : ''">
                            ×{{ useLocaleStore().number(Number(item.quantity)) }}
                            <span v-if="(item.job_order_quantity ?? item.quantity_to_produce) && Number(item.job_order_quantity ?? item.quantity_to_produce) !== Number(item.quantity)" class="text-indigo-600" :title="trans('Making :count', { count: item.job_order_quantity ?? item.quantity_to_produce })">→ {{ useLocaleStore().number(Number(item.job_order_quantity ?? item.quantity_to_produce)) }}</span>
                        </span>
                    </div>
                    <div class="truncate text-gray-600" :title="item.stock_name">{{ item.stock_name }}</div>
                    <div class="flex items-center gap-1 text-gray-400">
                        <span>{{ item.buyer_code ?? item.customer_name }}</span>
                        <span v-if="item.family">· {{ item.family }}</span>
                        <Link v-if="item.job_order_slug" :href="jobOrderHref(item)" class="primaryLink ml-auto">{{ item.job_order_reference }}</Link>
                    </div>
                    <div v-if="item.job_order_id" class="flex items-center gap-1 text-gray-600">
                        <FontAwesomeIcon icon="fal fa-user-hard-hat" class="text-gray-400" fixed-width />
                        {{ item.job_order_artisan ?? trans("No artisan") }}
                    </div>
                    <div v-else-if="item.maker" class="flex items-center gap-1 text-gray-400">
                        <FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width />
                        {{ item.maker }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-else-if="groups" class="mx-4 mt-5 space-y-6">
        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="group in groups"
                :key="group.label"
                type="button"
                class="rounded-full border px-2.5 py-px text-xs"
                :class="hiddenGroups.includes(group.label) ? 'border-gray-200 text-gray-400 hover:bg-gray-50' : 'border-indigo-500 bg-indigo-50 text-indigo-700'"
                @click="toggleGroup(group.label)">
                {{ group.items.length }} {{ group.label }}
            </button>
        </div>
        <div v-for="group in groups.filter(group => !hiddenGroups.includes(group.label))" :key="group.label" class="rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-2 font-medium dark:border-gray-700 dark:bg-gray-800">
                <span>{{ group.label }}</span>
                <span class="text-gray-500">{{ group.items.length }} {{ trans("lines") }}</span>
            </div>
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="item in group.items" :key="item.id" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <td class="w-36 px-4 py-1.5">
                            <div class="flex items-center gap-2">
                                <input v-if="item.state === 'open'" type="checkbox" :checked="item.id in selected" @change="toggle(item)" />
                                <input v-if="item.id in selected" v-model.number="selected[item.id]" type="number" step="0.001" :max="item.quantity" min="0.001" class="w-24 rounded border-gray-300" />
                            </div>
                        </td>
                        <td v-if="groupBy !== 'buyer_code'" class="w-40 px-2 py-1.5">{{ item.buyer_code ?? item.customer_name }}</td>
                        <td class="w-32 px-2 py-1.5">{{ item.stock_code }}</td>
                        <td class="px-2 py-1.5">{{ item.stock_name }}</td>
                        <td v-if="groupBy !== 'family'" class="w-40 px-2 py-1.5 text-gray-500">{{ item.family }}</td>
                        <td v-if="groupBy !== 'maker'" class="w-32 px-2 py-1.5 text-gray-500">{{ item.maker }}</td>
                        <td class="w-24 px-2 py-1.5 text-right tabular-nums">{{ useLocaleStore().number(Number(item.quantity)) }}</td>
                        <td class="w-24 px-2 py-1.5 text-gray-500">{{ item.priority }}</td>
                        <td class="w-28 px-2 py-1.5 text-gray-500 whitespace-nowrap">{{ item.needed_by ? useFormatTime(item.needed_by, { formatTime: "mdy" }) : "-" }}</td>
                        <td class="w-20 px-4 py-1.5 text-gray-500">{{ item.state }}</td>
                        <td class="w-32 px-2 py-1.5"><Link v-if="item.job_order_slug" :href="jobOrderHref(item)" class="primaryLink">{{ item.job_order_reference }}</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Table v-else-if="!mixes" :resource="data" class="mt-5">
        <template #cell(buyer_code)="{ item }">
            <span v-if="item.buyer_code">{{ item.buyer_code }}</span>
            <span v-else>{{ item.customer_name }} <span class="text-gray-500">{{ item.order_reference }}</span></span>
        </template>
        <template #cell(stock_code)="{ item }">
            <div class="whitespace-nowrap">{{ item.stock_code }} <span v-if="item.family" class="ml-1 rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-xs text-gray-600">{{ item.family }}</span></div>
            <div class="text-gray-500">{{ item.stock_name }}</div>
        </template>
        <template #cell(job_order_reference)="{ item }">
            <Link v-if="item.job_order_slug" :href="jobOrderHref(item)" class="primaryLink">{{ item.job_order_reference }}</Link>
        </template>
        <template #cell(pick)="{ item }">
            <div class="flex items-center gap-2">
                <input v-if="item.state === 'open'" type="checkbox" :checked="item.id in selected" @change="toggle(item)" />
                <input
                    v-if="item.id in selected"
                    v-model.number="selected[item.id]"
                    type="number"
                    step="0.001"
                    :max="item.quantity"
                    min="0.001"
                    class="w-24 rounded border-gray-300"
                />
            </div>
        </template>
        <template #cell(quantity)="{ item }">
            <span class="tabular-nums">{{ useLocaleStore().number(Number(item.quantity)) }}</span>
        </template>
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ useFormatTime(item.created_at, { formatTime: "mdy" }) }}</span>
        </template>
    </Table>
</template>
