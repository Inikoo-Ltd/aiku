<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 09 Aug 2026 15:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, ref, watch } from "vue"
import DatePicker from "primevue/datepicker"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserHardHat, faChevronDown, faChevronUp, faCheck, faTimes, faFilter, faClock, faExclamationTriangle, faHatWizard, faCog, faPlus } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faUserHardHat, faChevronDown, faChevronUp, faCheck, faTimes, faFilter, faClock, faExclamationTriangle, faHatWizard, faCog, faPlus)

interface TimeEditFields {
    time_edit: null | {
        reason: string | null
        note: string | null
        edited_by: string | null
        edited_at: string
        original_started_at: string | null
        original_ended_at: string | null
    }
    left_open_after_shift_at: string | null
    times_route: null | { name: string, parameters: object }
}

interface ArtisanSession extends TimeEditFields {
    id: number
    state: string
    task_name: string
    artefact_code: string
    job_order_reference: string
    started_at: string
    ended_at: string
    break_minutes: number
    quantity_made: number
    pack_size: number | null
    correct_route: null | { name: string, parameters: object }
    other_steps: { id: number, task_name: string, unit: string | null, pack_size: number | null, quantity_left: number }[]
    quantity_rejected: number
    earned: number | null
    units_per_hour: number | null
    standard_rate: number | null
    is_under_target: boolean
    is_hourly: boolean
    under_target_review: null | { reason: string, note: string | null, reviewed_by: string | null, reviewed_at: string }
    review_route: null | { name: string, parameters: object }
    void_route: null | { name: string, parameters: object }
    preparation_minutes: number | null
}

interface ArtisanJobStep {
    manufacture_task_id: number
    task_name: string
    hours: number
    quantity_made: number
    quantity_rejected: number
    earned: number | null
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
    sessions: (TimeEditFields & {
        id: number
        state: string
        activity: string
        reason: string | null
        reason_note: string | null
        reason_note_original: string | null
        job_order_reference: string | null
        started_at: string
        ended_at: string
        break_minutes: number
        pay: number | null
        moved_into: string | null
        undo_move_route: null | { name: string, parameters: object }
        void_route: null | { name: string, parameters: object }
    })[]
}

interface ArtisanJob {
    job_order_item_id: number
    job_order_reference: string
    artefact_code: string
    hours: number
    quantity_made: number
    quantity_rejected: number
    earned: number | null
    steps: ArtisanJobStep[]
}

interface ActivityTotal {
    hours: number
    number_sessions: number
}

type Props = {
    title: string
    pageHead: PageHeadingTypes
    period: { from: string, to: string }
    manufacture_task_id: number | null
    manufacture_tasks: { id: number, name: string }[]
    under_target: boolean
    time_edited: boolean
    time_edit_reasons: { value: string, label: string }[]
    left_open: { id: number, worker: string, task_name: string, job_order_reference: string | null, started_at: string, left_open_after_shift_at: string, paused_at: string | null, ended_at: string | null, status: 'paused' | 'running' | 'closed' }[]
    daily_hours_warnings: { count: number, route: { name: string, parameters: Record<string, string | number> } }
    artisan_pay_type: string | null
    artisan_pay_types: Record<string, string>
    pay_type_effective_from: string
    currency_code: string
    can_see_pay: boolean
    under_target_reasons: Record<string, string>
    non_productive_activities: { value: string, label: string }[]
    activity: string | null
    preparation_reason: string | null
    preparation_reasons: { id: number, name: string, is_other: boolean }[]
    preparation_reasons_route: null | { name: string, parameters: object }
    retired_preparation_reasons: { id: number, name: string }[]
    activity_totals: null | {
        total: ActivityTotal
        reasons: (ActivityTotal & { key: string, label: string, notes: (ActivityTotal & { label: string })[] })[]
        artisans: (ActivityTotal & { worker: string })[]
    }
    break_overrun: boolean
    break_overrun_tolerance_minutes: number
    break_overrun_tolerance_route: null | { name: string, parameters: Record<string, string> }
    short_preparation_minutes: number
    short_preparation_route: null | { name: string, parameters: Record<string, string> }
    breaks: { user_id: number, worker: string, date: string, number_breaks: number, times: { id: number, started_at: string, ended_at: string | null, planned_minutes: number, actual_minutes: number, overrun_minutes: number, is_overrun: boolean }[], planned_minutes: number, actual_minutes: number, overrun_minutes: number, number_overrun: number, is_open: boolean }[]
    break_issues: { id: number, worker: string, started_at: string, ended_at: string | null, planned_minutes: number, actual_minutes: number, overrun_minutes: number, left_open_after_shift_at: string | null, correct_route: null | { name: string, parameters: object } }[]
    artisans: {
        user_id: number
        worker: string
        artisan_pay_type: string | null
        pay_type_route: null | { name: string, parameters: object }
        pay_type_history: { changed_at: string, by: string | null, from: string | null, to: string | null, effective_from: string }[]
        is_guru: boolean
        guru_since: string | null
        guru_until: string | null
        guru_route: null | { name: string, parameters: object }
        number_sessions: number
        hours_worked: number
        quantity_made: number
        quantity_rejected: number
        earned: number | null
        under_target_open: number
        non_productive: NonProductiveSummary
        jobs: ArtisanJob[]
    }[]
}

const props = defineProps<Props>()

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
const payTypeFilter = ref<string | null>(props.artisan_pay_type)
const payTypeFilterOptions = computed(() => [{ value: null, label: ctrans('All pay types') }, ...Object.entries(props.artisan_pay_types).map(([value, label]) => ({ value, label }))])
const payTypeOptions = computed(() => Object.entries(props.artisan_pay_types).map(([value, label]) => ({ value, label })))
const activityFilter = ref<string | null>(props.activity)
const activityOptions = computed(() => [{ value: null, label: ctrans('All activities') }, { value: 'production', label: ctrans('Production') }, ...props.non_productive_activities])
const preparationReasonFilter = ref<string | null>(props.preparation_reason)
const preparationReasonOptions = computed(() => [
    { value: null, label: ctrans('All reasons') },
    ...props.preparation_reasons.map((reason) => ({ value: String(reason.id), label: reason.name })),
    ...props.retired_preparation_reasons.map((reason) => ({ value: String(reason.id), label: ctrans(':reason (removed)', { reason: reason.name }) })),
    { value: 'none', label: ctrans('No reason recorded') },
])
const editingReasons = ref<{ id: number | null, name: string }[] | null>(null)
const reasonsErrors = ref<Record<string, string>>({})

function openReasons() {
    editingReasons.value = props.preparation_reasons.filter((reason) => !reason.is_other).map((reason) => ({ id: reason.id, name: reason.name }))
    reasonsErrors.value = {}
}

function moveReason(index: number, offset: number) {
    const reasons = editingReasons.value
    if (!reasons || index + offset < 0 || index + offset >= reasons.length) return
    reasons.splice(index + offset, 0, reasons.splice(index, 1)[0])
}

function saveReasons() {
    if (!props.preparation_reasons_route || !editingReasons.value) return
    processing.value = true
    router.patch(
        route(props.preparation_reasons_route.name, props.preparation_reasons_route.parameters),
        { reasons: editingReasons.value },
        {
            preserveScroll: true,
            onSuccess: () => editingReasons.value = null,
            onError: (errors) => reasonsErrors.value = errors,
            onFinish: () => processing.value = false,
        }
    )
}
const underTargetOnly = ref(props.under_target)
const timeEditedOnly = ref(props.time_edited)
const breakOverrunOnly = ref(props.break_overrun)
const reviewing = ref<number | null>(null)
const reviewReason = ref<string | null>(null)
const reviewNote = ref('')
const expanded = ref<Set<number>>(new Set())
const nameSearch = ref('')
const expandedNonProductive = ref<Set<number>>(new Set())
function toggleNonProductive(userId: number) {
    if (expandedNonProductive.value.has(userId)) {
        expandedNonProductive.value.delete(userId)
    } else {
        expandedNonProductive.value.add(userId)
    }
}
const shownArtisans = computed(() => {
    const search = nameSearch.value.trim().toLocaleLowerCase()
    return search ? props.artisans.filter((artisan) => artisan.worker.toLocaleLowerCase().includes(search)) : props.artisans
})
const shownBreaks = computed(() => {
    const search = nameSearch.value.trim().toLocaleLowerCase()
    return search ? props.breaks.filter((row) => row.worker.toLocaleLowerCase().includes(search)) : props.breaks
})
const expandedBreakDays = ref<Set<string>>(new Set())
function toggleBreakDay(key: string) {
    if (expandedBreakDays.value.has(key)) {
        expandedBreakDays.value.delete(key)
    } else {
        expandedBreakDays.value.add(key)
    }
    expandedBreakDays.value = new Set(expandedBreakDays.value)
}
const processing = ref(false)

