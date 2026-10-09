<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 09 Aug 2026 15:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import DatePicker from "primevue/datepicker"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"
import InputText from "primevue/inputtext"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserHardHat, faChevronDown, faChevronUp } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faUserHardHat, faChevronDown, faChevronUp)

interface ArtisanSession {
    id: number
    state: string
    task_name: string
    artefact_code: string
    job_order_reference: string
    started_at: string
    ended_at: string
    break_minutes: number
    quantity_made: number
    quantity_rejected: number
    earned: number
    units_per_hour: number | null
    standard_rate: number | null
    is_under_target: boolean
    under_target_review: null | { reason: string, note: string | null, reviewed_by: string | null, reviewed_at: string }
    review_route: null | { name: string, parameters: object }
    void_route: null | { name: string, parameters: object }
}

interface ArtisanJobStep {
    manufacture_task_id: number
    task_name: string
    hours: number
    quantity_made: number
    quantity_rejected: number
    earned: number
    sessions: ArtisanSession[]
}

interface NonProductiveGroup {
    key: string
    label: string | null
    hours: Record<string, number>
}

interface NonProductiveSummary {
    hours: number
    pay: number | null
    activities: Record<string, number>
    days: NonProductiveGroup[]
    weeks: NonProductiveGroup[]
    job_orders: NonProductiveGroup[]
    sessions: {
        id: number
        state: string
        activity: string
        job_order_reference: string | null
        started_at: string
        ended_at: string
        break_minutes: number
        pay: number | null
        void_route: null | { name: string, parameters: object }
    }[]
}

interface ArtisanJob {
    job_order_item_id: number
    job_order_reference: string
    artefact_code: string
    hours: number
    quantity_made: number
    quantity_rejected: number
    earned: number
    steps: ArtisanJobStep[]
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    period: { from: string, to: string }
    manufacture_task_id: number | null
    manufacture_tasks: { id: number, name: string }[]
    under_target: boolean
    under_target_reasons: Record<string, string>
    non_productive_activities: { value: string, label: string }[]
    artisans: {
        user_id: number
        worker: string
        number_sessions: number
        hours_worked: number
        quantity_made: number
        quantity_rejected: number
        earned: number
        under_target_open: number
        non_productive: NonProductiveSummary
        jobs: ArtisanJob[]
    }[]
}>()

const from = ref(props.period.from)
const to = ref(props.period.to)

const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const dateModel = (target: typeof from) =>
    computed<Date | null>({
        get: () => (target.value ? fromIsoDate(target.value) : null),
        set: (value) => {
            if (value) target.value = toLocalIsoDate(value)
        },
    })
const fromDate = dateModel(from)
const toDate = dateModel(to)
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"
const stepOptions = computed(() => [{ id: null, name: ctrans('All steps') }, ...props.manufacture_tasks])
const reasonOptions = computed(() => Object.entries(props.under_target_reasons).map(([value, label]) => ({ value, label })))
const manufactureTaskId = ref<number | null>(props.manufacture_task_id)
const underTargetOnly = ref(props.under_target)
const reviewing = ref<number | null>(null)
const reviewReason = ref<string | null>(null)
const reviewNote = ref('')
const expanded = ref<Set<number>>(new Set())
const processing = ref(false)

function applyPeriod() {
    router.get(
        window.location.pathname,
        { from: from.value, to: to.value, manufacture_task_id: manufactureTaskId.value || undefined, under_target: underTargetOnly.value ? 1 : undefined },
        { preserveState: false }
    )
}

function toggle(userId: number) {
    if (expanded.value.has(userId)) {
        expanded.value.delete(userId)
    } else {
        expanded.value.add(userId)
    }
    expanded.value = new Set(expanded.value)
}

function hoursLabel(hours: number) {
    const h = Math.floor(hours)
    const m = Math.round((hours - h) * 60)
    return h ? `${h}h ${m}m` : `${m}m`
}

function activitySummary(summary: NonProductiveSummary) {
    return props.non_productive_activities
        .filter(activity => summary.activities[activity.value])
        .map(activity => `${activity.label} ${hoursLabel(summary.activities[activity.value])}`)
        .join(' · ')
}

