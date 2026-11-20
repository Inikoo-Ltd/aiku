<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 27 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import OrdersBlockedByProductionNotice from "@/Components/Production/OrdersBlockedByProductionNotice.vue"
import { computed, reactive, ref, watch } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUserHardHat, faPencil, faFilePdf, faPrint, faHashtag, faBars, faBuilding, faBells } from "@fal"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import CopyButton from "@/Components/Utils/CopyButton.vue"
import ArtisanPicker from "@/Components/Production/ArtisanPicker.vue"
import ModalCreateManualJobOrder from "@/Components/Production/ModalCreateManualJobOrder.vue"
import IngredientShortfalls, { type IngredientShortfall } from "@/Components/Production/IngredientShortfalls.vue"
import RunExpiryInput, { expiryFromMonths, type PeriodAfterOpeningOption, type RunShelfLife } from "@/Components/Production/RunExpiryInput.vue"
import PurchaseOrderItemStockInfo from "@/Components/Procurement/PurchaseOrderItemStockInfo.vue"
import DatePicker from "primevue/datepicker"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faCalendarDay, faMagic, faHourglassHalf, faFlask, faCheck } from "@fal"

library.add(faUserHardHat, faPencil, faFilePdf, faPrint, faHashtag, faBars, faBuilding, faBells, faCalendarDay, faMagic, faHourglassHalf)

type PublishedLabel = { id: number, name: string, run_sources: string[], pdf_url: string }

const PRINT_FRAME_LIFETIME_MS = 60000

const printingLabelId = ref<number | null>(null)

/**
 * A run prints its own batch code and expiry date, so the sheet coming out of the printer says the
 * same thing as the card on the board rather than whatever the design was saved with.
 */
function labelUrl(label: PublishedLabel, item: BoardItem) {
    const runTexts = new URLSearchParams()
    const expiry = formatExpiryForLabel(item.run_expiry)

    if (item.run_batch_code) runTexts.set("batch_code", item.run_batch_code)
    if (item.run_period_after_opening) runTexts.set("period_after_opening", item.run_period_after_opening)
    else if (expiry) runTexts.set("expiry_date", expiry)

    return runTexts.size ? `${label.pdf_url}?${runTexts}` : label.pdf_url
}

/**
 * A label printing a batch code or an expiry date waits for the run's own, typed when it is
 * prepared, and never falls back to the example saved on its design.
 */
function canPrintLabel(label: PublishedLabel, item: BoardItem) {
    return (!label.run_sources.includes("batch_code") || Boolean(item.run_batch_code))
        && (!label.run_sources.includes("expiry_date") || Boolean(item.run_expiry || item.run_period_after_opening))
}

function labelsNeed(item: BoardItem, source: string) {
    return (item.published_labels ?? []).some(label => label.run_sources.includes(source))
}

/**
 * Labels print d/m/Y. Only the date part is read, so a full timestamp cannot leak onto a sheet.
 */
function formatExpiryForLabel(date: string | null | undefined) {
    const match = date?.match(/^(\d{4})-(\d{2})-(\d{2})/)

    return match ? `${match[3]}/${match[2]}/${match[1]}` : null
}

async function printLabel(label: PublishedLabel, item: BoardItem) {
    if (printingLabelId.value || !canPrintLabel(label, item)) return

    printingLabelId.value = label.id

    try {
        const response = await axios.get(labelUrl(label, item), { responseType: "blob" })
        const pdfUrl = URL.createObjectURL(new Blob([response.data], { type: "application/pdf" }))
        const printFrame = document.createElement("iframe")

        Object.assign(printFrame.style, { position: "fixed", right: "0", bottom: "0", width: "0", height: "0", border: "0" })
        printFrame.src = pdfUrl
        printFrame.onload = () => {
            printFrame.contentWindow?.focus()
            printFrame.contentWindow?.print()

            setTimeout(() => {
                printFrame.remove()
                URL.revokeObjectURL(pdfUrl)
            }, PRINT_FRAME_LIFETIME_MS)
        }

        document.body.appendChild(printFrame)
    } catch {
        notify({
            title: ctrans("Something went wrong"),
            text: ctrans("The label :name could not be printed", { name: label.name }),
            type: "error",
        })
    } finally {
        printingLabelId.value = null
    }
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    groupBy: string | null
    mixes: { artefact_id: number, code: string, name: string, artefact_department_id: number | null, unit: string, needed: number, on_hand: number, in_progress: number, shortfall: number, artisan: string | null, needed_for: string[] }[] | null
    artisanWorkload: { id: number, name: string, open_job_orders: number, hidden: boolean, department_ids: number[] }[] | null
    mixJobOrders: { id: number, artefact_id: number, code: string, name: string, quantity: number, job_order_id: number, job_order_reference: string, job_order_slug: string, job_order_state: string, job_order_artisan: string | null }[] | null
    hitchhikers?: { count: number, showing: boolean }
    pipeline?: { count: number, ignoring: boolean }
    canCreateJobOrder?: boolean
    simpleBoard?: boolean
    periodsAfterOpening?: PeriodAfterOpeningOption[]
    productionBlockedNotice?: InstanceType<typeof OrdersBlockedByProductionNotice>['$props']['notice'] | null
    manualJobOrder?: InstanceType<typeof ModalCreateManualJobOrder>["$props"]["options"]
    trialRuns?: { unassigned: number, runs: TrialRun[] } | null
    groups: { label: string, items: { id: number, quantity: number, state: string, stock_code: string, stock_name: string, family: string | null, maker: string | null, buyer_code: string | null, customer_name: string | null, shop_name?: string | null, order_reference: string | null, job_order_reference: string | null, job_order_slug: string | null, priority: string, needed_by: string | null, published_labels?: PublishedLabel[] }[] }[] | null
}>()

const selected = reactive<Record<number, number>>({})

const isCreatingJobOrder = ref(false)
const jobOrderPrefill = ref<{ artefact_id: number, quantity: number, notes: string } | null>(null)
const pageUrl = new URL(window.location.href)
if (pageUrl.searchParams.has("create")) {
    isCreatingJobOrder.value = !!props.canCreateJobOrder
    const makeArtefactId = Number(pageUrl.searchParams.get("make"))
    if (makeArtefactId) {
        jobOrderPrefill.value = { artefact_id: makeArtefactId, quantity: Math.max(1, Number(pageUrl.searchParams.get("skos")) || 1), notes: pageUrl.searchParams.get("notes") ?? "" }
    }
    ;["create", "make", "skos", "notes"].forEach(key => pageUrl.searchParams.delete(key))
    window.history.replaceState(window.history.state, "", pageUrl.toString())
}

function createIngredientJob(item: BoardItem, shortfall: IngredientShortfall) {
    jobOrderPrefill.value = { artefact_id: shortfall.artefact_id, quantity: shortfall.short_skos, notes: ctrans("For :code", { code: item.stock_code }) }
    isCreatingJobOrder.value = true
}

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

function createJobOrders(ids: number[] = Object.keys(selected).map(Number), employeeId: number | null = null, quantity: number | null = null, plannedFor: string | null = null) {
    router.post(
        route("grp.org.productions.show.to_produce.job_orders.store", [route().params["organisation"], route().params["production"]]),
        { ids, employee_id: employeeId, quantity, planned_for: plannedFor },
        { preserveScroll: true, onSuccess: () => { for (const k in selected) delete selected[k] } }
    )
}

type BoardItem = { id: number, unit_name?: string | null, is_fill?: boolean, order_waiting_days?: number | null, shop_name?: string | null, customer_name?: string | null, order_reference?: string | null, batch_size?: number | null, packed_in?: number | null, order_quantum?: number | null, is_hitchhiker?: boolean, stock_code: string, stock_name: string, state: string, quantity: number, quantity_to_produce: number | null, maker: string | null, maker_id: number | null, maker_is_auto?: boolean, artefact_department_id?: number | null, preparing_at: string | null, kind?: "item" | "mix", artefact_id?: number, job_order_id?: number | null, job_order_state?: string | null, job_order_reference?: string | null, job_order_artisan?: string | null, job_order_hold_reason?: string | null, loose_pieces?: number, current_steps?: { name: string, artisans: (string | null)[] }[], job_order_sub_jobs?: { reference: string, artisan: string | null, quantity: number }[], stock_available?: number | null, stock_promised?: number | null, stock_cover?: object | null, buyer_code?: string | null, published_labels?: PublishedLabel[], batch_code?: string | null, run_batch_code?: string | null, run_expiry?: string | null, run_period_after_opening?: string | null, shelf_life?: RunShelfLife | null, label_expiry_date?: string | null, ingredient_shortfalls?: IngredientShortfall[], pipeline?: { pending_booking: number, in_production: number, job_orders: string[] } }

