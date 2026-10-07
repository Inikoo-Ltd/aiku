<script setup lang="ts">
import { Head, Link, router, usePoll } from "@inertiajs/vue3"
import { computed, reactive, ref, watch } from "vue"
import axios from "axios"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { notify } from "@kyvg/vue3-notification"

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
const levelClass = {
    error: "bg-red-50 text-red-700 ring-red-200",
    block: "bg-orange-50 text-orange-700 ring-orange-200",
    link: "bg-sky-50 text-sky-700 ring-sky-200",
    warning: "bg-amber-50 text-amber-700 ring-amber-200",
}
const levelLabel = computed(() => ({
    error: ctrans("Fix in the sheet"),
    block: ctrans("Needs a decision"),
    link: ctrans("Existing trade unit"),
    warning: ctrans("Check"),
}))

const sortedFindings = (row: Row) => [...row.findings].sort((a, b) => levelOrder[a.level] - levelOrder[b.level])

const counts = computed(() => {
    const active = props.rows.filter((row) => !isSkipped(row))
    const has = (row: Row, level: string) => row.findings.some((finding) => finding.level === level)
    return {
        rows: props.rows.length,
        skipped: props.rows.length - active.length,
        errors: active.filter((row) => has(row, "error")).length,
        blocks: active.filter((row) => row.findings.some((finding) => ["block", "link"].includes(finding.level) && !isAccepted(row, finding))).length,
        warnings: active.filter((row) => has(row, "warning")).length,
    }
})

const needsSkoName = (row: Row) => (row.values.units_per_sko ?? 1) > 1

const localDecisions = reactive<Record<number, Record<string, boolean>>>({})
const localSkips = reactive<Record<number, boolean>>({})
const isAccepted = (row: Row, finding: Finding) => localDecisions[row.id]?.[finding.code] ?? !!row.decisions[finding.code]?.accepted
const isSkipped = (row: Row) => localSkips[row.id] ?? row.skip

const pendingSaves = ref(0)
let saveChain: Promise<unknown> = Promise.resolve()
const updateRecord = (row: Row, payload: Record<string, any>) => {
    pendingSaves.value++
    saveChain = saveChain
        .then(() => axios.patch(route(props.routes.record.name, { ...props.routes.record.parameters, record: row.id }), payload, { headers: { Accept: "application/json" } }))
        .catch(() => notify({ title: ctrans("Not saved"), text: ctrans("A change could not be saved, the page will reload."), type: "error" }))
        .finally(() => {
            pendingSaves.value--
            if (pendingSaves.value === 0) {
                router.reload({ only: ["upload", "rows", "draft_orders"], onSuccess: () => {
                    Object.keys(localDecisions).forEach((key) => delete localDecisions[Number(key)])
                    Object.keys(localSkips).forEach((key) => delete localSkips[Number(key)])
                } })
            }
        })
}

