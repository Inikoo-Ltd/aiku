<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 07 Sep 2026 12:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import DatePicker from "primevue/datepicker"
import Checkbox from "primevue/checkbox"
import ToggleSwitch from "primevue/toggleswitch"
import Select from "primevue/select"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMoneyCheckAlt, faDownload, faFilter, faHatWizard } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faMoneyCheckAlt, faDownload, faFilter, faHatWizard)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    period: { from: string, to: string }
    artefact_department_id: number | null
    artefact_departments: { id: number, name: string }[]
    payroll_export_route: { name: string, parameters: object }
    show_pay_bands_to_artisans: boolean
    pay_bands_visible_route: { name: string, parameters: object }
    payroll_changes: {
        id: number
        worker: string | null
        pay_week: string
        change: string
        changes: { field: string, before: string | null, after: string | null }[]
        reason: string
        changed_by: string | null
        changed_at: string
        amount_delta: number | null
        hours_delta: number
        awaiting_approval: boolean
        approved_by: string | null
        approved_at: string | null
        approve_route: null | { name: string, parameters: object }
        settled_in_period: boolean
    }[]
    data: object
}>()

const payKeys = ["working", "band_0", "band_1", "band_2", "band_3", "band_d", "band_dg", "guru", "adjustment"]

const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const payrollFrom = ref<Date | null>(fromIsoDate(props.period.from))
const payrollTo = ref<Date | null>(fromIsoDate(props.period.to))
const timeEditedOnly = ref(false)
const departmentFilter = ref<number | null>(props.artefact_department_id)
const departmentFilterOptions = computed(() => [{ id: null, name: ctrans('All departments') }, ...props.artefact_departments])
const showPayBandsToArtisans = ref(props.show_pay_bands_to_artisans)
function savePayBandsVisibility(visible: boolean) {
    router.post(
        route(props.pay_bands_visible_route.name, props.pay_bands_visible_route.parameters),
        { show_pay_bands_to_artisans: visible },
        { preserveScroll: true, preserveState: true, onError: () => showPayBandsToArtisans.value = !visible }
    )
}
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const periodQuery = computed(() => ({
    from: payrollFrom.value ? toLocalIsoDate(payrollFrom.value) : undefined,
    to: payrollTo.value ? toLocalIsoDate(payrollTo.value) : undefined,
}))

const payrollExportUrl = computed(() => route(props.payroll_export_route.name, { ...props.payroll_export_route.parameters, ...periodQuery.value }))
const detailedExportUrl = computed(() => route(props.payroll_export_route.name, {
    ...props.payroll_export_route.parameters,
    ...periodQuery.value,
    detailed: 1,
    time_edited: timeEditedOnly.value ? 1 : undefined,
}))

function applyPeriod() {
    router.get(window.location.pathname, { ...periodQuery.value, artefact_department_id: departmentFilter.value || undefined }, { preserveScroll: true })
}

