<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import TableTimesheets from "@/Components/Tables/Grp/Org/HumanResources/TableTimesheets.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from 'laravel-vue-i18n'
import { ref, computed } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faCalendarDay,
    faUsers,
    faUser,
    faThList,
    faStopwatch,
    faExclamationCircle,
    faSackDollar,
    faCoins,
    faBriefcase,
} from '@fal'
library.add(
    faCalendarDay,
    faUsers,
    faUser,
    faThList,
    faStopwatch,
    faExclamationCircle,
    faSackDollar,
    faCoins,
    faBriefcase,
)

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
    employeeContext?: { id: number; slug: string; name: string } | null
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
    employee_id: props.employeeContext?.id ?? null,
    date: '',
    clock_in: '',
    clock_out: '',
    notes: '',
})

const openCreateTimesheetModal = () => {
    createTimesheetForm.reset()
    createTimesheetForm.clearErrors()
    createTimesheetForm.employee_id = props.employeeContext?.id ?? null
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

const showExportModal = ref(false)
const exportEmployeeIds = ref<number[]>([])
const exportType = ref<'xlsx' | 'csv'>('xlsx')

const openExportModal = () => {
    exportEmployeeIds.value = props.employeeContext ? [props.employeeContext.id] : []
    exportType.value = 'xlsx'
    showExportModal.value = true
}

const closeExportModal = () => {
    showExportModal.value = false
}

const submitExport = () => {
    const routeName = currentTab.value === 'employee'
        ? 'grp.org.hr.timesheets.export_by_employee'
        : 'grp.org.hr.timesheets.export_by_date'

    const params: Record<string, any> = { ...(route().params as Record<string, any>) }
    delete params.employee
    params.type = exportType.value

    const employeeIds = props.employeeContext ? [props.employeeContext.id] : exportEmployeeIds.value

    if (employeeIds.length) {
        params.employee_id = employeeIds
    } else {
        delete params.employee_id
    }

    if (currentTab.value === 'employee') {
        params.view = currentEmployeeView.value
    }

    window.location.href = route(routeName, params)
    closeExportModal()
}

</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #button-export-timesheets="{ action }">
            <Button
                :icon="action.icon"
                :label="action.label"
                :style="action.style"
                @click="openExportModal"
            />
        </template>

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
                <div v-if="employeeContext" class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                    {{ employeeContext.name }}
                </div>
                <Select
                    v-else
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

    <Modal :isOpen="showExportModal" @onClose="closeExportModal" width="w-full max-w-lg">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            {{ trans('Export timesheets') }}
        </h2>

        <p class="text-sm text-gray-500 mb-4">
            {{ trans('Exports whatever is currently on screen: the active tab, date range and view.') }}
        </p>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Employees') }}
                    <span v-if="!employeeContext" class="text-gray-400 font-normal">({{ trans('leave empty for everyone') }})</span>
                </label>
                <div v-if="employeeContext" class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                    {{ employeeContext.name }}
                </div>
                <MultiSelect
                    v-else
                    v-model="exportEmployeeIds"
                    :options="employeeOptions"
                    optionLabel="label"
                    optionValue="value"
                    filter
                    :maxSelectedLabels="3"
                    class="mt-1 w-full"
                    :placeholder="trans('All employees')"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Format') }}
                </label>
                <Select
                    v-model="exportType"
                    :options="[{ label: 'Excel (.xlsx)', value: 'xlsx' }, { label: 'CSV (.csv)', value: 'csv' }]"
                    optionLabel="label"
                    optionValue="value"
                    class="mt-1 w-full"
                />
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <Button type="tertiary" @click="closeExportModal">
                {{ trans('Cancel') }}
            </Button>
            <Button type="save" @click="submitExport">
                {{ trans('Export') }}
            </Button>
        </div>
    </Modal>
</template>