const correctingBreak = ref<Props['break_issues'][number] | null>(null)
const breakEnd = ref<Date | null>(null)
const breakEndErrors = ref<Record<string, string>>({})
function openBreakEnd(issue: Props['break_issues'][number]) {
    correctingBreak.value = issue
    breakEnd.value = null
    breakEndErrors.value = {}
}
function saveBreakEnd() {
    const issue = correctingBreak.value
    if (!issue?.correct_route || !breakEnd.value) return
    processing.value = true
    router.patch(
        route(issue.correct_route.name, issue.correct_route.parameters),
        { ended_at: breakEnd.value.toISOString() },
        {
            preserveScroll: true,
            onSuccess: () => correctingBreak.value = null,
            onError: (errors) => breakEndErrors.value = errors,
            onFinish: () => processing.value = false,
        }
    )
}

const editingBreakTolerance = ref(false)
const breakToleranceMinutes = ref<number | null>(props.break_overrun_tolerance_minutes)
const breakToleranceErrors = ref<Record<string, string>>({})
function openBreakTolerance() {
    breakToleranceMinutes.value = props.break_overrun_tolerance_minutes
    breakToleranceErrors.value = {}
    editingBreakTolerance.value = true
}
function saveBreakTolerance() {
    if (!props.break_overrun_tolerance_route) return
    processing.value = true
    router.post(
        route(props.break_overrun_tolerance_route.name, props.break_overrun_tolerance_route.parameters),
        { tolerance_minutes: breakToleranceMinutes.value },
        {
            preserveScroll: true,
            onSuccess: () => editingBreakTolerance.value = false,
            onError: (errors) => breakToleranceErrors.value = errors,
            onFinish: () => processing.value = false,
        }
    )
}

const editingShortPreparation = ref(false)
const shortPreparationMinutes = ref<number | null>(props.short_preparation_minutes)
const shortPreparationErrors = ref<Record<string, string>>({})
function openShortPreparation() {
    shortPreparationMinutes.value = props.short_preparation_minutes
    shortPreparationErrors.value = {}
    editingShortPreparation.value = true
}
function saveShortPreparation() {
    if (!props.short_preparation_route) return
    processing.value = true
    router.post(
        route(props.short_preparation_route.name, props.short_preparation_route.parameters),
        { minutes: shortPreparationMinutes.value },
        {
            preserveScroll: true,
            onSuccess: () => editingShortPreparation.value = false,
            onError: (errors) => shortPreparationErrors.value = errors,
            onFinish: () => processing.value = false,
        }
    )
}

function undoPreparationMove(session: { undo_move_route: null | { name: string, parameters: object } }) {
    if (!session.undo_move_route) return
    if (!window.confirm(ctrans('Keep this as Preparation instead of adding it to the job step?'))) return
    processing.value = true
    router.patch(
        route(session.undo_move_route.name, session.undo_move_route.parameters),
        {},
        { preserveScroll: true, onError: (errors) => window.alert(Object.values(errors).join('\n')), onFinish: () => processing.value = false }
    )
}

function applyPeriod() {
    router.get(
        window.location.pathname,
        { from: from.value, to: to.value, manufacture_task_id: manufactureTaskId.value || undefined, under_target: underTargetOnly.value ? 1 : undefined, time_edited: timeEditedOnly.value ? 1 : undefined, break_overrun: breakOverrunOnly.value ? 1 : undefined, artisan_pay_type: payTypeFilter.value || undefined, activity: activityFilter.value || undefined, preparation_reason: activityFilter.value === 'preparation' ? preparationReasonFilter.value || undefined : undefined },
        { preserveState: false }
    )
}

const breakdownArtisan = ref<Props['artisans'][number] | null>(null)
const settingsArtisan = ref<Props['artisans'][number] | null>(null)
const pendingPayType = ref<{ artisan: Props['artisans'][number], payType: string } | null>(null)
const payTypeToggleKey = ref(0)

function cancelPayType() {
    pendingPayType.value = null
    payTypeToggleKey.value++
}

function setPayType() {
    const pending = pendingPayType.value
    if (!pending?.artisan.pay_type_route) return
    processing.value = true
    router.patch(
        route(pending.artisan.pay_type_route.name, pending.artisan.pay_type_route.parameters),
        { artisan_pay_type: pending.payType },
        { preserveScroll: true, onFinish: () => { processing.value = false; pendingPayType.value = null; payTypeToggleKey.value++ } }
    )
}

const longDate = (iso: string) => fromIsoDate(iso).toLocaleDateString([], { weekday: "short", day: "numeric", month: "short", year: "numeric" })

const guruDates = ref<Record<number, { since: Date | null, until: Date | null }>>(
    Object.fromEntries(props.artisans.map((artisan) => [artisan.user_id, {
        since: artisan.guru_since ? fromIsoDate(artisan.guru_since) : null,
        until: artisan.guru_until ? fromIsoDate(artisan.guru_until) : null,
    }]))
)