function artisansOf(item: BoardItem): string[] {
    if (item.current_steps?.length) return [...new Set(item.current_steps.flatMap(step => step.artisans.map(artisan => artisan ?? "")))]
    return item.job_order_sub_jobs?.length
        ? [...new Set(item.job_order_sub_jobs.map(subJob => subJob.artisan ?? ""))]
        : [item.job_order_artisan ?? ""]
}

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
        kind: "mix", id: -mix.artefact_id, artefact_id: mix.artefact_id, artefact_department_id: mix.artefact_department_id, stock_code: mix.code, stock_name: mix.name, state: "open",
        quantity: mix.shortfall, quantity_to_produce: null, unit: mix.unit, needed_for: mix.needed_for, maker: mix.artisan, maker_id: null, preparing_at: null,
    }))
    ;(props.mixJobOrders ?? []).forEach(line => lanes[STAGE_BY_JOB_ORDER_STATE[line.job_order_state] ?? "assigned"].push({
        kind: "mix", id: line.id, artefact_id: line.artefact_id, stock_code: line.code, stock_name: line.name, state: "assigned", quantity: line.quantity,
        job_order_id: line.job_order_id, job_order_reference: line.job_order_reference, job_order_slug: line.job_order_slug, job_order_artisan: line.job_order_artisan,
    }))
    return [
        { label: ctrans("Needed"), items: lanes.needed },
        { label: ctrans("Assigned"), items: lanes.assigned },
        { label: ctrans("Mixing"), items: lanes.producing },
        { label: ctrans("Done"), items: lanes.done },
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
const freeStock = (item: BoardItem) => Math.max(0, Number(item.stock_available ?? 0) - Number(item.stock_promised ?? 0))
const LANE_BACKLOG = 0
const LANE_PREPARING = 1
const LANE_ASSIGNED = 2
const LANE_PRODUCING = 3
const LANE_DONE = 4

const queuedLane = computed(() => props.simpleBoard ? LANE_PRODUCING : LANE_ASSIGNED)

function isLaneShown(laneIndex: number): boolean {
    return !props.simpleBoard || (laneIndex !== LANE_PREPARING && laneIndex !== LANE_ASSIGNED)
}

function isDraggable(item: BoardItem, laneIndex: number): boolean {
    if (props.simpleBoard && laneIndex === LANE_PRODUCING) return !!item.job_order_id && !item.job_order_hold_reason
    if (item.state !== "open") return false
    if (props.simpleBoard) return laneIndex === LANE_BACKLOG
    return (laneIndex >= LANE_BACKLOG && laneIndex <= LANE_PREPARING) || (laneIndex === LANE_ASSIGNED && isReassignable(item))
}

function dropTarget(laneIndex: number): boolean {
    const first = dragging.value[0]
    if (!first || first.kind === "mix") return false
    if (props.simpleBoard && laneIndex === LANE_DONE) return dragging.value.every(item => item.job_order_id && item.artefact_id)
    if (props.simpleBoard) return first.job_order_id ? laneIndex === LANE_BACKLOG && dragging.value.every(item => item.state === "open" && isReassignable(item)) : laneIndex === LANE_PRODUCING
    if (first.job_order_id) return laneIndex === LANE_PREPARING && isReassignable(first)
    if (laneIndex === LANE_BACKLOG) return !!first.preparing_at
    if (laneIndex === LANE_PREPARING) return !first.preparing_at
    if (laneIndex === LANE_ASSIGNED) return !!first.preparing_at
    return false
}

function finish(items: BoardItem[]) {
    assignError.value = null
    const cards = [...new Map(items.map(item => [`${item.job_order_id}-${item.artefact_id}`, { job_order_id: item.job_order_id, artefact_id: item.artefact_id }])).values()]
    router.post(
        route("grp.org.productions.show.to_produce.job_orders.finish", [route().params["organisation"], route().params["production"]]),
        { cards },
        { preserveScroll: true, onSuccess: () => { selectedCards.value = [] }, onError: (errors) => assignError.value = Object.values(errors)[0] ?? null }
    )
}

function unassign(items: BoardItem[]) {
    router.post(
        route("grp.org.productions.show.to_produce.items.unassign", [route().params["organisation"], route().params["production"]]),
        { ids: items.map(item => item.id) },
        { preserveScroll: true, onSuccess: () => { selectedCards.value = [] } }
    )
}

const assignError = ref<string | null>(null)

type Run = { quantity: number, batch_code: string, expiry_date: string, period_after_opening: string | null }


const extraRuns = reactive<Record<number, Run[]>>({})

function assignToMe(items: BoardItem[], runs: Record<number, Run[]> = {}) {
    assignError.value = null
    router.post(
        route("grp.org.productions.show.to_produce.job_orders.store", [route().params["organisation"], route().params["production"]]),
        { ids: items.map(item => item.id), assign_to_me: true, runs },
        { preserveScroll: true, onSuccess: () => { selectedCards.value = [] }, onError: (errors) => assignError.value = errors.assign_to_me ?? Object.values(errors)[0] ?? null }
    )
}

type PreparingLine = { id: number, quantity?: number | null, batch_code?: string | null, expiry_date?: string | null, period_after_opening?: string | null, expiry_applies_to_label?: boolean }

function setPreparing(lines: PreparingLine[], preparing: boolean, onPrepared?: () => void) {
    router.post(
        route("grp.org.productions.show.to_produce.items.preparing", [route().params["organisation"], route().params["production"]]),
        { preparing, lines },
        { preserveScroll: true, onSuccess: () => { selectedCards.value = []; onPrepared?.() } }
    )
}

const isDismissing = ref(false)
const dismissReason = ref("")

function dismissPending() {
    router.post(
        route("grp.org.productions.show.to_produce.items.cant_be_done", [route().params["organisation"], route().params["production"]]),
        { ids: pendingItems.value.map(item => item.id), reason: dismissReason.value.trim() },
        { preserveScroll: true, onSuccess: () => { selectedCards.value = [] } }
    )
    pendingItems.value = []
}

function pipelineWarning(items: BoardItem[]) {
    const covered = items.filter(item => item.pipeline)
    if (!covered.length) return null

    return ctrans(":codes already made or being made in :job_orders, waiting to be booked in. Make it again anyway?", {
        codes: covered.map(item => item.stock_code).join(", "),
        job_orders: [...new Set(covered.flatMap(item => item.pipeline!.job_orders))].join(", "),
    })
}

function onDrop(laneIndex: number, event: DragEvent) {
    if (!dropTarget(laneIndex)) return
    const warning = !dragging.value[0].job_order_id && (laneIndex === LANE_ASSIGNED || laneIndex === LANE_PREPARING || laneIndex === LANE_PRODUCING) ? pipelineWarning(dragging.value) : null
    if (warning && !window.confirm(warning)) {
        dragging.value = []
        return
    }
    if (props.simpleBoard && laneIndex === LANE_DONE) {
        finish(dragging.value)
        dragging.value = []
    } else if (props.simpleBoard && laneIndex === LANE_PRODUCING) {
        openPicker("prepare", event)
    } else if (dragging.value[0].job_order_id) {
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
const pendingBatchCodes = reactive<Record<number, string>>({})
const pendingExpiryDates = reactive<Record<number, string>>({})
const pendingPeriods = reactive<Record<number, string | null>>({})
const originalExpiryDates = reactive<Record<number, string>>({})
const expiryAppliesToLabel = ref(false)

const changedExpiryItems = computed(() =>
    pendingItems.value.filter(item => !pendingPeriods[item.id] && (pendingExpiryDates[item.id] ?? "") !== (originalExpiryDates[item.id] ?? ""))
)
const pickerMode = ref<"prepare" | "assign" | "assign-mix" | "reassign">("prepare")
const pickerPosition = ref({ x: 0, y: 0 })

const pendingFirst = computed(() => pendingItems.value[0] ?? null)

function openReassign(item: BoardItem, event: MouseEvent) {
    dragging.value = [item]
    pickerMode.value = "reassign"
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
    pendingItems.value = dragging.value
    isDismissing.value = false
    dismissReason.value = ""
    for (const key in pendingQuantities) delete pendingQuantities[key]
    for (const key in pendingBatchCodes) delete pendingBatchCodes[key]
    for (const key in pendingExpiryDates) delete pendingExpiryDates[key]
    for (const key in pendingPeriods) delete pendingPeriods[key]
    for (const key in originalExpiryDates) delete originalExpiryDates[key]
    for (const key in extraRuns) delete extraRuns[key]
    dragging.value.forEach(item => {
        pendingQuantities[item.id] = Math.ceil(Number(item.quantity_to_produce ?? item.quantity))
        pendingBatchCodes[item.id] = item.run_batch_code ?? ""
        pendingExpiryDates[item.id] = item.run_expiry ?? item.label_expiry_date ?? ""
        pendingPeriods[item.id] = item.run_period_after_opening ?? null
        if (!pendingExpiryDates[item.id] && !pendingPeriods[item.id] && item.shelf_life) {
            pendingPeriods[item.id] = item.shelf_life.period_after_opening
            pendingExpiryDates[item.id] = expiryFromMonths(item.shelf_life.expiry_months)
        }
        originalExpiryDates[item.id] = pendingExpiryDates[item.id]
    })
    expiryAppliesToLabel.value = false
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
        setPreparing([{ id: item.id, quantity, batch_code: item.run_batch_code ?? null, expiry_date: item.run_expiry ?? null, period_after_opening: item.run_period_after_opening ?? null }], true)
    }
}

function confirmPrepare() {
    if (!pendingItems.value.length) return

    const prepared = [...pendingItems.value]
    const runs: Record<number, Run[]> = {}
    prepared.filter(item => extraRuns[item.id]?.length).forEach(item => {
        runs[item.id] = [
            { quantity: pendingQuantities[item.id], batch_code: pendingBatchCodes[item.id]?.trim() ?? "", expiry_date: pendingExpiryDates[item.id] ?? "", period_after_opening: pendingPeriods[item.id] ?? null },
            ...extraRuns[item.id].map(run => ({ ...run, batch_code: run.batch_code.trim() })),
        ]
    })
    setPreparing(pendingItems.value.map(item => ({
        id: item.id,
        quantity: runTotal(item),
        batch_code: pendingBatchCodes[item.id]?.trim() || null,
        expiry_date: pendingExpiryDates[item.id] || null,
        period_after_opening: pendingPeriods[item.id] || null,
        expiry_applies_to_label: expiryAppliesToLabel.value
            && (pendingExpiryDates[item.id] ?? "") !== (originalExpiryDates[item.id] ?? ""),
    })), true, props.simpleBoard ? () => assignToMe(prepared, runs) : undefined)
    pendingItems.value = []
}

const planDay = ref<Date>(new Date())

function planFor() {
    const day = planDay.value
    assign(null, `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, "0")}-${String(day.getDate()).padStart(2, "0")}`)
}

function assign(employeeId: number | null, plannedFor: string | null = null) {
    const first = pendingFirst.value
    if (!first) return
    if (pickerMode.value === "reassign") {
        router.patch(route("grp.models.job-order.update", { jobOrder: first.job_order_id }), plannedFor ? { employee_id: null, planned_for: plannedFor } : { employee_id: employeeId }, { preserveScroll: true })
    } else if (first.kind === "mix") {
        router.post(
            route("grp.org.productions.show.to_produce.mixes.job_orders.store", [route().params["organisation"], route().params["production"]]),
            { lines: [{ artefact_id: first.artefact_id, quantity: pendingQuantities[first.id] }], employee_id: employeeId, planned_for: plannedFor },
            { preserveScroll: true }
        )
    } else {
        createJobOrders(pendingItems.value.map(item => item.id), employeeId, null, plannedFor)
        selectedCards.value = []
    }
    pendingItems.value = []
}

function jobUnits(item: BoardItem, quantity: number = pendingQuantities[item.id]): number {
    const units = Math.ceil(Math.round((Number(quantity) || 0) * (Number(item.packed_in) || 1) * 1e6) / 1e6)
    const batch = Number(item.batch_size) || 0

    return batch > 0 ? Math.ceil(units / batch) * batch : units
}

function runTotal(item: BoardItem): number {
    return (Number(pendingQuantities[item.id]) || 0) + (extraRuns[item.id] ?? []).reduce((sum, run) => sum + (Number(run.quantity) || 0), 0)
}

function runUnits(item: BoardItem): number {
    return jobUnits(item) + (extraRuns[item.id] ?? []).reduce((sum, run) => sum + jobUnits(item, run.quantity), 0)
}

function addRun(item: BoardItem) {
    const missing = Math.ceil(Number(item.quantity)) - runTotal(item)
    ;(extraRuns[item.id] ??= []).push({ quantity: Math.max(1, missing), batch_code: "", expiry_date: "", period_after_opening: pendingPeriods[item.id] ?? null })
}

const autoMakerTooltip = ctrans("Picked for you: the least busy artisan in the area where this product is made, clocked in today first")

const pendingDefaultMaker = computed(() => {
    const makers = new Set(pendingItems.value.map(item => item.maker_id ?? item.maker ?? null))
    return makers.size === 1 ? pendingFirst.value : null
})

type BoardFilterKey = "source" | "family" | "requester" | "priority" | "artisan"
const boardSourceKey = "to-produce-board-source"
const boardFilters = reactive<Record<BoardFilterKey, string[]>>({ source: JSON.parse(localStorage.getItem(boardSourceKey) || "[]"), family: [], requester: [], priority: [], artisan: [] })
watch(() => boardFilters.source, (value) => localStorage.setItem(boardSourceKey, JSON.stringify(value)), { deep: true })

const sourceLabels: Record<string, string> = { own: ctrans("Own customers"), partners: ctrans("Partners") }

function sourceOf(item: { buyer_code?: string | null }): string {
    return item.buyer_code ? "partners" : "own"
}

function requesterWithOrder(item: { shop_name?: string | null, customer_name?: string | null, order_reference?: string | null }): string | null {
    return [item.order_reference, item.shop_name, item.customer_name].filter(Boolean).join(" · ") || null
}

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
        source: counted(items.map(sourceOf)),
        family: counted(items.map(item => item.family ?? "")),
        requester: counted(items.map(item => item.buyer_code ?? "")),
        customer: counted(items.filter(item => !item.buyer_code).map(item => item.customer_name ?? "")),
        priority: counted(items.map(item => item.priority)),
        artisan: counted(items.flatMap(artisansOf)),
    }
})

