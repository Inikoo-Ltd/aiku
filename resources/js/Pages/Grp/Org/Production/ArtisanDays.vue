<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 21 Oct 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import DatePicker from "primevue/datepicker"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import InputNumber from "primevue/inputnumber"
import ToggleSwitch from "primevue/toggleswitch"
import Dialog from "primevue/dialog"
import MultiSelect from "primevue/multiselect"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Table from "@/Components/Table/Table.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUserClock, faPencil, faCheck, faTimes, faFilter, faCog, faExclamationTriangle, faCalendarPlus, faUmbrellaBeach, faMoneyBill } from "@fal"

library.add(faUserClock, faPencil, faCheck, faTimes, faFilter, faCog, faExclamationTriangle, faCalendarPlus, faUmbrellaBeach, faMoneyBill)

type Overtime = {
    planned_start: string | null
    planned_end: string | null
    decision: "approved" | "not_approved"
    decision_label: string
    compensation: "paid" | "toil" | null
    compensation_label: string | null
    reason: string | null
    decided_by: string | null
    decided_at: string | null
}

type ArtisanDay = {
    id: number
    employee_id: number
    date: string
    is_weekend: boolean
    clocked_in_at: string | null
    clocked_out_at: string | null
    overtime: Overtime | null
    is_outside_planned: boolean
    is_overtime_decided: boolean
    is_overtime_awaiting: boolean
    user_id: number | null
    worker: string | null
    shift_start: string | null
    shift_end: string | null
    expected_minutes: number | null
    recorded_minutes: number
    clocked_minutes: number | null
    tablet_over_clocked_minutes: number
    open_clockings: number
    difference_minutes: number | null
    tolerance_minutes: number
    unchecked_running_jobs: number
    status: "ok" | "over" | "under" | "running_job" | "in_progress" | "no_shift" | "no_break"
    is_warning_open: boolean
    resolution: "approved" | "not_approved" | "reason_given" | "checked" | null
    resolution_label: string | null
    resolution_reason: string | null
    resolution_note: string | null
    resolved_minutes: number | null
    is_resolution_current: boolean
    resolved_by: string | null
    resolved_at: string | null
    resolve_route: { name: string, parameters: Record<string, number> }
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: { data: ArtisanDay[] }
    period: { from: string, to: string }
    open_only: boolean
    open_count: number
    can_resolve: boolean
    payroll_lock: { locked_before: string, can_change: boolean }
    can_set_tolerance: boolean
    tolerance_route: { name: string, parameters: string[] }
    tolerance_minutes: number
    tolerances: { tolerance_minutes: number, effective_from: string, set_by: string | null, set_at: string }[]
    default_tolerance_minutes: number
    short_reasons: Record<string, string>
    compensations: Record<string, string>
    overtime_route: { name: string, parameters: string[] }
    toil: {
        ratio: number
        can_set_ratio: boolean
        ratio_route: { name: string, parameters: string[] }
        taken_route: { name: string, parameters: string[] }
        artisans: { id: number, name: string, balance_hours: number }[]
    }
    statuses: Record<string, string>
    performance_route: { name: string, parameters: Record<string, string> }
}>()

const from = ref(props.period.from)
const to = ref(props.period.to)
const openOnly = ref(props.open_only)

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
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

function applyFilters() {
    router.get(window.location.pathname, { from: from.value, to: to.value, open_only: openOnly.value ? 1 : undefined }, { preserveState: false })
}

const clockMinutes = (minutes: number | null) => {
    if (minutes === null) return "—"
    const absolute = Math.abs(minutes)
    return `${minutes < 0 ? "−" : ""}${Math.floor(absolute / 60)}:${String(absolute % 60).padStart(2, "0")}`
}
const signedMinutes = (minutes: number | null) => (minutes !== null && minutes > 0 ? "+" : "") + clockMinutes(minutes)

const statusClass: Record<ArtisanDay["status"], string> = {
    ok: "bg-green-100 text-green-800",
    over: "bg-red-100 text-red-800",
    under: "bg-amber-100 text-amber-800",
    running_job: "bg-amber-100 text-amber-800",
    in_progress: "bg-gray-100 text-gray-600",
    no_shift: "bg-gray-100 text-gray-500",
    no_break: "bg-amber-100 text-amber-800",
}

