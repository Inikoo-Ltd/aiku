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
import { faUserHardHat, faPencil } from "@fal"

library.add(faUserHardHat, faPencil)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    groupBy: string | null
    mixes: { artefact_id: number, code: string, name: string, unit: string, needed: number, on_hand: number, in_progress: number, shortfall: number, artisan: string | null, needed_for: string[] }[] | null
    artisanWorkload: { id: number, name: string, open_job_orders: number, hidden: boolean }[] | null
    mixJobOrders: { id: number, artefact_id: number, code: string, name: string, quantity: number, job_order_id: number, job_order_reference: string, job_order_slug: string, job_order_state: string, job_order_artisan: string | null }[] | null
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

type BoardItem = { id: number, stock_code: string, stock_name: string, state: string, quantity: number, quantity_to_produce: number | null, maker: string | null, maker_id: number | null, preparing_at: string | null, kind?: "item" | "mix", artefact_id?: number, job_order_id?: number | null, job_order_state?: string | null, job_order_reference?: string | null, job_order_artisan?: string | null }

function isReassignable(item: BoardItem): boolean {
    return !!item.job_order_id && ["in_process", "submitted"].includes(item.job_order_state ?? "")
}

const STAGE_BY_JOB_ORDER_STATE: Record<string, "assigned" | "producing" | "done"> = {
    in_process: "assigned", submitted: "assigned", confirmed: "producing",
    received: "done", not_received: "done", booking_in: "done", booked_in: "done",
}

const mixLanes = computed(() => {
    if (!props.mixes) return null
    const lanes = { needed: [] as any[], assigned: [] as any[], producing: [] as any[], done: [] as any[] }
    props.mixes.filter(mix => mix.shortfall > 0).forEach(mix => lanes.needed.push({
        kind: "mix", id: -mix.artefact_id, artefact_id: mix.artefact_id, stock_code: mix.code, stock_name: mix.name, state: "open",
        quantity: mix.shortfall, quantity_to_produce: null, unit: mix.unit, needed_for: mix.needed_for, maker: mix.artisan, maker_id: null, preparing_at: null,
    }))
    ;(props.mixJobOrders ?? []).forEach(line => lanes[STAGE_BY_JOB_ORDER_STATE[line.job_order_state] ?? "assigned"].push({
        kind: "mix", id: line.id, artefact_id: line.artefact_id, stock_code: line.code, stock_name: line.name, state: "assigned", quantity: line.quantity,
        job_order_id: line.job_order_id, job_order_reference: line.job_order_reference, job_order_slug: line.job_order_slug, job_order_artisan: line.job_order_artisan,
    }))
    return [
        { label: trans("Needed"), items: lanes.needed },
        { label: trans("Assigned"), items: lanes.assigned },
        { label: trans("Mixing"), items: lanes.producing },
        { label: trans("Done"), items: lanes.done },
    ]
})

const MIX_LANE_NEEDED = 0
const MIX_LANE_ASSIGNED = 1

function mixDropTarget(laneIndex: number): boolean {
    return dragging.value.length > 0 && dragging.value[0].kind === "mix" && laneIndex === MIX_LANE_ASSIGNED
}

function onMixDrop(laneIndex: number, event: DragEvent) {
    if (mixDropTarget(laneIndex)) openPicker("assign-mix", event)
}
const LANE_BACKLOG = 0
const LANE_PREPARING = 1
const LANE_ASSIGNED = 2

function dropTarget(laneIndex: number): boolean {
    const first = dragging.value[0]
    if (!first || first.kind === "mix") return false
    if (first.job_order_id) return laneIndex === LANE_PREPARING && isReassignable(first)
    if (laneIndex === LANE_BACKLOG) return !!first.preparing_at
    if (laneIndex === LANE_PREPARING) return !first.preparing_at
    if (laneIndex === LANE_ASSIGNED) return !!first.preparing_at
    return false
}

function unassign(items: BoardItem[]) {
    router.post(
        route("grp.org.productions.show.to_produce.items.unassign", [route().params["organisation"], route().params["production"]]),
        { ids: items.map(item => item.id) },
        { preserveScroll: true, onSuccess: () => { selectedCards.value = [] } }
    )
}

