<script setup lang="ts">
import { Head, router, usePoll } from "@inertiajs/vue3"
import { computed, nextTick, ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import Checkbox from "primevue/checkbox"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle, faQuestionCircle, faLink, faInfoCircle, faCheckCircle, faSpinnerThird, faMinusCircle, faTimes, faChevronDown } from "@fal"

library.add(faExclamationTriangle, faQuestionCircle, faLink, faInfoCircle, faCheckCircle, faSpinnerThird, faMinusCircle, faTimes, faChevronDown)

interface Finding {
    level: "error" | "block" | "link" | "warning"
    code: string
    column: string | null
    message: string
    source?: string
}

interface Row {
    id: number
    row: number
    status: string
    values: Record<string, any>
    findings: Finding[]
    decisions: Record<string, { accepted: boolean }>
    skip: boolean
    errors: string[]
}

interface DraftOrder {
    key: string
    organisation: string | null
    via_agent: boolean
    cartons: number
    lines: number
    purchase_order: string | null
    purchase_order_lines: number | null
    new_draft: boolean
    error: string | null
}

interface RouteDef {
    name: string
    parameters: Record<string, any>
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    upload: {
        id: number
        state: string | null
        state_label: string | null
        filename: string
        errors: string[]
        can_edit: boolean
        problems: string[]
        review: { status?: string; note?: string; partial?: boolean; summary?: string; rows?: Record<string, string> } | null
        ai: string | null
        purchase_orders: Record<string, { purchase_order?: string; lines?: number; errors?: string[]; error?: string }> | null
    }
    supplier: { code: string; name: string; currency: string | null; products_route: RouteDef }
    rows: Row[]
    draft_orders: DraftOrder[]
    routes: { record: RouteDef; new_draft: RouteDef; import: RouteDef; cancel: RouteDef }
}>()

const isImporting = ref(false)

const isAiRunning = computed(() => ["queued", "running"].includes(props.upload.ai ?? ""))
const { start: startPolling, stop: stopPolling } = usePoll(4000, { only: ["upload", "rows"] }, { autoStart: isAiRunning.value })
watch(isAiRunning, (running) => (running ? startPolling() : stopPolling()))
const skoNames = ref<Record<number, string>>(Object.fromEntries(props.rows.map((row) => [row.id, row.values.sko_name ?? ""])))

const levelOrder = { error: 0, block: 1, link: 2, warning: 3 }
const levelHeadingClass = {
    error: "text-red-700",
    block: "text-orange-700",
    link: "text-sky-700",
    warning: "text-amber-700",
}
const levelBorderClass = {
    error: "border-red-300",
    block: "border-orange-300",
    link: "border-sky-300",
    warning: "border-amber-300",
}
const levelIcon = {
    error: "fal fa-exclamation-triangle",
    block: "fal fa-question-circle",
    link: "fal fa-link",
    warning: "fal fa-info-circle",
}
const levelLabel = computed(() => ({
    error: ctrans("Fix in the sheet"),
    block: ctrans("Needs a decision"),
    link: ctrans("Existing trade unit"),
    warning: ctrans("Check"),
}))

const hasErrors = (row: Row) => row.findings.some((finding) => finding.level === "error")

const findingGroups = (row: Row) =>
    (Object.keys(levelOrder) as Array<keyof typeof levelOrder>)
        .map((level) => ({ level, findings: row.findings.filter((finding) => finding.level === level) }))
        .filter((group) => group.findings.length)

type RowState = "ready" | "decision" | "error" | "skipped"

const rowState = (row: Row): RowState => {
    if (row.skip) {
        return "skipped"
    }
    if (hasErrors(row)) {
        return "error"
    }
    if (row.findings.some((finding) => ["block", "link"].includes(finding.level) && !row.decisions[finding.code]?.accepted)) {
        return "decision"
    }
    return "ready"
}

const rowStates = computed(() => {
    const grouped: Record<RowState, Row[]> = { ready: [], decision: [], error: [], skipped: [] }
    props.rows.forEach((row) => grouped[rowState(row)].push(row))
    return grouped
})

