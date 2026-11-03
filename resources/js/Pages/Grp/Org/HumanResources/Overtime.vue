<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Pagination from '@/Components/Table/Pagination.vue'
import Modal from '@/Components/Utils/Modal.vue'
import Dialog from 'primevue/dialog'
import OvertimeTimeFields from '@/Components/HumanResources/OvertimeTimeFields.vue'
import SegmentedToggle from '@/Components/Utils/SegmentedToggle.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import HrRequestCard from '@/Components/HumanResources/HrRequestCard.vue'
import HrRequestFilters from '@/Components/HumanResources/HrRequestFilters.vue'
import HrConfirmDialog from '@/Components/HumanResources/HrConfirmDialog.vue'
import EmployeeAvatar from '@/Components/HumanResources/EmployeeAvatar.vue'
import { debounce } from 'lodash-es'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import RadioButton from 'primevue/radiobutton'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { notify } from '@kyvg/vue3-notification'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useFormatTime } from '@/Composables/useFormatTime'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import { ctrans, ctransChoice } from '@/Composables/useTrans'
import { library } from "@fortawesome/fontawesome-svg-core";
import { faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup, faDownload, faFileExcel, faFileCsv, faClock } from "@fal";

library.add(faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup, faDownload, faFileExcel, faFileCsv, faClock)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: any
    employeeOptions: { value: number; label: string }[]
    overtimeTypeOptions: { value: number; label: string }[]
    statusOptions: { value: string; label: string }[]
    can_edit?: boolean
    filters?: {
        search: string
        status: string | null
        from: string | null
        to: string | null
    }
}>()

const showRequestModal = ref(false)
const isExportModalOpen = ref(false)
const isEditMode = ref(false)
const editingOvertimeId = ref<number | null>(null)
const isExporting = ref(false)
const processingOvertimeId = ref<number | null>(null)
const confirm = useConfirm()
const confirmGroup = 'overtime-requests'

const isPending = (item: any): boolean => item.status === 'pending'

const overtimeRequestUrl = (routeName: string, item: any): string =>
    route(routeName, { ...route().params, overtimeRequest: item.id })

const overtimeVisitOptions = (item: any, failureText: string) => ({
    preserveScroll: true,
    onStart: () => {
        processingOvertimeId.value = item.id
    },
    onFinish: () => {
        processingOvertimeId.value = null
    },
    onError: (errors: Record<string, string>) => {
        notify({
            title: ctrans('Something went wrong'),
            text: errors?.status ?? failureText,
            type: 'error',
        })
    },
})

const filterState = reactive({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? 'all',
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
})
const isLoadingList = ref(false)

const statusFilterOptions = computed(() => [
    { label: ctrans('All'), value: 'all' },
    { label: ctrans('Pending'), value: 'pending', tone: 'amber' as const },
    { label: ctrans('Approved'), value: 'approved', tone: 'emerald' as const },
    { label: ctrans('Rejected'), value: 'rejected', tone: 'red' as const },
])

const rows = computed<any[]>(() => props.data?.data ?? [])
const paginationMeta = computed(() => props.data?.meta ?? props.data ?? {})
const totalRequests = computed<number>(() => paginationMeta.value?.total ?? rows.value.length)
const hasActiveFilters = computed(
    () => filterState.search.trim() !== '' || filterState.status !== 'all' || filterState.from !== '' || filterState.to !== ''
)

const listVisitOptions = {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['data', 'filters'],
    onStart: () => {
        isLoadingList.value = true
    },
    onFinish: () => {
        isLoadingList.value = false
    },
}

const reloadList = (extraQuery: Record<string, any> = {}) => {
    const query: Record<string, any> = { ...extraQuery }
    const search = filterState.search.trim()

    if (search) query.filter = { global: search }
    if (filterState.status !== 'all') query.status = filterState.status
    if (filterState.from) query.from = filterState.from
    if (filterState.to) query.to = filterState.to

    router.get(
        route('grp.org.hr.overtime.index', { organisation: (route().params as Record<string, any>).organisation }),
        query,
        listVisitOptions
    )
}

const reloadListDebounced = debounce(() => reloadList(), 350)

watch(
    () => filterState.search,
    () => reloadListDebounced()
)

