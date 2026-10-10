<script setup lang='ts'>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faFileSpreadsheet, faChevronDown, faDownload, faUpload, faFileCsv, faFileExcel, faFileAlt } from '@fal'
import SelectButton from 'primevue/selectbutton'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import ProfilePagination from '@/Components/Profile/ProfilePagination.vue'
import { routeType } from '@/types/route'

library.add(faFileSpreadsheet, faChevronDown, faDownload, faUpload, faFileCsv, faFileExcel, faFileAlt)

type RecordStatus = 'failed' | 'complete' | 'processing'

interface FailReason {
    message: string
    count: number
    rows: number[]
}

interface UploadReport {
    id: number
    original_filename: string
    created_at: string
    uploaded_by: string | null
    number_rows: number
    number_success: number
    number_fails: number
    fail_reasons: FailReason[]
    records_route: routeType
    download_route: routeType
    preview_route?: routeType | null
}

interface UploadRecord {
    id: number
    row_number: number | null
    values: Record<string, string | null> | null
    errors: string[] | Record<string, string[]>
    status: RecordStatus
}

interface PaginationMeta {
    current_page: number
    last_page: number
    from: number | null
    to: number | null
    total: number
}

type RowFilter = 'failed' | 'complete' | 'all'

interface UploadRows {
    filter: RowFilter
    records: UploadRecord[]
    meta: PaginationMeta | null
    isLoading: boolean
}

const props = defineProps<{
    data?: {
        data: UploadReport[]
        meta?: PaginationMeta
    }
    tab?: string
}>()

const rowsPerPage = 50

const uploads = computed(() => props.data?.data ?? [])
const totalUploads = computed(() => props.data?.meta?.total ?? uploads.value.length)
const currentPage = computed(() => props.data?.meta?.current_page ?? 1)
const lastPage = computed(() => Math.max(1, props.data?.meta?.last_page ?? 1))
const isLoadingPage = ref(false)

const paginationReport = computed(() => ctrans('Showing :first to :last of :total uploads', {
    first: String(props.data?.meta?.from ?? 0),
    last: String(props.data?.meta?.to ?? 0),
    total: String(totalUploads.value),
}))

const goToPage = (page: number) => {
    router.reload({
        only: [props.tab ?? 'uploads'],
        data: { [`${props.tab ?? 'uploads'}Page`]: page },
        onStart: () => isLoadingPage.value = true,
        onFinish: () => isLoadingPage.value = false,
    })
}

const outcome = (upload: UploadReport): 'success' | 'failed' | 'mixed' | 'empty' => {
    if (!upload.number_success && !upload.number_fails) {
        return 'empty'
    }
    if (!upload.number_fails) {
        return 'success'
    }

    return upload.number_success ? 'mixed' : 'failed'
}

const outcomeIconClass: Record<ReturnType<typeof outcome>, string> = {
    success: 'bg-lime-50 text-lime-600 ring-lime-200',
    failed: 'bg-red-50 text-red-500 ring-red-200',
    mixed: 'bg-amber-50 text-amber-500 ring-amber-200',
    empty: 'bg-gray-50 text-gray-400 ring-gray-200',
}

const outcomeLabel = (upload: UploadReport): string => {
    switch (outcome(upload)) {
        case 'success':
            return ctrans('All :count rows added', { count: String(upload.number_success) })
        case 'failed':
            return ctrans('None of the :count rows were added', { count: String(upload.number_fails) })
        case 'mixed':
            return ctrans(':success added, :fails failed', { success: String(upload.number_success), fails: String(upload.number_fails) })
        default:
            return ctrans('No rows processed')
    }
}

const fileType = (upload: UploadReport): { extension: string, icon: string, class: string, label: string } => {
    const extension = upload.original_filename.split('.').pop()?.toLowerCase() ?? ''

    if (extension === 'csv') {
        return { extension, icon: 'fal fa-file-csv', class: 'text-sky-600', label: ctrans('CSV file') }
    }
    if (['xlsx', 'xls'].includes(extension)) {
        return { extension, icon: 'fal fa-file-excel', class: 'text-green-600', label: ctrans('Excel file') }
    }

    return { extension, icon: 'fal fa-file-alt', class: 'text-gray-400', label: ctrans('File') }
}