const stateMeta = computed(() => ({
    ready: { label: ctrans("Ready to import"), icon: "fal fa-check-circle", tile: "border-green-200 bg-green-50 text-green-800", chip: "bg-green-50 text-green-700 ring-green-200 hover:bg-green-100" },
    decision: { label: ctrans("Need a decision"), icon: "fal fa-question-circle", tile: "border-orange-200 bg-orange-50 text-orange-800", chip: "bg-orange-50 text-orange-700 ring-orange-200 hover:bg-orange-100" },
    error: { label: ctrans("Can't be imported"), icon: "fal fa-exclamation-triangle", tile: "border-red-200 bg-red-50 text-red-800", chip: "bg-red-50 text-red-700 ring-red-200 hover:bg-red-100" },
    skipped: { label: ctrans("Skipped"), icon: "fal fa-minus-circle", tile: "border-gray-200 bg-gray-50 text-gray-600", chip: "bg-gray-50 text-gray-500 ring-gray-200 hover:bg-gray-100" },
}))

const rowCardClass = (row: Row) => ({
    skipped: "border-gray-200 bg-gray-50 opacity-60",
    error: "border-red-300 border-l-4 border-l-red-500 bg-red-50/40",
    decision: "border-gray-200 border-l-4 border-l-orange-400",
    ready: "border-green-200 border-l-4 border-l-green-500 bg-green-50/30",
})[rowState(row)]

const isWaitingListOpen = ref(false)
const waitingProblems = computed(() => (isAiRunning.value ? props.upload.problems.slice(1) : props.upload.problems))
const stateFilter = ref<RowState | null>(null)
const toggleStateFilter = (state: RowState) => (stateFilter.value = stateFilter.value === state ? null : state)
const visibleRows = computed(() => (stateFilter.value ? props.rows.filter((row) => rowState(row) === stateFilter.value) : props.rows))

const scrollToRow = async (row: Row) => {
    if (stateFilter.value && rowState(row) !== stateFilter.value) {
        stateFilter.value = null
        await nextTick()
    }
    document.getElementById(`upload-row-${row.id}`)?.scrollIntoView({ behavior: "smooth", block: "center" })
}

const counts = computed(() => {
    const active = props.rows.filter((row) => !row.skip)
    const has = (row: Row, level: string) => row.findings.some((finding) => finding.level === level)
    return {
        rows: props.rows.length,
        skipped: props.rows.length - active.length,
        errors: active.filter((row) => has(row, "error")).length,
        blocks: active.filter((row) => row.findings.some((finding) => ["block", "link"].includes(finding.level) && !row.decisions[finding.code]?.accepted)).length,
        warnings: active.filter((row) => has(row, "warning")).length,
    }
})

const needsSkoName = (row: Row) => (row.values.units_per_sko ?? 1) > 1

const updateRecord = (row: Row, payload: Record<string, any>) => {
    router.patch(route(props.routes.record.name, { ...props.routes.record.parameters, record: row.id }), payload, {
        preserveScroll: true,
        preserveState: true,
    })
}

const toggleDecision = (row: Row, finding: Finding, accepted: boolean) => updateRecord(row, { decisions: { [finding.code]: accepted } })
const toggleSkip = (row: Row) => updateRecord(row, { skip: !row.skip })
const saveSkoName = (row: Row) => {
    if ((row.values.sko_name ?? "") !== skoNames.value[row.id]) {
        updateRecord(row, { sko_name: skoNames.value[row.id] })
    }
}

const toggleNewDraft = (order: DraftOrder) => {
    router.patch(route(props.routes.new_draft.name, props.routes.new_draft.parameters), { key: order.key, new_draft: !order.new_draft }, { preserveScroll: true })
}

const importUpload = () => {
    router.post(route(props.routes.import.name, props.routes.import.parameters), {}, {
        preserveScroll: true,
        onStart: () => (isImporting.value = true),
        onFinish: () => (isImporting.value = false),
    })
}

const cancelUpload = () => {
    if (confirm(ctrans("Cancel this upload? Nothing will be created."))) {
        router.post(route(props.routes.cancel.name, props.routes.cancel.parameters))
    }
}