watch(
    () => [filterState.status, filterState.from, filterState.to],
    () => {
        reloadListDebounced.cancel()
        reloadList()
    }
)

const clearFilters = () => {
    filterState.search = ''
    filterState.status = 'all'
    filterState.from = ''
    filterState.to = ''
}

const visitPage = (url?: string | null) => {
    if (!url) return
    router.get(url, {}, listVisitOptions)
}

const changePerPage = (perPage: number) => reloadList({ perPage })

const statusLabel = (status: string): string =>
    props.statusOptions.find((option) => option.value === status)?.label ?? status

const formatClock = (value?: string | null): string => (value ? useFormatTime(value, { formatTime: 'HH:mm' }) : '—')

const hasRecordedTime = (item: any): boolean => Boolean(item.recorded_start_at || item.recorded_duration_minutes)

const recordedDifference = (item: any): number =>
    item.recorded_duration_minutes && item.requested_duration_minutes
        ? item.recorded_duration_minutes - item.requested_duration_minutes
        : 0

const decisionDate = (item: any): string | null => {
    if (item.status === 'approved') return item.approved_at ?? null
    if (item.status === 'rejected') return item.rejected_at ?? null
    return null
}

const confirmApprove = (item: any) => {
    confirm.require({
        group: confirmGroup,
        header: ctrans('Approve overtime request'),
        message: ctrans('Are you sure you want to approve the overtime request of :name?', { name: item.employee_name }),
        rejectProps: { label: ctrans('Cancel') },
        acceptProps: { label: ctrans('Yes, approve') },
        accept: () => {
            router.patch(
                overtimeRequestUrl('grp.org.hr.overtime_requests.approve', item),
                {},
                overtimeVisitOptions(item, ctrans('Failed to approve the overtime request'))
            )
        },
    })
}

const confirmReject = (item: any) => {
    confirm.require({
        group: confirmGroup,
        header: ctrans('Reject overtime request'),
        message: ctrans('Are you sure you want to reject the overtime request of :name?', { name: item.employee_name }),
        rejectProps: { label: ctrans('Cancel') },
        acceptProps: { label: ctrans('Yes, reject'), severity: 'danger' },
        accept: () => {
            router.patch(
                overtimeRequestUrl('grp.org.hr.overtime_requests.reject', item),
                {},
                overtimeVisitOptions(item, ctrans('Failed to reject the overtime request'))
            )
        },
    })
}