const percentage = (count: number, upload: UploadReport) => {
    const total = Math.max(upload.number_rows, upload.number_success + upload.number_fails, 1)

    return (count / total) * 100
}

const rowsLabel = (reason: FailReason) => {
    const rows = reason.rows.join(', ')
    const remaining = reason.count - reason.rows.length

    if (remaining > 0) {
        return ctrans('Rows :rows and :remaining more', { rows, remaining: String(remaining) })
    }

    return reason.rows.length > 1 ? ctrans('Rows :rows', { rows }) : ctrans('Row :rows', { rows })
}

const openRows = ref<Record<number, UploadRows>>({})

const loadRows = async (upload: UploadReport, filter: RowFilter, page = 1) => {
    const current = openRows.value[upload.id]
    openRows.value[upload.id] = {
        filter,
        records: page > 1 && current ? current.records : [],
        meta: current?.meta ?? null,
        isLoading: true,
    }

    try {
        const { data } = await axios.get(route(upload.records_route.name, upload.records_route.parameters), {
            params: { status: filter === 'all' ? undefined : filter, page, perPage: rowsPerPage },
            headers: { Accept: 'application/json' },
        })
        openRows.value[upload.id].records.push(...data.data)
        openRows.value[upload.id].meta = data.meta
    } catch {
        notify({ title: ctrans('Something went wrong.'), text: ctrans('Failed to load the rows of this upload.'), type: 'error' })
    } finally {
        openRows.value[upload.id].isLoading = false
    }
}

const toggleRows = (upload: UploadReport) => {
    if (openRows.value[upload.id]) {
        delete openRows.value[upload.id]

        return
    }

    loadRows(upload, upload.number_fails ? 'failed' : 'all')
}

interface StatusFilter {
    value: RowFilter
    label: string
    activeClass: string
    passiveClass: string
}

const statusFilters = (upload: UploadReport): StatusFilter[] => [
    {
        value: 'failed',
        label: ctrans('Failed (:count)', { count: String(upload.number_fails) }),
        activeClass: 'border-red-500 bg-red-500 text-white',
        passiveClass: 'border-red-200 bg-red-50 text-red-600 hover:border-red-300 hover:bg-red-100',
    },
    {
        value: 'complete',
        label: ctrans('Added (:count)', { count: String(upload.number_success) }),
        activeClass: 'border-lime-600 bg-lime-600 text-white',
        passiveClass: 'border-lime-200 bg-lime-50 text-lime-700 hover:border-lime-300 hover:bg-lime-100',
    },
    {
        value: 'all',
        label: ctrans('All (:count)', { count: String(upload.number_success + upload.number_fails) }),
        activeClass: 'border-gray-700 bg-gray-700 text-white',
        passiveClass: 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 hover:bg-gray-100',
    },
]

const primaryFields = ['email', 'contact_name', 'company_name', 'name']

const primaryValue = (record: UploadRecord): string => {
    const values = record.values ?? {}
    const field = primaryFields.find((key) => values[key])

    return field ? String(values[field]) : ctrans('Empty row')
}

const otherValues = (record: UploadRecord): { field: string, value: string }[] => {
    const values = record.values ?? {}
    const primaryField = primaryFields.find((key) => values[key])

    return Object.entries(values)
        .filter(([field, value]) => field !== primaryField && value !== null && value !== '')
        .map(([field, value]) => ({ field: field.replace(/_/g, ' '), value: String(value) }))
}

const recordErrors = (record: UploadRecord): string[] => {
    if (Array.isArray(record.errors)) {
        return record.errors.map(String)
    }

    return Object.values(record.errors ?? {}).flat().map(String)
}
</script>