const resolving = ref<ArtisanDay | null>(null)
const resolution = ref<string>("approved")
const reason = ref<string | null>(null)
const note = ref("")
const errors = ref<Record<string, string>>({})
const lockReason = ref("")
const isLockedForPayroll = (day: { date: string }) => day.date.slice(0, 10) < props.payroll_lock.locked_before
const canResolveDay = (day: { date: string }) => isLockedForPayroll(day) ? props.payroll_lock.can_change : props.can_resolve
const processing = ref(false)
const overOptions = computed(() => [
    { value: "approved", label: ctrans("Approve overtime"), icon: ["fal", "check"] },
    { value: "not_approved", label: ctrans("Do not authorise"), icon: ["fal", "times"] },
])
const compensationOptions = computed(() => [
    { value: "paid", label: props.compensations.paid, icon: ["fal", "money-bill"] },
    { value: "toil", label: props.compensations.toil, icon: ["fal", "umbrella-beach"] },
])
const reasonOptions = computed(() => Object.entries(props.short_reasons).map(([value, label]) => ({ value, label })))
const noteRequired = computed(() => reason.value === "other")

const selectedRows = ref<Record<string, boolean>>({})
const selectedDays = computed(() => props.data.data.filter((day) => selectedRows.value[day.id]))

const overtimeDialog = ref(false)
const overtimeDays = ref<ArtisanDay[]>([])
const overtimeDate = ref(toLocalIsoDate(new Date()))
const overtimeDateModel = dateModel(overtimeDate)
const overtimeArtisans = ref<number[]>([])
const overtimeDecision = ref<"approved" | "not_approved">("approved")
const overtimeCompensation = ref<"paid" | "toil">("paid")
const overtimeReason = ref("")
const plannedStart = ref<Date | null>(null)
const plannedEnd = ref<Date | null>(null)
const overtimeErrors = ref<Record<string, string>>({})
const hhmm = (date: Date | null) => date ? `${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}` : null
const timeFromHhmm = (time: string | null | undefined): Date | null => {
    if (!time) return null
    const [hours, minutes] = time.split(":").map(Number)
    const date = new Date()
    date.setHours(hours, minutes, 0, 0)
    return date
}
const overtimeTargets = computed(() => overtimeDays.value.length
    ? overtimeDays.value.map((day) => ({ employee_id: day.employee_id, date: day.date }))
    : overtimeArtisans.value.map((employeeId) => ({ employee_id: employeeId, date: overtimeDate.value })))
const overtimeTouchesLockedWeek = computed(() => overtimeTargets.value.some((target) => isLockedForPayroll(target)))

function openOvertime(days: ArtisanDay[]) {
    const first = days[0]?.overtime
    overtimeDays.value = days
    overtimeArtisans.value = []
    overtimeDate.value = days[0]?.date ?? toLocalIsoDate(new Date())
    overtimeDecision.value = first?.decision ?? "approved"
    overtimeCompensation.value = first?.compensation ?? "paid"
    overtimeReason.value = first?.reason ?? ""
    plannedStart.value = timeFromHhmm(first?.planned_start)
    plannedEnd.value = timeFromHhmm(first?.planned_end)
    lockReason.value = ""
    overtimeErrors.value = {}
    overtimeDialog.value = true
}

function saveOvertime() {
    processing.value = true
    router.post(
        route(props.overtime_route.name, props.overtime_route.parameters),
        {
            days: overtimeTargets.value,
            decision: overtimeDecision.value,
            compensation: overtimeDecision.value === "approved" ? overtimeCompensation.value : null,
            reason: overtimeReason.value || null,
            planned_start: hhmm(plannedStart.value),
            planned_end: hhmm(plannedEnd.value),
            lock_reason: lockReason.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => { overtimeDialog.value = false; selectedRows.value = {} },
            onError: (formErrors) => overtimeErrors.value = formErrors,
            onFinish: () => processing.value = false,
        }
    )
}