function setGuru(artisan: { user_id: number, guru_route: null | { name: string, parameters: object } }, remove = false) {
    if (!artisan.guru_route) return
    const dates = guruDates.value[artisan.user_id]
    processing.value = true
    router.patch(
        route(artisan.guru_route.name, artisan.guru_route.parameters),
        {
            guru_since: !remove && dates.since ? toLocalIsoDate(dates.since) : null,
            guru_until: !remove && dates.until ? toLocalIsoDate(dates.until) : null,
        },
        { preserveScroll: true, preserveState: false, onFinish: () => processing.value = false }
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

const correcting = ref<number | null>(null)
const correctedQuantity = ref<number | null>(null)
const correctError = ref<string | null>(null)
const correctedStepId = ref<number | null>(null)
const correctReason = ref('')

function isRaising(session: ArtisanSession) {
    return movingTo(session) === null && correctedQuantity.value !== null && correctedQuantity.value > session.quantity_made
}

function movingTo(session: ArtisanSession) {
    return session.other_steps.find((step) => step.id === correctedStepId.value) ?? null
}

function correctedPackSize(session: ArtisanSession) {
    const step = movingTo(session)
    return step ? step.pack_size : session.pack_size
}

function canSaveCorrect(session: ArtisanSession) {
    if (correctedQuantity.value === null) return false
    if (movingTo(session) !== null || correctedQuantity.value < session.quantity_made) return true
    return isRaising(session) && correctReason.value.trim() !== ''
}

function openCorrect(session: ArtisanSession) {
    correcting.value = session.id
    correctedQuantity.value = null
    correctedStepId.value = null
    correctReason.value = ''
    correctError.value = null
}

function saveCorrect(session: ArtisanSession) {
    if (!session.correct_route || correctedQuantity.value === null) return
    processing.value = true
    router.patch(
        route(session.correct_route.name, session.correct_route.parameters),
        { quantity_made: correctedQuantity.value, job_order_item_task_id: correctedStepId.value, reason: isRaising(session) ? correctReason.value : null },
        {
            preserveScroll: true,
            onSuccess: () => correcting.value = null,
            onError: (errors) => correctError.value = errors.quantity_made ?? errors.job_order_item_task_id ?? null,
            onFinish: () => processing.value = false,
        }
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

interface EditableSession extends TimeEditFields {
    id: number
    started_at: string
    ended_at: string
    task_name?: string
    activity?: string
}

const editingTimes = ref<EditableSession | null>(null)
const editWorker = ref("")
const editStart = ref<Date | null>(null)
const editEnd = ref<Date | null>(null)
const editReason = ref<string | null>(null)
const editNote = ref("")
const editErrors = ref<Record<string, string>>({})
const outsideShiftConfirmed = ref(false)
const impossibleRateConfirmed = ref(false)
const warningConfirmed = computed(() => outsideShiftConfirmed.value || impossibleRateConfirmed.value)
watch([editStart, editEnd], () => {
    outsideShiftConfirmed.value = false
    impossibleRateConfirmed.value = false
})
const canSaveTimes = computed(() => !!editStart.value && !!editEnd.value && !!editReason.value && (editReason.value !== "other" || editNote.value.trim() !== "") && editEnd.value > editStart.value)

function openTimes(session: EditableSession, worker: string) {
    editingTimes.value = session
    editWorker.value = worker
    editStart.value = new Date(session.started_at)
    editEnd.value = new Date(session.ended_at)
    editReason.value = null
    editNote.value = ""
    editErrors.value = {}
    outsideShiftConfirmed.value = false
    impossibleRateConfirmed.value = false
}

function saveTimes() {
    const session = editingTimes.value
    if (!session?.times_route || !canSaveTimes.value) return
    processing.value = true
    router.patch(
        route(session.times_route.name, session.times_route.parameters),
        {
            started_at: editStart.value?.toISOString(),
            ended_at: editEnd.value?.toISOString(),
            reason: editReason.value,
            note: editNote.value || null,
            outside_shift_confirmed: outsideShiftConfirmed.value,
            impossible_rate_confirmed: impossibleRateConfirmed.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => editingTimes.value = null,
            onError: (errors) => {
                editErrors.value = errors
                outsideShiftConfirmed.value ||= !!errors.outside_shift
                impossibleRateConfirmed.value ||= !!errors.impossible_rate
            },
            onFinish: () => processing.value = false,
        }
    )
}

const hourMinute = (iso: string | null) => iso ? new Date(iso).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", hourCycle: "h23" }) : "…"

function clockTime(iso: string | null) {
    return iso ? new Date(iso).toLocaleString([], { weekday: "short", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit", hourCycle: "h23" }) : "—"
}

const clockHours = (hours: number | string) => {
    const minutes = Math.round(Number(hours) * 60)
    return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, "0")}`
}

function sessionHours(session: { started_at: string, ended_at: string, break_minutes: number }) {
    const seconds = Math.max(0, (new Date(session.ended_at).getTime() - new Date(session.started_at).getTime()) / 1000 - session.break_minutes * 60)
    return clockHours(seconds / 3600)
}

const locale = useLocaleStore()
const money = (amount: number | null) => amount === null ? '' : locale.currencyFormat(props.currency_code, amount)
const quantityLabel = (quantity: number | string) => Number(quantity).toLocaleString(undefined, { maximumFractionDigits: 2 })

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-4">
        <div class="mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Name') }}</label>
                <InputText v-model="nameSearch" :placeholder="ctrans('Search artisan')" class="w-48" :class="fieldFocusClass" :aria-label="ctrans('Search artisan')" />
            </div>
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
                <Select v-model="manufactureTaskId" :options="stepOptions" optionLabel="name" optionValue="id" filter :placeholder="ctrans('All steps')" class="min-w-48" :class="fieldFocusClass" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Pay type') }}</label>
                <Select v-model="payTypeFilter" :options="payTypeFilterOptions" optionLabel="label" optionValue="value" :placeholder="ctrans('All pay types')" class="min-w-40" :class="fieldFocusClass" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Activity') }}</label>
                <Select v-model="activityFilter" :options="activityOptions" optionLabel="label" optionValue="value" :placeholder="ctrans('All activities')" class="min-w-40" :class="fieldFocusClass" />
            </div>
            <div v-if="activityFilter === 'preparation'">
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Reason') }}</label>
                <Select v-model="preparationReasonFilter" :options="preparationReasonOptions" optionLabel="label" optionValue="value" :placeholder="ctrans('All reasons')" class="min-w-56" :class="fieldFocusClass" />
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-600 whitespace-nowrap">
                <Checkbox v-model="underTargetOnly" binary />
                {{ ctrans('Under target only') }}
            </label>
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-600 whitespace-nowrap">
                <Checkbox v-model="timeEditedOnly" binary />
                {{ ctrans('Edited times only') }}
            </label>
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-600 whitespace-nowrap">
                <Checkbox v-model="breakOverrunOnly" binary />
                {{ ctrans('Breaks over plan only') }}
            </label>
            <Button type="tertiary" :label="ctrans('Apply')" @click="applyPeriod">
                <template #icon><FontAwesomeIcon :icon="['fal', 'filter']" fixed-width aria-hidden="true" /></template>
            </Button>
            <Button v-if="preparation_reasons_route" type="tertiary" :label="ctrans('Preparation reasons')" class="ml-auto" @click="openReasons">
                <template #icon><FontAwesomeIcon :icon="['fal', 'cog']" fixed-width aria-hidden="true" /></template>
            </Button>
            <Button v-if="short_preparation_route" type="tertiary" :label="ctrans('Short preparation: :minutes min', { minutes: short_preparation_minutes })" :class="preparation_reasons_route ? '' : 'ml-auto'" @click="openShortPreparation">
                <template #icon><FontAwesomeIcon :icon="['fal', 'cog']" fixed-width aria-hidden="true" /></template>
            </Button>
        </div>

        <div v-if="activity_totals" class="mb-6 flex flex-wrap gap-10">
            <table v-if="activity === 'preparation'" class="w-auto text-sm tabular-nums">
                <thead class="whitespace-nowrap">
                    <tr class="border-b border-gray-200 text-xs text-gray-500">
                        <th class="py-2 pr-6 text-left font-medium">{{ ctrans('Reason') }}</th>
                        <th class="py-2 px-3 text-right font-medium">{{ ctrans('Time (h:mm)') }}</th>
                        <th class="py-2 pl-3 text-right font-medium">{{ ctrans('Sessions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="reason in activity_totals.reasons" :key="reason.key">
                        <tr class="border-b border-gray-100 text-gray-700">
                            <td class="py-1.5 pr-6">{{ reason.label }}</td>
                            <td class="py-1.5 px-3 text-right">{{ clockHours(reason.hours) }}</td>
                            <td class="py-1.5 pl-3 text-right">{{ reason.number_sessions }}</td>
                        </tr>
                        <tr v-for="note in reason.notes" :key="reason.key + note.label" class="text-xs text-gray-500">
                            <td class="py-1 pl-4 pr-6">{{ note.label }}</td>
                            <td class="py-1 px-3 text-right">{{ clockHours(note.hours) }}</td>
                            <td class="py-1 pl-3 text-right">{{ note.number_sessions }}</td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <table class="w-auto text-sm tabular-nums">
                <thead class="whitespace-nowrap">
                    <tr class="border-b border-gray-200 text-xs text-gray-500">
                        <th class="py-2 pr-6 text-left font-medium">{{ ctrans('Artisan') }}</th>
                        <th class="py-2 px-3 text-right font-medium">{{ ctrans('Time (h:mm)') }}</th>
                        <th class="py-2 pl-3 text-right font-medium">{{ ctrans('Sessions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in activity_totals.artisans" :key="row.worker" class="border-b border-gray-100 text-gray-700">
                        <td class="py-1.5 pr-6 whitespace-nowrap">{{ row.worker }}</td>
                        <td class="py-1.5 px-3 text-right">{{ clockHours(row.hours) }}</td>
                        <td class="py-1.5 pl-3 text-right">{{ row.number_sessions }}</td>
                    </tr>
                    <tr class="font-semibold text-gray-800">
                        <td class="py-1.5 pr-6">{{ ctrans('Total') }}</td>
                        <td class="py-1.5 px-3 text-right">{{ clockHours(activity_totals.total.hours) }}</td>
                        <td class="py-1.5 pl-3 text-right">{{ activity_totals.total.number_sessions }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="daily_hours_warnings.count" class="mb-4 flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900">
            <FontAwesomeIcon :icon="['fal', 'exclamation-triangle']" fixed-width aria-hidden="true" />
            <span>{{ ctrans(':count artisan days over or under the shift to deal with', { count: daily_hours_warnings.count }) }}</span>
            <Link :href="route(daily_hours_warnings.route.name, daily_hours_warnings.route.parameters)" class="font-medium underline">{{ ctrans('Daily hours') }}</Link>
        </div>

        <div v-if="left_open.length" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <div class="flex items-center gap-2 font-semibold">
                <FontAwesomeIcon :icon="['fal', 'exclamation-triangle']" fixed-width aria-hidden="true" />
                {{ ctrans('Paused or left running at the end of the shift, not checked yet') }}
            </div>
            <div class="mt-1 text-xs text-amber-800">{{ ctrans('When the operator clocks back in the tablet asks if they finished before the end of the shift. If it is not answered, or the job ran on without a clock-out, set the real times here with Edit times.') }}</div>
            <div v-for="open in left_open" :key="open.id" class="mt-1 flex flex-wrap gap-x-3 tabular-nums">
                <span class="font-medium">{{ open.worker }}</span>
                <span>{{ open.task_name }}<template v-if="open.job_order_reference"> · {{ open.job_order_reference }}</template></span>
                <span>{{ ctrans('started :time', { time: clockTime(open.started_at) }) }}</span>
                <span>{{ ctrans('shift ended :time', { time: clockTime(open.left_open_after_shift_at) }) }}</span>
                <span v-if="open.status === 'paused'">{{ ctrans('paused since :time', { time: clockTime(open.paused_at) }) }}</span>
                <span v-else-if="open.status === 'closed'">{{ ctrans('closed :time', { time: clockTime(open.ended_at) }) }}</span>
                <span v-else>{{ ctrans('still running') }}</span>
            </div>
        </div>

        <div v-if="break_issues.length" class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
            <div class="flex items-center gap-2 font-semibold">
                <FontAwesomeIcon :icon="['fal', 'exclamation-triangle']" fixed-width aria-hidden="true" />
                {{ ctrans('Breaks running over, or left open at the end of the shift') }}
            </div>
            <div class="mt-1 text-xs text-red-800">{{ ctrans('A break left open after the shift needs its real end set here. Clockings are fixed in HR.') }}</div>
            <div v-for="issue in break_issues" :key="issue.id" class="mt-1 flex flex-wrap items-center gap-x-3 tabular-nums">
                <span class="font-medium">{{ issue.worker }}</span>
                <span>{{ ctrans('started :time', { time: clockTime(issue.started_at) }) }}</span>
                <span>{{ ctrans(':actual of :planned min', { actual: issue.actual_minutes, planned: issue.planned_minutes }) }}</span>
                <span v-if="issue.overrun_minutes" class="font-medium">{{ ctrans(':minutes min over', { minutes: issue.overrun_minutes }) }}</span>
                <span v-if="issue.left_open_after_shift_at">{{ ctrans('shift ended :time', { time: clockTime(issue.left_open_after_shift_at) }) }}</span>
                <span v-if="issue.ended_at">{{ ctrans('finished :time', { time: clockTime(issue.ended_at) }) }}</span>
                <span v-else>{{ ctrans('still on break') }}</span>
                <button v-if="issue.correct_route" type="button" class="text-xs font-medium text-gray-700 hover:underline" @click="openBreakEnd(issue)">{{ ctrans('Set end') }}</button>
            </div>
        </div>

        <div v-if="!artisans.length" class="text-gray-400 text-center py-16 border border-dashed border-gray-200 rounded-lg">
            {{ ctrans('No finished work in this period') }}
        </div>

        <div v-else-if="!shownArtisans.length" class="text-gray-400 text-center py-16 border border-dashed border-gray-200 rounded-lg">
            {{ ctrans('No artisan matches :name', { name: nameSearch }) }}
        </div>

        <div v-if="shownArtisans.length" class="overflow-x-auto">
        <table class="w-full text-sm tabular-nums">
            <thead>
                <tr class="border-b border-gray-200 text-xs text-gray-500">
                    <th class="py-2 pr-4 text-left font-medium whitespace-nowrap">{{ ctrans('Artisan') }}</th>
                    <th class="py-2 px-3 text-right font-medium whitespace-nowrap">{{ ctrans('Tasks') }}</th>
                    <th class="py-2 px-3 text-right font-medium whitespace-nowrap">{{ ctrans('Time (h:mm)') }}</th>
                    <th class="py-2 px-3 text-right font-medium whitespace-nowrap">{{ ctrans('Units') }}</th>
                    <th class="py-2 px-3 text-right font-medium whitespace-nowrap">{{ ctrans('Rejected') }}</th>
                    <th class="py-2 px-3 text-right font-medium whitespace-nowrap">{{ ctrans('Per hour / target') }}</th>
                    <th class="py-2 px-3 text-left font-medium whitespace-nowrap">{{ ctrans('Other work') }}</th>
                    <th class="py-2 px-3 text-right font-medium whitespace-nowrap">{{ ctrans('Under target') }}</th>
                    <th v-if="can_see_pay" class="py-2 px-3 text-right font-medium whitespace-nowrap">{{ ctrans('Earned') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody v-for="artisan in shownArtisans" :key="artisan.user_id" class="border-b border-gray-200">
                <tr class="cursor-pointer hover:bg-gray-50" @click="toggle(artisan.user_id)">
                    <td class="py-2 pr-4">
                        <div class="flex items-center gap-2 font-semibold whitespace-nowrap">
                            {{ artisan.worker }}
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ artisan_pay_types[artisan.artisan_pay_type ?? 'piece_rate'] }}</span>
                            <span v-if="artisan.is_guru" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600"><FontAwesomeIcon :icon="['fal', 'hat-wizard']" fixed-width aria-hidden="true" /> {{ ctrans('Guru') }}</span>
                            <button v-if="artisan.pay_type_route || artisan.guru_route || artisan.pay_type_history.length" type="button"
                                v-tooltip="ctrans('Pay type and Guru')" :aria-label="ctrans('Pay type and Guru')"
                                class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                                @click.stop="settingsArtisan = artisan">
                                <FontAwesomeIcon :icon="['fal', 'cog']" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                    </td>
                    <td class="py-2 px-3 text-right text-gray-600">{{ artisan.number_sessions }}</td>
                    <td class="py-2 px-3 text-right text-gray-600">{{ clockHours(artisan.hours_worked) }}</td>
                    <td class="py-2 px-3 text-right text-gray-600">{{ quantityLabel(artisan.quantity_made) }}</td>
                    <td class="py-2 px-3 text-right text-red-500">{{ artisan.quantity_rejected ? quantityLabel(artisan.quantity_rejected) : '' }}</td>
                    <td></td>
                    <td class="py-2 px-3 text-gray-500 whitespace-nowrap">{{ artisan.non_productive.hours ? activitySummary(artisan.non_productive) : '' }}</td>
                    <td class="py-2 px-3 text-right">
                        <span v-if="artisan.under_target_open" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">{{ artisan.under_target_open }}</span>
                    </td>
                    <td v-if="can_see_pay" class="py-2 px-3 text-right font-semibold text-gray-800">{{ money(artisan.earned) }}</td>
                    <td class="py-2 text-right">
                        <button type="button" class="text-gray-400" :aria-expanded="expanded.has(artisan.user_id)" :aria-label="artisan.worker" @click.stop="toggle(artisan.user_id)">
                            <FontAwesomeIcon :icon="['fal', expanded.has(artisan.user_id) ? 'chevron-up' : 'chevron-down']" fixed-width aria-hidden="true" />
                        </button>
                    </td>
                </tr>

                <template v-if="expanded.has(artisan.user_id)">

                    <template v-if="artisan.non_productive.sessions.length">
                        <tr class="cursor-pointer border-t border-gray-100 font-medium text-gray-700 hover:bg-gray-50" @click="toggleNonProductive(artisan.user_id)">
                            <td class="py-1.5 pl-4 pr-4 whitespace-nowrap">
                                <FontAwesomeIcon :icon="['fal', expandedNonProductive.has(artisan.user_id) ? 'chevron-up' : 'chevron-down']" fixed-width class="mr-1 text-gray-400" aria-hidden="true" />
                                <span v-tooltip="ctrans('Preparation, cleaning and maintenance: paid by the hour, not in units per hour')">{{ ctrans('Other work') }}</span>
                                <button type="button" class="ml-3 text-xs font-medium text-gray-700 underline hover:text-gray-900" @click.stop="breakdownArtisan = artisan">{{ ctrans('By day') }}</button>
                            </td>
                            <td></td>
                            <td class="py-1.5 px-3 text-right">{{ clockHours(artisan.non_productive.hours) }}</td>
                            <td colspan="5"></td>
                            <td v-if="can_see_pay" class="py-1.5 px-3 text-right">{{ artisan.non_productive.pay === null ? '—' : money(artisan.non_productive.pay) }}</td>
                            <td></td>
                        </tr>
                        <template v-for="session in expandedNonProductive.has(artisan.user_id) ? artisan.non_productive.sessions : []" :key="session.id">
                            <tr class="text-gray-600" :class="session.state == 'voided' && !session.moved_into ? 'opacity-40 line-through' : ''">
                                <td class="py-1.5 pl-8 pr-4">
                                    <div class="flex items-center gap-2 whitespace-nowrap text-gray-400">
                                        {{ useFormatTime(session.ended_at) }}
<span class="text-gray-600">{{ hourMinute(session.started_at) }} – {{ hourMinute(session.ended_at) }}</span>
                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ session.activity }}</span>
                                        <span v-if="session.reason" class="text-xs text-gray-600">{{ session.reason }}<template v-if="session.reason_note">: <span v-tooltip="session.reason_note_original ? ctrans('Typed as: :text', { text: session.reason_note_original }) : undefined" :class="session.reason_note_original ? 'cursor-help underline decoration-dotted' : ''">{{ session.reason_note }}</span></template></span>
                                        <span class="text-xs">{{ session.job_order_reference ?? ctrans('General') }}</span>
                                        <span v-if="session.break_minutes" class="text-xs">{{ ctrans(':minutes m break', { minutes: session.break_minutes }) }}</span>
                                        <span v-if="session.time_edit" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ ctrans('Times edited') }}</span>
                                        <span v-if="session.moved_into" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600" v-tooltip="ctrans('Shorter than :minutes min, so its time counts in the job step started straight after it', { minutes: short_preparation_minutes })">{{ ctrans('Added to :step', { step: session.moved_into }) }}</span>
                                    </div>
                                </td>
                                <td></td>
                                <td class="py-1.5 px-3 text-right">{{ sessionHours(session) }}</td>
                                <td colspan="5"></td>
                                <td v-if="can_see_pay" class="py-1.5 px-3 text-right">{{ session.pay === null ? '—' : money(session.pay) }}</td>
                                <td class="py-1.5 pl-3 text-right whitespace-nowrap">
                                    <button v-if="session.times_route" type="button" class="text-xs font-medium text-gray-700 hover:underline" @click="openTimes(session, artisan.worker)">{{ ctrans('Edit times') }}</button>
                                    <button v-if="session.undo_move_route" type="button" class="text-xs font-medium text-gray-700 hover:underline disabled:opacity-40" :disabled="processing" @click="undoPreparationMove(session)">{{ ctrans('Keep as preparation') }}</button>
                                    <button v-if="session.void_route" type="button" class="ml-3 text-xs text-red-600 hover:underline disabled:opacity-40" :disabled="processing" @click="voidSession(session)">{{ ctrans('Void') }}</button>
                                </td>
                            </tr>
                            <tr v-if="session.time_edit">
                                <td colspan="10" class="pl-12 pb-1 text-xs text-gray-500">
                                    {{ ctrans('Times edited (:reason)', { reason: session.time_edit.reason ?? '' }) }}:
                                    {{ ctrans('first recorded :from to :to', { from: clockTime(session.time_edit.original_started_at), to: clockTime(session.time_edit.original_ended_at) }) }}
                                    <template v-if="session.time_edit.note"> · {{ session.time_edit.note }}</template>
                                    — {{ session.time_edit.edited_by ?? ctrans('automatic') }}, {{ clockTime(session.time_edit.edited_at) }}
                                </td>
                            </tr>
                        </template>
                    </template>

                    <template v-for="job in artisan.jobs" :key="job.job_order_item_id">
                        <tr class="border-t border-gray-100 font-medium text-gray-700">
                            <td class="py-1.5 pl-4 pr-4 whitespace-nowrap">{{ job.artefact_code }} · {{ job.job_order_reference }}</td>
                            <td></td>
                            <td class="py-1.5 px-3 text-right">{{ clockHours(job.hours) }}</td>
                            <td class="py-1.5 px-3 text-right">{{ quantityLabel(job.quantity_made) }}</td>
                            <td class="py-1.5 px-3 text-right text-red-500">{{ job.quantity_rejected ? quantityLabel(job.quantity_rejected) : '' }}</td>
                            <td colspan="3"></td>
                            <td v-if="can_see_pay" class="py-1.5 px-3 text-right">{{ money(job.earned) }}</td>
                            <td></td>
                        </tr>
                        <template v-for="step in job.steps" :key="step.manufacture_task_id">
                            <tr class="text-xs text-gray-500">
                                <td class="py-1 pl-8 pr-4"><span class="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-gray-600">{{ step.task_name }}</span></td>
                                <td></td>
                                <td class="py-1 px-3 text-right">{{ clockHours(step.hours) }}</td>
                                <td class="py-1 px-3 text-right">{{ quantityLabel(step.quantity_made) }}</td>
                                <td class="py-1 px-3 text-right text-red-500">{{ step.quantity_rejected ? quantityLabel(step.quantity_rejected) : '' }}</td>
                                <td colspan="3"></td>
                                <td v-if="can_see_pay" class="py-1 px-3 text-right">{{ money(step.earned) }}</td>
                                <td></td>
                            </tr>
                            <template v-for="session in step.sessions" :key="session.id">
                                <tr class="text-gray-700" :class="session.state == 'voided' ? 'opacity-40 line-through' : ''">
                                    <td class="py-1.5 pl-12 pr-4">
                                        <div class="flex items-center gap-2 whitespace-nowrap text-gray-400">
                                            {{ useFormatTime(session.ended_at) }}
<span class="text-gray-600">{{ hourMinute(session.started_at) }} – {{ hourMinute(session.ended_at) }}</span>
                                            <span v-if="session.break_minutes" class="text-xs">{{ ctrans(':minutes m break', { minutes: session.break_minutes }) }}</span>
                                            <span v-if="session.is_hourly" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ artisan_pay_types.hourly }}</span>
                                            <span v-if="session.preparation_minutes !== null" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ ctrans('Includes :minutes min preparation', { minutes: session.preparation_minutes }) }}</span>
                                            <span v-if="session.time_edit" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ ctrans('Times edited') }}</span>
                                            <span v-else-if="session.left_open_after_shift_at" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">{{ ctrans('Left running after shift') }}</span>
                                        </div>
                                    </td>
                                    <td></td>
                                    <td class="py-1.5 px-3 text-right text-gray-500">{{ sessionHours(session) }}</td>
                                    <td class="py-1.5 px-3 text-right">{{ quantityLabel(session.quantity_made) }}<span v-if="session.pack_size" class="text-xs text-gray-400"> × {{ session.pack_size }}</span></td>
                                    <td class="py-1.5 px-3 text-right text-red-500">{{ session.quantity_rejected ? quantityLabel(session.quantity_rejected) : '' }}</td>
                                    <td class="py-1.5 px-3 text-right whitespace-nowrap" :class="session.is_under_target ? 'text-amber-700' : 'text-gray-400'">
                                        <template v-if="session.standard_rate !== null">{{ session.units_per_hour ?? 0 }} / {{ session.standard_rate }}</template>
                                    </td>
                                    <td></td>
                                    <td class="py-1.5 px-3 text-right">
                                        <span v-if="session.is_under_target"
                                            class="rounded px-1.5 py-0.5 text-xs font-medium whitespace-nowrap"
                                            :class="session.under_target_review ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-800'">
                                            {{ session.under_target_review ? session.under_target_review.reason : ctrans('Action required') }}
                                        </span>
                                    </td>
                                    <td v-if="can_see_pay" class="py-1.5 px-3 text-right">{{ money(session.earned) }}</td>
                                    <td class="py-1.5 pl-3 text-right whitespace-nowrap">
                                        <button v-if="session.review_route && reviewing !== session.id" type="button" class="text-xs font-medium text-gray-700 hover:underline" @click="openReview(session)">{{ session.under_target_review ? ctrans('Change reason') : ctrans('Log reason') }}</button>
                                        <button v-if="session.times_route" type="button" class="ml-3 text-xs font-medium text-gray-700 hover:underline" @click="openTimes(session, artisan.worker)">{{ ctrans('Edit times') }}</button>
                                        <button v-if="session.correct_route && correcting !== session.id" type="button" v-tooltip="session.other_steps.length ? ctrans('Correct quantity or step') : ctrans('Correct quantity')" class="ml-3 text-xs font-medium text-gray-700 hover:underline" @click="openCorrect(session)">{{ ctrans('Correct') }}</button>
                                        <button v-if="session.void_route" type="button" class="ml-3 text-xs text-red-600 hover:underline disabled:opacity-40" :disabled="processing" @click="voidSession(session)">{{ ctrans('Void') }}</button>
                                    </td>
                                </tr>
                                <tr v-if="session.time_edit">
                                    <td colspan="10" class="pl-12 pb-1 text-xs text-gray-500">
                                        {{ ctrans('Times edited (:reason)', { reason: session.time_edit.reason ?? '' }) }}:
                                        {{ ctrans('first recorded :from to :to', { from: clockTime(session.time_edit.original_started_at), to: clockTime(session.time_edit.original_ended_at) }) }}
                                        <template v-if="session.time_edit.note"> · {{ session.time_edit.note }}</template>
                                        — {{ session.time_edit.edited_by ?? ctrans('automatic') }}, {{ clockTime(session.time_edit.edited_at) }}
                                    </td>
                                </tr>
                                <tr v-if="session.under_target_review?.note && reviewing !== session.id">
                                    <td colspan="10" class="pl-12 pb-1 text-xs text-gray-500">
                                        {{ session.under_target_review.note }}
                                        <span v-if="session.under_target_review.reviewed_by">— {{ session.under_target_review.reviewed_by }}</span>
                                    </td>
                                </tr>
                                <tr v-if="correcting === session.id || reviewing === session.id">
                                    <td colspan="10" class="pl-12 pr-2">
                                        <div v-if="correcting === session.id" class="mb-2 rounded border border-amber-200 bg-amber-50 p-2 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span>{{ ctrans(':code · :step was entered as :quantity', { code: session.artefact_code, step: session.task_name, quantity: session.quantity_made }) }}<template v-if="session.pack_size"> {{ ctrans('packs of :size', { size: session.pack_size }) }}</template>.</span>
                                <template v-if="session.other_steps.length">
                                    <span>{{ ctrans('Step really worked:') }}</span>
                                    <Select v-model="correctedStepId" :options="session.other_steps" optionLabel="task_name" optionValue="id" showClear :placeholder="session.task_name" :class="fieldFocusClass" />
                                </template>
                                <span>{{ correctedPackSize(session) ? ctrans('Whole packs really made:') : ctrans('Really made:') }}</span>
                                <InputNumber v-model="correctedQuantity" :min="0" :max="movingTo(session) && !movingTo(session)?.unit ? movingTo(session)?.quantity_left : undefined" :maxFractionDigits="correctedPackSize(session) ? 0 : 3" :suffix="movingTo(session)?.unit ? ' ' + movingTo(session)?.unit : undefined" inputClass="w-24" :class="fieldFocusClass" />
                                <span v-if="correctedPackSize(session) && correctedQuantity !== null" class="font-medium text-amber-800">= {{ (correctedQuantity * (correctedPackSize(session) ?? 1)).toLocaleString() }} × {{ session.artefact_code }}</span>
                                <InputText v-if="isRaising(session)" v-model="correctReason" maxlength="500" :placeholder="ctrans('Why it goes up')" class="min-w-0 flex-1" :class="fieldFocusClass" />
                                <Button type="tertiary" :label="ctrans('Save correction')" :loading="processing" :disabled="!canSaveCorrect(session)" @click="saveCorrect(session)">
                                    <template #icon><FontAwesomeIcon :icon="['fal', 'check']" class="text-green-600" fixed-width aria-hidden="true" /></template>
                                </Button>
                                <Button type="tertiary" :label="ctrans('Cancel')" @click="correcting = null">
                                    <template #icon><FontAwesomeIcon :icon="['fal', 'times']" class="text-red-500" fixed-width aria-hidden="true" /></template>
                                </Button>
                            </div>
                            <div v-if="movingTo(session)" class="mt-1 text-xs text-gray-600">{{ ctrans('The entry moves to :to with what was made there, :from goes back to what is left to do, and the pay for this entry is worked out again as :to.', { to: movingTo(session)?.task_name ?? '', from: session.task_name }) }}</div>
                            <div v-else-if="isRaising(session)" class="mt-1 text-xs text-gray-600">{{ ctrans('The entry goes up to what was really made, as long as the step has that much left to do, and the pay for this entry is worked out again.') }}</div>
                            <div v-else class="mt-1 text-xs text-gray-600">{{ ctrans('The job goes back to what was really made, any authorised overproduction is reduced with it, and the pay for this entry is worked out again.') }}</div>
                            <div v-if="correctError" class="mt-1 text-red-700">{{ correctError }}</div>
                        </div>
                                        <div v-if="reviewing === session.id" class="mb-2 flex flex-wrap items-end gap-2 rounded border border-amber-200 bg-amber-50 p-2 text-sm">
                            <Select v-model="reviewReason" :options="reasonOptions" optionLabel="label" optionValue="value" :placeholder="ctrans('Reason')" :class="fieldFocusClass" />
                            <InputText v-model="reviewNote" maxlength="1000" :placeholder="ctrans('Notes (optional)')" class="min-w-0 flex-1" />
                            <Button type="tertiary" :label="ctrans('Save')" :disabled="!reviewReason || processing" @click="saveReview(session)">
                                <template #icon><FontAwesomeIcon :icon="['fal', 'check']" class="text-green-600" fixed-width aria-hidden="true" /></template>
                            </Button>
                            <button type="button" class="text-xs text-gray-500 hover:underline" @click="reviewing = null">
                                {{ ctrans('Cancel') }}
                            </button>
                        </div>
                                    </td>
                                </tr>
                            </template>
                        </template>
                    </template>
                </template>
            </tbody>
        </table>
        </div>

        <div class="mt-10">
            <div class="mb-2 flex items-center gap-3">
                <h2 class="text-base font-semibold text-gray-800">{{ ctrans('Breaks') }}</h2>
                <span class="text-xs text-gray-500">{{ ctrans('Overrun when more than :minutes min over the planned break', { minutes: break_overrun_tolerance_minutes }) }}</span>
                <button v-if="break_overrun_tolerance_route" type="button" class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" v-tooltip="ctrans('Break overrun')" :aria-label="ctrans('Break overrun')" @click="openBreakTolerance">
                    <FontAwesomeIcon :icon="['fal', 'cog']" fixed-width aria-hidden="true" />
                </button>
            </div>
            <div v-if="!shownBreaks.length" class="text-gray-400 text-center py-8 border border-dashed border-gray-200 rounded-lg">
                {{ break_overrun ? ctrans('No break over plan in this period') : ctrans('No breaks in this period') }}
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-auto text-sm tabular-nums">
                    <thead class="whitespace-nowrap">
                        <tr class="border-b border-gray-200 text-xs text-gray-500">
                            <th class="py-2 pr-6 text-left font-medium">{{ ctrans('Day') }}</th>
                            <th class="py-2 pr-6 text-left font-medium">{{ ctrans('Artisan') }}</th>
                            <th class="py-2 px-3 text-right font-medium">{{ ctrans('Breaks') }}</th>
                            <th class="py-2 px-3 text-left font-medium">{{ ctrans('Times') }}</th>
                            <th class="py-2 px-3 text-right font-medium">{{ ctrans('Planned (min)') }}</th>
                            <th class="py-2 px-3 text-right font-medium">{{ ctrans('Actual (min)') }}</th>
                            <th class="py-2 px-3 text-right font-medium">{{ ctrans('Overrun (min)') }}</th>
                            <th class="py-2 pl-3 text-left font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in shownBreaks" :key="row.user_id + row.date">
                            <tr class="cursor-pointer border-b border-gray-100 text-gray-700 hover:bg-gray-50" @click="toggleBreakDay(row.user_id + row.date)">
                                <td class="py-1.5 pr-6 whitespace-nowrap">
                                    <FontAwesomeIcon :icon="['fal', expandedBreakDays.has(row.user_id + row.date) ? 'chevron-up' : 'chevron-down']" fixed-width class="mr-1 text-gray-400" aria-hidden="true" />
                                    {{ longDate(row.date) }}
                                </td>
                                <td class="py-1.5 pr-6 whitespace-nowrap">{{ row.worker }}</td>
                                <td class="py-1.5 px-3 text-right">{{ row.number_breaks }}</td>
                                <td class="py-1.5 px-3"></td>
                                <td class="py-1.5 px-3 text-right">{{ row.planned_minutes }}</td>
                                <td class="py-1.5 px-3 text-right">{{ row.actual_minutes }}</td>
                                <td class="py-1.5 px-3 text-right" :class="row.number_overrun ? 'font-medium text-red-700' : 'text-gray-400'">{{ row.overrun_minutes }}</td>
                                <td class="py-1.5 pl-3 whitespace-nowrap">
                                    <span v-if="row.number_overrun" class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800">{{ ctransChoice(':count overrun|:count overruns', row.number_overrun, { count: row.number_overrun }) }}</span>
                                    <span v-if="row.is_open" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">{{ ctrans('On break') }}</span>
                                </td>
                            </tr>
                            <template v-if="expandedBreakDays.has(row.user_id + row.date)">
                                <tr v-for="time in row.times" :key="time.id" class="border-b border-gray-50 text-xs" :class="time.is_overrun ? 'bg-red-50 text-red-800' : 'text-gray-500'">
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td class="py-1 px-3 whitespace-nowrap">
                                        <span v-if="time.ended_at">{{ hourMinute(time.started_at) }} – {{ hourMinute(time.ended_at) }}</span>
                                        <span v-else class="font-medium text-amber-800">{{ ctrans('On break since :time (:minutes min)', { time: hourMinute(time.started_at), minutes: time.actual_minutes }) }}</span>
                                    </td>
                                    <td class="py-1 px-3 text-right">{{ time.planned_minutes }}</td>
                                    <td class="py-1 px-3 text-right">{{ time.actual_minutes }}</td>
                                    <td class="py-1 px-3 text-right" :class="time.is_overrun ? 'font-medium' : ''">{{ time.overrun_minutes }}</td>
                                    <td></td>
                                </tr>
                            </template>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog :visible="!!editingReasons" modal :closable="!processing" :header="ctrans('Preparation reasons')" :style="{ width: '36rem' }" @update:visible="(visible: boolean) => { if (!visible && !processing) editingReasons = null }">
            <div class="text-xs text-gray-500">{{ ctrans('Artisans choose one of these when they finish Preparation. A reason taken off the list stays on the sessions that used it. "Other" is always last and asks them to type the reason.') }}</div>
            <div v-for="(reason, index) in editingReasons ?? []" :key="index" class="mt-2 flex items-center gap-2">
                <InputText v-model="reason.name" class="flex-1" :class="fieldFocusClass" maxlength="120" :invalid="!!reasonsErrors[`reasons.${index}.name`]" :aria-label="ctrans('Reason')" />
                <button type="button" class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" :disabled="index === 0" :aria-label="ctrans('Move up')" @click="moveReason(index, -1)">
                    <FontAwesomeIcon :icon="['fal', 'chevron-up']" fixed-width aria-hidden="true" />
                </button>
                <button type="button" class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" :disabled="index === (editingReasons?.length ?? 0) - 1" :aria-label="ctrans('Move down')" @click="moveReason(index, 1)">
                    <FontAwesomeIcon :icon="['fal', 'chevron-down']" fixed-width aria-hidden="true" />
                </button>
                <button type="button" class="rounded px-1 text-red-500 hover:bg-gray-100" :aria-label="ctrans('Remove')" @click="editingReasons?.splice(index, 1)">
                    <FontAwesomeIcon :icon="['fal', 'times']" fixed-width aria-hidden="true" />
                </button>
            </div>
            <div class="mt-2 rounded border border-dashed border-gray-200 px-3 py-2 text-sm text-gray-500">{{ ctrans('Other') }}</div>
            <div v-for="(error, key) in reasonsErrors" :key="key" class="mt-2 text-sm text-red-600">{{ error }}</div>
            <div class="mt-4 flex justify-between gap-2">
                <Button type="tertiary" :label="ctrans('Add reason')" @click="editingReasons?.push({ id: null, name: '' })">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'plus']" fixed-width aria-hidden="true" /></template>
                </Button>
                <div class="flex gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" :disabled="processing" @click="editingReasons = null">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'times']" fixed-width class="text-red-500" aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Save')" :disabled="processing" @click="saveReasons">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'check']" fixed-width class="text-green-600" aria-hidden="true" /></template>
                    </Button>
                </div>
            </div>
        </Dialog>

        <Dialog :visible="!!breakdownArtisan" modal :header="ctrans(':name · other work', { name: breakdownArtisan?.worker ?? '' })" :style="{ width: '48rem' }" @update:visible="(visible: boolean) => { if (!visible) breakdownArtisan = null }">
            <template v-for="artisan in breakdownArtisan ? [breakdownArtisan] : []" :key="artisan.user_id">
                <div class="flex flex-wrap gap-10">
                                    <table class="w-auto text-xs tabular-nums">
                        <thead>
                            <tr class="text-left text-gray-400">
                                <th class="py-1 font-medium">{{ ctrans('Day') }}</th>
                                <th v-for="activity in non_productive_activities" :key="activity.value" class="py-1 pl-6 font-medium text-right">{{ activity.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="day in artisan.non_productive.days" :key="day.key" class="border-t border-gray-50 text-gray-600">
                                <td class="py-1">{{ useFormatTime(day.key) }}</td>
                                <td v-for="activity in non_productive_activities" :key="activity.value" class="py-1 pl-6 text-right">{{ day.hours[activity.value] ? hoursLabel(day.hours[activity.value]) : '—' }}</td>
                            </tr>
                            <tr v-for="week in artisan.non_productive.weeks" :key="'w' + week.key" class="border-t border-gray-100 font-medium text-gray-700">
                                <td class="py-1">{{ ctrans('Week of :date', { date: useFormatTime(week.key) }) }}</td>
                                <td v-for="activity in non_productive_activities" :key="activity.value" class="py-1 pl-6 text-right">{{ hoursLabel(week.hours[activity.value]) }}</td>
                            </tr>
                        </tbody>
                    </table>
                                    <table class="w-auto text-xs tabular-nums">
                        <thead>
                            <tr class="text-left text-gray-400">
                                <th class="py-1 font-medium">{{ ctrans('Job order') }}</th>
                                <th v-for="activity in non_productive_activities" :key="activity.value" class="py-1 pl-6 font-medium text-right">{{ activity.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="jobOrder in artisan.non_productive.job_orders" :key="jobOrder.key" class="border-t border-gray-50 text-gray-600">
                                <td class="py-1">{{ jobOrder.label ?? ctrans('General, no job order') }}</td>
                                <td v-for="activity in non_productive_activities" :key="activity.value" class="py-1 pl-6 text-right">{{ jobOrder.hours[activity.value] ? hoursLabel(jobOrder.hours[activity.value]) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                                </div>
            </template>
        </Dialog>

        <Dialog :visible="!!settingsArtisan" modal :header="settingsArtisan?.worker" :style="{ width: '48rem' }" @update:visible="(visible: boolean) => { if (!visible) settingsArtisan = null }">
            <template v-for="artisan in settingsArtisan ? [settingsArtisan] : []" :key="artisan.user_id">
                <div v-if="artisan.pay_type_route" class="py-2 flex items-center gap-3 border-b border-gray-100 text-sm text-gray-600">
                    <span>{{ ctrans('Pay type') }}</span>
                    <SegmentedToggle :key="payTypeToggleKey" :modelValue="artisan.artisan_pay_type ?? 'piece_rate'" :options="payTypeOptions" :aria-label="ctrans('Pay type')" @update:modelValue="(payType: string) => pendingPayType = { artisan, payType }" />
                    <span class="text-xs text-gray-400">{{ ctrans('Hourly: no tiers, bonus or under-target flags. Applies to this week\'s sessions too.') }}</span>
                </div>
                <div v-if="artisan.pay_type_history.length" class="py-2 border-b border-gray-100 text-xs text-gray-500">
                    <div class="font-medium text-gray-600">{{ ctrans('Pay type changes') }}</div>
                    <div v-for="change in artisan.pay_type_history" :key="change.changed_at" class="tabular-nums">
                        {{ ctrans(':from to :to, from :date', { from: change.from ?? '—', to: change.to ?? '—', date: longDate(change.effective_from) }) }}
                        · {{ ctrans('changed :time by :user', { time: clockTime(change.changed_at), user: change.by ?? ctrans('system') }) }}
                    </div>
                </div>
                <div v-if="artisan.guru_route" class="py-2 flex flex-wrap items-center gap-3 border-b border-gray-100 text-sm text-gray-600">
                    <span>{{ ctrans('Guru') }}</span>
                    <DatePicker v-model="guruDates[artisan.user_id].since" :maxDate="guruDates[artisan.user_id].until ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" size="small" :placeholder="ctrans('Since')" :class="fieldFocusClass" :aria-label="ctrans('Guru since')" />
                    <DatePicker v-model="guruDates[artisan.user_id].until" :minDate="guruDates[artisan.user_id].since ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon showClear iconDisplay="input" size="small" :placeholder="ctrans('No end date')" :class="fieldFocusClass" :aria-label="ctrans('Guru until')" />
                    <Button type="tertiary" size="xs" :label="ctrans('Save')" :disabled="processing || !guruDates[artisan.user_id].since" @click="setGuru(artisan)" />
                    <Button v-if="artisan.guru_since" type="negative" size="xs" :label="ctrans('Not a Guru')" :disabled="processing" @click="setGuru(artisan, true)" />
                    <span class="text-xs text-gray-400">{{ ctrans('Guru: extra pay for every hour worked between these dates, on top of the band rate.') }}</span>
                </div>
            </template>
        </Dialog>

        <Dialog :visible="!!pendingPayType" modal :closable="!processing" :closeOnEscape="!processing" :header="ctrans('Change pay type')" :style="{ width: '28rem' }" @update:visible="(visible: boolean) => { if (!visible && !processing) cancelPayType() }">
            <div v-if="pendingPayType" class="space-y-3 text-sm">
                <div class="text-gray-700">
                    {{ ctrans("You're changing :name from :from to :to. This changes how their work is paid from :date. Are you sure?", {
                        name: pendingPayType.artisan.worker,
                        from: artisan_pay_types[pendingPayType.artisan.artisan_pay_type ?? 'piece_rate'],
                        to: artisan_pay_types[pendingPayType.payType],
                        date: longDate(pay_type_effective_from),
                    }) }}
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" autofocus :disabled="processing" @click="cancelPayType" />
                    <Button type="tertiary" :label="ctrans('Yes, change it')" :loading="processing" @click="setPayType">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'exclamation-triangle']" class="text-amber-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </div>
        </Dialog>

        <Dialog :visible="!!correctingBreak" modal :closable="!processing" :header="ctrans('Set end of break')" :style="{ width: '28rem' }" @update:visible="(visible: boolean) => { if (!visible && !processing) correctingBreak = null }">
            <div v-if="correctingBreak" class="space-y-3 text-sm">
                <div class="text-gray-600">
                    <span class="font-medium text-gray-800">{{ correctingBreak.worker }}</span> · {{ ctrans(':minutes min planned', { minutes: correctingBreak.planned_minutes }) }}
                </div>
                <div class="text-xs text-gray-500">{{ ctrans('Started :time', { time: clockTime(correctingBreak.started_at) }) }}</div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Back from break at') }}</label>
                    <DatePicker v-model="breakEnd" showTime hourFormat="24" dateFormat="d M yy" :minDate="new Date(correctingBreak.started_at)" :maxDate="new Date()" fluid :class="fieldFocusClass" :aria-label="ctrans('Back from break at')" />
                </div>
                <div class="text-xs text-gray-500">{{ ctrans('Hours and pay of the jobs the break overlaps are worked out again. The end first recorded is kept.') }}</div>
                <div v-for="(message, field) in breakEndErrors" :key="field" class="rounded border border-red-200 bg-red-50 px-2 py-1 text-red-700">{{ message }}</div>
                <div class="flex justify-end gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" :disabled="processing" @click="correctingBreak = null">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'times']" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Save')" :loading="processing" :disabled="!breakEnd" @click="saveBreakEnd">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'check']" class="text-green-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </div>
        </Dialog>

        <Dialog :visible="editingBreakTolerance" modal :closable="!processing" :header="ctrans('Break overrun')" :style="{ width: '24rem' }" @update:visible="(visible: boolean) => { if (!visible && !processing) editingBreakTolerance = false }">
            <div class="space-y-3 text-sm">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Minutes over the planned break before it shows as overrun') }}</label>
                    <InputNumber v-model="breakToleranceMinutes" :min="0" :max="120" suffix=" min" fluid :class="fieldFocusClass" :aria-label="ctrans('Minutes')" />
                </div>
                <div v-for="(message, field) in breakToleranceErrors" :key="field" class="rounded border border-red-200 bg-red-50 px-2 py-1 text-red-700">{{ message }}</div>
                <div class="flex justify-end gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" :disabled="processing" @click="editingBreakTolerance = false">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'times']" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Save')" :loading="processing" :disabled="breakToleranceMinutes === null" @click="saveBreakTolerance">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'check']" class="text-green-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </div>
        </Dialog>

        <Dialog :visible="editingShortPreparation" modal :closable="!processing" :header="ctrans('Short preparation')" :style="{ width: '24rem' }" @update:visible="(visible: boolean) => { if (!visible && !processing) editingShortPreparation = false }">
            <div class="space-y-3 text-sm">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Preparation shorter than this is added to the job step started straight after it, and asks for no reason. 0 keeps every preparation.') }}</label>
                    <InputNumber v-model="shortPreparationMinutes" :min="0" :max="60" suffix=" min" fluid :class="fieldFocusClass" :aria-label="ctrans('Minutes')" />
                </div>
                <div v-for="(message, field) in shortPreparationErrors" :key="field" class="rounded border border-red-200 bg-red-50 px-2 py-1 text-red-700">{{ message }}</div>
                <div class="flex justify-end gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" :disabled="processing" @click="editingShortPreparation = false">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'times']" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Save')" :loading="processing" :disabled="shortPreparationMinutes === null" @click="saveShortPreparation">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'check']" class="text-green-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </div>
        </Dialog>

        <Dialog :visible="!!editingTimes" modal :header="ctrans('Edit times')" :style="{ width: '32rem' }" @update:visible="(visible: boolean) => { if (!visible) editingTimes = null }">
            <div v-if="editingTimes" class="space-y-3 text-sm">
                <div class="text-gray-600">
                    <span class="font-medium text-gray-800">{{ editWorker }}</span> · {{ editingTimes.task_name ?? editingTimes.activity }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ ctrans('Recorded :from to :to', { from: clockTime(editingTimes.time_edit?.original_started_at ?? editingTimes.started_at), to: clockTime(editingTimes.time_edit?.original_ended_at ?? editingTimes.ended_at) }) }}
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Start') }}</label>
                        <DatePicker v-model="editStart" showTime hourFormat="24" dateFormat="d M yy" :maxDate="editEnd ?? undefined" fluid :class="fieldFocusClass" :aria-label="ctrans('Start')" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ ctrans('End') }}</label>
                        <DatePicker v-model="editEnd" showTime hourFormat="24" dateFormat="d M yy" :minDate="editStart ?? undefined" :maxDate="new Date()" fluid :class="fieldFocusClass" :aria-label="ctrans('End')" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Reason') }}</label>
                    <Select v-model="editReason" :options="time_edit_reasons" optionLabel="label" optionValue="value" :placeholder="ctrans('Choose a reason')" fluid :class="fieldFocusClass" />
                </div>
                <div v-if="editReason">
                    <label class="block text-xs text-gray-500 mb-1">{{ editReason === 'other' ? ctrans('Note') : ctrans('Note (optional)') }}</label>
                    <InputText v-model="editNote" maxlength="1000" fluid />
                </div>
                <div class="text-xs text-gray-500">{{ ctrans('Hours, rate per hour and pay are worked out again for the new times. The first times recorded are kept.') }}</div>
                <div v-for="(message, field) in editErrors" :key="field"
                    class="rounded px-2 py-1"
                    :class="['outside_shift', 'impossible_rate'].includes(field) ? 'border border-amber-200 bg-amber-50 text-amber-800' : 'border border-red-200 bg-red-50 text-red-700'">
                    {{ message }}
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" @click="editingTimes = null">
                        <template #icon><FontAwesomeIcon :icon="['fal', 'times']" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" :label="warningConfirmed ? ctrans('Save anyway') : ctrans('Save times')" :loading="processing" :disabled="!canSaveTimes" @click="saveTimes">
                        <template #icon><FontAwesomeIcon :icon="['fal', warningConfirmed ? 'exclamation-triangle' : 'check']" :class="warningConfirmed ? 'text-amber-600' : 'text-green-600'" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </div>
        </Dialog>
    </div>
</template>