function setPreparing(lines: { id: number, quantity?: number | null }[], preparing: boolean) {
    router.post(
        route("grp.org.productions.show.to_produce.items.preparing", [route().params["organisation"], route().params["production"]]),
        { preparing, lines },
        { preserveScroll: true, onSuccess: () => { selectedCards.value = [] } }
    )
}

function onDrop(laneIndex: number, event: DragEvent) {
    if (!dropTarget(laneIndex)) return
    if (dragging.value[0].job_order_id) {
        unassign(dragging.value)
        dragging.value = []
    } else if (laneIndex === LANE_ASSIGNED) {
        openPicker("assign", event)
    } else if (laneIndex === LANE_PREPARING) {
        openPicker("prepare", event)
    } else {
        setPreparing(dragging.value.map(item => ({ id: item.id })), false)
        dragging.value = []
    }
}

const selectedCards = ref<number[]>([])
const selectedLane = ref<number | null>(null)

function toggleCard(item: BoardItem, laneIndex: number) {
    if (selectedLane.value !== laneIndex) {
        selectedCards.value = []
        selectedLane.value = laneIndex
    }
    const index = selectedCards.value.indexOf(item.id)
    index === -1 ? selectedCards.value.push(item.id) : selectedCards.value.splice(index, 1)
}

function startDrag(item: BoardItem, laneItems: BoardItem[]) {
    dragging.value = selectedCards.value.includes(item.id)
        ? laneItems.filter(candidate => selectedCards.value.includes(candidate.id))
        : [item]
}

const dragging = ref<BoardItem[]>([])
const pendingItems = ref<BoardItem[]>([])
const pendingQuantities = reactive<Record<number, number>>({})
const pickerMode = ref<"prepare" | "assign" | "assign-mix" | "reassign">("prepare")
const pickerPosition = ref({ x: 0, y: 0 })

const pendingFirst = computed(() => pendingItems.value[0] ?? null)

function openReassign(item: BoardItem, event: MouseEvent) {
    dragging.value = [item]
    pickerMode.value = "reassign"
    artisanSearch.value = ""
    pendingItems.value = [item]
    pickerPosition.value = {
        x: Math.max(8, Math.min(event.clientX - 40, window.innerWidth - 330)),
        y: Math.max(8, Math.min(event.clientY + 8, window.innerHeight - 420)),
    }
    dragging.value = []
}

function openPicker(mode: "prepare" | "assign" | "assign-mix", event: DragEvent) {
    if (!dragging.value.length) return
    pickerMode.value = mode
    artisanSearch.value = ""
    pendingItems.value = dragging.value
    for (const key in pendingQuantities) delete pendingQuantities[key]
    dragging.value.forEach(item => pendingQuantities[item.id] = Math.ceil(Number(item.quantity_to_produce ?? item.quantity)))
    const laneLeft = (event.currentTarget as HTMLElement).getBoundingClientRect().left
    pickerPosition.value = {
        x: Math.max(8, laneLeft - 160),
        y: Math.max(8, Math.min(event.clientY - 40, window.innerHeight - 420)),
    }
    dragging.value = []
}

function updatePreparingQuantity(item: BoardItem, event: Event) {
    const quantity = Number((event.target as HTMLInputElement).value)
    if (quantity >= 1 && quantity !== Math.ceil(Number(item.quantity_to_produce ?? item.quantity))) {
        setPreparing([{ id: item.id, quantity }], true)
    }
}

function confirmPrepare() {
    if (pendingItems.value.length) {
        setPreparing(pendingItems.value.map(item => ({ id: item.id, quantity: pendingQuantities[item.id] })), true)
        pendingItems.value = []
    }
}

function assign(employeeId: number) {
    const first = pendingFirst.value
    if (!first) return
    if (pickerMode.value === "reassign") {
        router.patch(route("grp.models.job-order.update", { jobOrder: first.job_order_id }), { employee_id: employeeId }, { preserveScroll: true })
    } else if (first.kind === "mix") {
        router.post(
            route("grp.org.productions.show.to_produce.mixes.job_orders.store", [route().params["organisation"], route().params["production"]]),
            { lines: [{ artefact_id: first.artefact_id, quantity: pendingQuantities[first.id] }], employee_id: employeeId },
            { preserveScroll: true }
        )
    } else {
        createJobOrders(pendingItems.value.map(item => item.id), employeeId)
        selectedCards.value = []
    }
    pendingItems.value = []
}