function voidSession(session: { void_route: null | { name: string, parameters: object }, quantity_made?: number }) {
    if (!session.void_route) return
    if (!window.confirm(ctrans('Void this entry?') + (session.quantity_made !== undefined ? ` ${session.quantity_made}` : ''))) return
    processing.value = true
    router.patch(
        route(session.void_route.name, session.void_route.parameters),
        {},
        { preserveScroll: true, onFinish: () => processing.value = false }
    )
}

function openReview(session: ArtisanSession) {
    reviewing.value = session.id
    reviewReason.value = null
    reviewNote.value = ''
}

function saveReview(session: ArtisanSession) {
    if (!session.review_route || !reviewReason.value) return
    processing.value = true
    router.patch(
        route(session.review_route.name, session.review_route.parameters),
        { under_target_reason: reviewReason.value, under_target_note: reviewNote.value || null },
        {
            preserveScroll: true,
            onSuccess: () => reviewing.value = null,
            onFinish: () => processing.value = false,
        }
    )
}

function sessionDuration(session: { started_at: string, ended_at: string, break_minutes: number }) {
    const seconds = Math.max(0, (new Date(session.ended_at).getTime() - new Date(session.started_at).getTime()) / 1000 - session.break_minutes * 60)
    const h = Math.floor(seconds / 3600)
    const m = Math.round((seconds % 3600) / 60)
    return h ? `${h}h ${m}m` : `${m}m`
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-4 max-w-4xl">
        <div class="mb-6 flex items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('From') }}</label>
                <DatePicker v-model="fromDate" :maxDate="toDate ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :aria-label="ctrans('From')" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('To') }}</label>
                <DatePicker v-model="toDate" :minDate="fromDate ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :aria-label="ctrans('To')" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Step') }}</label>
                <Select v-model="manufactureTaskId" :options="stepOptions" optionLabel="name" optionValue="id" filter :class="fieldFocusClass" />
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-600">
                <Checkbox v-model="underTargetOnly" binary />
                {{ ctrans('Under target only') }}
            </label>
            <button type="button" class="rounded bg-[--app-accent] text-[--app-accent-text] hover:bg-[--app-accent-strong] text-sm px-3 py-2" @click="applyPeriod">
                {{ ctrans('Apply') }}
            </button>
        </div>

        <div v-if="!artisans.length" class="text-gray-400 text-center py-16 border border-dashed border-gray-200 rounded-lg">
            {{ ctrans('No finished work in this period') }}
        </div>

        <div v-for="artisan in artisans" :key="artisan.user_id" class="mb-3 rounded-xl border border-gray-200 bg-white">
            <button type="button" class="w-full px-4 py-3 flex items-center justify-between gap-4 text-left" @click="toggle(artisan.user_id)">
                <div class="font-semibold">{{ artisan.worker }}</div>
                <div class="flex items-center gap-6 text-sm text-gray-600 tabular-nums">
                    <span>{{ artisan.number_sessions }} {{ ctrans('tasks') }}</span>
                    <span>{{ artisan.hours_worked }} h</span>
                    <span>{{ artisan.quantity_made }} {{ ctrans('units') }}</span>
                    <span v-if="artisan.quantity_rejected" class="text-red-500">{{ artisan.quantity_rejected }} {{ ctrans('rejected') }}</span>
                    <span v-if="artisan.non_productive.hours" class="text-gray-500">{{ activitySummary(artisan.non_productive) }}</span>
                    <span v-if="artisan.under_target_open" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">
                        {{ artisan.under_target_open }} {{ ctrans('under target') }}
                    </span>
                    <span class="font-semibold text-gray-800">{{ artisan.earned.toFixed(2) }}</span>
                    <FontAwesomeIcon :icon="['fal', expanded.has(artisan.user_id) ? 'chevron-up' : 'chevron-down']" fixed-width class="text-gray-400" />
                </div>
            </button>

            <div v-if="expanded.has(artisan.user_id)" class="border-t border-gray-100 px-4 py-2">
                <div v-if="artisan.non_productive.sessions.length" class="py-2 border-b border-gray-100">
                    <div class="flex items-center justify-between gap-3 text-sm font-medium text-gray-700">
                        <div>{{ ctrans('Preparation and cleaning') }} <span class="font-normal text-gray-400">· {{ ctrans('paid at base rate, not in units per hour') }}</span></div>
                        <div class="flex items-center gap-4 shrink-0 tabular-nums">
                            <span class="text-gray-400">{{ hoursLabel(artisan.non_productive.hours) }}</span>
                            <span class="w-16 text-right">{{ artisan.non_productive.pay === null ? '—' : artisan.non_productive.pay.toFixed(2) }}</span>
                        </div>
                    </div>

                    <table class="mt-2 w-full text-xs tabular-nums">
                        <thead>
                            <tr class="text-left text-gray-400">
                                <th class="py-1 font-medium">{{ ctrans('Day') }}</th>
                                <th v-for="activity in non_productive_activities" :key="activity.value" class="py-1 font-medium text-right">{{ activity.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="day in artisan.non_productive.days" :key="day.key" class="border-t border-gray-50 text-gray-600">
                                <td class="py-1">{{ useFormatTime(day.key) }}</td>
                                <td v-for="activity in non_productive_activities" :key="activity.value" class="py-1 text-right">{{ day.hours[activity.value] ? hoursLabel(day.hours[activity.value]) : '—' }}</td>
                            </tr>
                            <tr v-for="week in artisan.non_productive.weeks" :key="'w' + week.key" class="border-t border-gray-100 font-medium text-gray-700">
                                <td class="py-1">{{ ctrans('Week of :date', { date: useFormatTime(week.key) }) }}</td>
                                <td v-for="activity in non_productive_activities" :key="activity.value" class="py-1 text-right">{{ hoursLabel(week.hours[activity.value]) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="mt-2 w-full text-xs tabular-nums">
                        <thead>
                            <tr class="text-left text-gray-400">
                                <th class="py-1 font-medium">{{ ctrans('Job order') }}</th>
                                <th v-for="activity in non_productive_activities" :key="activity.value" class="py-1 font-medium text-right">{{ activity.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="jobOrder in artisan.non_productive.job_orders" :key="jobOrder.key" class="border-t border-gray-50 text-gray-600">
                                <td class="py-1">{{ jobOrder.label ?? ctrans('General, no job order') }}</td>
                                <td v-for="activity in non_productive_activities" :key="activity.value" class="py-1 text-right">{{ jobOrder.hours[activity.value] ? hoursLabel(jobOrder.hours[activity.value]) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-for="session in artisan.non_productive.sessions" :key="session.id"
                        class="py-1 flex items-center justify-between gap-3 text-sm"
                        :class="session.state == 'voided' ? 'opacity-40 line-through' : ''">
                        <span class="flex items-center gap-2 text-gray-400">
                            {{ useFormatTime(session.ended_at) }}
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ session.activity }}</span>
                            <span class="text-xs">{{ session.job_order_reference ?? ctrans('General') }}</span>
                        </span>
                        <div class="flex items-center gap-4 shrink-0 tabular-nums text-gray-700">
                            <span class="text-gray-400">
                                {{ sessionDuration(session) }}
                                <span v-if="session.break_minutes">({{ session.break_minutes }}m {{ ctrans('break') }})</span>
                            </span>
                            <span class="w-16 text-right">{{ session.pay === null ? '—' : session.pay.toFixed(2) }}</span>
                            <button v-if="session.void_route" type="button"
                                class="text-xs text-red-600 hover:underline disabled:opacity-40"
                                :disabled="processing" @click="voidSession(session)">
                                {{ ctrans('Void') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-for="job in artisan.jobs" :key="job.job_order_item_id" class="py-2 border-b border-gray-100 last:border-0">
                    <div class="flex items-center justify-between gap-3 text-sm font-medium text-gray-700">
                        <div class="truncate">{{ job.artefact_code }} · {{ job.job_order_reference }}</div>
                        <div class="flex items-center gap-4 shrink-0 tabular-nums">
                            <span class="text-gray-400">{{ job.hours }} h</span>
                            <span>{{ job.quantity_made }}</span>
                            <span v-if="job.quantity_rejected" class="text-red-500">-{{ job.quantity_rejected }}</span>
                            <span class="w-16 text-right">{{ job.earned.toFixed(2) }}</span>
                        </div>
                    </div>

                    <div v-for="step in job.steps" :key="step.manufacture_task_id" class="mt-1 pl-4 border-l-2 border-gray-100">
                        <div class="flex items-center justify-between gap-3 text-xs text-gray-500">
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-gray-600">{{ step.task_name }}</span>
                            <div class="flex items-center gap-4 shrink-0 tabular-nums">
                                <span>{{ step.hours }} h</span>
                                <span>{{ step.quantity_made }}</span>
                                <span v-if="step.quantity_rejected" class="text-red-500">-{{ step.quantity_rejected }}</span>
                                <span class="w-16 text-right">{{ step.earned.toFixed(2) }}</span>
                            </div>
                        </div>

                        <div v-for="session in step.sessions" :key="session.id">
                        <div
                            class="py-1 flex items-center justify-between gap-3 text-sm"
                            :class="session.state == 'voided' ? 'opacity-40 line-through' : ''">
                            <span class="flex items-center gap-2 text-gray-400">
                                {{ useFormatTime(session.ended_at) }}
                                <span v-if="session.is_under_target"
                                    class="rounded px-1.5 py-0.5 text-xs font-medium"
                                    :class="session.under_target_review ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-800'">
                                    {{ session.under_target_review ? session.under_target_review.reason : ctrans('Under target / action required') }}
                                </span>
                            </span>
                            <div class="flex items-center gap-4 shrink-0 tabular-nums text-gray-700">
                                <span class="text-gray-400">
                                    {{ sessionDuration(session) }}
                                    <span v-if="session.break_minutes">({{ session.break_minutes }}m {{ ctrans('break') }})</span>
                                </span>
                                <span>{{ session.quantity_made }}</span>
                                <span v-if="session.quantity_rejected" class="text-red-500">-{{ session.quantity_rejected }}</span>
                                <span v-if="session.standard_rate !== null" :class="session.is_under_target ? 'text-amber-700' : 'text-gray-400'">
                                    {{ session.units_per_hour ?? 0 }}/{{ session.standard_rate }} {{ ctrans('per hr') }}
                                </span>
                                <span class="w-16 text-right">{{ session.earned.toFixed(2) }}</span>
                                <button
                                    v-if="session.review_route && reviewing !== session.id"
                                    type="button"
                                    class="text-xs font-medium text-gray-700 hover:underline"
                                    @click="openReview(session)"
                                >
                                    {{ session.under_target_review ? ctrans('Change reason') : ctrans('Log reason') }}
                                </button>
                                <button
                                    v-if="session.void_route"
                                    type="button"
                                    class="text-xs text-red-600 hover:underline disabled:opacity-40"
                                    :disabled="processing"
                                    @click="voidSession(session)"
                                >
                                    {{ ctrans('Void') }}
                                </button>
                            </div>
                        </div>
                        <div v-if="session.under_target_review?.note && reviewing !== session.id" class="pb-1 pl-2 text-xs text-gray-500">
                            {{ session.under_target_review.note }}
                            <span v-if="session.under_target_review.reviewed_by">— {{ session.under_target_review.reviewed_by }}</span>
                        </div>
                        <div v-if="reviewing === session.id" class="mb-2 flex flex-wrap items-end gap-2 rounded border border-amber-200 bg-amber-50 p-2 text-sm">
                            <Select v-model="reviewReason" :options="reasonOptions" optionLabel="label" optionValue="value" :placeholder="ctrans('Reason')" :class="fieldFocusClass" />
                            <InputText v-model="reviewNote" maxlength="1000" :placeholder="ctrans('Notes (optional)')" class="min-w-0 flex-1" />
                            <button type="button" class="rounded bg-[--app-accent] px-3 py-1.5 text-[--app-accent-text] hover:bg-[--app-accent-strong] disabled:opacity-40" :disabled="!reviewReason || processing" @click="saveReview(session)">
                                {{ ctrans('Save') }}
                            </button>
                            <button type="button" class="text-xs text-gray-500 hover:underline" @click="reviewing = null">
                                {{ ctrans('Cancel') }}
                            </button>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
