<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import { debounce } from 'lodash-es'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import DatePicker from 'primevue/datepicker'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Pagination from '@/Components/Table/Pagination.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import EmployeeAvatar from '@/Components/HumanResources/EmployeeAvatar.vue'
import { capitalize } from '@/Composables/capitalize'
import { useFormatTime, useRangeFromNow } from '@/Composables/useFormatTime'
import { isoToDate, dateToIso, fieldFocusClass } from '@/Composables/useHrFormControls'
import { ctrans, ctransChoice } from '@/Composables/useTrans'
import { useLayoutStore } from '@/Stores/layout'
import { PageHeadingTypes } from '@/types/PageHeading'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faMailBulk, faPlus, faPaperclip, faUsers, faEnvelope, faSearch, faTimes, faDownload } from '@fal'

library.add(faMailBulk, faPlus, faPaperclip, faUsers, faEnvelope, faSearch, faTimes, faDownload)

interface BulkEmailAttachment {
    index: number
    name: string
    size: number | null
    available: boolean
}

interface BulkEmailRecipient {
    id: number
    name: string
    avatar: string | null
}

interface BulkEmail {
    id: number
    subject: string
    excerpt: string
    body: string
    number_recipients: number
    recipients_sample: BulkEmailRecipient[]
    attachments: BulkEmailAttachment[]
    sender: { name: string; avatar: Record<string, string> | null } | null
    created_at: string
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: any
    filters?: {
        search: string
        from: string | null
        to: string | null
    }
}>()

const layout = useLayoutStore()

const filterState = reactive({
    search: props.filters?.search ?? '',
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
})
const isLoadingList = ref(false)

const rows = computed<BulkEmail[]>(() => props.data?.data ?? [])
const paginationMeta = computed(() => props.data?.meta ?? props.data ?? {})
const totalEmails = computed<number>(() => paginationMeta.value?.total ?? rows.value.length)
const hasActiveFilters = computed(() => filterState.search.trim() !== '' || filterState.from !== '' || filterState.to !== '')
const createRoute = computed(() => (props.pageHead.actions ?? []).find((action: any) => action?.key === 'new bulk email')?.route ?? null)

const fromDate = computed<Date | null>({
    get: () => isoToDate(filterState.from),
    set: (value) => {
        filterState.from = dateToIso(value)
    },
})

const toDate = computed<Date | null>({
    get: () => isoToDate(filterState.to),
    set: (value) => {
        filterState.to = dateToIso(value)
    },
})

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
    if (filterState.from) query.from = filterState.from
    if (filterState.to) query.to = filterState.to

    router.get(
        route('grp.org.hr.bulk_emails.index', { organisation: (route().params as Record<string, any>).organisation }),
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
    () => [filterState.from, filterState.to],
    () => {
        reloadListDebounced.cancel()
        reloadList()
    }
)

const clearFilters = () => {
    filterState.search = ''
    filterState.from = ''
    filterState.to = ''
}

const visitPage = (url?: string | null) => {
    if (!url) return
    router.get(url, {}, listVisitOptions)
}

const changePerPage = (perPage: number) => reloadList({ perPage })