const money = (value: number | null | undefined, symbol = "") => (value === null || value === undefined ? "—" : symbol + Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 }))
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead">
        <template #other>
            <div v-if="upload.can_edit" class="flex gap-2">
                <Button type="tertiary" :label="ctrans('Cancel upload')" @click="cancelUpload" />
                <Button
                    type="save"
                    :label="ctrans('Import')"
                    :loading="isImporting"
                    :disabled="upload.problems.length > 0"
                    @click="importUpload"
                />
            </div>
        </template>
    </PageHeading>

    <div class="p-4 space-y-4 text-sm text-gray-700">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-gray-500">
            <span>{{ ctrans("Supplier") }}
                <a :href="route('grp.supply-chain.suppliers.show', supplier.products_route.parameters)" target="_blank" rel="noopener" class="font-medium text-[--app-accent-strong] hover:underline">{{ supplier.name }}</a>
            </span>
            <a :href="route(supplier.products_route.name, supplier.products_route.parameters)" target="_blank" rel="noopener" class="font-medium text-[--app-accent-strong] hover:underline">
                {{ ctrans("Supplier products") }}
            </a>
            <span class="flex flex-wrap items-center gap-1.5 text-xs tabular-nums">
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-700 ring-1 ring-inset ring-gray-200">{{ ctrans("State") }}: {{ upload.state_label }}</span>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-700 ring-1 ring-inset ring-gray-200">{{ counts.rows }} {{ ctrans("rows") }}</span>
                <span v-if="counts.skipped" class="rounded-full bg-gray-50 px-2 py-0.5 text-gray-500 ring-1 ring-inset ring-gray-200">{{ counts.skipped }} {{ ctrans("skipped") }}</span>
                <span v-if="counts.errors" class="rounded-full bg-red-50 px-2 py-0.5 text-red-700 ring-1 ring-inset ring-red-200">{{ counts.errors }} {{ ctrans("with errors") }}</span>
                <span v-if="counts.blocks" class="rounded-full bg-orange-50 px-2 py-0.5 text-orange-700 ring-1 ring-inset ring-orange-200">{{ counts.blocks }} {{ ctrans("need a decision") }}</span>
                <span v-if="counts.warnings" class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-700 ring-1 ring-inset ring-amber-200">{{ counts.warnings }} {{ ctrans("with warnings") }}</span>
            </span>
        </div>

        <div
            v-if="upload.can_edit && (isAiRunning || waitingProblems.length)"
            class="relative overflow-hidden rounded border border-orange-200 bg-orange-50 text-xs text-orange-800"
        >
            <component
                :is="waitingProblems.length ? 'button' : 'div'"
                :type="waitingProblems.length ? 'button' : undefined"
                :aria-expanded="waitingProblems.length ? isWaitingListOpen : undefined"
                class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-left"
                :class="waitingProblems.length && 'transition duration-200 hover:bg-orange-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-orange-400'"
                @click="waitingProblems.length && (isWaitingListOpen = !isWaitingListOpen)"
            >
                <span class="inline-flex items-center gap-2">
                    <FontAwesomeIcon :icon="isAiRunning ? 'fal fa-spinner-third' : 'fal fa-exclamation-triangle'" :spin="isAiRunning" fixed-width class="text-orange-600" />
                    <span class="text-sm font-semibold">{{ ctrans("Import is waiting") }}</span>
                </span>
                <span v-if="isAiRunning" role="status" class="inline-flex items-center gap-1.5 rounded-full bg-white px-2 py-0.5 ring-1 ring-inset ring-orange-200">
                    {{ ctrans("AI checks running, this page updates by itself") }}
                </span>
                <span v-if="waitingProblems.length" class="inline-flex items-center rounded-full bg-white px-2 py-0.5 tabular-nums ring-1 ring-inset ring-orange-200">
                    {{ waitingProblems.length === 1 ? ctrans("1 issue to resolve") : ctrans(":count issues to resolve", { count: waitingProblems.length }) }}
                </span>
                <span v-if="waitingProblems.length" class="ml-auto inline-flex items-center gap-1 text-orange-700">
                    {{ isWaitingListOpen ? ctrans("Hide") : ctrans("Show") }}
                    <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width class="transition-transform duration-200" :class="isWaitingListOpen && 'rotate-180'" />
                </span>
            </component>
            <ul v-if="waitingProblems.length" v-show="isWaitingListOpen" class="max-h-60 list-disc overflow-y-auto border-t border-orange-200 py-2 pl-8 pr-3">
                <li v-for="problem in waitingProblems" :key="problem">{{ problem }}</li>
            </ul>
            <div v-if="isAiRunning" class="absolute inset-x-0 bottom-0 h-0.5 overflow-hidden">
                <div class="upload-ai-progress h-full w-1/3 bg-orange-400" />
            </div>
        </div>

        <div v-if="upload.errors.length" class="rounded border border-red-200 bg-red-50 p-3 text-red-700">
            <div class="font-semibold">{{ ctrans("The file was refused") }}</div>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="error in upload.errors" :key="error">{{ error }}</li>
            </ul>
        </div>

        <div v-if="!isAiRunning && upload.review?.summary" class="rounded border border-gray-200 bg-gray-50 p-3">
            <div class="font-semibold">{{ ctrans("AI review") }}<span v-if="upload.review.partial" class="font-normal text-gray-500"> · {{ ctrans("flagged rows only") }}</span></div>
            <p class="mt-1 whitespace-pre-line">{{ upload.review.summary }}</p>
        </div>
        <div v-else-if="upload.review?.note" class="rounded border border-gray-200 bg-gray-50 p-3 text-gray-600">
            {{ upload.review.note }}
        </div>

        <div v-if="rows.length" class="rounded border border-gray-200 p-3">
            <div class="font-semibold">{{ ctrans("Summary") }}</div>
            <div class="mt-2 grid grid-cols-2 gap-2 md:grid-cols-4">
                <button
                    v-for="(meta, state) in stateMeta"
                    :key="state"
                    type="button"
                    :aria-pressed="stateFilter === state"
                    :disabled="!rowStates[state].length"
                    class="flex items-center gap-2 rounded border px-3 py-2 text-left transition duration-200 hover:brightness-95 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent] disabled:cursor-default disabled:opacity-50 disabled:hover:shadow-none disabled:hover:brightness-100"
                    :class="[meta.tile, stateFilter === state && 'ring-2 ring-current ring-offset-1']"
                    @click="toggleStateFilter(state)"
                >
                    <FontAwesomeIcon :icon="meta.icon" fixed-width class="text-lg" />
                    <div>
                        <div class="text-lg font-semibold leading-none tabular-nums">{{ rowStates[state].length }}</div>
                        <div class="mt-0.5 text-xs">{{ meta.label }}</div>
                    </div>
                </button>
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5">
                <button
                    v-for="row in rows"
                    :key="row.id"
                    type="button"
                    v-tooltip="stateMeta[rowState(row)].label"
                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs ring-1 ring-inset transition duration-200"
                    :class="stateMeta[rowState(row)].chip"
                    @click="scrollToRow(row)"
                >
                    <FontAwesomeIcon :icon="stateMeta[rowState(row)].icon" fixed-width />
                    <span class="tabular-nums">{{ row.row }}</span>
                    <span class="font-medium">{{ row.values.part_reference }}</span>
                </button>
            </div>
        </div>

        <div v-if="draft_orders.length" class="rounded border border-gray-200 p-3">
            <div class="font-semibold">{{ ctrans("Draft purchase orders") }}</div>
            <table class="mt-2 w-full text-xs">
                <tbody>
                    <tr v-for="order in draft_orders" :key="order.key" class="border-t border-gray-100">
                        <td class="py-1.5 pr-3 font-medium">{{ order.key }}</td>
                        <td class="py-1.5 pr-3">{{ order.organisation ?? "—" }}<span v-if="order.via_agent" class="text-gray-500"> · {{ ctrans("through the agent") }}</span></td>
                        <td class="py-1.5 pr-3 tabular-nums">{{ order.cartons }} {{ ctrans("cartons") }}, {{ order.lines }} {{ ctrans("lines") }}</td>
                        <td class="py-1.5 pr-3">
                            <span v-if="order.error" class="text-red-700">{{ order.error }}</span>
                            <span v-else-if="order.purchase_order && !order.new_draft">{{ ctrans("Adds to open draft") }} {{ order.purchase_order }} ({{ order.purchase_order_lines }} {{ ctrans("lines") }})</span>
                            <span v-else>{{ ctrans("New draft") }}</span>
                        </td>
                        <td class="py-1.5 text-right">
                            <label v-if="upload.can_edit && order.purchase_order && !order.error" class="inline-flex items-center gap-1.5 text-gray-600">
                                <input type="checkbox" :checked="order.new_draft" @change="toggleNewDraft(order)" />
                                {{ ctrans("New draft instead") }}
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="upload.purchase_orders" class="rounded border border-gray-200 p-3">
            <div class="font-semibold">{{ ctrans("Draft purchase orders created") }}</div>
            <ul class="mt-1 space-y-0.5 text-xs">
                <li v-for="(order, key) in upload.purchase_orders" :key="key">
                    <span class="font-medium">{{ key }}</span>:
                    <span v-if="order.error" class="text-red-700">{{ order.error }}</span>
                    <span v-else>{{ order.purchase_order }}, {{ order.lines }} {{ ctrans("lines") }}</span>
                    <span v-for="error in order.errors ?? []" :key="error" class="block text-red-700">{{ error }}</span>
                </li>
            </ul>
        </div>

        <div class="space-y-2">
            <div v-if="stateFilter" class="flex items-center gap-2 rounded border px-3 py-2 text-xs" :class="stateMeta[stateFilter].tile">
                <FontAwesomeIcon :icon="stateMeta[stateFilter].icon" fixed-width />
                <span>{{ ctrans("Only showing :count of :total rows", { count: visibleRows.length, total: rows.length }) }}: <span class="font-semibold">{{ stateMeta[stateFilter].label }}</span></span>
                <button
                    type="button"
                    v-tooltip="ctrans('Show all rows')"
                    :aria-label="ctrans('Show all rows')"
                    class="ml-auto rounded p-1 transition duration-200 hover:bg-black/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-current"
                    @click="stateFilter = null"
                >
                    <FontAwesomeIcon icon="fal fa-times" fixed-width />
                </button>
            </div>
            <p v-if="stateFilter && !visibleRows.length" class="px-1 text-xs text-gray-500">{{ ctrans("No rows left in this group.") }}</p>
            <div
                v-for="row in visibleRows"
                :key="row.id"
                :id="`upload-row-${row.id}`"
                class="scroll-mt-20 rounded border p-3 transition-colors duration-300"
                :class="rowCardClass(row)"
            >
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <span class="text-xs text-gray-500 tabular-nums">{{ ctrans("Row") }} {{ row.row }}</span>
                    <span class="font-medium">{{ row.values.part_reference }}</span>
                    <span>{{ row.values.unit_name }}</span>
                    <span class="text-gray-500">{{ row.values.family }}</span>
                    <span
                        v-if="rowState(row) !== 'skipped'"
                        class="inline-flex items-center gap-1 self-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                        :class="stateMeta[rowState(row)].chip"
                    >
                        <FontAwesomeIcon :icon="stateMeta[rowState(row)].icon" fixed-width />
                        {{ stateMeta[rowState(row)].label }}
                    </span>
                    <span v-if="row.status !== 'preview'" class="text-xs font-medium" :class="row.status === 'failed' ? 'text-red-700' : 'text-gray-500'">{{ row.status }}</span>
                    <button
                        v-if="upload.can_edit"
                        type="button"
                        class="ml-auto rounded border px-2.5 py-1 text-xs font-medium transition duration-200"
                        :class="row.skip
                            ? 'border-[--app-accent] bg-[--app-accent] text-[--app-accent-text] hover:bg-[--app-accent-strong]'
                            : hasErrors(row)
                                ? 'border-red-300 bg-white text-red-700 hover:bg-red-50'
                                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                        @click="toggleSkip(row)"
                    >
                        {{ row.skip ? ctrans("Include row") : ctrans("Skip row") }}
                    </button>
                </div>

                <div class="mt-1 flex flex-wrap gap-x-5 gap-y-0.5 text-xs text-gray-500 tabular-nums">
                    <span>{{ row.values.units_per_sko ?? "—" }} {{ ctrans("units/SKO") }}</span>
                    <span>{{ row.values.skos_per_outer ?? "—" }} {{ ctrans("SKOs/outer") }}</span>
                    <span>{{ row.values.skos_per_carton ?? "—" }} {{ ctrans("SKOs/carton") }}</span>
                    <span>{{ ctrans("cost") }} {{ money(row.values.unit_cost) }} {{ supplier.currency }}</span>
                    <span>{{ money(row.values.recommended_price, "£") }} / {{ ctrans("RRP") }} {{ money(row.values.recommended_rrp, "£") }}</span>
                    <span>{{ money(row.values.recommended_price_eur, "€") }} / {{ ctrans("RRP") }} {{ money(row.values.recommended_rrp_eur, "€") }}</span>
                    <span v-if="row.values.unit_barcode">{{ row.values.unit_barcode === "auto" ? ctrans("pool barcode") : row.values.unit_barcode }}</span>
                    <span v-for="(cartons, key) in row.values.order" :key="key">{{ key }} {{ cartons }} {{ ctrans("cartons") }}</span>
                </div>

                <div v-if="!row.skip && needsSkoName(row)" class="mt-2 flex items-center gap-2 text-xs">
                    <label class="text-gray-500" :for="`sko-name-${row.id}`">{{ ctrans("SKO name") }}</label>
                    <input
                        :id="`sko-name-${row.id}`"
                        v-model="skoNames[row.id]"
                        :disabled="!upload.can_edit"
                        type="text"
                        class="w-full max-w-xl rounded border-gray-300 py-1 text-xs"
                        :placeholder="ctrans('e.g. Pack of :units :name', { units: row.values.units_per_sko, name: row.values.unit_name })"
                        @blur="saveSkoName(row)"
                        @keydown.enter="saveSkoName(row)"
                    />
                </div>

                <div v-if="!row.skip && hasErrors(row)" class="mt-2 flex items-start gap-2 rounded border border-red-200 bg-white px-3 py-2 text-xs text-red-800">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width class="mt-0.5 text-red-600" />
                    <span>
                        <span class="font-semibold">{{ ctrans("This row can't be imported.") }}</span>
                        {{ ctrans("Fix it in the sheet and upload again, or skip this row.") }}
                    </span>
                </div>

                <div v-if="row.findings.length" class="mt-2 space-y-2">
                    <section v-for="group in findingGroups(row)" :key="group.level">
                        <h4 class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide" :class="levelHeadingClass[group.level]">
                            <FontAwesomeIcon :icon="levelIcon[group.level]" fixed-width />
                            {{ levelLabel[group.level] }}
                            <span class="tabular-nums font-normal opacity-70">({{ group.findings.length }})</span>
                        </h4>
                        <ul class="mt-1 space-y-1 border-l-2 pl-3" :class="levelBorderClass[group.level]">
                            <li
                                v-for="finding in group.findings"
                                :key="finding.code"
                                class="grid grid-cols-1 items-center gap-x-4 gap-y-1 text-xs md:grid-cols-[minmax(0,44rem)_auto]"
                            >
                                <span class="min-w-0">
                                    {{ finding.message }}
                                    <span v-if="finding.source === 'jev'" class="text-gray-500">({{ ctrans("AI") }})</span>
                                </span>
                                <label
                                    v-if="upload.can_edit && !row.skip && ['block', 'link'].includes(finding.level)"
                                    :for="`decision-${row.id}-${finding.code}`"
                                    class="inline-flex cursor-pointer items-center gap-2 text-gray-600"
                                >
                                    <Checkbox
                                        :inputId="`decision-${row.id}-${finding.code}`"
                                        :modelValue="!!row.decisions[finding.code]?.accepted"
                                        binary
                                        class="upload-accept-checkbox"
                                        @update:modelValue="(checked: boolean) => toggleDecision(row, finding, checked)"
                                    />
                                    {{ finding.level === "link" ? ctrans("Add this supplier to it") : ctrans("This is OK, I accept responsibility") }}
                                </label>
                            </li>
                        </ul>
                    </section>
                </div>

                <p v-if="upload.review?.rows?.[row.row]" class="mt-2 text-xs text-gray-600">
                    <span class="font-medium">{{ ctrans("AI suggests") }}:</span> {{ upload.review.rows[row.row] }}
                </p>

                <ul v-if="row.status === 'failed'" class="mt-2 text-xs text-red-700">
                    <li v-for="error in row.errors" :key="error">{{ error }}</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<style scoped>
.upload-accept-checkbox:deep(.p-checkbox-box) {
    transition: background-color 0.2s, border-color 0.2s;
}

.upload-accept-checkbox:not(.p-checkbox-checked):hover :deep(.p-checkbox-box) {
    border-color: #16a34a;
}

.upload-accept-checkbox.p-checkbox-checked :deep(.p-checkbox-box) {
    background-color: #16a34a;
    border-color: #16a34a;
}

.upload-accept-checkbox.p-checkbox-checked:hover :deep(.p-checkbox-box) {
    background-color: #15803d;
    border-color: #15803d;
}

.upload-accept-checkbox :deep(.p-checkbox-input:focus-visible + .p-checkbox-box) {
    outline: 2px solid #86efac;
    outline-offset: 2px;
}
.upload-ai-progress {
    animation: upload-ai-progress 1.6s ease-in-out infinite;
}

@keyframes upload-ai-progress {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(300%);
    }
}
</style>
