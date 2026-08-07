<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import TableTimesheets from "@/Components/Tables/Grp/Org/HumanResources/TableTimesheets.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from 'laravel-vue-i18n'
import { format, startOfWeek, startOfMonth, startOfQuarter, startOfYear, addDays } from 'date-fns'
import { ref, computed } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import qs from 'qs'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCalendarAlt } from '@fal'
library.add(faCalendarAlt)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string,
        navigation: any
    }
    employee_view: {
        current: string,
        navigation: any
    }
    employeeOptions?: { value: number; label: string }[]
    employees?: {}
    employee?: {}
}>()

const showCreateTimesheetModal = ref(false)

const createTimesheetForm = useForm<{
    employee_id: number | null
    date: string
    clock_in: string
    clock_out: string
    notes: string
}>({
    employee_id: null,
    date: '',
    clock_in: '',
    clock_out: '',
    notes: '',
})

const openCreateTimesheetModal = () => {
    createTimesheetForm.reset()
    createTimesheetForm.clearErrors()
    showCreateTimesheetModal.value = true
}

const closeCreateTimesheetModal = () => {
    showCreateTimesheetModal.value = false
    createTimesheetForm.reset()
    createTimesheetForm.clearErrors()
}

const submitCreateTimesheet = () => {
    createTimesheetForm.post(route('grp.org.hr.timesheets.store', route().params), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateTimesheetModal()
        },
    })
}

const parseYmdDate = (value: string): Date | null => {
    if (!value) {
        return null
    }

    const [year, month, day] = value.split('-').map(Number)
    if (!year || !month || !day) {
        return null
    }

    return new Date(year, month - 1, day)
}

const formatYmdDate = (date: Date | null): string => {
    if (!date) {
        return ''
    }

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

const parseHmTime = (value: string): Date | null => {
    if (!value) {
        return null
    }

    const [hours, minutes] = value.split(':').map(Number)
    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
        return null
    }

    const date = new Date()
    date.setHours(hours, minutes, 0, 0)

    return date
}

const formatHmTime = (date: Date | null): string => {
    if (!date) {
        return ''
    }

    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')

    return `${hours}:${minutes}`
}

const timesheetDateModel = computed<Date | null>({
    get: () => parseYmdDate(createTimesheetForm.date),
    set: (value) => {
        createTimesheetForm.date = formatYmdDate(value)
    },
})

const clockInModel = computed<Date | null>({
    get: () => parseHmTime(createTimesheetForm.clock_in),
    set: (value) => {
        createTimesheetForm.clock_in = formatHmTime(value)
    },
})

const clockOutModel = computed<Date | null>({
    get: () => parseHmTime(createTimesheetForm.clock_out),
    set: (value) => {
        createTimesheetForm.clock_out = formatHmTime(value)
    },
})


const currentTab = ref(props.tabs?.current || 'employee')
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const currentData = computed(() => {
    return (props as any)[currentTab.value]
})

const currentEmployeeView = ref(props.employee_view?.current || 'overview')

function handleEmployeeViewUpdate(view: string) {
    currentEmployeeView.value = view
    const params = new URLSearchParams(location.search)
    params.set('view', view)
    router.get(location.pathname + `?${params.toString()}`, {}, { preserveState: true, preserveScroll: true })
}

const periodPrefix = computed(() => currentTab.value === 'employee' ? 'employee' : 'employees')

const periodParam = computed(() => {
    const url = usePage().url as string
    const queryString = url.includes('?') ? url.slice(url.indexOf('?') + 1) : ''
    const params = qs.parse(queryString) as Record<string, any>

    return params?.[`${periodPrefix.value}_period`] ?? params?.period ?? null
})