<template>
    <div class="flex flex-col px-4 py-4 sm:px-6">
        <div class="sticky top-[36px] md:top-[33px] lg:top-10 z-10 -mx-4 -mt-4 mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-white/95 px-4 py-2 backdrop-blur sm:-mx-6 sm:px-6">
            <div class="flex items-center gap-x-2 text-sm text-gray-500">
                <span class="rounded-md bg-gray-100 px-2 py-0.5 font-semibold tabular-nums text-gray-700">{{ totalUploads }}</span>
                {{ totalUploads === 1 ? ctrans('upload') : ctrans('uploads') }}
            </div>

            <ProfilePagination
                v-if="lastPage > 1"
                class="!border-t-0 !py-0"
                :current-page="currentPage"
                :last-page="lastPage"
                :report="paginationReport"
                :disabled="isLoadingPage"
                @page="goToPage" />
        </div>

        <div class="relative overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
            <ol v-if="uploads.length" class="divide-y divide-gray-100">
                <li v-for="upload in uploads" :key="upload.id" class="px-5 py-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:gap-6">
                        <div class="flex shrink-0 items-start gap-x-3 md:w-72">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1"
                                :class="outcomeIconClass[outcome(upload)]">
                                <FontAwesomeIcon icon="fal fa-file-spreadsheet" fixed-width aria-hidden="true" />
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900"
                                    v-tooltip="useFormatTime(upload.created_at, { formatTime: 'hms' })">
                                    {{ useFormatTime(upload.created_at, { formatTime: 'short-datetime' }) }}
                                </div>
                                <div class="truncate text-xs text-gray-500">
                                    {{ ctrans('Uploaded by') }} {{ upload.uploaded_by ?? ctrans('unknown user') }}
                                </div>
                                <a :href="route(upload.download_route.name, upload.download_route.parameters)"
                                    class="block truncate text-xs text-gray-400 hover:text-gray-600 hover:underline"
                                    v-tooltip="upload.original_filename">
                                    {{ upload.original_filename }}
                                </a>
                                <div class="mt-1 flex items-center gap-x-2 text-xs text-gray-400">
                                    <span class="inline-flex items-center gap-x-1" v-tooltip="fileType(upload).label">
                                        <FontAwesomeIcon :icon="fileType(upload).icon" :class="fileType(upload).class" fixed-width aria-hidden="true" />
                                        <span class="uppercase">{{ fileType(upload).extension }}</span>
                                    </span>
                                    <a :href="route(upload.download_route.name, upload.download_route.parameters)"
                                        class="text-gray-400 hover:text-gray-700"
                                        :aria-label="ctrans('Download')"
                                        v-tooltip="ctrans('Download')">
                                        <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                                    </a>
                                    <a v-if="upload.preview_route"
                                        :href="route(upload.preview_route.name, upload.preview_route.parameters)"
                                        class="font-medium text-[--app-accent-strong] hover:underline">
                                        {{ ctrans('Open preview') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="min-w-0 flex-1 space-y-2 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-gray-700">{{ outcomeLabel(upload) }}</span>
                                <span class="text-xs tabular-nums text-gray-400">
                                    {{ ctrans(':count rows in file', { count: String(upload.number_rows) }) }}
                                </span>
                            </div>

                            <div class="flex h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                <div class="bg-lime-500" :style="{ width: `${percentage(upload.number_success, upload)}%` }" />
                                <div class="bg-red-400" :style="{ width: `${percentage(upload.number_fails, upload)}%` }" />
                            </div>

                            <ul v-if="upload.fail_reasons.length" class="space-y-1">
                                <li v-for="reason in upload.fail_reasons" :key="reason.message" class="flex items-baseline gap-x-2 text-xs">
                                    <span class="shrink-0 rounded-full bg-red-50 px-1.5 py-0.5 font-semibold tabular-nums text-red-600 ring-1 ring-inset ring-red-600/20">{{ reason.count }}×</span>
                                    <span class="text-gray-700">{{ reason.message }}</span>
                                    <span class="truncate text-gray-400">{{ rowsLabel(reason) }}</span>
                                </li>
                            </ul>

                            <button v-if="upload.number_success || upload.number_fails" type="button"
                                class="flex items-center gap-x-1 text-xs text-[--app-accent] hover:text-[--app-accent-strong] hover:underline"
                                @click="toggleRows(upload)">
                                {{ openRows[upload.id] ? ctrans('Hide rows') : ctrans('Show rows') }}
                                <FontAwesomeIcon icon="fal fa-chevron-down" class="text-[10px] transition-transform"
                                    :class="openRows[upload.id] ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                    </div>

                    <div v-if="openRows[upload.id]" class="mt-3 md:ml-[19.5rem]">
                        <SelectButton
                            :modelValue="openRows[upload.id].filter"
                            :options="statusFilters(upload)"
                            optionLabel="label"
                            optionValue="value"
                            dataKey="value"
                            :allowEmpty="false"
                            :disabled="openRows[upload.id].isLoading"
                            :aria-label="ctrans('Show rows by status')"
                            class="row-filter mb-2"
                            @update:modelValue="(filter: RowFilter) => loadRows(upload, filter)">
                            <template #option="{ option }">
                                <span class="rounded-full border px-2.5 py-0.5 text-xs transition-colors"
                                    :class="openRows[upload.id].filter === option.value ? option.activeClass : option.passiveClass">
                                    {{ option.label }}
                                </span>
                            </template>
                        </SelectButton>

                        <ol class="max-h-96 divide-y divide-gray-100 overflow-y-auto rounded-lg ring-1 ring-gray-200 [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent]">
                            <li v-for="record in openRows[upload.id].records" :key="record.id" class="flex gap-x-3 px-3 py-2 text-xs">
                                <span class="w-12 shrink-0 tabular-nums text-gray-400">#{{ record.row_number }}</span>
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full"
                                    :class="record.status === 'failed' ? 'bg-red-400' : record.status === 'complete' ? 'bg-lime-500' : 'bg-gray-300'" />
                                <div class="min-w-0 flex-1">
                                    <div class="break-all font-medium text-gray-800">{{ primaryValue(record) }}</div>
                                    <div v-if="otherValues(record).length" class="flex flex-wrap gap-x-3 text-gray-500">
                                        <span v-for="item in otherValues(record)" :key="item.field">
                                            <span class="text-gray-400">{{ item.field }}:</span> {{ item.value }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="recordErrors(record).length" class="max-w-[40%] shrink-0 text-right text-red-600">
                                    <div v-for="(error, index) in recordErrors(record)" :key="index">{{ error }}</div>
                                </div>
                            </li>

                            <li v-if="!openRows[upload.id].isLoading && !openRows[upload.id].records.length" class="px-3 py-4 text-center text-xs italic text-gray-400">
                                {{ ctrans('No rows here') }}
                            </li>
                            <li v-if="openRows[upload.id].isLoading" class="flex justify-center px-3 py-3">
                                <LoadingIcon />
                            </li>
                        </ol>

                        <button
                            v-if="!openRows[upload.id].isLoading && openRows[upload.id].meta && openRows[upload.id].meta!.current_page < openRows[upload.id].meta!.last_page"
                            type="button"
                            class="mt-2 text-xs text-[--app-accent] hover:text-[--app-accent-strong] hover:underline"
                            @click="loadRows(upload, openRows[upload.id].filter, openRows[upload.id].meta!.current_page + 1)">
                            {{ ctrans('Load more (:shown of :total)', { shown: String(openRows[upload.id].records.length), total: String(openRows[upload.id].meta!.total) }) }}
                        </button>
                    </div>
                </li>
            </ol>

            <div v-else class="flex flex-col items-center justify-center gap-y-2 py-16 text-gray-400">
                <FontAwesomeIcon icon="fal fa-upload" class="text-2xl" fixed-width aria-hidden="true" />
                <span class="text-sm italic">{{ ctrans('No uploads yet') }}</span>
            </div>

            <div v-if="isLoadingPage" class="absolute inset-0 flex items-center justify-center bg-white/60">
                <LoadingIcon size="2x" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.row-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}
.row-filter :deep(.p-togglebutton),
.row-filter :deep(.p-togglebutton-content) {
    padding: 0;
    border: 0;
    border-radius: 9999px;
    background: transparent;
    box-shadow: none;
}
.row-filter :deep(.p-togglebutton:focus-visible) {
    outline: 2px solid var(--app-accent);
    outline-offset: 1px;
}
.row-filter :deep(.p-togglebutton:disabled) {
    cursor: wait;
}
</style>
