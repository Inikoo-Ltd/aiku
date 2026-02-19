<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Table from '@/Components/Table/Table.vue'
import Modal from '@/Components/Utils/Modal.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Tag from '@/Components/Tag.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core";
import { faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup } from "@fal";

library.add(faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: any
    employeeOptions: { value: number; label: string }[]
    overtimeTypeOptions: { value: number; label: string }[]
    statusOptions: { value: string; label: string }[]
}>()

const showRequestModal = ref(false)
const isEditMode = ref(false)
const editingOvertimeId = ref<number | null>(null)

const hourOptions = Array.from({ length: 24 }, (_, index) =>
    index < 10 ? `0${index}` : `${index}`
)

const minuteOptions = Array.from({ length: 60 }, (_, index) =>
    index < 10 ? `0${index}` : `${index}`
)

const extractTimeParts = (value: string): { hour: string; minute: string } => {
    const date = new Date(value)

    if (!Number.isNaN(date.getTime())) {
        const hour = String(date.getHours()).padStart(2, '0')
        const minute = String(date.getMinutes()).padStart(2, '0')

        return { hour, minute }
    }

    const timePart = value.substring(11, 16)
    const [rawHour, rawMinute] = timePart.split(':')

    const hour = (rawHour ?? '00').padStart(2, '0')
    const minute = (rawMinute ?? '00').padStart(2, '0')

    return { hour, minute }
}

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

const openEditModal = (item: any) => {
    isEditMode.value = true
    editingOvertimeId.value = item.id ?? null
    form.reset()
    form.clearErrors()

    form.employee_id = item.employee_id ?? null
    form.overtime_type_id = item.overtime_type_id ?? null
    form.requested_date = item.requested_date?.slice(0, 10) ?? ''
    form.status = item.status ?? 'pending'
    form.reason = item.reason ?? ''

    const startAt: string | null = item.requested_start_at ?? null

    if (startAt) {
        const { hour, minute } = extractTimeParts(startAt)
        form.start_hour = hour
        form.start_minute = minute
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
            const { hour, minute } = extractTimeParts(recordedStart)
            form.recorded_start_hour = hour
            form.recorded_start_minute = minute
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
            <Button
                type="create"
                :icon="action.icon"
                size="xs"
                :label="action.label"
                @click="openRequestModal"
            />
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
                <select
                    v-model="form.employee_id"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option :value="null" disabled>
                        {{ trans('Select staff member') }}
                    </option>
                    <option v-for="employee in employeeOptions" :key="employee.value" :value="employee.value">
                        {{ employee.label }}
                    </option>
                </select>
                <div v-if="form.errors.employee_id" class="mt-1 text-sm text-red-600">
                    {{ form.errors.employee_id }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('What type of overtime do you want to submit?') }}
                </label>
                <select
                    v-model="form.overtime_type_id"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option :value="null" disabled>
                        {{ trans('Select overtime type') }}
                    </option>
                    <option
                        v-for="overtimeType in overtimeTypeOptions"
                        :key="overtimeType.value"
                        :value="overtimeType.value"
                    >
                        {{ overtimeType.label }}
                    </option>
                </select>
                <div v-if="form.errors.overtime_type_id" class="mt-1 text-sm text-red-600">
                    {{ form.errors.overtime_type_id }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('What date did the overtime occur?') }}
                </label>
                <input
                    v-model="form.requested_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                        <select
                            v-model="form.start_hour"
                            class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="hour in hourOptions" :key="hour" :value="hour">
                                {{ hour }}
                            </option>
                        </select>
                        <select
                            v-model="form.start_minute"
                            class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="minute in minuteOptions" :key="minute" :value="minute">
                                {{ minute }}
                            </option>
                        </select>
                    </div>
                    <div v-if="form.errors.requested_start_at" class="mt-1 text-sm text-red-600">
                        {{ form.errors.requested_start_at }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('How long was the overtime?') }}
                    </label>
                    <div class="mt-1 flex gap-2">
                        <select
                            v-model="form.duration_hours"
                            class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="hour in 13" :key="hour" :value="String(hour - 1)">
                                {{ hour - 1 }} {{ trans('hrs') }}
                            </option>
                        </select>
                        <select
                            v-model="form.duration_minutes"
                            class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="minute in [0, 15, 30, 45]" :key="minute" :value="String(minute)">
                                {{ minute }} {{ trans('mins') }}
                            </option>
                        </select>
                    </div>
                    <div v-if="form.errors.requested_duration_minutes" class="mt-1 text-sm text-red-600">
                        {{ form.errors.requested_duration_minutes }}
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4 mt-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ trans('Recorded time') }}
                </label>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input
                            v-model="form.recorded_same_as_requested"
                            type="radio"
                            :value="true"
                            class="border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <span>{{ trans('Same as requested time') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input
                            v-model="form.recorded_same_as_requested"
                            type="radio"
                            :value="false"
                            class="border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <span>{{ trans('Use different recorded time') }}</span>
                    </label>
                </div>

                <div v-if="!form.recorded_same_as_requested" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Recorded start time') }}
                        </label>
                        <div class="mt-1 flex gap-2">
                            <select
                                v-model="form.recorded_start_hour"
                                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="hour in hourOptions" :key="hour" :value="hour">
                                    {{ hour }}
                                </option>
                            </select>
                            <select
                                v-model="form.recorded_start_minute"
                                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="minute in minuteOptions" :key="minute" :value="minute">
                                    {{ minute }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Recorded duration') }}
                        </label>
                        <div class="mt-1 flex gap-2">
                            <select
                                v-model="form.recorded_duration_hours"
                                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="hour in 13" :key="hour" :value="String(hour - 1)">
                                    {{ hour - 1 }} {{ trans('hrs') }}
                                </option>
                            </select>
                            <select
                                v-model="form.recorded_duration_minutes"
                                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="minute in [0, 15, 30, 45]" :key="minute" :value="String(minute)">
                                    {{ minute }} {{ trans('mins') }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Status') }}
                </label>
                <select
                    v-model="form.status"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option
                        v-for="status in statusOptions"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </option>
                </select>
                <div v-if="form.errors.status" class="mt-1 text-sm text-red-600">
                    {{ form.errors.status }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Any notes?') }}
                </label>
                <textarea
                    v-model="form.reason"
                    rows="3"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <div v-if="form.errors.reason" class="mt-1 text-sm text-red-600">
                    {{ form.errors.reason }}
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="closeRequestModal" />
                <Button
                    type="save"
                    :label="trans('Submit')"
                    :loading="form.processing"
                    @click="submitRequest"
                />
            </div>
        </form>
    </Modal>
</template>