function periodLabel(period: any) {
    if (!period) return false

    if (period.day) {
        // May 28th, 2024
        const date = new Date(period.day.slice(0, 4), period.day.slice(4, 6) - 1, period.day.slice(6, 8))
        return `${format(date, 'MMMM do, yyyy')}`
    }

    if (period.week) {
        // May 26th, 2024 - June 1st, 2024
        const year = period.week.slice(0, 4)
        const weekNumber = parseInt(period.week.slice(4), 10)
        const startOfTheWeek = startOfWeek(addDays(new Date(year, 0, 1), (weekNumber - 1) * 7), { weekStartsOn: 1 })
        return `${format(startOfTheWeek, 'MMMM do, yyyy')} - ${format(addDays(startOfTheWeek, 6), 'MMMM do, yyyy')}`
    }

    if (period.month) {
        // May 2024
        const year = period.month.slice(0, 4)
        const monthNumber = period.month.slice(4, 6) - 1
        const startOfTheMonth = startOfMonth(new Date(year, monthNumber))
        return `${format(startOfTheMonth, 'MMMM yyyy')}`
    }

    if (period.quarter) {
        // April 2024 - June 2024
        const year = period.quarter.slice(0, 4)
        const quarterNumber = parseInt(period.quarter.slice(5), 10)
        const startOfTheQuarter = startOfQuarter(new Date(year, (quarterNumber - 1) * 3))
        return `${format(startOfTheQuarter, 'MMMM yyyy')} - ${format(addDays(startOfTheQuarter, 89), 'MMMM yyyy')}`
    }

    if (period.year) {
        // 2024
        const year = period.year
        const startOfTheYear = startOfYear(new Date(year))
        return `${format(startOfTheYear, 'yyyy')}`
    }
}

</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #button-timesheet="{ action }">
            <Button
                :icon="action.icon"
                :label="action.label"
                :style="action.style"
                @click="openCreateTimesheetModal"
            />
        </template>
    </PageHeading>

    <Tabs v-if="Object.keys(tabs.navigation || {}).length" :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <Tabs
        v-if="currentTab === 'employee' && Object.keys(employee_view.navigation || {}).length"
        :current="currentEmployeeView"
        :navigation="employee_view.navigation"
        @update:tab="handleEmployeeViewUpdate"
        class="mt-2"
    />

    <div v-if="periodParam" class="mt-3 mb-1">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-sm font-semibold text-indigo-700">
            <font-awesome-icon :icon="['fal', 'calendar-alt']" class="text-indigo-400" />
            {{ periodLabel(periodParam) }}
        </span>
    </div>

    <!-- TABLE -->
    <TableTimesheets :key="`${currentTab}-${currentEmployeeView}`" :tab="currentTab" :data="currentData" />

    <Modal :isOpen="showCreateTimesheetModal" @onClose="closeCreateTimesheetModal" width="w-full max-w-lg">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            {{ trans('Add timesheet') }}
        </h2>

        <form class="space-y-4" @submit.prevent="submitCreateTimesheet">
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Employee') }}
                </label>
                <Select
                    v-model="createTimesheetForm.employee_id"
                    :options="employeeOptions"
                    optionLabel="label"
                    optionValue="value"
                    filter
                    class="mt-1 w-full"
                    :placeholder="trans('Select employee')"
                />
                <div v-if="createTimesheetForm.errors.employee_id" class="mt-1 text-sm text-red-600">
                    {{ createTimesheetForm.errors.employee_id }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Date') }}
                </label>
                <DatePicker
                    v-model="timesheetDateModel"
                    class="mt-1 w-full"
                    dateFormat="yy-mm-dd"
                    showIcon
                />
                <div v-if="createTimesheetForm.errors.date" class="mt-1 text-sm text-red-600">
                    {{ createTimesheetForm.errors.date }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('Clock in') }} <span class="text-gray-400 font-normal">({{ trans('optional') }})</span>
                    </label>
                    <DatePicker
                        v-model="clockInModel"
                        timeOnly
                        hourFormat="24"
                        class="mt-1 w-full"
                        showIcon
                    />
                    <div v-if="createTimesheetForm.errors.clock_in" class="mt-1 text-sm text-red-600">
                        {{ createTimesheetForm.errors.clock_in }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('Clock out') }} <span class="text-gray-400 font-normal">({{ trans('optional') }})</span>
                    </label>
                    <DatePicker
                        v-model="clockOutModel"
                        timeOnly
                        hourFormat="24"
                        :disabled="!createTimesheetForm.clock_in"
                        class="mt-1 w-full"
                        showIcon
                    />
                    <div v-if="createTimesheetForm.errors.clock_out" class="mt-1 text-sm text-red-600">
                        {{ createTimesheetForm.errors.clock_out }}
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Notes') }} <span class="text-gray-400 font-normal">({{ trans('optional') }})</span>
                </label>
                <Textarea
                    v-model="createTimesheetForm.notes"
                    rows="3"
                    class="mt-1 block w-full"
                />
                <div v-if="createTimesheetForm.errors.notes" class="mt-1 text-sm text-red-600">
                    {{ createTimesheetForm.errors.notes }}
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button type="tertiary" @click="closeCreateTimesheetModal">
                    {{ trans('Cancel') }}
                </Button>
                <Button type="save" :loading="createTimesheetForm.processing" @click="submitCreateTimesheet">
                    {{ trans('Save') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
