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
import { faUserClock, faPencil, faCheck, faTimes, faFilter, faCog, faExclamationTriangle } from "@fal"

library.add(faUserClock, faPencil, faCheck, faTimes, faFilter, faCog, faExclamationTriangle)

type ArtisanDay = {
    id: number
    date: string
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
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

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
const reasonOptions = computed(() => Object.entries(props.short_reasons).map(([value, label]) => ({ value, label })))
const noteRequired = computed(() => resolving.value?.status === "over" ? resolution.value === "not_approved" : reason.value === "other")

function openResolve(day: ArtisanDay) {
    resolving.value = day
    errors.value = {}
    resolution.value = day.status === "over" ? (day.resolution === "not_approved" ? "not_approved" : "approved") : day.status === "no_break" ? "checked" : "reason_given"
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

    <Table :resource="data">
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
                <span class="whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-medium" :class="item.is_resolution_current ? 'bg-gray-100 text-gray-700' : statusClass[item.status]">
                    {{ item.is_resolution_current ? item.resolution_label : statuses[item.status] }}
                </span>
                <span v-if="item.tablet_over_clocked_minutes || item.open_clockings" class="whitespace-nowrap rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800"
                    v-tooltip="item.open_clockings ? ctrans('Clocked in and not clocked out: correct the clocking in Timesheets') : ctrans('More time on the tablet than clocked: correct the job times on Performance or the clocking. Only the clocked time is paid.')">
                    {{ item.open_clockings ? ctrans('Not clocked out') : ctrans('Tablet over clocked +:time', { time: clockMinutes(item.tablet_over_clocked_minutes) }) }}
                </span>
                <span v-if="item.status === 'running_job'" class="text-xs text-gray-600">
                    {{ ctrans('Set the times of the job left running on') }}
                    <Link :href="route(performance_route.name, { ...performance_route.parameters, from: item.date, to: item.date })" class="font-medium hover:underline">{{ ctrans('Performance') }}</Link>
                </span>
                <span v-if="item.is_resolution_current" class="text-xs text-gray-500">
                    <template v-if="item.resolution_reason">{{ item.resolution_reason }}</template><template v-if="item.resolution_reason && item.resolution_note">: </template>{{ item.resolution_note }}
                    · {{ item.resolved_by }}, {{ useFormatTime(item.resolved_at, { formatTime: 'd MMM' }) }}
                </span>
                <span v-else-if="item.resolution && item.is_warning_open" class="text-xs text-amber-700">
                    {{ ctrans('Hours changed since :by answered (:minutes)', { by: item.resolved_by ?? '', minutes: clockMinutes(item.resolved_minutes) }) }}
                </span>
                <span v-if="isLockedForPayroll(item)" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ ctrans('Locked for payroll') }}</span>
                <template v-if="canResolveDay(item) && ['over', 'under', 'no_break'].includes(item.status)">
                    <button v-if="item.is_resolution_current" type="button" class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" v-tooltip="ctrans('Change')" :aria-label="ctrans('Change')" @click="openResolve(item)">
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
                <template v-if="resolving.status === 'over'">{{ ctrans('Clocked :recorded against a shift of :expected: :difference over.', { recorded: clockMinutes(resolving.clocked_minutes ?? 0), expected: clockMinutes(resolving.expected_minutes), difference: clockMinutes(resolving.difference_minutes) }) }}</template>
                <template v-else-if="resolving.status === 'no_break'">{{ ctrans('No break clocked on this day. Nothing is taken off the pay: check with the artisan whether they took a break.') }}</template>
                <template v-else>{{ ctrans('Clocked :recorded against a shift of :expected: :difference short.', { recorded: clockMinutes(resolving.clocked_minutes ?? 0), expected: clockMinutes(resolving.expected_minutes), difference: clockMinutes(-(resolving.difference_minutes ?? 0)) }) }}</template>
            </div>
            <template v-if="resolving.status === 'over'">
                <SegmentedToggle v-model="resolution" :options="overOptions" :aria-label="ctrans('Overtime')" />
                <div v-if="resolution === 'not_approved'" class="text-xs text-gray-500">{{ ctrans('The hours stay in payroll, marked as not authorised with your reason, for the accountant and HR.') }}</div>
            </template>
            <label v-else-if="resolving.status === 'under'" class="flex flex-col gap-1">
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
</template>