const toilDialog = ref(false)
const toilRatio = ref<number | null>(props.toil.ratio)
const toilEmployee = ref<number | null>(null)
const toilDate = ref(toLocalIsoDate(new Date()))
const toilDateModel = dateModel(toilDate)
const toilHours = ref<number | null>(null)
const toilNote = ref("")
const toilErrors = ref<Record<string, string>>({})

function openToil() {
    toilRatio.value = props.toil.ratio
    toilEmployee.value = null
    toilHours.value = null
    toilNote.value = ""
    toilErrors.value = {}
    toilDialog.value = true
}

function saveToilRatio() {
    processing.value = true
    router.post(route(props.toil.ratio_route.name, props.toil.ratio_route.parameters), { toil_ratio: toilRatio.value }, {
        preserveScroll: true,
        onError: (formErrors) => toilErrors.value = formErrors,
        onFinish: () => processing.value = false,
    })
}

function saveToilTaken() {
    processing.value = true
    router.post(route(props.toil.taken_route.name, props.toil.taken_route.parameters), { employee_id: toilEmployee.value, date: toilDate.value, hours: toilHours.value, note: toilNote.value || null }, {
        preserveScroll: true,
        onSuccess: () => { toilEmployee.value = null; toilHours.value = null; toilNote.value = ""; toilErrors.value = {} },
        onError: (formErrors) => toilErrors.value = formErrors,
        onFinish: () => processing.value = false,
    })
}

function openResolve(day: ArtisanDay) {
    if (day.status === "over" || day.overtime) {
        openOvertime([day])
        return
    }
    resolving.value = day
    errors.value = {}
    resolution.value = day.status === "no_break" ? "checked" : "reason_given"
    reason.value = null
    note.value = day.is_resolution_current ? day.resolution_note ?? "" : ""
    lockReason.value = ""
}

function saveResolve() {
    if (!resolving.value) return
    processing.value = true
    router.patch(
        route(resolving.value.resolve_route.name, resolving.value.resolve_route.parameters),
        { resolution: resolution.value, resolution_reason: resolution.value === "reason_given" ? reason.value : null, resolution_note: note.value || null, lock_reason: lockReason.value || null },
        {
            preserveScroll: true,
            onSuccess: () => resolving.value = null,
            onError: (formErrors) => errors.value = formErrors,
            onFinish: () => processing.value = false,
        }
    )
}

const editingTolerance = ref(false)
const toleranceMinutes = ref<number | null>(props.tolerance_minutes)
const toleranceFrom = ref(toLocalIsoDate(new Date()))
const toleranceFromDate = dateModel(toleranceFrom)
const toleranceErrors = ref<Record<string, string>>({})

function openTolerance() {
    toleranceMinutes.value = props.tolerance_minutes
    toleranceFrom.value = toLocalIsoDate(new Date())
    toleranceErrors.value = {}
    editingTolerance.value = true
}