const pendingDefaultMaker = computed(() => {
    const makers = new Set(pendingItems.value.map(item => item.maker_id ?? item.maker ?? null))
    return makers.size === 1 ? pendingFirst.value : null
})

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

const artisanMenuOpen = ref(false)

function initials(name: string): string {
    return name.split(/[\s-]+/).filter(Boolean).slice(0, 2).map(part => part[0].toUpperCase()).join("")
}

const artisanSearch = ref("")

function fold(text: string): string {
    return text.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase()
}

function fuzzyMatch(needle: string, haystack: string): boolean {
    let index = 0
    for (const char of needle) {
        index = haystack.indexOf(char, index)
        if (index === -1) return false
        index++
    }
    return true
}

function editDistance(a: string, b: string): number {
    const row = Array.from({ length: b.length + 1 }, (_, i) => i)
    for (let i = 1; i <= a.length; i++) {
        let previous = row[0]
        row[0] = i
        for (let j = 1; j <= b.length; j++) {
            const temp = row[j]
            row[j] = Math.min(row[j] + 1, row[j - 1] + 1, previous + (a[i - 1] === b[j - 1] ? 0 : 1))
            previous = temp
        }
    }
    return row[b.length]
}

function nameMatches(needle: string, name: string): boolean {
    const folded = fold(name)
    if (fuzzyMatch(needle, folded)) return true
    if (needle.length < 3) return false
    return folded.split(/[\s-]+/).some(word => editDistance(needle, word.slice(0, needle.length)) <= 1 || editDistance(needle, word) <= 1)
}