const customerDialogOpen = ref(false)
const customerSearch = ref("")
const customerOptionsShown = computed(() => {
    const search = customerSearch.value.trim().toLowerCase()
    return search ? boardFilterOptions.value.customer.filter(option => option.value.toLowerCase().includes(search)) : boardFilterOptions.value.customer
})
const selectedCustomers = computed(() => boardFilters.requester.filter(value => boardFilterOptions.value.customer.some(option => option.value === value)))
const customerItemsCount = computed(() => boardFilterOptions.value.customer.reduce((total, option) => total + option.count, 0))

function clearSelectedCustomers() {
    boardFilters.requester = boardFilters.requester.filter(value => !selectedCustomers.value.includes(value))
}

function toggleBoardFilter(key: BoardFilterKey, value: string) {
    const index = boardFilters[key].indexOf(value)
    index === -1 ? boardFilters[key].push(value) : boardFilters[key].splice(index, 1)
}

const STALE_ORDER_DAYS = 2
const onlyBlockingOrders = ref(new URLSearchParams(usePage().url.split("?")[1] ?? "").get("blocking") === "1")
const isBlockingOrder = (item: BoardItem) => (item.order_waiting_days ?? 0) >= STALE_ORDER_DAYS
const blockingOrdersCount = computed(() => (props.groups ?? []).flatMap(group => group.items).filter(isBlockingOrder).length)

const filteredGroups = computed(() =>
    (props.groups ?? []).map((lane, laneIndex) => ({
        ...lane,
        items: lane.items.filter(item =>
            (!onlyBlockingOrders.value || isBlockingOrder(item)) &&
            (!boardFilters.source.length || boardFilters.source.includes(sourceOf(item)))
            && (!boardFilters.family.length || boardFilters.family.includes(item.family ?? ""))
            && (!boardFilters.requester.length || boardFilters.requester.includes(requesterOf(item)))
            && (!boardFilters.priority.length || boardFilters.priority.includes(item.priority))
            && (laneIndex < LANE_ASSIGNED || !boardFilters.artisan.length || artisansOf(item).some(artisan => boardFilters.artisan.includes(artisan)))
        ).sort((a, b) => onlyBlockingOrders.value ? (b.order_waiting_days ?? 0) - (a.order_waiting_days ?? 0) : 0),
    }))
)

const artisanMenuOpen = ref(false)

function initials(name: string): string {
    return name.split(/[\s-]+/).filter(Boolean).slice(0, 2).map(part => part[0].toUpperCase()).join("")
}

const showHiddenArtisans = ref(false)
const artisanError = ref<string | null>(null)

