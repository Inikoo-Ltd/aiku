<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Table from '@/Components/Table/Table.vue'
import Modal from '@/Components/Utils/Modal.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Tag from '@/Components/Tag.vue'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import RadioButton from 'primevue/radiobutton'
import Textarea from 'primevue/textarea'
import { useFormatTime } from '@/Composables/useFormatTime'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core";
import { faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup, faDownload, faFileExcel, faFileCsv } from "@fal";

library.add(faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup, faDownload, faFileExcel, faFileCsv)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: any
    employeeOptions: { value: number; label: string }[]
    overtimeTypeOptions: { value: number; label: string }[]
    statusOptions: { value: string; label: string }[]
}>()

const showRequestModal = ref(false)
const isExportModalOpen = ref(false)
const isEditMode = ref(false)
const editingOvertimeId = ref<number | null>(null)
const isExporting = ref(false)

const hourOptions = Array.from({ length: 24 }, (_, index) =>
    index < 10 ? `0${index}` : `${index}`
)

const minuteOptions = Array.from({ length: 60 }, (_, index) =>
    index < 10 ? `0${index}` : `${index}`
)

const durationBaseMinutes = [0, 15, 30, 45]

const form = useForm<{
    employee_id: number | null
    overtime_type_id: number | null
    requested_date: string
    start_hour: string
    start_minute: string
    duration_hours: string
    duration_minutes: string
    reason: string
    status: string
    recorded_same_as_requested: boolean
    recorded_start_hour: string
    recorded_start_minute: string
    recorded_duration_hours: string
    recorded_duration_minutes: string
}>({
    employee_id: null,
    overtime_type_id: null,
    requested_date: '',
    start_hour: '00',
    start_minute: '00',
    duration_hours: '0',
    duration_minutes: '0',
    reason: '',
    status: 'pending',
    recorded_same_as_requested: true,
    recorded_start_hour: '00',
    recorded_start_minute: '00',
    recorded_duration_hours: '0',
    recorded_duration_minutes: '0',
})

const exportForm = useForm({
    from: '',
    to: '',
    type: '',
    status: '',
    employee_id: null as number | null,
    format: 'xlsx',
})

const hourSelectOptions = computed(() =>
    hourOptions.map((hour) => ({
        label: hour,
        value: hour,
    }))
)

const minuteSelectOptions = computed(() =>
    minuteOptions.map((minute) => ({
        label: minute,
        value: minute,
    }))
)

const durationHourOptions = computed(() =>
    Array.from({ length: 13 }, (_, index) => ({
        label: `${index} ${trans('hrs')}`,
        value: String(index),
    }))
)

const durationMinuteOptions = computed(() =>
    durationBaseMinutes.map((minute) => ({
        label: `${minute} ${trans('mins')}`,
        value: String(minute),
    }))
)

const exportFormatOptions = computed(() => [
    { label: trans('Excel (XLSX)'), value: 'xlsx' },
    { label: trans('CSV'), value: 'csv' },
])

const getFormError = (key: string): string | undefined => {
    return (form.errors as Record<string, string | undefined>)[key]
}

const formatDuration = (minutes?: number | null): string => {
    if (!minutes) {
        return '-'
    }

    const hours = Math.floor(minutes / 60)
    const remainingMinutes = minutes % 60

    if (hours && remainingMinutes) {
        return `${hours}h ${remainingMinutes}m`
    }

    if (hours) {
        return `${hours}h`
    }

    return `${remainingMinutes}m`
}

const openRequestModal = () => {
    isEditMode.value = false
    editingOvertimeId.value = null
    form.reset()
    form.clearErrors()
    form.start_hour = '00'
    form.start_minute = '00'
    form.duration_hours = '0'
    form.duration_minutes = '0'
    form.status = 'pending'
    form.recorded_same_as_requested = true
    form.recorded_start_hour = '00'
    form.recorded_start_minute = '00'
    form.recorded_duration_hours = '0'
    form.recorded_duration_minutes = '0'
    showRequestModal.value = true
}

const openExportModal = () => {
    exportForm.reset()
    exportForm.format = 'xlsx'
    isExportModalOpen.value = true
}

const closeExportModal = () => {
    isExportModalOpen.value = false
    exportForm.reset()
}

