<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserTie, faIndustryAlt, faBuilding, faSparkles, faRedoAlt } from "@fal"

library.add(faUserTie, faIndustryAlt, faBuilding, faSparkles, faRedoAlt)

export interface JourneySegment {
    key: string
    label: string
    description: string
    state: "done" | "skipped" | "untracked" | "on_track" | "at_risk" | "overdue" | "future"
    done_at: string | null
    planned_at: string
    forecast_at: string
    behind_plan: boolean
    days_overdue: number
    mark_column: string | null
}

export interface JourneyRibbon {
    key: string
    id: number
    is_supplier_order: boolean
    is_split_pending: boolean
    slug: string
    reference: string
    purchase_order_reference: string
    supplier_code: string | null
    supplier_name: string | null
    mark_route: routeType
    organisation_code: string
    journey: "agent" | "supplier" | "partner"
    parent_code: string | null
    parent_name: string | null
    buyer_name: string | null
    type: "npo" | "reorder"
    amount: number | null
    currency_code: string
    amount_grp: number | null
    created_at: string
    current_stage: string | null
    status: "on_track" | "at_risk" | "overdue" | "completed"
    days_overdue: number
    eta: string | null
    route: routeType
    segments: JourneySegment[]
}

const props = defineProps<{
    ribbon: JourneyRibbon
    stageKeys: string[]
    groupCurrency: string
    canMark: boolean
}>()

const emit = defineEmits<{ mark: [ribbon: JourneyRibbon, segment: JourneySegment, event: MouseEvent] }>()

const segmentsByKey = computed(() => Object.fromEntries(props.ribbon.segments.map((segment) => [segment.key, segment])))

const currentSegment = computed(() => props.ribbon.segments.find((segment) => segment.key === props.ribbon.current_stage))

const journeyIcon = { agent: "fal fa-user-tie", supplier: "fal fa-industry-alt", partner: "fal fa-building" }

const cellClass: Record<JourneySegment["state"], string> = {
    done: "bg-emerald-200 text-emerald-900",
    skipped: "bg-emerald-100 text-emerald-700/70",
    untracked: "bg-slate-50",
    on_track: "bg-blue-500 text-white font-semibold",
    at_risk: "bg-amber-400 text-amber-950 font-semibold",
    overdue: "bg-red-500 text-white font-semibold",
    future: "bg-gray-100 text-gray-500"
}

const statusClass: Record<JourneyRibbon["status"], string> = {
    on_track: "text-emerald-600",
    at_risk: "text-amber-600",
    overdue: "text-red-600",
    completed: "text-blue-600"
}

const statusLabel = computed(() => ({
    on_track: ctrans("On track"),
    at_risk: ctrans("At risk"),
    overdue: ctrans("Overdue"),
    completed: ctrans("Completed")
})[props.ribbon.status])

const statusNote = computed(() => {
    const segment = currentSegment.value
    if (props.ribbon.status === "completed") {
        return props.ribbon.eta ? `${ctrans("Finished")} ${shortDate(props.ribbon.eta)}` : ""
    }
    if (props.ribbon.status === "on_track") {
        return `${ctrans("ETA")} ${shortDate(props.ribbon.eta)}`
    }
    return segment?.label ?? ""
})

const chevron = {
    clipPath: "polygon(0 0, calc(100% - 7px) 0, 100% 50%, calc(100% - 7px) 100%, 0 100%, 7px 50%)"
}

function shortDate(date: string | null): string {
    if (!date) {
        return ""
    }
    return useFormatTime(date, { formatTime: date.slice(0, 4) === String(new Date().getFullYear()) ? "d MMM" : "d MMM yy" })
}

function amount(value: number | null, currency: string, digits = 0): string {
    if (value === null) {
        return ""
    }
    return new Intl.NumberFormat("en-GB", { style: "currency", currency, maximumFractionDigits: digits }).format(value)
}

function cellTitle(segment: JourneySegment): string {
    const parts = [segment.label, segment.description]
    if (segment.done_at) {
        parts.push(`${ctrans("Done")}: ${useFormatTime(segment.done_at)}`)
    } else if (segment.state === "skipped") {
        parts.push(ctrans("Not recorded, a later stage is done"))
    } else if (segment.state === "untracked") {
        parts.push(ctrans("Not recorded on this order. Mark it done when it happens"))
    }
    parts.push(`${ctrans("Plan")}: ${useFormatTime(segment.planned_at)}`)
    if (segment.behind_plan) {
        parts.push(ctrans("Behind plan, now expected :date", { date: useFormatTime(segment.forecast_at) }))
    }
    if (segment.days_overdue) {
        parts.push(ctrans(":days days overdue", { days: segment.days_overdue }))
    }
    if (isMarkable(segment)) {
        parts.push(ctrans("Click to mark done"))
    }
    return parts.join("\n")
}