function saveTolerance() {
    processing.value = true
    router.post(
        route(props.tolerance_route.name, props.tolerance_route.parameters),
        { tolerance_minutes: toleranceMinutes.value, effective_from: toleranceFrom.value },
        {
            preserveScroll: true,
            onSuccess: () => editingTolerance.value = false,
            onError: (formErrors) => toleranceErrors.value = formErrors,
            onFinish: () => processing.value = false,
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-3">
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <label class="flex flex-col gap-1">
                <span class="text-xs text-gray-500">{{ ctrans('From') }}</span>
                <DatePicker v-model="fromDate" :maxDate="toDate ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :aria-label="ctrans('From')" />
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-xs text-gray-500">{{ ctrans('To') }}</span>
                <DatePicker v-model="toDate" :minDate="fromDate ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :aria-label="ctrans('To')" />
            </label>
            <label class="flex items-center gap-2 pb-2">
                <ToggleSwitch v-model="openOnly" :aria-label="ctrans('Only days to deal with')" />
                <span class="text-gray-700">{{ ctrans('Only days to deal with') }}</span>
            </label>
            <Button type="tertiary" :label="ctrans('Apply')" @click="applyFilters">
                <template #icon><FontAwesomeIcon :icon="['fal', 'filter']" fixed-width aria-hidden="true" /></template>
            </Button>
            <template v-if="can_resolve">
                <Button type="tertiary" :label="ctrans('Plan overtime')" @click="openOvertime([])">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'calendar-plus']" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button v-if="selectedDays.length" type="tertiary" :label="ctrans('Overtime for :count selected', { count: selectedDays.length })" @click="openOvertime(selectedDays)">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'check']" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
            <Button type="tertiary" :label="ctrans('Time off in lieu')" @click="openToil">
                <template #icon><FontAwesomeIcon :icon="['fal', 'umbrella-beach']" fixed-width aria-hidden="true" /></template>
            </Button>
            <div class="ml-auto flex items-center gap-2 pb-2 text-xs text-gray-500">
                <span>{{ ctrans('Flagged when more than :minutes min over or under the shift', { minutes: tolerance_minutes }) }}</span>
                <button v-if="can_set_tolerance" type="button" class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" v-tooltip="ctrans('Tolerance')" :aria-label="ctrans('Tolerance')" @click="openTolerance">
                    <FontAwesomeIcon :icon="['fal', 'cog']" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>

        <div v-if="open_count" class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900">
            <FontAwesomeIcon :icon="['fal', 'exclamation-triangle']" class="mr-1" fixed-width aria-hidden="true" />
            {{ ctrans(':count days in this period to deal with', { count: open_count }) }}
        </div>
    </div>

    <Table :resource="data" :isCheckBox="can_resolve" :selectedRow="selectedRows" @onSelectRow="(rows: Record<string, boolean>) => selectedRows = { ...rows }">
        <template #cell(date)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.date) }}</span>
        </template>

        <template #cell(worker)="{ item }">
            <span class="font-medium">{{ item.worker }}</span>
        </template>

        <template #cell(shift)="{ item }">
            <span v-if="item.shift_start" class="whitespace-nowrap tabular-nums text-gray-600">{{ item.shift_start }}–{{ item.shift_end }}</span>
            <span v-else-if="item.expected_minutes === 0" class="text-gray-500">{{ ctrans('Day off') }}</span>
            <span v-else class="text-gray-400">—</span>
        </template>

        <template #cell(clocked_times)="{ item }">
            <span v-if="item.clocked_in_at" class="whitespace-nowrap tabular-nums text-gray-600">{{ item.clocked_in_at }}–{{ item.clocked_out_at ?? '…' }}</span>
            <span v-else class="text-gray-400">—</span>
        </template>

        <template #cell(planned)="{ item }">
            <span v-if="item.overtime?.planned_start" class="whitespace-nowrap tabular-nums text-gray-600">{{ item.overtime.planned_start }}–{{ item.overtime.planned_end }}</span>
            <span v-else class="text-gray-400">—</span>
            <span v-if="item.is_outside_planned" class="ml-1 whitespace-nowrap rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800" v-tooltip="ctrans('Clocked in before or out after the planned overtime and the shift')">{{ ctrans('Outside planned times') }}</span>
        </template>

        <template #cell(expected_minutes)="{ item }">
            <span class="tabular-nums">{{ clockMinutes(item.expected_minutes) }}</span>
        </template>

        <template #cell(clocked_minutes)="{ item }">
            <span v-if="item.clocked_minutes === null" class="text-gray-400">{{ ctrans('Not clocked') }}</span>
            <span v-else class="tabular-nums">{{ clockMinutes(item.clocked_minutes) }}</span>
        </template>

        <template #cell(recorded_minutes)="{ item }">
            <span class="tabular-nums text-gray-500">{{ clockMinutes(item.recorded_minutes) }}</span>
        </template>

        <template #cell(difference_minutes)="{ item }">
            <span class="tabular-nums" :class="item.status === 'over' ? 'text-red-700' : item.status === 'under' ? 'text-amber-700' : 'text-gray-500'">{{ item.expected_minutes === null ? '—' : signedMinutes(item.difference_minutes) }}</span>
        </template>

        <template #cell(status)="{ item }">
            <div class="flex flex-wrap items-center gap-2">
                <span v-if="item.overtime && (item.is_overtime_decided || item.status === 'over')" class="whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-medium" :class="item.is_overtime_decided ? 'bg-gray-100 text-gray-700' : statusClass.over">
                    {{ item.overtime.decision_label }}<template v-if="item.overtime.compensation_label"> · {{ item.overtime.compensation_label }}</template><template v-if="item.is_weekend && item.overtime.compensation === 'paid'"> · {{ ctrans('1.5 × Band 0') }}</template>
                </span>
                <span v-else class="whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-medium" :class="item.is_resolution_current ? 'bg-gray-100 text-gray-700' : statusClass[item.status]">
                    {{ item.is_resolution_current ? item.resolution_label : statuses[item.status] }}
                </span>
                <span v-if="item.overtime && item.is_overtime_decided" class="text-xs text-gray-500">
                    <template v-if="item.overtime.reason">{{ item.overtime.reason }} · </template>{{ item.overtime.decided_by }}, {{ useFormatTime(item.overtime.decided_at, { formatTime: 'd MMM' }) }}
                </span>
                <span v-else-if="item.overtime && item.is_overtime_awaiting" class="text-xs text-amber-700">{{ ctrans('Clocked outside the planned times: check and save the overtime again') }}</span>
                <span v-if="item.tablet_over_clocked_minutes || item.open_clockings" class="whitespace-nowrap rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800"
                    v-tooltip="item.open_clockings ? ctrans('Clocked in and not clocked out: correct the clocking in Timesheets') : ctrans('More time on the tablet than clocked: correct the job times on Performance or the clocking. Only the clocked time is paid.')">
                    {{ item.open_clockings ? ctrans('Not clocked out') : ctrans('Tablet over clocked +:time', { time: clockMinutes(item.tablet_over_clocked_minutes) }) }}
                </span>
                <span v-if="item.status === 'running_job'" class="text-xs text-gray-600">
                    {{ ctrans('Set the times of the job left running on') }}
                    <Link :href="route(performance_route.name, { ...performance_route.parameters, from: item.date, to: item.date })" class="font-medium hover:underline">{{ ctrans('Performance') }}</Link>
                </span>
                <span v-if="item.is_resolution_current && !item.overtime" class="text-xs text-gray-500">
                    <template v-if="item.resolution_reason">{{ item.resolution_reason }}</template><template v-if="item.resolution_reason && item.resolution_note">: </template>{{ item.resolution_note }}
                    · {{ item.resolved_by }}, {{ useFormatTime(item.resolved_at, { formatTime: 'd MMM' }) }}
                </span>
                <span v-else-if="item.resolution && item.is_warning_open && !item.overtime" class="text-xs text-amber-700">
                    {{ ctrans('Hours changed since :by answered (:minutes)', { by: item.resolved_by ?? '', minutes: clockMinutes(item.resolved_minutes) }) }}
                </span>
                <span v-if="isLockedForPayroll(item)" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ ctrans('Locked for payroll') }}</span>
                <template v-if="canResolveDay(item) && (['over', 'under', 'no_break'].includes(item.status) || item.overtime)">
                    <button v-if="item.overtime ? item.is_overtime_decided : item.is_resolution_current" type="button" class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" v-tooltip="ctrans('Change')" :aria-label="ctrans('Change')" @click="openResolve(item)">
                        <FontAwesomeIcon :icon="['fal', 'pencil']" fixed-width aria-hidden="true" />
                    </button>
                    <Button v-else type="tertiary" size="xs" :label="item.status === 'over' ? ctrans('Approve or not') : item.status === 'no_break' ? ctrans('Mark as checked') : ctrans('Give reason')" @click="openResolve(item)" />
                </template>
            </div>
        </template>
    </Table>

    <Dialog :visible="!!resolving" modal :closable="!processing" :header="resolving ? `${resolving.worker} · ${useFormatTime(resolving.date)}` : ''" :style="{ width: '30rem' }" @update:visible="(visible: boolean) => { if (!visible && !processing) resolving = null }">
        <div v-if="resolving" class="space-y-3 text-sm">
            <div class="text-gray-700">
                <template v-if="resolving.status === 'no_break'">{{ ctrans('No break clocked on this day. Nothing is taken off the pay: check with the artisan whether they took a break.') }}</template>
                <template v-else>{{ ctrans('Clocked :recorded against a shift of :expected: :difference short.', { recorded: clockMinutes(resolving.clocked_minutes ?? 0), expected: clockMinutes(resolving.expected_minutes), difference: clockMinutes(-(resolving.difference_minutes ?? 0)) }) }}</template>
            </div>
            <label v-if="resolving.status === 'under'" class="flex flex-col gap-1">
                <span class="text-xs text-gray-500">{{ ctrans('Why was the day short?') }}</span>
                <Select v-model="reason" :options="reasonOptions" optionLabel="label" optionValue="value" :placeholder="ctrans('Choose a reason')" :invalid="!!errors.resolution_reason" :class="fieldFocusClass" />
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-xs text-gray-500">{{ noteRequired ? ctrans('Reason') : ctrans('Note (optional)') }}</span>
                <Textarea v-model="note" rows="2" autoResize maxlength="1000" :invalid="!!errors.resolution_note" :class="fieldFocusClass" />
            </label>
            <label v-if="isLockedForPayroll(resolving)" class="flex flex-col gap-1">
                <span class="text-xs text-gray-500">{{ ctrans('This week is locked for payroll: reason for changing it') }}</span>
                <Textarea v-model="lockReason" rows="2" autoResize maxlength="1000" :invalid="!!errors.lock_reason" :class="fieldFocusClass" />
            </label>
            <div v-for="message in Object.values(errors)" :key="message" class="text-xs text-red-600">{{ message }}</div>
            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="ctrans('Cancel')" :disabled="processing" @click="resolving = null">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'times']" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" :label="ctrans('Save')" :loading="processing" :disabled="(resolving.status === 'under' && !reason) || (noteRequired && !note.trim()) || (isLockedForPayroll(resolving) && !lockReason.trim())" @click="saveResolve">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'check']" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </div>
    </Dialog>

    <Dialog :visible="editingTolerance" modal :closable="!processing" :header="ctrans('Tolerance')" :style="{ width: '30rem' }" @update:visible="(visible: boolean) => { if (!visible && !processing) editingTolerance = false }">
        <div class="space-y-3 text-sm">
            <div class="text-xs text-gray-500">{{ ctrans('A day is flagged when the time clocked is more than this many minutes over or under the shift. Days before the date keep the tolerance they had.') }}</div>
            <div class="flex flex-wrap items-end gap-3">
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans('Minutes either way') }}</span>
                    <InputNumber v-model="toleranceMinutes" :min="0" :max="240" :invalid="!!toleranceErrors.tolerance_minutes" :pt="{ pcInputText: { root: { class: '!w-24 !text-right' } } }" />
                </label>
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans('From') }}</span>
                    <DatePicker v-model="toleranceFromDate" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :invalid="!!toleranceErrors.effective_from" :class="fieldFocusClass" />
                </label>
            </div>
            <div v-for="message in Object.values(toleranceErrors)" :key="message" class="text-xs text-red-600">{{ message }}</div>
            <table v-if="tolerances.length" class="w-full text-xs">
                <thead class="whitespace-nowrap">
                    <tr class="text-left text-gray-400">
                        <th class="py-1 font-medium">{{ ctrans('From') }}</th>
                        <th class="py-1 text-right font-medium">{{ ctrans('Minutes') }}</th>
                        <th class="py-1 pl-4 font-medium">{{ ctrans('Set by') }}</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <tr v-for="tolerance in tolerances" :key="tolerance.effective_from" class="border-t border-gray-100">
                        <td class="py-1">{{ useFormatTime(tolerance.effective_from) }}</td>
                        <td class="py-1 text-right">{{ tolerance.tolerance_minutes }}</td>
                        <td class="py-1 pl-4 text-gray-600">{{ tolerance.set_by }}, {{ useFormatTime(tolerance.set_at) }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="text-xs text-gray-500">{{ ctrans('Not changed yet: :minutes minutes', { minutes: default_tolerance_minutes }) }}</div>
            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="ctrans('Cancel')" :disabled="processing" @click="editingTolerance = false">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'times']" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" :label="ctrans('Save')" :loading="processing" :disabled="toleranceMinutes === null" @click="saveTolerance">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'check']" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </div>
    </Dialog>

    <Dialog :visible="overtimeDialog" modal :closable="!processing" :header="ctrans('Overtime')" :style="{ width: '34rem' }" @update:visible="(visible: boolean) => { if (!visible && !processing) overtimeDialog = false }">
        <div class="space-y-3 text-sm">
            <div v-if="overtimeDays.length" class="max-h-32 overflow-y-auto text-gray-700">
                <div v-for="day in overtimeDays" :key="day.id" class="flex justify-between gap-3">
                    <span>{{ day.worker }} · {{ useFormatTime(day.date) }}</span>
                    <span class="tabular-nums text-gray-500">{{ day.clocked_in_at ? `${day.clocked_in_at}–${day.clocked_out_at ?? '…'}` : '' }} {{ day.expected_minutes !== null ? signedMinutes(day.difference_minutes) : '' }}</span>
                </div>
            </div>
            <template v-else>
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans('Day') }} <span class="text-red-500">*</span></span>
                    <DatePicker v-model="overtimeDateModel" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :invalid="!!overtimeErrors.date" :class="fieldFocusClass" />
                </label>
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans('Artisans') }} <span class="text-red-500">*</span></span>
                    <MultiSelect v-model="overtimeArtisans" :options="toil.artisans" optionLabel="name" optionValue="id" filter display="chip" :placeholder="ctrans('Choose artisans')" :class="fieldFocusClass" />
                </label>
            </template>
            <div class="flex flex-wrap items-end gap-3">
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans('Planned from') }} <span v-if="!overtimeDays.length" class="text-red-500">*</span></span>
                    <DatePicker v-model="plannedStart" timeOnly hourFormat="24" :stepMinute="15" showClear :invalid="!!overtimeErrors.planned_start" :class="fieldFocusClass" class="w-28" />
                </label>
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans('Planned to') }} <span v-if="!overtimeDays.length" class="text-red-500">*</span></span>
                    <DatePicker v-model="plannedEnd" timeOnly hourFormat="24" :stepMinute="15" showClear :invalid="!!overtimeErrors.planned_end" :class="fieldFocusClass" class="w-28" />
                </label>
                <span class="pb-2 text-xs text-gray-500">{{ ctrans('Work clocked outside these times and the shift is flagged') }}</span>
            </div>
            <SegmentedToggle v-model="overtimeDecision" :options="overOptions" :aria-label="ctrans('Overtime')" />
            <template v-if="overtimeDecision === 'approved'">
                <SegmentedToggle v-model="overtimeCompensation" :options="compensationOptions" :aria-label="ctrans('Pay or time off in lieu')" />
                <div class="text-xs text-gray-500">
                    {{ overtimeCompensation === 'paid'
                        ? ctrans('Paid: Saturday and Sunday overtime at 1.5 × Band 0, weekday overtime as normal hours.')
                        : ctrans('Not paid: the overtime hours times :ratio are added to the artisan\'s time off in lieu balance.', { ratio: toil.ratio }) }}
                </div>
            </template>
            <div v-else class="text-xs text-gray-500">{{ ctrans('The hours stay in payroll, marked as not authorised with your reason, for the accountant and HR.') }}</div>
            <label class="flex flex-col gap-1">
                <span class="text-xs text-gray-500">{{ overtimeDecision === 'not_approved' ? ctrans('Reason') : ctrans('Note (optional)') }} <span v-if="overtimeDecision === 'not_approved'" class="text-red-500">*</span></span>
                <Textarea v-model="overtimeReason" rows="2" autoResize maxlength="1000" :invalid="!!overtimeErrors.reason" :class="fieldFocusClass" />
            </label>
            <label v-if="overtimeTouchesLockedWeek" class="flex flex-col gap-1">
                <span class="text-xs text-gray-500">{{ ctrans('This week is locked for payroll: reason for changing it') }} <span class="text-red-500">*</span></span>
                <Textarea v-model="lockReason" rows="2" autoResize maxlength="1000" :invalid="!!overtimeErrors.lock_reason" :class="fieldFocusClass" />
            </label>
            <div v-for="message in Object.values(overtimeErrors)" :key="message" class="text-xs text-red-600">{{ message }}</div>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" :disabled="processing" @click="overtimeDialog = false">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'times']" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="primary" full :label="ctrans('Save')" :loading="processing" :disabled="!overtimeTargets.length || (!overtimeDays.length && (!plannedStart || !plannedEnd)) || (overtimeDecision === 'not_approved' && !overtimeReason.trim()) || (!plannedStart !== !plannedEnd) || (overtimeTouchesLockedWeek && !lockReason.trim())" @click="saveOvertime">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'check']" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </div>
    </Dialog>

    <Dialog :visible="toilDialog" modal :closable="!processing" :header="ctrans('Time off in lieu')" :style="{ width: '34rem' }" @update:visible="(visible: boolean) => { if (!visible && !processing) toilDialog = false }">
        <div class="space-y-3 text-sm">
            <div class="flex flex-wrap items-end gap-3">
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans('TOIL hours per overtime hour') }}</span>
                    <InputNumber v-model="toilRatio" :min="0.5" :max="3" :minFractionDigits="1" :maxFractionDigits="2" :disabled="!toil.can_set_ratio" :invalid="!!toilErrors.toil_ratio" :pt="{ pcInputText: { root: { class: '!w-24 !text-right' } } }" />
                </label>
                <Button v-if="toil.can_set_ratio" type="tertiary" :label="ctrans('Save ratio')" :loading="processing" :disabled="toilRatio === null || toilRatio === toil.ratio" @click="saveToilRatio">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'check']" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
                <span class="pb-2 text-xs text-gray-500">{{ ctrans('Overtime already given as TOIL keeps its ratio') }}</span>
            </div>
            <table class="w-full text-xs">
                <thead class="whitespace-nowrap">
                    <tr class="text-left text-gray-400">
                        <th class="py-1 font-medium">{{ ctrans('Artisan') }}</th>
                        <th class="py-1 text-right font-medium">{{ ctrans('Balance') }}</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <tr v-for="artisan in toil.artisans.filter((artisan) => artisan.balance_hours)" :key="artisan.id" class="border-t border-gray-100">
                        <td class="py-1">{{ artisan.name }}</td>
                        <td class="py-1 text-right">{{ artisan.balance_hours.toFixed(2) }} h</td>
                    </tr>
                    <tr v-if="!toil.artisans.some((artisan) => artisan.balance_hours)"><td colspan="2" class="py-1 text-gray-500">{{ ctrans('Nobody is owed time off in lieu') }}</td></tr>
                </tbody>
            </table>
            <template v-if="can_resolve">
                <div class="border-t border-gray-100 pt-2 text-xs font-medium text-gray-700">{{ ctrans('Record TOIL taken') }}</div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="flex min-w-48 flex-1 flex-col gap-1">
                        <span class="text-xs text-gray-500">{{ ctrans('Artisan') }} <span class="text-red-500">*</span></span>
                        <Select v-model="toilEmployee" :options="toil.artisans.filter((artisan) => artisan.balance_hours > 0)" optionLabel="name" optionValue="id" :placeholder="ctrans('Choose an artisan')" :invalid="!!toilErrors.employee_id" :class="fieldFocusClass" />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-gray-500">{{ ctrans('Day') }} <span class="text-red-500">*</span></span>
                        <DatePicker v-model="toilDateModel" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-gray-500">{{ ctrans('Hours') }} <span class="text-red-500">*</span></span>
                        <InputNumber v-model="toilHours" :min="0.25" :max="100" :minFractionDigits="0" :maxFractionDigits="2" :invalid="!!toilErrors.hours" :pt="{ pcInputText: { root: { class: '!w-20 !text-right' } } }" />
                    </label>
                </div>
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans('Note (optional)') }}</span>
                    <Textarea v-model="toilNote" rows="1" autoResize maxlength="1000" :class="fieldFocusClass" />
                </label>
            </template>
            <div v-for="message in Object.values(toilErrors)" :key="message" class="text-xs text-red-600">{{ message }}</div>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Close')" :disabled="processing" @click="toilDialog = false">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'times']" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button v-if="can_resolve" type="primary" full :label="ctrans('Record TOIL taken')" :loading="processing" :disabled="!toilEmployee || !toilHours" @click="saveToilTaken">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'check']" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </div>
    </Dialog>
</template>