function toggleArtisan(artisan: { id: number, hidden: boolean }) {
    artisanError.value = null
    router.post(
        route(artisan.hidden ? "grp.org.productions.show.to_produce.artisans.show" : "grp.org.productions.show.to_produce.artisans.hide", [route().params["organisation"], route().params["production"], artisan.id]),
        {},
        { preserveScroll: true, onError: (errors) => artisanError.value = errors.artisan ?? null }
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

type TrialRun = {
    id: number
    reference: string
    slug: string
    created_at: string | null
    awaiting_release: boolean
    artisans: string[]
    nobody: boolean
    can_pick_artisan: boolean
    proposed_artisan: { id: number, name: string | null } | null
    artefacts: { code: string | null, name: string | null, quantity: number }[]
    steps_done: number
    steps: number
    development: { code: string, name: string | null, slug: string | null }
}

library.add(faFlask, faCheck)
const showTrialRuns = ref(false)
const trialPicking = ref<TrialRun | null>(null)
const trialPickerPosition = ref({ x: 0, y: 0 })
const trialErrors = reactive<Record<number, string>>({})
const trialBusy = ref<number | null>(null)

function openTrialPicker(run: TrialRun, event: MouseEvent) {
    trialPicking.value = run
    trialPickerPosition.value = {
        x: Math.max(8, Math.min(event.clientX - 40, window.innerWidth - 330)),
        y: Math.max(8, Math.min(event.clientY + 8, window.innerHeight - 420)),
    }
}

function firstError(errors: Record<string, string | string[]>): string {
    const error = Object.values(errors)[0]
    return Array.isArray(error) ? error.join(" · ") : String(error ?? "")
}

function releaseTrialRun(run: TrialRun) {
    trialBusy.value = run.id
    router.patch(route("grp.models.job-order.confirm", { jobOrder: run.id }), {}, {
        preserveScroll: true,
        onStart: () => delete trialErrors[run.id],
        onError: errors => trialErrors[run.id] = firstError(errors),
        onFinish: () => trialBusy.value = null,
    })
}

function assignTrialRun(employeeId: number) {
    const run = trialPicking.value
    trialPicking.value = null
    if (!run) return
    trialBusy.value = run.id
    router.patch(route("grp.models.job-order.update", { jobOrder: run.id }), { employee_id: employeeId }, {
        preserveScroll: true,
        onStart: () => delete trialErrors[run.id],
        onSuccess: () => run.awaiting_release ? releaseTrialRun(run) : trialBusy.value = null,
        onError: errors => { trialErrors[run.id] = firstError(errors); trialBusy.value = null },
    })
}

function jobOrderHref(item: { job_order_slug: string }) {
    return route("grp.org.productions.show.operations.job-orders.show", [route().params["organisation"], route().params["production"], item.job_order_slug])
}

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div v-if="Object.keys(selected).length" class="sticky top-0 z-10 mx-4 mt-4 flex items-center justify-between rounded-lg bg-[var(--app-accent)] px-4 py-2 text-[var(--app-accent-text)]">
        <span>{{ ctransChoice(":count line selected|:count lines selected", Object.keys(selected).length) }}</span>
        <div class="flex gap-2">
            <button type="button" class="rounded bg-white px-3 py-1 text-[var(--app-accent)]" @click="createJobOrders()">
                {{ ctrans("Create job orders") }}
            </button>
        </div>
    </div>


    <div v-if="mixes" class="mx-4 mt-5">
        <div v-if="!mixes.length && !(mixJobOrders ?? []).length" class="rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center text-gray-400">
            {{ ctrans("No mixes needed. Mixes appear here when an open job order uses a raw material that is made in-house.") }}
        </div>
        <div v-else-if="mixLanes" class="flex gap-3">
            <div
                v-for="(lane, laneIndex) in mixLanes"
                :key="lane.label"
                class="flex min-w-0 flex-1 flex-col rounded-lg border bg-gray-50 transition dark:bg-gray-800"
                :class="mixDropTarget(laneIndex) ? 'border-[var(--app-accent)] ring-2 ring-[var(--app-accent-muted)]' : 'border-gray-200 dark:border-gray-700'"
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
                        <div v-if="item.needed_for" class="truncate text-gray-400" :title="item.needed_for.join(', ')">{{ ctrans("for") }} {{ item.needed_for.join(", ") }}</div>
                        <div v-if="item.job_order_slug" class="flex items-center gap-1 text-gray-600">
                            <FontAwesomeIcon icon="fal fa-user-hard-hat" class="text-gray-400" fixed-width />
                            <span v-if="item.job_order_sub_jobs?.length" class="truncate" :title="item.job_order_sub_jobs.map((subJob: { artisan: string | null, quantity: number }) => `${subJob.artisan ?? ctrans('No artisan')} ${subJob.quantity}`).join(', ')">
                                {{ item.job_order_sub_jobs.map((subJob: { artisan: string | null, quantity: number }) => `${subJob.artisan ?? ctrans("No artisan")} ${subJob.quantity.toLocaleString()}`).join(" · ") }}
                            </span>
                            <template v-else>{{ item.job_order_artisan ?? ctrans("No artisan") }}</template>
                            <Link :href="jobOrderHref(item)" class="primaryLink ml-auto">{{ item.job_order_reference }}</Link>
                        </div>
                        <div v-else-if="item.maker" class="flex items-center gap-1 text-gray-400">
                            <FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width />
                            {{ item.maker }}
                            <FontAwesomeIcon v-if="item.maker_is_auto" v-tooltip="autoMakerTooltip" icon="fal fa-magic" fixed-width class="text-[var(--app-accent)]" :aria-label="autoMakerTooltip" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="artisanWorkload && groupBy === 'maker'" class="mx-4 mt-5 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
        <div class="mb-2 flex items-center gap-2 text-sm text-gray-500">
            <span>{{ ctrans("Open job orders per artisan") }} <span class="text-gray-400">· {{ ctrans("everyone should have at least two") }}</span></span>
            <button v-if="artisanWorkload.some(artisan => artisan.hidden)" type="button" class="ml-auto text-xs text-gray-400 hover:text-[var(--app-accent)]" @click="showHiddenArtisans = !showHiddenArtisans">
                {{ artisanWorkload.filter(artisan => artisan.hidden).length }} {{ ctrans("not artisans") }} · {{ showHiddenArtisans ? ctrans("hide") : ctrans("show") }}
            </button>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <span
                v-for="artisan in artisanWorkload.filter(artisan => !artisan.hidden)"
                :key="artisan.id"
                class="flex items-center gap-1 rounded-full border px-2.5 py-px text-xs"
                :class="artisan.open_job_orders === 0 ? 'border-red-300 bg-red-50 text-red-700' : artisan.open_job_orders === 1 ? 'border-amber-300 bg-amber-50 text-amber-700' : 'border-gray-200 text-gray-600'">
                <Link :href="route('grp.org.productions.show.operations.job-orders.index', { organisation: route().params['organisation'], production: route().params['production'], 'filter[employee_id]': artisan.id })" class="hover:underline">{{ artisan.name }} · {{ artisan.open_job_orders }}</Link>
                <button type="button" class="opacity-40 hover:opacity-100" :title="ctrans('Not an artisan')" @click="toggleArtisan(artisan)">×</button>
            </span>
        </div>
        <div v-if="artisanError" class="mt-2 text-xs text-red-600">{{ artisanError }}</div>
        <div v-if="showHiddenArtisans" class="mt-2 flex flex-wrap gap-1.5">
            <span v-for="artisan in artisanWorkload.filter(artisan => artisan.hidden)" :key="artisan.id" class="flex items-center gap-1 rounded-full border border-dashed border-gray-300 px-2.5 py-px text-xs text-gray-400">
                {{ artisan.name }}
                <button type="button" class="hover:text-[var(--app-accent)]" :title="ctrans('Is an artisan')" @click="toggleArtisan(artisan)">+</button>
            </span>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="pendingFirst" class="fixed inset-0 z-40" @click="pendingItems = []" />
        <div v-if="pendingFirst" class="fixed z-50 rounded-lg border border-[var(--app-accent-muted)] bg-white p-3 text-xs shadow-xl dark:bg-gray-900" :class="simpleBoard && pickerMode === 'prepare' ? 'w-96' : 'w-80'" :style="{ left: pickerPosition.x + 'px', top: pickerPosition.y + 'px' }">
            <div v-if="pendingItems.length === 1" class="mb-1.5 font-medium">{{ pendingFirst.stock_code }} <span class="font-normal text-gray-500">{{ pendingFirst.stock_name }}</span></div>
            <div v-else class="mb-1.5 font-medium">{{ ctrans(":count cards", { count: pendingItems.length }) }}</div>

            <template v-if="pickerMode === 'assign-mix'">
                <label class="mb-2 flex items-center gap-2">
                    <span class="text-gray-500">{{ ctrans("Quantity") }}</span>
                    <input v-model.number="pendingQuantities[pendingFirst.id]" type="number" min="1" step="1" class="w-20 rounded border-gray-300 py-0.5 text-xs tabular-nums" />
                    <span class="text-gray-400">{{ (pendingFirst as any).unit }} · {{ ctrans("short") }} {{ useLocaleStore().number(Number(pendingFirst.quantity)) }}</span>
                </label>
            </template>

            <template v-if="pickerMode === 'prepare'">
                <div v-if="!simpleBoard" class="mb-1 text-gray-500">{{ ctrans("How many to make? (labels)") }}</div>
                <form @submit.prevent="confirmPrepare">
                    <div v-if="!simpleBoard" class="flex max-h-64 flex-col gap-1 overflow-y-auto">
                        <label v-for="(item, index) in pendingItems" :key="item.id" class="flex items-center gap-2">
                            <span v-if="pendingItems.length > 1" class="w-24 truncate font-medium" :title="item.stock_name">{{ item.stock_code }}</span>
                            <input v-model.number="pendingQuantities[item.id]" type="number" min="1" step="1" :autofocus="index === 0" class="w-20 rounded border-gray-300 py-0.5 text-xs tabular-nums" />
                            <span v-if="pendingQuantities[item.id] > Math.ceil(Number(item.quantity))" class="text-gray-400">+{{ pendingQuantities[item.id] - Math.ceil(Number(item.quantity)) }} {{ ctrans("for stock") }}</span>
                            <span
                                v-if="item.batch_size"
                                class="rounded bg-[var(--app-accent-soft)] px-1.5 py-px text-[var(--app-accent-strong)]"
                                :title="ctrans('Batch of :batch units, packed in :packed', { batch: item.batch_size ?? 0, packed: item.packed_in ?? 1 })">
                                {{ item.unit_name ? ctrans("makes :count :unit", { count: jobUnits(item), unit: item.unit_name }) : ctransChoice("makes :count unit|makes :count units", jobUnits(item)) }}
                            </span>
                        </label>
                    </div>
                    <div class="mt-2 space-y-2 border-t border-gray-100 pt-2">
                        <div class="text-gray-500">
                            {{ ctrans("What the labels print") }}
                            <span class="block text-gray-400">{{ ctrans("An empty batch code is named after the job order once it is made.") }}</span>
                        </div>
                        <div v-for="item in pendingItems" :key="`texts-${item.id}`" class="space-y-1">
                            <div v-if="pendingItems.length > 1" class="truncate font-medium" :title="item.stock_name">{{ item.stock_code }}</div>
                            <div class="grid gap-1.5" :class="simpleBoard ? 'grid-cols-[4rem_1fr_1fr]' : 'grid-cols-2'">
                                <label v-if="simpleBoard" class="flex min-w-0 flex-col gap-0.5">
                                    <span class="text-gray-500">{{ ctrans("SKOs") }}</span>
                                    <input v-model.number="pendingQuantities[item.id]" type="number" min="1" step="1" required class="w-full min-w-0 rounded border-gray-300 py-0.5 text-xs tabular-nums" />
                                </label>
                                <label class="flex min-w-0 flex-col gap-0.5">
                                    <span class="text-gray-500">{{ ctrans("Batch code") }}</span>
                                    <input
                                        v-model="pendingBatchCodes[item.id]"
                                        type="text"
                                        maxlength="64"
                                        :required="labelsNeed(item, 'batch_code')"
                                        :placeholder="labelsNeed(item, 'batch_code') ? ctrans('Printed on the label') : ctrans('From job order')"
                                        :title="labelsNeed(item, 'batch_code')
                                            ? ctrans('The label prints the batch code, type it before preparing')
                                            : ctrans('Leave empty and the job order reference names the batch')"
                                        class="w-full min-w-0 rounded border-gray-300 py-0.5 text-xs" />
                                </label>
                                <RunExpiryInput
                                    v-model:expiryDate="pendingExpiryDates[item.id]"
                                    v-model:periodAfterOpening="pendingPeriods[item.id]"
                                    :periods="periodsAfterOpening ?? []"
                                    :months="item.shelf_life?.expiry_months"
                                    :required="labelsNeed(item, 'expiry_date')" />
                            </div>
                            <template v-if="simpleBoard">
                                <div v-for="(run, runIndex) in extraRuns[item.id] ?? []" :key="runIndex" class="grid grid-cols-[4rem_1fr_1fr_auto] items-end gap-1.5">
                                    <input v-model.number="run.quantity" type="number" min="1" step="1" required :aria-label="ctrans('SKOs')" class="w-full min-w-0 rounded border-gray-300 py-0.5 text-xs tabular-nums" />
                                    <input v-model="run.batch_code" type="text" maxlength="64" :required="labelsNeed(item, 'batch_code')" :aria-label="ctrans('Batch code')" :placeholder="labelsNeed(item, 'batch_code') ? ctrans('Printed on the label') : ctrans('From job order')" class="w-full min-w-0 rounded border-gray-300 py-0.5 text-xs" />
                                    <RunExpiryInput v-model:expiryDate="run.expiry_date" v-model:periodAfterOpening="run.period_after_opening" :periods="periodsAfterOpening ?? []" :months="item.shelf_life?.expiry_months" :required="labelsNeed(item, 'expiry_date')" />
                                    <button type="button" class="px-1 text-gray-400 hover:text-red-600" :title="ctrans('Remove this batch')" @click="extraRuns[item.id].splice(runIndex, 1)">×</button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="text-gray-500 hover:text-[var(--app-accent)]" @click="addRun(item)">+ {{ ctrans("Another batch") }}</button>
                                    <span class="ml-auto text-gray-400">
                                        {{ ctrans(":count SKOs", { count: runTotal(item) }) }}
                                        <template v-if="runTotal(item) > Math.ceil(Number(item.quantity))">(+{{ runTotal(item) - Math.ceil(Number(item.quantity)) }} {{ ctrans("for stock") }})</template>
                                        <template v-if="item.batch_size"> · {{ item.unit_name ? ctrans("makes :count :unit", { count: runUnits(item), unit: item.unit_name }) : ctransChoice("makes :count unit|makes :count units", runUnits(item)) }}</template>
                                    </span>
                                </div>
                            </template>
                        </div>

                        <div v-if="changedExpiryItems.length" class="rounded border border-amber-200 bg-amber-50 p-1.5" role="group" :aria-label="ctrans('Keep the new expiry date?')">
                            <div class="mb-1 text-amber-800">
                                {{ changedExpiryItems.length > 1
                                    ? ctrans("Expiry date changed on :count labels. Keep it for?", { count: changedExpiryItems.length })
                                    : ctrans("Expiry date changed. Keep it for?") }}
                            </div>
                            <label class="flex items-start gap-1.5 text-amber-900">
                                <input v-model="expiryAppliesToLabel" type="radio" :value="false" class="mt-0.5" />
                                <span>{{ ctrans("This run only") }} (1x) <span class="block text-amber-700">{{ ctrans("the label design keeps its own date") }}</span></span>
                            </label>
                            <label class="mt-1 flex items-start gap-1.5 text-amber-900">
                                <input v-model="expiryAppliesToLabel" type="radio" :value="true" class="mt-0.5" />
                                <span>{{ ctrans("Save on the label") }} <span class="block text-amber-700">{{ ctrans("every future run starts from this date") }}</span></span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="mt-2 w-full rounded bg-[var(--app-accent)] px-3 py-1 font-medium text-[var(--app-accent-text)] hover:bg-[var(--app-accent)]">{{ simpleBoard
                        ? (pendingItems.length > 1 ? ctrans("Start making :count", { count: pendingItems.length }) : ctrans("Start making"))
                        : (pendingItems.length > 1 ? ctrans("Prepare :count", { count: pendingItems.length }) : ctrans("Prepare")) }}</button>
                </form>
                <div class="mt-2 border-t border-gray-100 pt-2">
                    <button v-if="!isDismissing" type="button" class="w-full rounded border border-red-200 px-3 py-1 text-red-700 hover:bg-red-50" @click="isDismissing = true">{{ ctrans("Can't be done") }}</button>
                    <form v-else @submit.prevent="dismissPending">
                        <label class="flex flex-col gap-0.5">
                            <span class="text-gray-500">{{ ctrans("Why can't it be done? The buyer is told.") }}</span>
                            <textarea v-model="dismissReason" required maxlength="1000" rows="2" autofocus class="w-full rounded border-gray-300 py-0.5 text-xs" />
                        </label>
                        <button type="submit" :disabled="!dismissReason.trim()" class="mt-1.5 w-full rounded bg-red-600 px-3 py-1 font-medium text-white hover:bg-red-500 disabled:opacity-50">{{ pendingItems.length > 1 ? ctrans("Can't be done :count", { count: pendingItems.length }) : ctrans("Can't be done") }}</button>
                    </form>
                </div>
            </template>

            <template v-else>
                <div v-if="pendingItems.length > 1" class="mb-1.5 flex flex-wrap gap-1">
                    <span v-for="item in pendingItems" :key="item.id" class="rounded bg-gray-100 px-1.5 py-px text-gray-600" :title="item.stock_name">{{ item.stock_code }} <span class="text-gray-400">×{{ Math.ceil(Number(item.quantity_to_produce ?? item.quantity)) }}</span></span>
                </div>
                <div class="mb-1 text-gray-500">
                    <template v-if="pickerMode === 'reassign'">{{ ctrans("Change artisan of :reference", { reference: pendingFirst.job_order_reference ?? "" }) }} <span class="text-gray-400">({{ pendingFirst.job_order_artisan ?? ctrans("nobody") }})</span></template>
                    <template v-else>{{ pickerMode === 'assign-mix' ? ctrans("Who mixes it?") : pendingItems.length > 1 ? ctrans("Who makes them?") : ctrans("Who makes :count?", { count: Math.ceil(Number(pendingFirst.quantity_to_produce ?? pendingFirst.quantity)) }) }}</template>
                </div>
                <div v-if="canCreateJobOrder" class="mb-2 flex items-center gap-1.5 border-b border-gray-100 pb-2">
                    <DatePicker v-model="planDay" :minDate="new Date()" dateFormat="D d M" :manualInput="false" :pt="{ pcInputText: { root: { class: '!w-24 !py-0.5 !text-xs' } } }" :aria-label="ctrans('Day')" />
                    <Button type="tertiary" size="xs" :label="ctrans('Plan for this day')" :tooltip="ctrans('No operator yet: the supervisor gives each step to an operator on the Day plan')" @click="planFor">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-calendar-day" class="text-gray-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
                <ArtisanPicker :artisans="artisanWorkload ?? []" :default-maker="pendingDefaultMaker" :department-ids="pendingItems.map(item => item.artefact_department_id)" @pick="assign" />
            </template>
        </div>
    </Teleport>

    <div v-if="groupBy === 'board' && (canCreateJobOrder || (productionBlockedNotice && !onlyBlockingOrders))" class="mx-4 mt-4 flex flex-wrap items-center justify-end gap-3">
        <OrdersBlockedByProductionNotice v-if="productionBlockedNotice && !onlyBlockingOrders" :notice="productionBlockedNotice" inline class="mr-auto" />
        <template v-if="canCreateJobOrder">
        <Link :href="route('grp.org.productions.show.operations.day_plan', [route().params['organisation'], route().params['production']])" class="flex items-center gap-1.5 text-sm text-gray-700 hover:underline">
            <FontAwesomeIcon icon="fal fa-calendar-day" class="text-gray-500" fixed-width aria-hidden="true" />
            {{ ctrans("Day plan") }}
        </Link>
        <button type="button" class="rounded bg-[var(--app-accent)] px-3 py-1.5 text-sm font-medium text-[var(--app-accent-text)] hover:bg-[var(--app-accent-strong)]" @click="isCreatingJobOrder = true">
            + {{ ctrans("Create job order") }}
        </button>
        </template>
    </div>
    <ModalCreateManualJobOrder
        :isOpen="isCreatingJobOrder"
        :options="manualJobOrder ?? null"
        :artisans="(artisanWorkload ?? []).filter(artisan => !artisan.hidden)"
        :prefill="jobOrderPrefill"
        @onClose="isCreatingJobOrder = false; jobOrderPrefill = null" />

    <div v-if="groupBy === 'board' && groups" class="mx-4 mt-4 text-sm">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900">
            <div v-for="(label, key) in { source: ctrans('Source'), family: ctrans('Category'), requester: ctrans('Requester'), priority: ctrans('Urgency') }" :key="key" class="flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ label }}</span>
                <button
                    v-for="option in boardFilterOptions[key]"
                    :key="option.value"
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                    :class="boardFilters[key].includes(option.value)
                        ? 'border-[var(--app-accent)] bg-[var(--app-accent)] text-[var(--app-accent-text)] shadow-sm'
                        : option.value === 'urgent' ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                    @click="toggleBoardFilter(key, option.value)">
                    <span class="capitalize">{{ key === "source" ? sourceLabels[option.value] : option.value }}</span>
                    <span class="rounded-full px-1.5 text-xs tabular-nums" :class="boardFilters[key].includes(option.value) ? 'bg-white/20' : 'bg-white text-gray-500'">{{ option.count }}</span>
                </button>
                <button
                    v-if="key === 'requester' && boardFilterOptions.customer.length"
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                    :class="selectedCustomers.length ? 'border-[var(--app-accent)] bg-[var(--app-accent)] text-[var(--app-accent-text)] shadow-sm' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                    @click="customerSearch = ''; customerDialogOpen = true">
                    <span class="max-w-48 truncate">{{ selectedCustomers.length ? selectedCustomers.join(", ") : ctrans("Customers") }}</span>
                    <span class="rounded-full px-1.5 text-xs tabular-nums" :class="selectedCustomers.length ? 'bg-white/20' : 'bg-white text-gray-500'">{{ selectedCustomers.length ? `${selectedCustomers.length}/${boardFilterOptions.customer.length}` : customerItemsCount }}</span>
                </button>
            </div>
        <button
            v-if="blockingOrdersCount || onlyBlockingOrders"
            type="button"
            v-tooltip="ctrans('Bottles a customer order has waited :days days or more for, longest wait first', { days: STALE_ORDER_DAYS })"
            class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
            :class="onlyBlockingOrders ? 'border-[var(--app-accent)] bg-[var(--app-accent)] text-[var(--app-accent-text)] shadow-sm' : 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100'"
            @click="onlyBlockingOrders = !onlyBlockingOrders">
            <FontAwesomeIcon icon="fal fa-hourglass-half" fixed-width />
            <span>{{ ctrans("Blocking orders") }}</span>
            <span class="rounded-full px-1.5 text-xs tabular-nums" :class="onlyBlockingOrders ? 'bg-white/20' : 'bg-white text-red-600'">{{ blockingOrdersCount }}</span>
        </button>
        <div v-if="boardFilterOptions.artisan.length" class="relative flex items-center gap-1.5">
            <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Artisan") }}</span>
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                :class="boardFilters.artisan.length ? 'border-[var(--app-accent)] bg-[var(--app-accent)] text-[var(--app-accent-text)] shadow-sm' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                @click="artisanMenuOpen = !artisanMenuOpen">
                <FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width :class="boardFilters.artisan.length ? 'text-[var(--app-accent-text)] opacity-70' : 'text-gray-400'" />
                <span class="max-w-48 truncate">{{ boardFilters.artisan.length ? boardFilters.artisan.join(", ") : ctrans("Everybody") }}</span>
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
                <button v-if="boardFilters.artisan.length" type="button" class="mt-1 w-full rounded px-2 py-1 text-left text-xs text-gray-400 hover:bg-gray-50" @click="boardFilters.artisan = []">{{ ctrans("Everybody") }}</button>
            </div>
        </div>
        <button
            v-if="trialRuns?.runs.length"
            type="button"
            v-tooltip="ctrans('Trial runs Product Development asked for; the count is those still waiting for an artisan or to be released')"
            class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
            :class="showTrialRuns ? 'border-pink-500 bg-pink-500 text-white shadow-sm' : 'border-pink-300 bg-pink-50 text-pink-700 hover:bg-pink-100'"
            @click="showTrialRuns = !showTrialRuns">
            <FontAwesomeIcon icon="fal fa-flask" fixed-width />
            <span>{{ ctrans("Trial runs") }}</span>
            <span class="rounded-full px-1.5 text-xs tabular-nums" :class="showTrialRuns ? 'bg-white/20' : trialRuns.unassigned ? 'bg-pink-500 text-white' : 'bg-white text-pink-600'">{{ trialRuns.unassigned || trialRuns.runs.length }}</span>
        </button>
            <button v-if="boardFilters.source.length || boardFilters.family.length || boardFilters.requester.length || boardFilters.priority.length" type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="boardFilters.source = []; boardFilters.family = []; boardFilters.requester = []; boardFilters.priority = []">× {{ ctrans("Clear") }}</button>
        </div>
    </div>

    <Dialog v-model:visible="customerDialogOpen" modal dismissableMask :header="ctrans('Customers')" :style="{ width: '28rem' }" :pt="{ header: { class: '!py-2 !px-4' }, content: { class: '!px-4 !pb-3' } }">
        <InputText v-model="customerSearch" :placeholder="ctrans('Search')" class="mb-2 w-full" size="small" autofocus />
        <div class="max-h-96 overflow-y-auto">
            <label v-for="option in customerOptionsShown" :key="option.value" class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                <Checkbox :modelValue="boardFilters.requester.includes(option.value)" binary @update:modelValue="toggleBoardFilter('requester', option.value)" />
                <span class="truncate">{{ option.value }}</span>
                <span class="ml-auto text-xs tabular-nums text-gray-400">{{ option.count }}</span>
            </label>
            <div v-if="!customerOptionsShown.length" class="px-2 py-3 text-sm text-gray-400">{{ ctrans("No customers found") }}</div>
        </div>
        <div v-if="selectedCustomers.length" class="mt-2 flex justify-end">
            <button type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="clearSelectedCustomers">× {{ ctrans("Clear") }}</button>
        </div>
    </Dialog>

    <div v-if="groupBy === 'board' && hitchhikers && (hitchhikers.count || hitchhikers.showing)" class="mx-4 mt-3 text-xs text-gray-500">
        <Link
            :href="route(route().current() as string, { ...route().params, hitchhikers: hitchhikers.showing ? undefined : 1 })"
            preserve-scroll
            class="hover:text-[var(--app-accent)]">
            <template v-if="hitchhikers.showing">{{ ctrans("Hiding lines that are too small for a batch") }}</template>
            <template v-else>{{ hitchhikers.count }} {{ ctrans("lines too small for a batch are waiting for company") }} · {{ ctrans("show") }}</template>
        </Link>
    </div>

    <div v-if="groupBy === 'board' && pipeline && (pipeline.count || pipeline.ignoring)" class="mx-4 mt-1 text-xs text-gray-500">
        <Link
            :href="route(route().current() as string, { ...route().params, ignore_pipeline: pipeline.ignoring ? undefined : 1 })"
            preserve-scroll
            class="hover:text-[var(--app-accent)]">
            <template v-if="pipeline.ignoring">{{ ctrans("Ignoring stock waiting to be booked in") }} · {{ ctrans("show") }}</template>
            <template v-else>{{ pipeline.count }} {{ ctrans("backlog lines are covered by stock waiting to be booked in") }} · {{ ctrans("ignore") }}</template>
        </Link>
    </div>

    <div v-if="groupBy === 'board' && showTrialRuns && trialRuns?.runs.length" class="mx-4 mt-3 overflow-x-auto rounded-lg border border-pink-300 bg-white text-sm dark:bg-gray-900">
        <table class="min-w-full">
            <thead class="whitespace-nowrap bg-pink-100 text-left text-xs font-medium uppercase tracking-wide text-pink-800">
                <tr>
                    <th class="px-3 py-2">{{ ctrans("Job") }}</th>
                    <th class="px-3 py-2">{{ ctrans("Development") }}</th>
                    <th class="px-3 py-2">{{ ctrans("Trial") }}</th>
                    <th class="px-3 py-2 text-right">{{ ctrans("Quantity") }}</th>
                    <th class="px-3 py-2">{{ ctrans("Artisan") }}</th>
                    <th class="px-3 py-2 text-right">{{ ctrans("Steps") }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-pink-100">
                <tr v-for="run in trialRuns.runs" :key="run.id" class="bg-pink-50 align-top dark:bg-pink-950/30">
                    <td class="whitespace-nowrap px-3 py-2">
                        <Link :href="jobOrderHref({ job_order_slug: run.slug })" class="font-medium text-gray-800 hover:underline">{{ run.reference }}</Link>
                        <div v-if="run.created_at" class="text-xs text-gray-400">{{ useFormatTime(run.created_at) }}</div>
                    </td>
                    <td class="px-3 py-2">
                        <Link v-if="run.development.slug" :href="route('grp.product_developments.show', [run.development.slug, { tab: 'formulation' }])" class="whitespace-nowrap font-medium text-gray-800 hover:underline">{{ run.development.code }}</Link>
                        <span v-else class="whitespace-nowrap font-medium text-gray-800">{{ run.development.code }}</span>
                        <div v-if="run.development.name" class="text-xs text-gray-500">{{ run.development.name }}</div>
                    </td>
                    <td class="px-3 py-2">
                        <div v-for="(artefact, index) in run.artefacts" :key="index">
                            <span class="whitespace-nowrap font-medium">{{ artefact.code }}</span>
                            <span class="ml-1 text-gray-500">{{ artefact.name }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">
                        <div v-for="(artefact, index) in run.artefacts" :key="index">{{ useLocaleStore().number(artefact.quantity) }}</div>
                    </td>
                    <td class="px-3 py-2">
                        <template v-if="run.artisans.length">{{ run.artisans.join(", ") }}</template>
                        <span v-else class="font-medium text-pink-700">{{ ctrans("Nobody yet") }}</span>
                        <div v-if="!run.artisans.length && run.proposed_artisan?.name" class="text-xs text-gray-500">{{ ctrans("Proposed: :name", { name: run.proposed_artisan.name }) }}</div>
                        <div v-if="trialErrors[run.id]" class="mt-1 text-xs text-red-600">{{ trialErrors[run.id] }}</div>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ run.steps_done }}/{{ run.steps }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">
                        <LoadingIcon v-if="trialBusy === run.id" />
                        <template v-else-if="canCreateJobOrder && (run.awaiting_release || run.can_pick_artisan)">
                            <Button v-if="run.can_pick_artisan" type="tertiary" size="xs" :label="run.awaiting_release ? ctrans('Assign and release') : ctrans('Assign')" @click="openTrialPicker(run, $event)">
                                <template #icon><FontAwesomeIcon icon="fal fa-user-hard-hat" class="text-pink-600" fixed-width aria-hidden="true" /></template>
                            </Button>
                            <Button v-else type="tertiary" size="xs" :label="ctrans('Release')" @click="releaseTrialRun(run)">
                                <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </template>
                        <span v-else-if="!run.awaiting_release" class="text-xs text-gray-500">{{ run.steps && run.steps_done === run.steps ? ctrans("Made, to hand to the development office") : run.steps_done ? ctrans("Producing") : ctrans("Released") }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <Teleport to="body">
        <div v-if="trialPicking" class="fixed inset-0 z-40" @click="trialPicking = null" />
        <div v-if="trialPicking" class="fixed z-50 w-80 rounded-lg border border-pink-300 bg-white p-3 text-xs shadow-xl dark:bg-gray-900" :style="{ left: trialPickerPosition.x + 'px', top: trialPickerPosition.y + 'px' }">
            <div class="mb-2 font-semibold text-gray-700">{{ trialPicking.awaiting_release ? ctrans("Who makes :reference? It is released to the floor", { reference: trialPicking.reference }) : ctrans("Who makes :reference?", { reference: trialPicking.reference }) }}</div>
            <ArtisanPicker :artisans="artisanWorkload ?? []" :default-maker="trialPicking.proposed_artisan ? { maker_id: trialPicking.proposed_artisan.id, maker: trialPicking.proposed_artisan.name } : null" @pick="assignTrialRun" />
        </div>
    </Teleport>

    <div v-if="assignError" class="mx-4 mt-3 text-sm text-red-600">{{ assignError }}</div>

    <div v-if="groupBy === 'board' && groups" class="mx-4 mt-3 flex gap-3">
        <template v-for="(lane, laneIndex) in filteredGroups" :key="lane.label">
        <div
            v-if="isLaneShown(laneIndex)"
            class="flex min-w-0 flex-1 flex-col rounded-lg border bg-gray-50 transition dark:bg-gray-800"
            :class="dropTarget(laneIndex) ? 'border-[var(--app-accent)] ring-2 ring-[var(--app-accent-muted)]' : 'border-gray-200 dark:border-gray-700'"
            @dragover="dropTarget(laneIndex) ? $event.preventDefault() : null"
            @drop.prevent="onDrop(laneIndex, $event)">
            <div class="flex items-center gap-2 px-3 py-2 font-medium">
                <span>{{ lane.label }}</span>
                <button v-if="selectedLane === laneIndex && selectedCards.length" type="button" class="rounded-full bg-[var(--app-accent)] px-2 text-xs font-normal text-[var(--app-accent-text)]" :title="ctrans('Click to clear selection, drag any selected card to move them all')" @click="selectedCards = []">{{ selectedCards.length }} {{ ctrans("selected") }} ×</button>
                <span class="ml-auto rounded-full bg-white px-2 text-xs text-gray-500 dark:bg-gray-900">{{ lane.items.length }}</span>
            </div>
            <div class="flex max-h-[70vh] flex-col gap-1.5 overflow-y-auto px-2 pb-2">
                <div
                    v-for="item in lane.items"
                    :key="item.id"
                    class="rounded border bg-white px-2 py-1.5 text-xs transition dark:bg-gray-900"
                    :class="[
                        isDraggable(item, laneIndex) ? 'cursor-grab select-none active:cursor-grabbing' : '',
                        selectedCards.includes(item.id) ? 'border-[var(--app-accent)] bg-[var(--app-accent-soft)] ring-1 ring-[var(--app-accent)] dark:bg-indigo-950' : 'border-gray-200 dark:border-gray-700',
                    ]"
                    :draggable="isDraggable(item, laneIndex)"
                    @click="isDraggable(item, laneIndex) ? toggleCard(item, laneIndex) : null"
                    @dragstart="startDrag(item, lane.items)"
                    @dragend="dragging = []">
                    <div class="flex items-center gap-1.5">
                        <span class="font-medium">{{ item.stock_code }}</span>
                        <span
                            v-if="item.poked_at"
                            class="rounded bg-red-100 px-1 font-normal text-red-700"
                            :title="ctrans(':who poked :at: the buyer urgently needs this', { who: item.poked_by ?? item.buyer_code ?? '', at: useFormatTime(item.poked_at, { formatTime: 'dd MMM HH:mm' }) })">
                            <FontAwesomeIcon icon="fal fa-bells" fixed-width aria-hidden="true" />
                            {{ ctrans("Poked") }}
                        </span>
                        <span
                            v-if="item.state === 'ordered'"
                            class="rounded bg-gray-100 px-1 font-normal text-gray-500 dark:bg-gray-800"
                            :title="ctrans('Its order has already gone to the warehouse. The job stays on the board until it is received into stock')">
                            {{ ctrans("Order sent") }}
                        </span>
                        <span
                            v-if="item.is_hitchhiker"
                            class="rounded bg-gray-100 px-1 font-normal text-gray-500 dark:bg-gray-800"
                            :title="ctrans('Under a batch of :batch units, waiting for another order to ride with', { batch: item.batch_size ?? 0 })">
                            {{ ctrans("hitchhiking") }}
                        </span>
                        <span
                            v-if="item.pipeline"
                            class="rounded px-1 font-normal"
                            :class="item.pipeline.pending_booking ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'"
                            :title="ctrans('Surplus from :job_orders covers this line', { job_orders: item.pipeline.job_orders.join(', ') })">
                            <template v-if="item.pipeline.pending_booking">{{ ctrans("Pending booking") }}: {{ useLocaleStore().number(item.pipeline.pending_booking) }}</template>
                            <template v-if="item.pipeline.pending_booking && item.pipeline.in_production"> · </template>
                            <template v-if="item.pipeline.in_production">{{ ctrans("In production") }}: {{ useLocaleStore().number(item.pipeline.in_production) }}</template>
                            {{ ctrans("SKO") }}
                        </span>
                        <span class="ml-auto flex items-center gap-1 tabular-nums" :class="item.priority === 'urgent' ? 'text-red-600 font-semibold' : ''">
                            ×{{ useLocaleStore().number(Math.ceil(Number(item.quantity))) }} {{ item.is_fill ? ctrans("bottles") : ctrans("SKO") }}
                            <template v-if="laneIndex === LANE_PREPARING && item.state === 'open'">
                                <span class="text-[var(--app-accent)]">→</span>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    :value="Math.ceil(Number(item.quantity_to_produce ?? item.quantity))"
                                    :title="ctrans('Quantity to make, edit to change')"
                                    class="w-14 rounded border-gray-200 px-1 py-0 text-right text-xs font-semibold tabular-nums text-[var(--app-accent-strong)] hover:border-[var(--app-accent-muted)] focus:border-[var(--app-accent)]"
                                    @click.stop
                                    @mousedown.stop
                                    @change="updatePreparingQuantity(item, $event)" />
                            </template>
                            <span v-else-if="item.job_order_quantity" class="text-[var(--app-accent)]" :title="ctrans('Making :count units', { count: item.job_order_quantity })">→ {{ useLocaleStore().number(Number(item.job_order_quantity) / (Number(item.packed_in) || 1)) }} {{ ctrans("SKO") }} ({{ item.unit_name ? `${useLocaleStore().number(Number(item.job_order_quantity))} ${item.unit_name}` : ctransChoice(":number unit|:number units", Number(item.job_order_quantity), { number: useLocaleStore().number(Number(item.job_order_quantity)) }) }})</span>
                            <span v-else-if="item.quantity_to_produce && Number(item.quantity_to_produce) !== Number(item.quantity)" class="text-[var(--app-accent)]" :title="ctrans('Making :count SKO', { count: item.quantity_to_produce })">→ {{ useLocaleStore().number(Number(item.quantity_to_produce)) }} {{ ctrans("SKO") }}</span>
                        </span>
                    </div>
                    <div class="truncate text-gray-600" :title="item.stock_name">{{ item.stock_name }}</div>
                    <IngredientShortfalls v-if="item.ingredient_shortfalls?.length" :shortfalls="item.ingredient_shortfalls" :canCreate="canCreateJobOrder" @create="(shortfall) => createIngredientJob(item, shortfall)" />
                    <div v-if="item.loose_pieces" class="text-amber-600">{{ ctrans(':count loose pieces left, not in a full pack', { count: item.loose_pieces }) }}</div>
                    <div class="flex items-center gap-1 text-gray-400">
                        <span>{{ item.buyer_code ?? requesterWithOrder(item) ?? item.notes }}</span>
                        <span v-if="item.family">· {{ item.family }}</span>
                        <span
                            v-if="item.order_waiting_days != null"
                            v-tooltip="ctrans('The customer order waits for these bottles before it can go to the warehouse')"
                            class="whitespace-nowrap rounded px-1 text-[10px] font-semibold"
                            :class="item.order_waiting_days >= 2 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'">
                            {{ ctransChoice("Order waiting :count day|Order waiting :count days", item.order_waiting_days, { count: item.order_waiting_days }) }}
                        </span>
                        <span v-if="laneIndex === queuedLane && isReassignable(item)" class="ml-auto rounded bg-emerald-50 px-1 text-[10px] font-semibold uppercase text-emerald-700">{{ ctrans("Queued") }}</span>
                        <span v-if="item.job_order_hold_reason" v-tooltip="item.job_order_hold_reason" class="rounded bg-yellow-100 px-1 text-[10px] font-semibold uppercase text-yellow-800" :class="laneIndex === queuedLane && isReassignable(item) ? '' : 'ml-auto'">{{ ctrans("On hold") }}</span>
                        <Link v-if="item.job_order_slug" :href="jobOrderHref(item)" class="primaryLink" :class="(laneIndex === queuedLane && isReassignable(item)) || item.job_order_hold_reason ? '' : 'ml-auto'">{{ item.job_order_reference }}</Link>
                    </div>
                    <div v-if="laneIndex <= LANE_PREPARING && item.stock_cover" @click.stop @mousedown.stop>
                        <PurchaseOrderItemStockInfo :item="item" isOrderClosed hideComing />
                    </div>
                    <div v-if="laneIndex <= LANE_PREPARING && (!item.stock_cover || Number(item.stock_promised) > 0)" class="text-gray-400">
                        <span v-if="!item.stock_cover" :class="freeStock(item) >= Number(item.quantity) ? 'text-emerald-600' : ''">{{ ctrans("In stock") }}: {{ useLocaleStore().number(Number(item.stock_available ?? 0)) }}</span>
                        <span v-if="Number(item.stock_promised) > 0"><template v-if="!item.stock_cover"> · </template>{{ ctrans("promised to pre-picks") }}: {{ useLocaleStore().number(Number(item.stock_promised)) }} · {{ ctrans("not promised") }}: {{ useLocaleStore().number(freeStock(item)) }}</span>
                    </div>
                    <div
                        v-if="laneIndex === LANE_PREPARING && item.batch_code"
                        class="mt-1 flex max-w-full items-center gap-1 text-gray-500"
                        :title="ctrans('Batch code for this run')"
                        @click.stop
                        @mousedown.stop>
                        <FontAwesomeIcon icon="fal fa-hashtag" class="text-gray-400" fixed-width />
                        <span class="truncate font-medium tabular-nums">{{ item.batch_code }}</span>
                        <CopyButton :text="item.batch_code" />
                    </div>
                    <div v-if="laneIndex <= LANE_ASSIGNED && item.published_labels?.length" class="mt-1 flex flex-col gap-1">
                        <div v-for="label in item.published_labels" :key="label.id" class="flex max-w-full items-center gap-1">
                            <span
                                v-if="!canPrintLabel(label, item)"
                                class="flex min-w-0 flex-1 items-center gap-1 rounded border border-gray-200 bg-gray-50 px-1.5 py-px text-gray-400"
                                :title="laneIndex === LANE_PREPARING
                                    ? ctrans('Prepare this run again with its batch code and expiry date to print the label :name', { name: label.name })
                                    : ctrans('The label :name prints once the run is prepared with its batch code and expiry date', { name: label.name })">
                                <FontAwesomeIcon icon="fal fa-file-pdf" fixed-width />
                                <span class="truncate">{{ label.name }}</span>
                            </span>
                            <template v-else>
                            <a
                                :href="labelUrl(label, item)"
                                target="_blank"
                                rel="noopener"
                                class="flex min-w-0 flex-1 items-center gap-1 rounded border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] px-1.5 py-px text-[var(--app-accent-strong)] hover:bg-[var(--app-accent-soft)]"
                                :title="ctrans('Open label :name', { name: label.name })"
                                draggable="false"
                                @click.stop
                                @mousedown.stop>
                                <FontAwesomeIcon icon="fal fa-file-pdf" fixed-width />
                                <span class="truncate">{{ label.name }}</span>
                            </a>
                            <button
                                type="button"
                                class="flex shrink-0 items-center gap-1 rounded border border-[var(--app-accent-muted)] bg-white px-1.5 py-px text-[var(--app-accent-strong)] hover:bg-[var(--app-accent-soft)] disabled:cursor-wait disabled:opacity-60"
                                :title="ctrans('Print label :name', { name: label.name })"
                                :disabled="printingLabelId === label.id"
                                @click.stop="printLabel(label, item)"
                                @mousedown.stop>
                                <LoadingIcon v-if="printingLabelId === label.id" />
                                <FontAwesomeIcon v-else icon="fal fa-print" fixed-width />
                                {{ ctrans("Print") }}
                            </button>
                            </template>
                        </div>
                    </div>
                    <div v-if="item.current_steps?.length" class="space-y-0.5 text-gray-600">
                        <div v-for="step in item.current_steps" :key="step.name" class="flex items-center gap-1" :title="ctrans('Current step: :step', { step: step.name })">
                            <FontAwesomeIcon icon="fal fa-user-hard-hat" class="text-gray-400" fixed-width />
                            <span class="truncate">
                                <template v-for="(artisan, artisanIndex) in step.artisans" :key="artisanIndex"><template v-if="artisanIndex">, </template><span :class="artisan ? '' : 'font-medium text-amber-600'">{{ artisan ?? ctrans("Unassigned") }}</span></template>
                                · {{ step.name }}
                            </span>
                        </div>
                    </div>
                    <div v-else-if="item.job_order_sub_jobs?.length" class="space-y-0.5 text-gray-600">
                        <div v-for="subJob in item.job_order_sub_jobs" :key="subJob.reference" class="flex items-center gap-1" :title="subJob.reference">
                            <FontAwesomeIcon icon="fal fa-user-hard-hat" class="text-gray-400" fixed-width />
                            <span class="truncate">{{ subJob.artisan ?? ctrans("No artisan") }}</span>
                            <span class="ml-auto shrink-0 tabular-nums text-gray-500">{{ subJob.quantity.toLocaleString() }}</span>
                        </div>
                    </div>
                    <button v-else-if="item.job_order_id && isReassignable(item)" type="button" class="flex items-center gap-1 rounded text-gray-600 hover:bg-[var(--app-accent-soft)] hover:text-[var(--app-accent-strong)]" :title="ctrans('Change artisan')" @click.stop="openReassign(item, $event)">
                        <FontAwesomeIcon icon="fal fa-user-hard-hat" class="text-gray-400" fixed-width />
                        {{ item.job_order_artisan ?? ctrans("No artisan") }}
                        <FontAwesomeIcon icon="fal fa-pencil" class="text-[9px] text-gray-300" fixed-width />
                    </button>
                    <div v-else-if="item.job_order_id" class="flex items-center gap-1 text-gray-600">
                        <FontAwesomeIcon icon="fal fa-user-hard-hat" class="text-gray-400" fixed-width />
                        {{ item.job_order_artisan ?? ctrans("No artisan") }}
                    </div>
                    <div v-else-if="item.maker" class="flex items-center gap-1 text-gray-400">
                        <FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width />
                        {{ item.maker }}
                        <FontAwesomeIcon v-if="item.maker_is_auto" v-tooltip="autoMakerTooltip" icon="fal fa-magic" fixed-width class="text-[var(--app-accent)]" :aria-label="autoMakerTooltip" />
                    </div>
                </div>
            </div>
        </div>
        </template>
    </div>

    <div v-else-if="groups" class="mx-4 mt-5 space-y-6">
        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="group in groups"
                :key="group.label"
                type="button"
                class="rounded-full border px-2.5 py-px text-xs"
                :class="hiddenGroups.includes(group.label) ? 'border-gray-200 text-gray-400 hover:bg-gray-50' : 'border-[var(--app-accent)] bg-[var(--app-accent-soft)] text-[var(--app-accent-strong)]'"
                @click="toggleGroup(group.label)">
                {{ group.items.length }} {{ group.label }}
            </button>
        </div>
        <div v-for="group in groups.filter(group => !hiddenGroups.includes(group.label))" :key="group.label" class="rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-2 font-medium dark:border-gray-700 dark:bg-gray-800">
                <span>{{ group.label }}</span>
                <span class="text-gray-500">{{ group.items.length }} {{ ctrans("lines") }}</span>
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
                        <td v-if="groupBy !== 'buyer_code'" class="w-40 px-2 py-1.5">{{ item.buyer_code ?? requesterWithOrder(item) }}</td>
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
            <span v-else>{{ [item.shop_name, item.customer_name].filter(Boolean).join(" · ") }} <span class="whitespace-nowrap text-gray-500">{{ item.order_reference }}</span></span>
        </template>
        <template #cell(stock_code)="{ item }">
            <div class="whitespace-nowrap">{{ item.stock_code }} <span v-if="item.poked_at" class="ml-1 rounded bg-red-100 px-1 text-xs text-red-700" :title="ctrans(':who poked :at: the buyer urgently needs this', { who: item.poked_by ?? item.buyer_code ?? '', at: useFormatTime(item.poked_at, { formatTime: 'dd MMM HH:mm' }) })"><FontAwesomeIcon icon="fal fa-bells" fixed-width aria-hidden="true" /> {{ ctrans("Poked") }}</span> <span v-if="item.family" class="ml-1 rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-xs text-gray-600">{{ item.family }}</span></div>
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
