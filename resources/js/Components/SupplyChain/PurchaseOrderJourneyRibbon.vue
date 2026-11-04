<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Link } from "@inertiajs/vue3"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faUserTie, faIndustryAlt, faBuilding, faSparkles, faRedoAlt, faChevronRight, faCheck, faExclamationTriangle, faPeopleArrows, faClipboardList, faUser } from "@fal"

library.add(faUserTie, faIndustryAlt, faBuilding, faSparkles, faRedoAlt, faChevronRight, faCheck, faExclamationTriangle, faPeopleArrows, faClipboardList, faUser)

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
    checks?: { key: string; label: string; done: boolean; date: string | null }[]
}

export interface JourneyRibbon {
    key: string
    id: number
    slug: string
    reference: string
    supplier_code: string | null
    supplier_name: string | null
    mark_route: routeType
    organisation_code: string
    organisation_name: string
    supplier_company: string | null
    supplier_nickname: string | null
    agent_name: string | null
    agent_slug: string | null
    placed_by: string | null
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
    current_label: string | null
    status: "on_track" | "at_risk" | "overdue" | "completed"
    days_overdue: number
    eta: string | null
    route: routeType
    segments: JourneySegment[]
}

const props = defineProps<{
    ribbon: JourneyRibbon
    stages: { key: string; label: string; description: string; markable: boolean }[]
    groupCurrency: string
    canMark: boolean
    expandAll: boolean
}>()

const emit = defineEmits<{ mark: [ribbon: JourneyRibbon, segment: JourneySegment, event: MouseEvent] }>()

const isOpen = ref(props.expandAll)
watch(() => props.expandAll, (value) => (isOpen.value = value))

function toggleFromDetails(event: MouseEvent): void {
    if ((event.target as HTMLElement).closest("a, button") || window.getSelection()?.toString()) {
        return
    }
    isOpen.value = !isOpen.value
}

const segmentsByKey = computed(() => Object.fromEntries(props.ribbon.segments.map((segment) => [segment.key, segment])))

const journeyIcon = { agent: "fal fa-user-tie", supplier: "fal fa-industry-alt", partner: "fal fa-building" }

const journeyLabel = { agent: ctrans("Agent"), supplier: ctrans("Direct supplier"), partner: ctrans("Inter-company") }

const isCurrent = (segment: JourneySegment) => ["on_track", "at_risk", "overdue"].includes(segment.state)

const cellClass: Record<JourneySegment["state"], string> = {
    done: "border-emerald-300 bg-emerald-50 text-emerald-900",
    skipped: "border-emerald-200 bg-emerald-50/50 text-emerald-700",
    untracked: "border-dashed border-gray-200 bg-white text-gray-400",
    on_track: "border-2 border-gray-700 bg-white text-gray-900",
    at_risk: "border-2 border-amber-400 bg-amber-50 text-gray-900",
    overdue: "border-2 border-red-400 bg-red-50 text-gray-900",
    future: "border-gray-200 bg-gray-50 text-gray-500"
}

const statusClass: Record<JourneyRibbon["status"], string> = {
    on_track: "text-emerald-600",
    at_risk: "text-amber-600",
    overdue: "text-red-600",
    completed: "text-gray-600"
}

const statusLabel = computed(() => ({
    on_track: ctrans("On track"),
    at_risk: ctrans("At risk"),
    overdue: ctrans("Overdue"),
    completed: ctrans("Completed")
})[props.ribbon.status])

const currentSegment = computed(() => props.ribbon.segments.find((segment) => segment.key === props.ribbon.current_stage) ?? null)
const currentNumber = computed(() => props.stages.findIndex((stage) => stage.key === props.ribbon.current_stage) + 1)

function dayNumber(date: string | null): number | null {
    if (!date) {
        return null
    }
    return Math.round((Date.parse(date.slice(0, 10)) - Date.parse(props.ribbon.created_at.slice(0, 10))) / 86400000)
}