const money = (amount: number) => `£${amount.toFixed(2)}`
const signedMoney = (amount: number) => (amount > 0 ? "+" : amount < 0 ? "-" : "") + money(Math.abs(amount))
const processing = ref(false)
function approvePayrollChange(change: { approve_route: null | { name: string, parameters: object } }) {
    if (!change.approve_route) return
    processing.value = true
    router.patch(route(change.approve_route.name, change.approve_route.parameters), {}, { preserveScroll: true, onFinish: () => processing.value = false })
}
const clockMinutes = (minutes: number) => `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, "0")}`
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Payroll from') }}</label>
            <DatePicker v-model="payrollFrom" :maxDate="payrollTo ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :aria-label="ctrans('Payroll from')" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ ctrans('To') }}</label>
            <DatePicker v-model="payrollTo" :minDate="payrollFrom ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :aria-label="ctrans('To')" />
        </div>
        <div v-if="artefact_departments.length">
            <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Department') }}</label>
            <Select v-model="departmentFilter" :options="departmentFilterOptions" optionLabel="name" optionValue="id" :placeholder="ctrans('All departments')" class="min-w-44" :class="fieldFocusClass" />
        </div>
        <Button type="tertiary" :label="ctrans('Apply')" @click="applyPeriod">
            <template #icon><FontAwesomeIcon :icon="['fal', 'filter']" fixed-width aria-hidden="true" /></template>
        </Button>
        <a :href="payrollExportUrl">
            <Button type="tertiary" :label="ctrans('Export payroll CSV')">
                <template #icon><FontAwesomeIcon :icon="['fal', 'download']" fixed-width aria-hidden="true" /></template>
            </Button>
        </a>
        <div class="ml-auto flex items-end gap-3">
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-600" v-tooltip="ctrans('Bands are still worked out and paid when hidden')">
                <ToggleSwitch v-model="showPayBandsToArtisans" @update:modelValue="savePayBandsVisibility" />
                {{ ctrans('Show pay bands on the floor tablet') }}
            </label>
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-600">
                <Checkbox v-model="timeEditedOnly" binary />
                {{ ctrans('Edited times only') }}
            </label>
            <a :href="detailedExportUrl">
                <Button type="tertiary" :label="ctrans('Export detailed CSV')">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'download']" fixed-width aria-hidden="true" /></template>
                </Button>
            </a>
        </div>
    </div>

    <p class="mx-4 mt-2 text-xs text-gray-500">
        {{ ctrans('Clocked hours (clocked breaks left out) are paid at the National Living Wage (band 0). The tablet hours are split into bands, and clocked time not on the tablet is band 0. Each band adds what it pays above the National Living Wage, for its hours. Tablet time beyond the clocked time is not paid. Hourly workers are paid their clocked hours at band 0, apart from machine maintenance (band 2) and development, which keep their own rate. Jobs left running after the end of the shift are not counted until their times are checked.') }}
    </p>

    <div v-if="payroll_changes.length" class="mx-4 mt-3 rounded-lg border px-4 py-3 text-sm" :class="payroll_changes.some(change => change.awaiting_approval) ? 'border-red-300 bg-red-50 text-red-900' : 'border-gray-200 bg-gray-50 text-gray-700'">
        <div class="mb-1 font-medium">{{ ctrans('Changes after the payroll lock') }}</div>
        <div v-for="change in payroll_changes" :key="change.id" class="mt-1 flex flex-wrap items-baseline gap-x-3">
            <span class="font-medium">{{ change.worker }}</span>
            <span>{{ ctrans('Week of :date', { date: change.pay_week }) }}</span>
            <span>{{ change.change }}<template v-for="item in change.changes" :key="item.field">, {{ item.field }} {{ item.before ?? '—' }} → {{ item.after ?? '—' }}</template></span>
            <span v-if="change.amount_delta !== null" class="tabular-nums">{{ signedMoney(change.amount_delta) }}</span>
            <span v-if="change.settled_in_period" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ ctrans('In this pay run') }}</span>
            <span class="text-xs">{{ ctrans(':user, :when: :reason', { user: change.changed_by ?? '', when: new Date(change.changed_at).toLocaleString(), reason: change.reason }) }}</span>
            <span v-if="change.approved_at" class="text-xs text-gray-500">{{ ctrans('Approved by :user, :when', { user: change.approved_by ?? '', when: new Date(change.approved_at).toLocaleString() }) }}</span>
            <span v-else-if="change.awaiting_approval && !change.approve_route" class="text-xs font-medium">{{ ctrans('Awaiting the accountant') }}</span>
            <Button v-if="change.approve_route" type="tertiary" size="xs" :label="ctrans('Seen / approved')" :disabled="processing" @click="approvePayrollChange(change)" />
        </div>
    </div>

    <Table :resource="data" class="mt-2">
        <template #cell(worker)="{ item }">
            <div class="flex items-center gap-2 whitespace-nowrap">
                <Link :href="route(item.route.name, item.route.parameters)" class="font-medium text-gray-700 hover:underline">{{ item.worker }}</Link>
                <span v-if="item.is_guru" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600"><FontAwesomeIcon :icon="['fal', 'hat-wizard']" fixed-width aria-hidden="true" /> {{ ctrans('Guru') }}</span>
                <span v-if="item.not_authorised_overtime" class="cursor-help rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800" v-tooltip="item.not_authorised_overtime.days.map((day) => `${day.date}: +${clockMinutes(day.minutes)}${day.note ? ' · ' + day.note : ''}${day.by ? ' (' + day.by + ')' : ''}`).join('\n')">
                    {{ ctrans('Overtime not authorised :time', { time: clockMinutes(item.not_authorised_overtime.minutes) }) }}
                </span>
                <span v-if="item.tablet_over_clocked_hours" class="cursor-help rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800" v-tooltip="ctrans('More time on the tablet than clocked: only the clocked time is paid. Correct the job times or the clocking on Daily hours.')">
                    {{ ctrans('Tablet over clocked :hours h', { hours: item.tablet_over_clocked_hours.toFixed(2) }) }}
                </span>
                <span v-if="item.unapproved_changes" class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800">{{ ctrans('Changed after lock, awaiting accountant') }}</span>
                <span v-if="item.excluded_sessions" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800" v-tooltip="ctrans('Left running after the end of the shift: not counted until the times are checked on the Performance page')">
                    {{ ctrans(':count to check', { count: item.excluded_sessions }) }}
                </span>
            </div>
        </template>
        <template v-for="key in payKeys" :key="key" #[`cell(${key})`]="{ item }">
            <div class="grid grid-cols-[3.5rem_4.5rem] justify-end gap-2 tabular-nums whitespace-nowrap">
                <span class="text-right text-gray-400">{{ item[`${key}_hours`].toFixed(2) }}</span>
                <span class="text-right">{{ money(item[`${key}_amount`]) }}</span>
            </div>
        </template>
        <template #cell(departments)="{ item }">
            <span class="text-sm text-gray-500 whitespace-nowrap">{{ item.departments }}</span>
        </template>
        <template #cell(artisan_pay_type)="{ item }">
            <span class="whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ item.artisan_pay_type === 'hourly' ? ctrans('Hourly') : ctrans('Piece rate') }}</span>
        </template>
        <template #cell(is_agency_worker)="{ item }">
            <span v-if="item.is_agency_worker" class="whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ ctrans('Agency') }}</span>
            <span v-else class="text-xs text-gray-400">{{ ctrans('No') }}</span>
        </template>
        <template v-for="key in ['tablet_hours', 'not_on_tablet_hours']" :key="key" #[`cell(${key})`]="{ item }">
            <span class="tabular-nums text-gray-500">{{ item[key].toFixed(2) }}</span>
        </template>
        <template v-for="key in ['tablet_hours', 'not_on_tablet_hours']" :key="`footer-${key}`" #[`footerRows-cell(${key})`]="{ item }">
            <span class="tabular-nums font-semibold text-gray-700">{{ item[key].toFixed(2) }}</span>
        </template>
        <template #footerRows-cell(artisan_pay_type)><span /></template>
        <template #footerRows-cell(is_agency_worker)><span /></template>
        <template #cell(total)="{ item }">
            <span class="font-semibold tabular-nums">{{ money(item.total) }}</span>
        </template>

        <template v-for="key in payKeys" :key="`footer-${key}`" #[`footerRows-cell(${key})`]="{ item }">
            <div class="grid grid-cols-[3.5rem_4.5rem] justify-end gap-2 tabular-nums whitespace-nowrap font-semibold text-gray-700">
                <span class="text-right">{{ item[`${key}_hours`].toFixed(2) }}</span>
                <span class="text-right">{{ money(item[`${key}_amount`]) }}</span>
            </div>
        </template>
        <template #footerRows-cell(total)="{ item }">
            <span class="font-semibold tabular-nums text-gray-800">{{ money(item.total) }}</span>
        </template>
    </Table>
</template>