const confirmDelete = (item: any) => {
    confirm.require({
        group: confirmGroup,
        header: ctrans('Delete overtime request'),
        message: isPending(item)
            ? ctrans('Are you sure you want to delete the overtime request of :name?', { name: item.employee_name })
            : ctrans('The overtime request of :name has already been processed. Are you sure you want to delete it?', { name: item.employee_name }),
        rejectProps: { label: ctrans('Cancel') },
        acceptProps: { label: ctrans('Delete'), severity: 'danger' },
        accept: () => {
            router.delete(
                overtimeRequestUrl('grp.org.hr.overtime_requests.delete', item),
                overtimeVisitOptions(item, ctrans('Failed to delete the overtime request'))
            )
        },
    })
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

const exportForm = useForm({
    from: '',
    to: '',
    type: '',
    status: '',
    employee_id: null as number | null,
    format: 'xlsx',
})

const fieldFocusClass = '[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]'

const recordedTimeOptions = computed(() => [
    { label: ctrans('Same as requested'), value: 'same' },
    { label: ctrans('Different time'), value: 'different' },
])

const recordedTimeMode = computed<string>({
    get: () => (form.recorded_same_as_requested ? 'same' : 'different'),
    set: (value) => {
        form.recorded_same_as_requested = value !== 'different'
    },
})

const exportFormatOptions = computed(() => [
    { label: ctrans('Excel (XLSX)'), value: 'xlsx' },
    { label: ctrans('CSV'), value: 'csv' },
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
                <Button type="tertiary" :label="ctrans('Export')" @click="openExportModal">
                    <template #icon>
                        <FontAwesomeIcon :icon="faDownload" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="create" icon="fal fa-plus" :label="action.label" @click="openRequestModal" />
            </div>
        </template>
    </PageHeading>

    <HrRequestFilters
        v-model:search="filterState.search"
        v-model:status="filterState.status"
        v-model:from="filterState.from"
        v-model:to="filterState.to"
        :total="totalRequests"
        :countLabel="ctransChoice('overtime request|overtime requests', totalRequests)"
        :loading="isLoadingList"
        :statusOptions="statusFilterOptions"
        :searchPlaceholder="ctrans('Search staff, approver, type or reason')"
        @clear="clearFilters"
    />

    <div class="px-4 py-4 transition-opacity" :class="{ 'pointer-events-none opacity-50': isLoadingList }" :aria-busy="isLoadingList">
        <div v-if="rows.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <FontAwesomeIcon :icon="faClock" class="text-3xl text-gray-300" aria-hidden="true" />
            <p class="mt-3 text-sm font-medium text-gray-900">
                {{ hasActiveFilters ? ctrans('No overtime requests match these filters') : ctrans('No overtime requests yet') }}
            </p>
            <p v-if="hasActiveFilters" class="mt-1 text-sm text-gray-500">
                {{ ctrans('Try a different search, status or date range.') }}
            </p>
            <div v-if="hasActiveFilters" class="mt-4 flex justify-center">
                <Button type="tertiary" :label="ctrans('Clear filters')" @click="clearFilters">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </div>

        <div v-else class="flex flex-col gap-4">
            <HrRequestCard v-for="item in rows" :key="item.id" :status="item.status" :statusLabel="statusLabel(item.status)">
                <template #avatar>
                    <EmployeeAvatar :name="item.employee_name" :image="item.employee_avatar" size="md" />
                </template>
                <template #title>{{ item.employee_name }}</template>
                <template #meta>
                    <span class="inline-flex rounded bg-white/70 px-1.5 py-0.5 ring-1 ring-inset ring-gray-200">
                        {{ item.overtime_type_name }}
                    </span>
                </template>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-md bg-gray-50 px-3 py-2">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans('Requested') }}</div>
                        <div class="mt-1 text-sm font-medium text-gray-900">{{ useFormatTime(item.requested_date) }}</div>
                        <div class="text-sm tabular-nums text-gray-600">
                            {{ ctrans('From') }} {{ formatClock(item.requested_start_at) }}
                            <span class="text-gray-300">·</span>
                            {{ formatDuration(item.requested_duration_minutes) }}
                        </div>
                    </div>

                    <div class="rounded-md bg-gray-50 px-3 py-2">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans('Recorded') }}</div>
                        <template v-if="hasRecordedTime(item)">
                            <div class="mt-1 text-sm font-medium tabular-nums text-gray-900">
                                {{ formatClock(item.recorded_start_at) }} → {{ formatClock(item.recorded_end_at) }}
                            </div>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm tabular-nums text-gray-600">
                                {{ formatDuration(item.recorded_duration_minutes) }}
                                <span
                                    v-if="recordedDifference(item) !== 0"
                                    class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                >
                                    {{ recordedDifference(item) > 0 ? '+' : '−' }}{{ formatDuration(Math.abs(recordedDifference(item))) }}
                                    {{ ctrans('vs requested') }}
                                </span>
                            </div>
                        </template>
                        <div v-else class="mt-1 text-sm text-gray-400">{{ ctrans('Not recorded yet') }}</div>
                    </div>
                </div>

                <dl class="mt-4 space-y-2 text-sm empty:hidden">
                    <div v-if="item.reason">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans('Reason') }}</dt>
                        <dd class="whitespace-pre-line break-words text-gray-700">{{ item.reason }}</dd>
                    </div>
                    <div v-if="item.lieu_requested_minutes">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans('Lieu requested') }}</dt>
                        <dd class="tabular-nums text-gray-700">{{ formatDuration(item.lieu_requested_minutes) }}</dd>
                    </div>
                    <div v-if="item.approver_name || decisionDate(item) || item.decision_note">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans('Decision') }}</dt>
                        <dd class="text-gray-700">
                            <span v-if="item.approver_name" class="inline-flex items-center gap-1.5 align-middle">
                                <EmployeeAvatar :name="item.approver_name" :image="item.approver_avatar" size="xs" />
                                {{ item.approver_name }}
                            </span>
                            <span v-if="item.approver_name && decisionDate(item)" class="text-gray-300"> · </span>
                            <span v-if="decisionDate(item)" class="tabular-nums">{{ useFormatTime(decisionDate(item), { formatTime: 'hm' }) }}</span>
                            <p v-if="item.decision_note" class="whitespace-pre-line break-words text-gray-600">{{ item.decision_note }}</p>
                        </dd>
                    </div>
                </dl>

                <template v-if="can_edit !== false" #actions>
                    <template v-if="isPending(item)">
                        <Button
                            type="tertiary"
                            size="xs"
                            :label="ctrans('Approve')"
                            :disabled="processingOvertimeId === item.id"
                            @click="() => confirmApprove(item)"
                        >
                            <template #icon>
                                <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button
                            type="tertiary"
                            size="xs"
                            :label="ctrans('Reject')"
                            :disabled="processingOvertimeId === item.id"
                            @click="() => confirmReject(item)"
                        >
                            <template #icon>
                                <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button type="tertiary" size="xs" :label="ctrans('Edit')" @click="() => openEditModal(item)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faEdit" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </template>
                    <span v-else class="mr-auto whitespace-nowrap text-xs text-gray-400">
                        {{ ctrans('Processed') }}
                    </span>
                    <Button
                        type="tertiary"
                        size="xs"
                        :label="ctrans('Delete')"
                        :disabled="processingOvertimeId === item.id"
                        @click="() => confirmDelete(item)"
                    >
                        <template #icon>
                            <FontAwesomeIcon :icon="faTrash" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </template>
            </HrRequestCard>
        </div>

        <Pagination
            v-if="rows.length > 0 && Number(paginationMeta?.total ?? 0) > 15"
            :on-click="visitPage"
            :has-data="true"
            :meta="paginationMeta"
            :on-per-page-change="changePerPage"
            custom-wrapper-class="mt-4 rounded-lg border border-gray-200"
        />
    </div>

    <Dialog
        :visible="showRequestModal"
        modal
        :draggable="false"
        :header="isEditMode ? ctrans('Edit overtime request') : ctrans('New overtime request')"
        :style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }"
        @update:visible="(isVisible: boolean) => !isVisible && closeRequestModal()"
    >
        <form class="space-y-4" @submit.prevent="submitRequest">
            <div>
                <label for="overtime-employee" class="mb-1 block text-sm font-medium text-gray-700">
                    {{ ctrans('Staff member') }} <span class="text-red-500">*</span>
                </label>
                <Select
                    v-model="form.employee_id"
                    inputId="overtime-employee"
                    :options="employeeOptions"
                    optionLabel="label"
                    optionValue="value"
                    filter
                    :invalid="!!form.errors.employee_id"
                    :placeholder="ctrans('Select staff member')"
                    fluid
                    :class="fieldFocusClass"
                />
                <p v-if="form.errors.employee_id" class="mt-1 text-sm text-red-500">{{ form.errors.employee_id }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="overtime-type" class="mb-1 block text-sm font-medium text-gray-700">
                        {{ ctrans('Overtime type') }} <span class="text-red-500">*</span>
                    </label>
                    <Select
                        v-model="form.overtime_type_id"
                        inputId="overtime-type"
                        :options="overtimeTypeOptions"
                        optionLabel="label"
                        optionValue="value"
                        :invalid="!!form.errors.overtime_type_id"
                        :placeholder="ctrans('Select overtime type')"
                        fluid
                        :class="fieldFocusClass"
                    />
                    <p v-if="form.errors.overtime_type_id" class="mt-1 text-sm text-red-500">{{ form.errors.overtime_type_id }}</p>
                </div>

                <div>
                    <label for="overtime-date" class="mb-1 block text-sm font-medium text-gray-700">
                        {{ ctrans('Date worked') }} <span class="text-red-500">*</span>
                    </label>
                    <DatePicker
                        v-model="requestedDateModel"
                        inputId="overtime-date"
                        dateFormat="d M yy"
                        :manualInput="false"
                        :invalid="!!form.errors.requested_date"
                        :placeholder="ctrans('Select a date')"
                        showIcon
                        iconDisplay="input"
                        fluid
                        :class="fieldFocusClass"
                    />
                    <p v-if="form.errors.requested_date" class="mt-1 text-sm text-red-500">{{ form.errors.requested_date }}</p>
                </div>
            </div>

            <OvertimeTimeFields
                v-model:startHour="form.start_hour"
                v-model:startMinute="form.start_minute"
                v-model:durationHours="form.duration_hours"
                v-model:durationMinutes="form.duration_minutes"
                idPrefix="overtime-requested"
                :startError="getFormError('requested_start_at')"
                :durationError="getFormError('requested_duration_minutes')"
            />

            <div class="border-t border-gray-200 pt-4">
                <span class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans('Recorded time') }}</span>
                <SegmentedToggle v-model="recordedTimeMode" :options="recordedTimeOptions" :ariaLabel="ctrans('Recorded time')" />

                <OvertimeTimeFields
                    v-if="!form.recorded_same_as_requested"
                    v-model:startHour="form.recorded_start_hour"
                    v-model:startMinute="form.recorded_start_minute"
                    v-model:durationHours="form.recorded_duration_hours"
                    v-model:durationMinutes="form.recorded_duration_minutes"
                    idPrefix="overtime-recorded"
                    class="mt-3"
                    :startLabel="ctrans('Recorded start time')"
                    :durationLabel="ctrans('Recorded duration')"
                    :startError="getFormError('recorded_start_at')"
                    :durationError="getFormError('recorded_duration_minutes')"
                />
            </div>

            <div>
                <label for="overtime-status" class="mb-1 block text-sm font-medium text-gray-700">
                    {{ ctrans('Status') }} <span class="text-red-500">*</span>
                </label>
                <Select
                    v-model="form.status"
                    inputId="overtime-status"
                    :options="statusOptions"
                    optionLabel="label"
                    optionValue="value"
                    :invalid="!!form.errors.status"
                    fluid
                    :class="fieldFocusClass"
                />
                <p v-if="form.errors.status" class="mt-1 text-sm text-red-500">{{ form.errors.status }}</p>
            </div>

            <div>
                <label for="overtime-reason" class="mb-1 block text-sm font-medium text-gray-700">
                    {{ ctrans('Reason') }}
                </label>
                <Textarea
                    v-model="form.reason"
                    id="overtime-reason"
                    rows="3"
                    autoResize
                    fluid
                    :invalid="!!form.errors.reason"
                    class="focus:!border-[--app-accent]"
                />
                <p v-if="form.errors.reason" class="mt-1 text-sm text-red-500">{{ form.errors.reason }}</p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <Button type="tertiary" :label="ctrans('Cancel')" @click.prevent="closeRequestModal">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="tertiary" :label="isEditMode ? ctrans('Update request') : ctrans('Submit request')" :loading="form.processing" nativeType="submit">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </form>
    </Dialog>

    <Modal :isOpen="isExportModalOpen" @onClose="closeExportModal" width="w-full max-w-lg">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            {{ ctrans('Export Overtime Reports') }}
        </h2>
        <p class="text-sm text-gray-600 mb-4">
            {{ ctrans('Select filters and export format for your overtime report.') }}
        </p>

        <form @submit.prevent="submitExport" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ ctrans('From Date') }}
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
                        {{ ctrans('To Date') }}
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
                        {{ ctrans('Overtime Type') }}
                    </label>
                    <Select
                        v-model="exportForm.type"
                        :options="[{ value: '', label: ctrans('All Types') }, ...overtimeTypeOptions]"
                        optionLabel="label"
                        optionValue="value"
                        class="mt-1 w-full"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ ctrans('Status') }}
                    </label>
                    <Select
                        v-model="exportForm.status"
                        :options="[{ value: '', label: ctrans('All Statuses') }, ...statusOptions]"
                        optionLabel="label"
                        optionValue="value"
                        class="mt-1 w-full"
                    />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ ctrans('Employee') }}
                </label>
                <Select
                    v-model="exportForm.employee_id"
                    :options="[{ value: null, label: ctrans('All Employees') }, ...employeeOptions]"
                    optionLabel="label"
                    optionValue="value"
                    class="mt-1 w-full"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ ctrans('Export Format') }}
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
                <Button type="tertiary" :label="ctrans('Cancel')" @click="closeExportModal">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="tertiary" nativeType="submit" :label="ctrans('Export')" :loading="isExporting">
                    <template #icon>
                        <FontAwesomeIcon :icon="faDownload" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </form>
    </Modal>

    <HrConfirmDialog :group="confirmGroup" />
</template>