function zDay(date: string | null): string {
    const day = dayNumber(date)
    return day === null ? "" : `Z${day < 0 ? "" : "+"}${day}`
}

function absentStage(stage: { key: string; label: string; description: string }): JourneySegment {
    return {
        key: stage.key,
        label: stage.label,
        description: stage.description,
        state: "untracked",
        done_at: null,
        planned_at: "",
        forecast_at: "",
        behind_plan: false,
        days_overdue: 0,
        mark_column: stage.key
    }
}

function tracksAbsent(stage: { key: string; markable: boolean }): boolean {
    return props.ribbon.journey === "supplier" && stage.markable
}

function absentTitle(key: string): string {
    if (key === "deposit_paid") {
        return ctrans("No deposit on this order")
    }
    if (key === "products_online") {
        return ctrans("No products for sale on this order")
    }
    return ctrans("Not used for orders between AW companies")
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
    if (segment.checks?.length) {
        parts.push(segment.checks.map((check) => `${check.done ? "✓" : "○"} ${check.label}`).join("   "))
    }
    if (segment.planned_at) {
        parts.push(`${ctrans("Target")}: ${useFormatTime(segment.planned_at)}`)
    }
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
    <tbody class="border-b border-gray-100 last:border-0">
        <tr>
            <td class="min-w-[17rem] max-w-[22rem] cursor-pointer py-2 pl-2 pr-3 align-middle" @click="toggleFromDetails">
                <div class="flex items-start gap-1.5">
                    <button
                        type="button"
                        class="shrink-0 self-center rounded p-0.5 text-gray-400 hover:text-gray-700"
                        :aria-expanded="isOpen"
                        :aria-label="isOpen ? ctrans('Hide details') : ctrans('Show details')"
                        @click="isOpen = !isOpen">
                        <FontAwesomeIcon icon="fal fa-chevron-right" class="transition-transform" :class="{ 'rotate-90': isOpen }" fixed-width aria-hidden="true" />
                    </button>
                    <div class="min-w-0 flex-1 space-y-1 text-xs text-gray-600">
                        <div class="flex items-center gap-1.5 pb-1.5">
                            <FontAwesomeIcon v-tooltip="journeyLabel[ribbon.journey]" :icon="journeyIcon[ribbon.journey]" class="shrink-0 text-gray-600" fixed-width aria-hidden="true" />
                            <span class="min-w-0 text-sm font-semibold leading-snug text-gray-900" :title="ribbon.parent_code ?? ''">{{ ribbon.supplier_company ?? ribbon.parent_name ?? ribbon.parent_code }}</span>
                            <span class="ml-auto flex shrink-0 flex-col items-end gap-0.5">
                                <span class="whitespace-nowrap text-sm font-semibold text-gray-700" :title="ribbon.amount !== null ? amount(ribbon.amount, ribbon.currency_code, 2) : ''">
                                    {{ ribbon.amount_grp !== null ? amount(ribbon.amount_grp, groupCurrency) : amount(ribbon.amount, ribbon.currency_code) }}
                                </span>
                                <span class="whitespace-nowrap rounded border border-gray-200 px-1.5 text-gray-600" :title="ctrans('Calendar days since the order was created (Day Zero)')">
                                    {{ ctrans("Age") }} {{ zDay(new Date().toISOString()) }}
                                </span>
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-1">
                            <span class="flex items-center gap-1">
                                <FontAwesomeIcon v-tooltip="ctrans('Placed by')" icon="fal fa-user" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                                {{ ribbon.placed_by ?? ctrans("Unknown") }}
                            </span>
                            <span class="text-gray-400">·</span>
                            <span :title="ribbon.organisation_code">{{ ctrans("for :organisation", { organisation: ribbon.organisation_name }) }}</span>
                        </div>
                        <div v-if="ribbon.agent_name" class="flex items-center gap-1">
                            <FontAwesomeIcon v-tooltip="ctrans('Agent')" icon="fal fa-people-arrows" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                            <Link :href="route('grp.supply-chain.agents.show', { agent: ribbon.agent_slug! })" class="hover:text-gray-900 hover:underline">{{ ribbon.agent_name }}</Link>
                        </div>
                        <div v-if="ribbon.supplier_nickname" class="text-gray-500">{{ ctrans("Nickname :nickname", { nickname: ribbon.supplier_nickname }) }}</div>
                        <div class="flex items-center gap-1">
                            <FontAwesomeIcon v-tooltip="ctrans('Purchase order')" icon="fal fa-clipboard-list" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                            <Link :href="route(ribbon.route.name, ribbon.route.parameters)" class="min-w-0 truncate whitespace-nowrap text-gray-600 hover:underline" :title="ribbon.reference">
                                {{ ribbon.reference }}
                            </Link>
                            <FontAwesomeIcon
                                v-tooltip="ribbon.type === 'npo' ? ctrans('NPO: has products never received before') : ctrans('Reorder')"
                                :icon="ribbon.type === 'npo' ? 'fal fa-sparkles' : 'fal fa-redo-alt'"
                                class="shrink-0"
                                :class="ribbon.type === 'npo' ? 'text-gray-700' : 'text-gray-400'"
                                fixed-width
                                aria-hidden="true" />
                        </div>
                    </div>
                </div>
            </td>
            <td v-for="stage in stages" :key="stage.key" class="journey-stage-cell px-1 py-2 align-middle">
                <template v-for="key in [stage.key]" :key="key">
                <button
                    v-if="segmentsByKey[key]"
                    type="button"
                    :title="cellTitle(segmentsByKey[key])"
                    class="relative z-[1] flex h-11 w-full min-w-[6.5rem] flex-col items-center justify-center rounded-md border px-1.5 text-[11px] leading-tight focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-700"
                    :class="[cellClass[segmentsByKey[key].state], isMarkable(segmentsByKey[key]) ? 'cursor-pointer hover:brightness-95' : 'cursor-default']"
                    @click="isMarkable(segmentsByKey[key]) && emit('mark', ribbon, segmentsByKey[key], $event)">
                    <template v-if="segmentsByKey[key].state === 'done'">
                        <span class="font-semibold">
                            <FontAwesomeIcon icon="fal fa-check" aria-hidden="true" />
                            {{ segmentsByKey[key].done_at ? zDay(segmentsByKey[key].done_at) : "" }}
                        </span>
                        <span class="truncate">{{ segmentsByKey[key].done_at ? shortDate(segmentsByKey[key].done_at) : ctrans("Done") }}</span>
                    </template>
                    <template v-else-if="segmentsByKey[key].state === 'skipped'">
                        <FontAwesomeIcon icon="fal fa-check" aria-hidden="true" />
                        <span>{{ ctrans("Not recorded") }}</span>
                    </template>
                    <template v-else-if="segmentsByKey[key].state === 'untracked'">—</template>
                    <template v-else-if="segmentsByKey[key].state === 'future'">
                        <span class="font-semibold" :class="{ 'line-through': segmentsByKey[key].behind_plan }">{{ zDay(segmentsByKey[key].planned_at) }}</span>
                        <span>{{ ctrans("Target") }}</span>
                    </template>
                    <template v-else>
                        <span class="line-clamp-2 font-semibold">
                            <FontAwesomeIcon
                                v-if="segmentsByKey[key].state !== 'on_track'"
                                icon="fal fa-exclamation-triangle"
                                :class="segmentsByKey[key].state === 'overdue' ? 'text-red-600' : 'text-amber-600'"
                                aria-hidden="true" />
                            {{ ribbon.current_label ?? segmentsByKey[key].label }}
                        </span>
                        <span v-if="segmentsByKey[key].state === 'overdue'" class="font-semibold text-red-600">{{ ctrans(":days d late", { days: segmentsByKey[key].days_overdue }) }}</span>
                        <span v-else>{{ zDay(segmentsByKey[key].planned_at) }} {{ ctrans("target") }}</span>
                    </template>
                </button>
                <button
                    v-else-if="canMark && tracksAbsent(stage)"
                    type="button"
                    :title="`${stage.label}\n${stage.description}\n${ctrans('Not recorded on this order. Click to mark done')}`"
                    class="relative z-[1] flex h-11 w-full min-w-[6.5rem] cursor-pointer items-center justify-center rounded-md border border-dashed border-gray-200 bg-white text-gray-400 hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-700"
                    @click="emit('mark', ribbon, absentStage(stage), $event)">—</button>
                <div v-else-if="tracksAbsent(stage)" class="relative z-[1] flex h-11 items-center justify-center rounded-md border border-dashed border-gray-200 bg-white text-gray-400" :title="ctrans('Not recorded on this order')">—</div>
                <div v-else class="h-11" :title="absentTitle(stage.key)" />
                </template>
            </td>
            <td class="whitespace-nowrap py-2 pl-3 pr-3 align-middle">
                <div class="text-sm font-semibold" :class="statusClass[ribbon.status]">
                    {{ statusLabel }}
                    <span v-if="ribbon.status === 'overdue'" class="font-normal">+{{ ribbon.days_overdue }}d</span>
                </div>
                <div class="text-xs text-gray-500">
                    {{ ribbon.status === "completed" ? (ribbon.eta ? `${ctrans("Finished")} ${shortDate(ribbon.eta)}` : "") : `${ctrans("ETA")} ${shortDate(ribbon.eta)}` }}
                </div>
            </td>
        </tr>
        <tr v-if="isOpen" class="bg-gray-50">
            <td :colspan="stages.length + 2" class="px-3 py-2">
                <div class="sticky left-3 flex max-w-[calc(100vw-4rem)] flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                    <template v-if="currentSegment">
                        <span class="font-semibold text-gray-900">{{ currentNumber }} · {{ currentSegment.label }}</span>
                        <span class="text-gray-700">
                            {{ ctrans("Next action") }}: <span class="font-medium">{{ ribbon.current_label }}</span>
                            · {{ ctrans("Target") }} {{ shortDate(currentSegment.planned_at) }} ({{ zDay(currentSegment.planned_at) }})
                            <span v-if="currentSegment.days_overdue" class="font-semibold text-red-600">· {{ ctrans(":days days overdue", { days: currentSegment.days_overdue }) }}</span>
                        </span>
                        <span v-if="currentSegment.checks?.length" class="flex flex-wrap gap-1">
                            <span
                                v-for="check in currentSegment.checks"
                                :key="check.key"
                                class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs"
                                :class="check.done ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800'"
                                :title="check.date ? useFormatTime(check.date) : ctrans('Not done yet')">
                                {{ check.label }}
                                <FontAwesomeIcon :icon="check.done ? 'fal fa-check' : 'fal fa-exclamation-triangle'" aria-hidden="true" />
                            </span>
                        </span>
                        <Button
                            v-if="isMarkable(currentSegment)"
                            type="tertiary"
                            size="xs"
                            :label="ctrans('Mark :stage done', { stage: currentSegment.label })"
                            @click="(event: MouseEvent) => emit('mark', ribbon, currentSegment!, event)" />
                    </template>
                    <span v-else class="text-gray-700">
                        {{ ribbon.status === "completed" ? ctrans("Every stage is done") : ctrans("Not started yet") }}
                    </span>
                    <Link :href="route(ribbon.route.name, ribbon.route.parameters)" class="text-gray-700 underline hover:text-gray-900">
                        {{ ctrans("Documents & activity") }}
                    </Link>
                </div>
            </td>
        </tr>
    </tbody>
</template>

<style scoped>
.journey-stage-cell {
    position: relative;
}

.journey-stage-cell::before {
    content: "";
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    border-top: 1px solid #e5e7eb;
}
</style>