function isMarkable(segment: JourneySegment): boolean {
    return props.canMark && segment.mark_column !== null
}
</script>

<template>
    <tr class="border-b border-gray-100 last:border-0">
        <td class="min-w-[15rem] max-w-[20rem] py-2 pl-3 pr-4 align-middle">
            <div class="flex items-start gap-1.5">
                <FontAwesomeIcon
                    v-tooltip="ribbon.type === 'npo' ? ctrans('NPO: has products never received before') : ctrans('Reorder')"
                    :icon="ribbon.type === 'npo' ? 'fal fa-sparkles' : 'fal fa-redo-alt'"
                    class="mt-1 shrink-0 text-xs"
                    :class="ribbon.type === 'npo' ? 'text-indigo-500' : 'text-gray-400'"
                    fixed-width
                    aria-hidden="true" />
                <Link :href="route(ribbon.route.name, ribbon.route.parameters)" class="break-all text-sm font-semibold leading-snug text-gray-900 hover:underline">
                    {{ ribbon.reference }}
                </Link>
            </div>
            <div class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-gray-500">
                <span>{{ useFormatTime(ribbon.created_at, { formatTime: "d MMM yyyy" }) }}</span>
                <span>·</span>
                <span class="font-medium text-gray-600">{{ ribbon.organisation_code }}</span>
                <span>←</span>
                <FontAwesomeIcon :icon="journeyIcon[ribbon.journey]" class="text-gray-400" fixed-width aria-hidden="true" />
                <span :title="ribbon.parent_name ?? ''">{{ ribbon.parent_code }}</span>
                <template v-if="ribbon.supplier_code">
                    <span>→</span>
                    <span class="font-medium text-gray-600" :title="ribbon.supplier_name ?? ''">{{ ribbon.supplier_code }}</span>
                </template>
                <span v-else-if="ribbon.is_split_pending" v-tooltip="ctrans('The agent has not split this order by supplier yet')" class="rounded bg-amber-50 px-1 text-[10px] text-amber-700">
                    {{ ctrans("not split") }}
                </span>
            </div>
            <div class="mt-0.5 flex items-center gap-2 text-xs">
                <span class="text-gray-400">{{ ribbon.buyer_name ? `${ctrans("by")} ${ribbon.buyer_name}` : ctrans("buyer unknown") }}</span>
                <span
                    class="font-semibold text-gray-700"
                    :title="ribbon.amount !== null ? amount(ribbon.amount, ribbon.currency_code, 2) : ''">
                    {{ ribbon.amount_grp !== null ? amount(ribbon.amount_grp, groupCurrency) : amount(ribbon.amount, ribbon.currency_code) }}
                </span>
            </div>
        </td>
        <td v-for="key in stageKeys" :key="key" class="px-0 py-2 align-middle">
            <button
                v-if="segmentsByKey[key]"
                type="button"
                :title="cellTitle(segmentsByKey[key])"
                :style="chevron"
                class="-mr-1 flex h-10 w-full min-w-[4.75rem] flex-col items-center justify-center px-2 text-[11px] leading-tight"
                :class="[cellClass[segmentsByKey[key].state], isMarkable(segmentsByKey[key]) ? 'cursor-pointer hover:brightness-95' : 'cursor-default']"
                @click="isMarkable(segmentsByKey[key]) && emit('mark', ribbon, segmentsByKey[key], $event)">
                <template v-if="segmentsByKey[key].state === 'done'">
                    {{ segmentsByKey[key].done_at ? shortDate(segmentsByKey[key].done_at) : "✓" }}
                </template>
                <template v-else-if="segmentsByKey[key].state === 'skipped'">✓</template>
                <template v-else-if="segmentsByKey[key].state === 'untracked'" />
                <span v-else-if="segmentsByKey[key].state === 'future'" :class="segmentsByKey[key].behind_plan ? 'text-red-400' : ''">
                    {{ shortDate(segmentsByKey[key].planned_at) }}
                </span>
                <template v-else>
                    <span>{{ shortDate(segmentsByKey[key].planned_at) }}</span>
                    <span v-if="segmentsByKey[key].state === 'overdue'">+{{ segmentsByKey[key].days_overdue }}d</span>
                </template>
            </button>
            <div v-else class="mx-2 h-px bg-gray-200" :title="ctrans('Not part of this journey')" />
        </td>
        <td class="whitespace-nowrap py-2 pl-4 pr-3 align-middle">
            <div class="text-sm font-semibold" :class="statusClass[ribbon.status]">
                {{ statusLabel }}
                <span v-if="ribbon.status === 'overdue'" class="font-normal">+{{ ribbon.days_overdue }}d</span>
            </div>
            <div class="text-xs text-gray-500">{{ statusNote }}</div>
        </td>
    </tr>
</template>