const toggleDecision = (row: Row, finding: Finding, accepted: boolean) => {
    localDecisions[row.id] = { ...(localDecisions[row.id] ?? {}), [finding.code]: accepted }
    updateRecord(row, { decisions: { [finding.code]: accepted } })
}
const toggleSkip = (row: Row) => {
    const skip = !isSkipped(row)
    localSkips[row.id] = skip
    updateRecord(row, { skip })
}
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
                    :disabled="upload.problems.length > 0 || pendingSaves > 0"
                    @click="importUpload"
                />
            </div>
        </template>
    </PageHeading>

    <div class="p-4 space-y-4 text-sm text-gray-700">
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-gray-500">
            <span>{{ ctrans("Supplier") }} <span class="font-medium text-gray-700">{{ supplier.name }}</span></span>
            <span>{{ ctrans("State") }} <span class="font-medium text-gray-700">{{ upload.state_label }}</span></span>
            <span class="tabular-nums">{{ counts.rows }} {{ ctrans("rows") }}</span>
            <span v-if="counts.skipped" class="tabular-nums">{{ counts.skipped }} {{ ctrans("skipped") }}</span>
            <span v-if="counts.errors" class="tabular-nums text-red-700">{{ counts.errors }} {{ ctrans("with errors") }}</span>
            <span v-if="counts.blocks" class="tabular-nums text-orange-700">{{ counts.blocks }} {{ ctrans("need a decision") }}</span>
            <span v-if="counts.warnings" class="tabular-nums text-amber-700">{{ counts.warnings }} {{ ctrans("with warnings") }}</span>
            <Link :href="route(supplier.products_route.name, supplier.products_route.parameters)" class="font-medium hover:underline">
                {{ ctrans("Supplier products") }}
            </Link>
        </div>

        <div v-if="upload.errors.length" class="rounded border border-red-200 bg-red-50 p-3 text-red-700">
            <div class="font-semibold">{{ ctrans("The file was refused") }}</div>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="error in upload.errors" :key="error">{{ error }}</li>
            </ul>
        </div>

        <div v-if="isAiRunning" class="rounded border border-gray-200 bg-gray-50 p-3 text-gray-600">
            {{ ctrans("AI checks are running, this page updates by itself. Import waits for them.") }}
        </div>
        <div v-else-if="upload.review?.summary" class="rounded border border-gray-200 bg-gray-50 p-3">
            <div class="font-semibold">{{ ctrans("AI review") }}<span v-if="upload.review.partial" class="font-normal text-gray-500"> · {{ ctrans("flagged rows only") }}</span></div>
            <p class="mt-1 whitespace-pre-line">{{ upload.review.summary }}</p>
        </div>
        <div v-else-if="upload.review?.note" class="rounded border border-gray-200 bg-gray-50 p-3 text-gray-600">
            {{ upload.review.note }}
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
            <div
                v-for="row in rows"
                :key="row.id"
                class="rounded border p-3"
                :class="isSkipped(row) ? 'border-gray-200 bg-gray-50 opacity-60' : 'border-gray-200'"
            >
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <span class="text-xs text-gray-500 tabular-nums">{{ ctrans("Row") }} {{ row.row }}</span>
                    <span class="font-medium">{{ row.values.part_reference }}</span>
                    <span>{{ row.values.unit_name }}</span>
                    <span class="text-gray-500">{{ row.values.family }}</span>
                    <span v-if="row.status !== 'preview'" class="text-xs font-medium" :class="row.status === 'failed' ? 'text-red-700' : 'text-gray-500'">{{ row.status }}</span>
                    <button v-if="upload.can_edit" type="button" class="ml-auto text-xs text-gray-600 hover:underline" @click="toggleSkip(row)">
                        {{ isSkipped(row) ? ctrans("Include row") : ctrans("Skip row") }}
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

                <div v-if="!isSkipped(row) && needsSkoName(row)" class="mt-2 flex items-center gap-2 text-xs">
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

                <ul v-if="row.findings.length" class="mt-2 space-y-1">
                    <li v-for="finding in sortedFindings(row)" :key="finding.code" class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded px-1.5 py-0.5 ring-1 ring-inset" :class="levelClass[finding.level]">{{ levelLabel[finding.level] }}</span>
                        <span>{{ finding.message }}</span>
                        <span v-if="finding.source === 'jev'" class="text-gray-500">({{ ctrans("AI") }})</span>
                        <label
                            v-if="upload.can_edit && !isSkipped(row) && ['block', 'link'].includes(finding.level)"
                            class="inline-flex items-center gap-1.5 text-gray-600"
                        >
                            <input type="checkbox" :checked="isAccepted(row, finding)" @change="toggleDecision(row, finding, ($event.target as HTMLInputElement).checked)" />
                            {{ finding.level === "link" ? ctrans("Add this supplier to it") : ctrans("This is OK, I accept responsibility") }}
                        </label>
                    </li>
                </ul>

                <p v-if="upload.review?.rows?.[row.row]" class="mt-2 text-xs text-gray-600">
                    <span class="font-medium">{{ ctrans("AI suggests") }}:</span> {{ upload.review.rows[row.row] }}
                </p>

                <ul v-if="row.status === 'failed'" class="mt-2 text-xs text-red-700">
                    <li v-for="error in row.errors" :key="error">{{ error }}</li>
                </ul>
            </div>
        </div>

        <div v-if="upload.can_edit && upload.problems.length" class="rounded border border-orange-200 bg-orange-50 p-3 text-xs text-orange-800">
            <div class="font-semibold text-sm">{{ ctrans("Import is waiting for") }}</div>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="problem in upload.problems" :key="problem">{{ problem }}</li>
            </ul>
        </div>
    </div>
</template>