const formatFileSize = (bytes: number) =>
    bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`

const recipientsLabel = (count: number) =>
    count === 1 ? ctrans(':count employee', { count }) : ctrans(':count employees', { count })

const organisationSlug = () => (route().params as Record<string, any>).organisation

const showUrl = (email: BulkEmail) =>
    route('grp.org.hr.bulk_emails.show', { organisation: organisationSlug(), employeeBulkEmail: email.id })

const downloadUrl = (email: BulkEmail, attachment: BulkEmailAttachment) =>
    route('grp.org.hr.bulk_emails.attachments.download', {
        organisation: organisationSlug(),
        employeeBulkEmail: email.id,
        attachment: attachment.index,
    })

const sentAt = (email: BulkEmail) => useFormatTime(email.created_at, { formatTime: 'hm' })
const sentAgo = (email: BulkEmail) => ctrans(':time ago', { time: useRangeFromNow(email.created_at) })

const datePickerPt = { pcInputText: { root: { class: '!w-32 !py-1.5 !text-sm' } } }
const chipClass = 'inline-flex max-w-full items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700'
</script>

<template layout="Grp">
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-new-bulk-email="{ action }">
            <Link :href="route(action.route.name, action.route.parameters)">
                <Button type="create" icon="fal fa-plus" :label="action.label" />
            </Link>
        </template>
    </PageHeading>

    <div
        class="sticky z-10 flex flex-col gap-3 border-b border-gray-200 bg-white px-4 py-3 shadow-sm lg:flex-row lg:items-center lg:justify-between"
        :class="layout.hasTopBanner ? 'top-[61px] lg:top-[68px]' : 'top-[33px] lg:top-10'"
    >
        <div class="flex items-center gap-2 text-sm text-gray-700" aria-live="polite">
            <span class="font-semibold tabular-nums text-gray-900">{{ totalEmails }}</span>
            <span>{{ ctransChoice('bulk email|bulk emails', totalEmails) }}</span>
            <LoadingIcon v-if="isLoadingList" class="text-[--app-accent]" />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <IconField class="w-full sm:w-64">
                <InputIcon>
                    <FontAwesomeIcon icon="fal fa-search" class="text-gray-400" fixed-width aria-hidden="true" />
                </InputIcon>
                <InputText
                    v-model="filterState.search"
                    size="small"
                    fluid
                    maxlength="255"
                    :placeholder="ctrans('Search subject or message')"
                    :aria-label="ctrans('Search subject or message')"
                    class="focus:!border-[--app-accent]"
                />
            </IconField>

            <div class="flex items-center gap-1.5">
                <DatePicker
                    v-model="fromDate"
                    :maxDate="toDate ?? undefined"
                    dateFormat="d M yy"
                    :manualInput="false"
                    :placeholder="ctrans('From')"
                    :aria-label="ctrans('From')"
                    showIcon
                    iconDisplay="input"
                    showClear
                    :class="fieldFocusClass"
                    :pt="datePickerPt"
                />
                <span class="text-gray-400">–</span>
                <DatePicker
                    v-model="toDate"
                    :minDate="fromDate ?? undefined"
                    dateFormat="d M yy"
                    :manualInput="false"
                    :placeholder="ctrans('To')"
                    :aria-label="ctrans('To')"
                    showIcon
                    iconDisplay="input"
                    showClear
                    :class="fieldFocusClass"
                    :pt="datePickerPt"
                />
            </div>

            <Button v-if="hasActiveFilters" type="tertiary" size="sm" :label="ctrans('Clear filters')" @click="clearFilters">
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>
    </div>

    <div class="px-4 py-4 transition-opacity" :class="{ 'pointer-events-none opacity-50': isLoadingList }" :aria-busy="isLoadingList">
        <div v-if="rows.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <FontAwesomeIcon icon="fal fa-mail-bulk" class="text-3xl text-gray-300" aria-hidden="true" />
            <p class="mt-3 text-sm font-medium text-gray-900">
                {{ hasActiveFilters ? ctrans('No bulk emails match these filters') : ctrans('No bulk emails sent yet') }}
            </p>
            <p v-if="hasActiveFilters" class="mt-1 text-sm text-gray-500">
                {{ ctrans('Try a different search or date range.') }}
            </p>
            <div v-if="hasActiveFilters" class="mt-4 flex justify-center">
                <Button type="tertiary" :label="ctrans('Clear filters')" @click="clearFilters">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
            <div v-else-if="createRoute" class="mt-4 flex justify-center">
                <Link :href="route(createRoute.name, createRoute.parameters)">
                    <Button type="create" icon="fal fa-plus" :label="ctrans('New bulk email')" />
                </Link>
            </div>
        </div>

        <div v-else class="flex flex-col gap-4">
            <article v-for="email in rows" :key="email.id" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <Link
                    :href="showUrl(email)"
                    class="group flex items-start gap-3 px-4 py-4 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[--app-accent]"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[--app-accent-soft] text-[--app-accent-deep]">
                        <FontAwesomeIcon icon="fal fa-envelope" fixed-width aria-hidden="true" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block break-words font-semibold text-gray-900 group-hover:text-[--app-accent-strong] group-hover:underline">
                            {{ email.subject }}
                        </span>
                        <span v-if="email.excerpt" class="mt-1 line-clamp-2 break-words text-sm text-gray-600">{{ email.excerpt }}</span>
                    </span>

                    <span class="flex shrink-0 items-center gap-2 text-xs tabular-nums text-gray-700">
                        <span v-if="email.recipients_sample.length" class="flex items-center pl-2">
                            <EmployeeAvatar
                                v-for="recipient in email.recipients_sample"
                                :key="recipient.id"
                                v-tooltip="recipient.name"
                                :name="recipient.name"
                                :image="recipient.avatar"
                                size="xs"
                                class="-ml-2 !h-7 !w-7 ring-2 ring-white"
                            />
                            <span
                                v-if="email.number_recipients > email.recipients_sample.length"
                                class="-ml-2 inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-gray-100 px-1.5 text-[10px] font-medium text-gray-600 ring-2 ring-white"
                            >
                                +{{ email.number_recipients - email.recipients_sample.length }}
                            </span>
                        </span>
                        <FontAwesomeIcon v-else icon="fal fa-users" class="text-gray-500" fixed-width aria-hidden="true" />
                        {{ recipientsLabel(email.number_recipients) }}
                    </span>
                </Link>

                <ul v-if="email.attachments.length" class="flex flex-wrap gap-2 px-4 pb-4 pl-[4.25rem]">
                    <li v-for="attachment in email.attachments" :key="attachment.index" class="max-w-full">
                        <a
                            v-if="attachment.available"
                            :href="downloadUrl(email, attachment)"
                            :download="attachment.name"
                            :class="chipClass"
                            class="hover:bg-[--app-accent-soft] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[--app-accent]"
                        >
                            <FontAwesomeIcon icon="fal fa-paperclip" class="shrink-0 text-gray-500" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ attachment.name }}</span>
                            <span v-if="attachment.size" class="shrink-0 tabular-nums text-gray-500">{{ formatFileSize(attachment.size) }}</span>
                            <FontAwesomeIcon icon="fal fa-download" class="shrink-0 text-[--app-accent]" fixed-width aria-hidden="true" />
                            <span class="sr-only">{{ ctrans('Download') }}</span>
                        </a>
                        <span v-else :class="chipClass" v-tooltip="ctrans('File no longer available')">
                            <FontAwesomeIcon icon="fal fa-paperclip" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                            <span class="truncate text-gray-500">{{ attachment.name }}</span>
                            <span v-if="attachment.size" class="shrink-0 tabular-nums text-gray-400">{{ formatFileSize(attachment.size) }}</span>
                        </span>
                    </li>
                </ul>

                <footer class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-gray-100 bg-gray-50 px-4 py-2 text-xs text-gray-600">
                    <span class="inline-flex items-center gap-2">
                        <EmployeeAvatar :name="email.sender?.name ?? null" :image="email.sender?.avatar ?? null" size="xs" />
                        <span v-if="email.sender">{{ ctrans('Sent by :name', { name: email.sender.name }) }}</span>
                        <span v-else>{{ ctrans('Sender no longer available') }}</span>
                    </span>
                    <span class="text-gray-300" aria-hidden="true">·</span>
                    <span class="tabular-nums">{{ sentAt(email) }}</span>
                    <span class="text-gray-400">({{ sentAgo(email) }})</span>
                </footer>
            </article>
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
</template>