const artisanChoices = computed(() => {
    const needle = fold(artisanSearch.value.trim())
    const visible = (props.artisanWorkload ?? []).filter(artisan => !artisan.hidden)
    const matched = needle ? visible.filter(artisan => nameMatches(needle, artisan.name)) : visible
    const list = (matched.length ? matched : visible)
        .sort((a, b) => a.name.localeCompare(b.name))
    const ref = pendingDefaultMaker.value
    const isDefault = (a: { id: number, name: string }) => ref?.maker_id ? a.id === ref.maker_id : (!!ref?.maker && a.name === ref.maker)
    return [...list.filter(isDefault), ...list.filter(a => !isDefault(a))]
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
        <div v-if="!mixes.length && !(mixJobOrders ?? []).length" class="rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center text-gray-400">
            {{ trans("No mixes needed. Mixes appear here when an open job order uses a raw material that is made in-house.") }}
        </div>
        <div v-else-if="mixLanes" class="flex gap-3">
            <div
                v-for="(lane, laneIndex) in mixLanes"
                :key="lane.label"
                class="flex min-w-0 flex-1 flex-col rounded-lg border bg-gray-50 transition dark:bg-gray-800"
                :class="mixDropTarget(laneIndex) ? 'border-indigo-400 ring-2 ring-indigo-200' : 'border-gray-200 dark:border-gray-700'"
                @dragover="mixDropTarget(laneIndex) ? $event.preventDefault() : null"
                @drop.prevent="onMixDrop(laneIndex, $event)">
                <div class="flex items-center gap-2 px-3 py-2 font-medium">
                    <span>{{ lane.label }}</span>
                    <span class="ml-auto rounded-full bg-white px-2 text-xs text-gray-500 dark:bg-gray-900">{{ lane.items.length }}</span>
                </div>
                <div class="flex max-h-[70vh] flex-col gap-1.5 overflow-y-auto px-2 pb-2">
                    <div
                        v-for="item in lane.items"
                        :key="item.id"
                        class="rounded border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900"
                        :class="laneIndex === MIX_LANE_NEEDED ? 'cursor-grab active:cursor-grabbing' : ''"
                        :draggable="laneIndex === MIX_LANE_NEEDED"
                        @dragstart="dragging = [item]"
                        @dragend="dragging = []">
                        <div class="flex items-center gap-1.5">
                            <span class="font-medium">{{ item.stock_code }}</span>
                            <span class="ml-auto tabular-nums" :class="laneIndex === MIX_LANE_NEEDED ? 'font-semibold text-red-600' : ''">×{{ useLocaleStore().number(Number(item.quantity)) }}<span v-if="item.unit" class="font-normal text-gray-400"> {{ item.unit }}</span></span>
                        </div>
                        <div class="truncate text-gray-600" :title="item.stock_name">{{ item.stock_name }}</div>
                        <div v-if="item.needed_for" class="truncate text-gray-400" :title="item.needed_for.join(', ')">{{ trans("for") }} {{ item.needed_for.join(", ") }}</div>
                        <div v-if="item.job_order_slug" class="flex items-center gap-1 text-gray-600">
                            <FontAwesomeIcon icon="fal fa-user-hard-hat" class="text-gray-400" fixed-width />
                            {{ item.job_order_artisan ?? trans("No artisan") }}
                            <Link :href="jobOrderHref(item)" class="primaryLink ml-auto">{{ item.job_order_reference }}</Link>
                        </div>
                        <div v-else-if="item.maker" class="flex items-center gap-1 text-gray-400">
                            <FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width />
                            {{ item.maker }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
        <div v-if="pendingFirst" class="fixed inset-0 z-40" @click="pendingItems = []" />
        <div v-if="pendingFirst" class="fixed z-50 w-80 rounded-lg border border-indigo-300 bg-white p-3 text-xs shadow-xl dark:bg-gray-900" :style="{ left: pickerPosition.x + 'px', top: pickerPosition.y + 'px' }">
            <div v-if="pendingItems.length === 1" class="mb-1.5 font-medium">{{ pendingFirst.stock_code }} <span class="font-normal text-gray-500">{{ pendingFirst.stock_name }}</span></div>
            <div v-else class="mb-1.5 font-medium">{{ trans(":count cards", { count: pendingItems.length }) }}</div>

            <template v-if="pickerMode === 'assign-mix'">
                <label class="mb-2 flex items-center gap-2">
                    <span class="text-gray-500">{{ trans("Quantity") }}</span>
                    <input v-model.number="pendingQuantities[pendingFirst.id]" type="number" min="1" step="1" class="w-20 rounded border-gray-300 py-0.5 text-xs tabular-nums" />
                    <span class="text-gray-400">{{ (pendingFirst as any).unit }} · {{ trans("short") }} {{ useLocaleStore().number(Number(pendingFirst.quantity)) }}</span>
                </label>
            </template>

            <template v-if="pickerMode === 'prepare'">
                <div class="mb-1 text-gray-500">{{ trans("How many to make? (labels)") }}</div>
                <form @submit.prevent="confirmPrepare">
                    <div class="flex max-h-64 flex-col gap-1 overflow-y-auto">
                        <label v-for="(item, index) in pendingItems" :key="item.id" class="flex items-center gap-2">
                            <span v-if="pendingItems.length > 1" class="w-24 truncate font-medium" :title="item.stock_name">{{ item.stock_code }}</span>
                            <input v-model.number="pendingQuantities[item.id]" type="number" min="1" step="1" :autofocus="index === 0" class="w-20 rounded border-gray-300 py-0.5 text-xs tabular-nums" />
                            <span v-if="pendingQuantities[item.id] > Math.ceil(Number(item.quantity))" class="text-gray-400">+{{ pendingQuantities[item.id] - Math.ceil(Number(item.quantity)) }} {{ trans("for stock") }}</span>
                        </label>
                    </div>
                    <button type="submit" class="mt-2 w-full rounded bg-indigo-600 px-3 py-1 font-medium text-white hover:bg-indigo-500">{{ pendingItems.length > 1 ? trans("Prepare :count", { count: pendingItems.length }) : trans("Prepare") }}</button>
                </form>
            </template>

            <template v-else>
                <div v-if="pendingItems.length > 1" class="mb-1.5 flex flex-wrap gap-1">
                    <span v-for="item in pendingItems" :key="item.id" class="rounded bg-gray-100 px-1.5 py-px text-gray-600" :title="item.stock_name">{{ item.stock_code }} <span class="text-gray-400">×{{ Math.ceil(Number(item.quantity_to_produce ?? item.quantity)) }}</span></span>
                </div>
                <div class="mb-1 text-gray-500">
                    <template v-if="pickerMode === 'reassign'">{{ trans("Change artisan of :reference", { reference: pendingFirst.job_order_reference ?? "" }) }} <span class="text-gray-400">({{ pendingFirst.job_order_artisan ?? trans("nobody") }})</span></template>
                    <template v-else>{{ pickerMode === 'assign-mix' ? trans("Who mixes it?") : pendingItems.length > 1 ? trans("Who makes them?") : trans("Who makes :count?", { count: Math.ceil(Number(pendingFirst.quantity_to_produce ?? pendingFirst.quantity)) }) }}</template>
                </div>
                <input v-if="(artisanWorkload ?? []).length > 8" v-model="artisanSearch" type="search" :placeholder="trans('Type a name…')" autofocus class="mb-1 w-full rounded border-gray-300 py-0.5 text-xs" />
                <div class="flex max-h-64 flex-col gap-0.5 overflow-y-auto">
                    <button
                        v-for="artisan in artisanChoices"
                        :key="artisan.id"
                        type="button"
                        class="flex items-center gap-1.5 rounded px-2 py-1 text-left hover:bg-indigo-50"
                        :class="pendingDefaultMaker && (artisan.id === pendingDefaultMaker.maker_id || artisan.name === pendingDefaultMaker.maker) ? 'bg-indigo-50 font-medium text-indigo-700' : ''"
                        @click="assign(artisan.id)">
                        <FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width class="text-gray-400" />
                        <span class="truncate">{{ artisan.name }}</span>
                        <span v-if="pendingDefaultMaker && (artisan.id === pendingDefaultMaker.maker_id || artisan.name === pendingDefaultMaker.maker)" class="ml-auto text-[10px] uppercase tracking-wide">{{ trans("default") }}</span>
                        <span v-else class="ml-auto text-gray-400">{{ artisan.open_job_orders }}</span>
                    </button>
                </div>
            </template>
        </div>
    </Teleport>

    <div v-if="groupBy === 'board' && groups" class="mx-4 mt-4 text-sm">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900">
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
        <div v-if="boardFilterOptions.artisan.length" class="relative flex items-center gap-1.5">
            <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans("Artisan") }}</span>
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                :class="boardFilters.artisan.length ? 'border-indigo-500 bg-indigo-600 text-white shadow-sm' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                @click="artisanMenuOpen = !artisanMenuOpen">
                <FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width :class="boardFilters.artisan.length ? 'text-white/70' : 'text-gray-400'" />
                <span class="max-w-48 truncate">{{ boardFilters.artisan.length ? boardFilters.artisan.join(", ") : trans("Everybody") }}</span>
                <span class="rounded-full px-1.5 text-xs tabular-nums" :class="boardFilters.artisan.length ? 'bg-white/20' : 'bg-white text-gray-500'">{{ boardFilters.artisan.length || boardFilterOptions.artisan.length }}</span>
            </button>
            <div v-if="artisanMenuOpen" class="fixed inset-0 z-30" @click="artisanMenuOpen = false" />
            <div v-if="artisanMenuOpen" class="absolute left-0 top-8 z-40 w-64 rounded-lg border border-gray-200 bg-white p-1.5 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                <label v-for="option in boardFilterOptions.artisan" :key="option.value" class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <input type="checkbox" :checked="boardFilters.artisan.includes(option.value)" @change="toggleBoardFilter('artisan', option.value)" />
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-[10px] font-semibold text-gray-600">{{ initials(option.value) }}</span>
                    <span class="truncate">{{ option.value }}</span>
                    <span class="ml-auto text-xs text-gray-400">{{ option.count }}</span>
                </label>
                <button v-if="boardFilters.artisan.length" type="button" class="mt-1 w-full rounded px-2 py-1 text-left text-xs text-gray-400 hover:bg-gray-50" @click="boardFilters.artisan = []">{{ trans("Everybody") }}</button>
            </div>
        </div>
            <button v-if="boardFilters.family.length || boardFilters.requester.length || boardFilters.priority.length" type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="boardFilters.family = []; boardFilters.requester = []; boardFilters.priority = []">× {{ trans("Clear") }}</button>
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
                <button v-if="selectedLane === laneIndex && selectedCards.length" type="button" class="rounded-full bg-indigo-600 px-2 text-xs font-normal text-white" :title="trans('Click to clear selection, drag any selected card to move them all')" @click="selectedCards = []">{{ selectedCards.length }} {{ trans("selected") }} ×</button>
                <span class="ml-auto rounded-full bg-white px-2 text-xs text-gray-500 dark:bg-gray-900">{{ lane.items.length }}</span>
            </div>
            <div class="flex max-h-[70vh] flex-col gap-1.5 overflow-y-auto px-2 pb-2">
                <div
                    v-for="item in lane.items"
                    :key="item.id"
                    class="rounded border bg-white px-2 py-1.5 text-xs transition dark:bg-gray-900"
                    :class="[
                        (laneIndex <= LANE_PREPARING && item.state === 'open') || (laneIndex === LANE_ASSIGNED && isReassignable(item)) ? 'cursor-grab select-none active:cursor-grabbing' : '',
                        selectedCards.includes(item.id) ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:bg-indigo-950' : 'border-gray-200 dark:border-gray-700',
                    ]"
                    :draggable="(laneIndex <= LANE_PREPARING && item.state === 'open') || (laneIndex === LANE_ASSIGNED && isReassignable(item))"
                    @click="(laneIndex <= LANE_PREPARING && item.state === 'open') || (laneIndex === LANE_ASSIGNED && isReassignable(item)) ? toggleCard(item, laneIndex) : null"
                    @dragstart="startDrag(item, lane.items)"
                    @dragend="dragging = []">
                    <div class="flex items-center gap-1.5">
                        <span class="font-medium">{{ item.stock_code }}</span>
                        <span class="ml-auto flex items-center gap-1 tabular-nums" :class="item.priority === 'urgent' ? 'text-red-600 font-semibold' : ''">
                            ×{{ useLocaleStore().number(Number(item.quantity)) }}
                            <template v-if="laneIndex === LANE_PREPARING && item.state === 'open'">
                                <span class="text-indigo-600">→</span>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    :value="Math.ceil(Number(item.quantity_to_produce ?? item.quantity))"
                                    :title="trans('Quantity to make, edit to change')"
                                    class="w-14 rounded border-gray-200 px-1 py-0 text-right text-xs font-semibold tabular-nums text-indigo-700 hover:border-indigo-300 focus:border-indigo-500"
                                    @click.stop
                                    @mousedown.stop
                                    @change="updatePreparingQuantity(item, $event)" />
                            </template>
                            <span v-else-if="(item.job_order_quantity ?? item.quantity_to_produce) && Number(item.job_order_quantity ?? item.quantity_to_produce) !== Number(item.quantity)" class="text-indigo-600" :title="trans('Making :count', { count: item.job_order_quantity ?? item.quantity_to_produce })">→ {{ useLocaleStore().number(Number(item.job_order_quantity ?? item.quantity_to_produce)) }}</span>
                        </span>
                    </div>
                    <div class="truncate text-gray-600" :title="item.stock_name">{{ item.stock_name }}</div>
                    <div class="flex items-center gap-1 text-gray-400">
                        <span>{{ item.buyer_code ?? item.customer_name }}</span>
                        <span v-if="item.family">· {{ item.family }}</span>
                        <Link v-if="item.job_order_slug" :href="jobOrderHref(item)" class="primaryLink ml-auto">{{ item.job_order_reference }}</Link>
                    </div>
                    <button v-if="item.job_order_id && isReassignable(item)" type="button" class="flex items-center gap-1 rounded text-gray-600 hover:bg-indigo-50 hover:text-indigo-700" :title="trans('Change artisan')" @click.stop="openReassign(item, $event)">
                        <FontAwesomeIcon icon="fal fa-user-hard-hat" class="text-gray-400" fixed-width />
                        {{ item.job_order_artisan ?? trans("No artisan") }}
                        <FontAwesomeIcon icon="fal fa-pencil" class="text-[9px] text-gray-300" fixed-width />
                    </button>
                    <div v-else-if="item.job_order_id" class="flex items-center gap-1 text-gray-600">
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