const submitExport = () => {
    const orgId = (route().params as Record<string, string | number | undefined>).organisation

    if (!orgId) {
        alert('Error: Cannot find organisation ID')
        return
    }

    isExporting.value = true

    const exportParams: Record<string, any> = {
        organisation: orgId,
        format: exportForm.format,
    }

    if (exportForm.from) exportParams.from = exportForm.from
    if (exportForm.to) exportParams.to = exportForm.to
    if (exportForm.type) exportParams.type = exportForm.type
    if (exportForm.status) exportParams.status = exportForm.status
    if (exportForm.employee_id) exportParams.employee_id = exportForm.employee_id

    isExportModalOpen.value = false
    window.location.href = route('grp.org.hr.overtime.export', exportParams)

    setTimeout(() => {
        isExporting.value = false
    }, 1500)
}

const getFullDate = (dateStr: string, hour: string, minute: string) => {
    const [y, m, d] = dateStr.split('-').map(Number);
    return new Date(y, m - 1, d, Number(hour), Number(minute));
}

const toLocalISOString = (date: Date) => {
    const tzo = -date.getTimezoneOffset(),
        dif = tzo >= 0 ? '+' : '-',
        pad = (num: number) => {
            const norm = Math.floor(Math.abs(num));
            return (norm < 10 ? '0' : '') + norm;
        };
    return date.getFullYear() +
        '-' + pad(date.getMonth() + 1) +
        '-' + pad(date.getDate()) +
        'T' + pad(date.getHours()) +
        ':' + pad(date.getMinutes()) +
        ':' + pad(date.getSeconds()) +
        dif + pad(tzo / 60) + ':' + pad(tzo % 60);
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

const requestedDateModel = computed<Date | null>({
    get: () => parseYmdDate(form.requested_date),
    set: (value) => {
        form.requested_date = formatYmdDate(value)
    },
})

const exportFromDateModel = computed<Date | null>({
    get: () => parseYmdDate(exportForm.from),
    set: (value) => {
        exportForm.from = formatYmdDate(value)
    },
})

const exportToDateModel = computed<Date | null>({
    get: () => parseYmdDate(exportForm.to),
    set: (value) => {
        exportForm.to = formatYmdDate(value)
    },
})

const openEditModal = (item: any) => {
    isEditMode.value = true
    editingOvertimeId.value = item.id ?? null
    form.reset()
    form.clearErrors()

    form.employee_id = item.employee_id ?? null
    form.overtime_type_id = item.overtime_type_id ?? null
    form.requested_date = item.requested_date ? useFormatTime(item.requested_date, { formatTime: 'yyyy-MM-dd' }) : ''
    form.status = item.status ?? 'pending'
    form.reason = item.reason ?? ''

    const startAt: string | null = item.requested_start_at ?? null

    if (startAt) {
        form.start_hour = useFormatTime(startAt, { formatTime: 'HH' })
        form.start_minute = useFormatTime(startAt, { formatTime: 'mm' })
    } else {
        form.start_hour = '00'
        form.start_minute = '00'
    }

    const durationMinutes: number = item.requested_duration_minutes ?? 0
    const durationHours = Math.floor(durationMinutes / 60)
    const remainingMinutes = durationMinutes % 60

    form.duration_hours = String(durationHours)
    form.duration_minutes = String(remainingMinutes)

    if (
        item.recorded_start_at &&
        item.recorded_duration_minutes &&
        item.recorded_start_at !== item.requested_start_at
    ) {
        form.recorded_same_as_requested = false

        const recordedStart: string | null = item.recorded_start_at ?? null

        if (recordedStart) {
            form.recorded_start_hour = useFormatTime(recordedStart, { formatTime: 'HH' })
            form.recorded_start_minute = useFormatTime(recordedStart, { formatTime: 'mm' })
        } else {
            form.recorded_start_hour = '00'
            form.recorded_start_minute = '00'
        }

        const recordedDuration: number = item.recorded_duration_minutes ?? 0
        const recordedDurationHours = Math.floor(recordedDuration / 60)
        const recordedRemainingMinutes = recordedDuration % 60

        form.recorded_duration_hours = String(recordedDurationHours)
        form.recorded_duration_minutes = String(recordedRemainingMinutes)
    } else {
        form.recorded_same_as_requested = true
        form.recorded_start_hour = form.start_hour
        form.recorded_start_minute = form.start_minute
        form.recorded_duration_hours = form.duration_hours
        form.recorded_duration_minutes = form.duration_minutes
    }

    showRequestModal.value = true
}

const closeRequestModal = () => {
    showRequestModal.value = false
    form.clearErrors()
    isEditMode.value = false
    editingOvertimeId.value = null
}

const submitRequest = () => {
    form.transform((data) => {
        const requestedStartAt = getFullDate(data.requested_date, data.start_hour, data.start_minute)
        const requestedDurationMinutes = (Number(data.duration_hours) * 60) + Number(data.duration_minutes)
        const requestedEndAt = new Date(requestedStartAt.getTime() + requestedDurationMinutes * 60000)

        let recordedStartAt = null
        let recordedEndAt = null
        let recordedDurationMinutes = 0

        if (data.recorded_same_as_requested) {
            recordedStartAt = requestedStartAt
            recordedEndAt = requestedEndAt
            recordedDurationMinutes = requestedDurationMinutes
        } else {
            recordedStartAt = getFullDate(data.requested_date, data.recorded_start_hour, data.recorded_start_minute)
            recordedDurationMinutes = (Number(data.recorded_duration_hours) * 60) + Number(data.recorded_duration_minutes)
            recordedEndAt = new Date(recordedStartAt.getTime() + recordedDurationMinutes * 60000)
        }

        return {
            ...data,
            requested_start_at: toLocalISOString(requestedStartAt),
            requested_end_at: toLocalISOString(requestedEndAt),
            requested_duration_minutes: requestedDurationMinutes,
            recorded_start_at: recordedStartAt ? toLocalISOString(recordedStartAt) : null,
            recorded_end_at: recordedEndAt ? toLocalISOString(recordedEndAt) : null,
            recorded_duration_minutes: recordedDurationMinutes,
        }
    })

    if (isEditMode.value && editingOvertimeId.value) {
        form.patch(
            route('grp.org.hr.overtime_requests.update', {
                ...route().params,
                overtimeRequest: editingOvertimeId.value,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeRequestModal()
                },
            }
        )
    } else {
        form.post(route('grp.org.hr.overtime_requests.store', route().params), {
            preserveScroll: true,
            onSuccess: () => {
                closeRequestModal()
            },
        })
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #button-overtime-request="{ action }">
            <div class="flex gap-2">
                <Button
                    type="secondary"
                    :icon="faDownload"
                    size="xs"
                    :label="trans('Export')"
                    @click="openExportModal"
                />
                <Button
                    type="create"
                    :icon="action.icon"
                    size="xs"
                    :label="action.label"
                    @click="openRequestModal"
                />
            </div>
        </template>
    </PageHeading>

    <div class="mt-4">
        <Table :resource="data">
            <template #cell(employee_name)="{ item }">
                <span class="whitespace-nowrap">
                    {{ item.employee_name }}
                </span>
            </template>

            <template #cell(requested_date)="{ item }">
                <span class="whitespace-nowrap">
                    {{ useFormatTime(item.requested_date) }}
                </span>
            </template>

            <template #cell(approver_name)="{ item }">
                <span class="whitespace-nowrap">
                    {{ item.approver_name ?? '—' }}
                </span>
            </template>

            <template #cell(requested_start_at)="{ item }">
                <span class="whitespace-nowrap">
                    {{ useFormatTime(item.requested_start_at, { formatTime: 'hm' }) }}
                </span>
            </template>

            <template #cell(recorded_start_at)="{ item }">
                <span class="whitespace-nowrap">
                    {{ item.recorded_start_at ? useFormatTime(item.recorded_start_at, { formatTime: 'hm' }) : '—' }}
                </span>
            </template>

            <template #cell(recorded_end_at)="{ item }">
                <span class="whitespace-nowrap">
                    {{ item.recorded_end_at ? useFormatTime(item.recorded_end_at, { formatTime: 'hm' }) : '—' }}
                </span>
            </template>

            <template #cell(requested_duration_minutes)="{ item }">
                <span class="whitespace-nowrap">
                    {{ formatDuration(item.requested_duration_minutes) }}
                </span>
            </template>

            <template #cell(recorded_duration_minutes)="{ item }">
                <span class="whitespace-nowrap">
                    {{ item.recorded_duration_minutes ? formatDuration(item.recorded_duration_minutes) : '—' }}
                </span>
            </template>

            <template #cell(lieu_requested_minutes)="{ item }">
                <span class="whitespace-nowrap">
                    {{ formatDuration(item.lieu_requested_minutes) }}
                </span>
            </template>

            <template #cell(recorder_name)="{ item }">
                <span class="whitespace-nowrap">
                    {{ item.recorder_name ?? '—' }}
                </span>
            </template>

            <template #cell(status)="{ item }">
                <Tag
                    :theme="item.status === 'approved' ? 3 : item.status === 'pending' ? 1 : item.status === 'rejected' ? 7 : 99"
                    size="xs"
                    :label="item.status"
                >
                    <template #label>
                        <span class="capitalize">
                            {{ item.status }}
                        </span>
                    </template>
                </Tag>
            </template>

            <template #cell(reason)="{ item }">
                <span class="whitespace-nowrap">
                    {{ item.reason ?? '—' }}
                </span>
            </template>

            <template #cell(options)="{ item }">
                <div class="flex gap-2">
                    <Button
                        type="transparent"
                        size="xs"
                        :icon="faEdit"
                        :label="trans('Edit')"
                        @click="() => openEditModal(item)"
                    />
                    <ModalConfirmation
                        :routeYes="{
                            name: 'grp.org.hr.overtime_requests.approve',
                            parameters: { ...route().params, overtimeRequest: item.id },
                            method: 'patch',
                        }"
                    >
                        <template #default="{ changeModel, isLoadingdelete }">
                            <Button
                                type="positive"
                                size="xs"
                                :icon="faCheck"
                                :label="trans('Approve')"
                                :loading="isLoadingdelete"
                                @click="changeModel"
                            />
                        </template>
                        <template #btn-yes="{ clickYes, isLoadingdelete }">
                            <Button
                                :loading="isLoadingdelete"
                                @click="clickYes"
                                :label="trans('Yes, approve')"
                                type="positive"
                            />
                        </template>
                    </ModalConfirmation>
                    <ModalConfirmation
                        :routeYes="{
                            name: 'grp.org.hr.overtime_requests.reject',
                            parameters: { ...route().params, overtimeRequest: item.id },
                            method: 'patch',
                        }"
                    >
                        <template #default="{ changeModel, isLoadingdelete }">
                            <Button
                                type="warning"
                                size="xs"
                                :icon="faTimes"
                                :label="trans('Reject')"
                                :loading="isLoadingdelete"
                                @click="changeModel"
                            />
                        </template>
                        <template #btn-yes="{ clickYes, isLoadingdelete }">
                            <Button
                                :loading="isLoadingdelete"
                                @click="clickYes"
                                :label="trans('Yes, reject')"
                                type="warning"
                            />
                        </template>
                    </ModalConfirmation>
                    <ModalConfirmationDelete
                        :routeDelete="{
                            name: 'grp.org.hr.overtime_requests.delete',
                            parameters: { ...route().params, overtimeRequest: item.id },
                        }"
                    >
                        <template #default="{ changeModel, isLoadingdelete }">
                            <Button
                                type="negative"
                                size="xs"
                                :icon="faTrash"
                                :label="trans('Delete')"
                                :loading="isLoadingdelete"
                                @click="changeModel"
                            />
                        </template>
                    </ModalConfirmationDelete>
                </div>
            </template>
        </Table>
    </div>

    <Modal :isOpen="showRequestModal" @onClose="closeRequestModal" width="w-full max-w-lg">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            {{ trans('Create overtime request') }}
        </h2>

        <form class="space-y-4" @submit.prevent="submitRequest">
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Which staff member?') }}
                </label>
                <Select
                    v-model="form.employee_id"
                    :options="employeeOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="mt-1 w-full"
                    :placeholder="trans('Select staff member')"
                />
                <div v-if="form.errors.employee_id" class="mt-1 text-sm text-red-600">
                    {{ form.errors.employee_id }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('What type of overtime do you want to submit?') }}
                </label>
                <Select
                    v-model="form.overtime_type_id"
                    :options="overtimeTypeOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="mt-1 w-full"
                    :placeholder="trans('Select overtime type')"
                />
                <div v-if="form.errors.overtime_type_id" class="mt-1 text-sm text-red-600">
                    {{ form.errors.overtime_type_id }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('What date did the overtime occur?') }}
                </label>
                <DatePicker
                    v-model="requestedDateModel"
                    class="mt-1 w-full"
                    dateFormat="yy-mm-dd"
                    showIcon
                />
                <div v-if="form.errors.requested_date" class="mt-1 text-sm text-red-600">
                    {{ form.errors.requested_date }}
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('What time did it start?') }}
                    </label>
                    <div class="mt-1 flex gap-2">
                        <Select
                            v-model="form.start_hour"
                            :options="hourSelectOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                        <Select
                            v-model="form.start_minute"
                            :options="minuteSelectOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                    </div>
                    <div v-if="getFormError('requested_start_at')" class="mt-1 text-sm text-red-600">
                        {{ getFormError('requested_start_at') }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('How long was the overtime?') }}
                    </label>
                    <div class="mt-1 flex gap-2">
                        <Select
                            v-model="form.duration_hours"
                            :options="durationHourOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                        <Select
                            v-model="form.duration_minutes"
                            :options="durationMinuteOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                    </div>
                    <div v-if="getFormError('requested_duration_minutes')" class="mt-1 text-sm text-red-600">
                        {{ getFormError('requested_duration_minutes') }}
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4 mt-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ trans('Recorded time') }}
                </label>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <RadioButton
                            v-model="form.recorded_same_as_requested"
                            :value="true"
                            inputId="recorded_same_as_requested_true"
                        />
                        <span>{{ trans('Same as requested time') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <RadioButton
                            v-model="form.recorded_same_as_requested"
                            :value="false"
                            inputId="recorded_same_as_requested_false"
                        />
                        <span>{{ trans('Use different recorded time') }}</span>
                    </label>
                </div>

                <div v-if="!form.recorded_same_as_requested" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Recorded start time') }}
                        </label>
                        <div class="mt-1 flex gap-2">
                            <Select
                                v-model="form.recorded_start_hour"
                                :options="hourSelectOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                            />
                            <Select
                                v-model="form.recorded_start_minute"
                                :options="minuteSelectOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Recorded duration') }}
                        </label>
                        <div class="mt-1 flex gap-2">
                            <Select
                                v-model="form.recorded_duration_hours"
                                :options="durationHourOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                            />
                            <Select
                                v-model="form.recorded_duration_minutes"
                                :options="durationMinuteOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Status') }}
                </label>
                <Select
                    v-model="form.status"
                    :options="statusOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="mt-1 w-full"
                />
                <div v-if="form.errors.status" class="mt-1 text-sm text-red-600">
                    {{ form.errors.status }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Any notes?') }}
                </label>
                <Textarea
                    v-model="form.reason"
                    rows="3"
                    class="mt-1 w-full"
                />
                <div v-if="form.errors.reason" class="mt-1 text-sm text-red-600">
                    {{ form.errors.reason }}
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button type="tertiary" :label="trans('Cancel')" @click.prevent="closeRequestModal" />
                <Button
                    type="save"
                    :label="trans('Submit')"
                    :loading="form.processing"
                    nativeType="submit"
                />
            </div>
        </form>
    </Modal>

    <Modal :isOpen="isExportModalOpen" @onClose="closeExportModal" width="w-full max-w-lg">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            {{ trans('Export Overtime Reports') }}
        </h2>
        <p class="text-sm text-gray-600 mb-4">
            {{ trans('Select filters and export format for your overtime report.') }}
        </p>

        <form @submit.prevent="submitExport" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('From Date') }}
                    </label>
                    <DatePicker
                        v-model="exportFromDateModel"
                        class="mt-1 w-full"
                        dateFormat="yy-mm-dd"
                        showIcon
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('To Date') }}
                    </label>
                    <DatePicker
                        v-model="exportToDateModel"
                        class="mt-1 w-full"
                        dateFormat="yy-mm-dd"
                        showIcon
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('Overtime Type') }}
                    </label>
                    <Select
                        v-model="exportForm.type"
                        :options="[{ value: '', label: trans('All Types') }, ...overtimeTypeOptions]"
                        optionLabel="label"
                        optionValue="value"
                        class="mt-1 w-full"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('Status') }}
                    </label>
                    <Select
                        v-model="exportForm.status"
                        :options="[{ value: '', label: trans('All Statuses') }, ...statusOptions]"
                        optionLabel="label"
                        optionValue="value"
                        class="mt-1 w-full"
                    />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Employee') }}
                </label>
                <Select
                    v-model="exportForm.employee_id"
                    :options="[{ value: null, label: trans('All Employees') }, ...employeeOptions]"
                    optionLabel="label"
                    optionValue="value"
                    class="mt-1 w-full"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Export Format') }}
                </label>
                <div class="mt-2 flex gap-4">
                    <label
                        v-for="formatOption in exportFormatOptions"
                        :key="formatOption.value"
                        class="flex cursor-pointer items-center gap-2"
                    >
                        <RadioButton
                            v-model="exportForm.format"
                            :value="formatOption.value"
                            :inputId="`overtime-export-format-${formatOption.value}`"
                        />
                        <span class="text-sm">{{ formatOption.label }}</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button @click="closeExportModal" :label="trans('Cancel')" type="tertiary" />
                <Button
                    type="save"
                    nativeType="submit"
                    :label="trans('Export')"
                    :loading="isExporting"
                    icon="fal fa-download"
                />
            </div>
        </form>
    </Modal>
</template>
