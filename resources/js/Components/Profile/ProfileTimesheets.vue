<script setup lang='ts'>
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSearch, faSort, faSortAmountDown, faSortAmountUp, faChevronLeft, faChevronRight, faChevronDoubleLeft, faChevronDoubleRight, faClock } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import PureInput from '@/Components/Pure/PureInput.vue'
import { useFormatTime, useSecondsToMS } from '@/Composables/useFormatTime'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { Timesheet } from '@/types/timesheet'
import { Table } from '@/types/Table'
library.add(faSearch, faSort, faSortAmountDown, faSortAmountUp, faChevronLeft, faChevronRight, faChevronDoubleLeft, faChevronDoubleRight, faClock)

const props = defineProps<{
    data: Table
    layoutVersion?: number
}>()

const locale = inject('locale', aikuLocaleStructure)

const rowsPerPageOptions = [15, 25, 40]

const searchValue = ref<string>('')
const sortDirection = ref<'asc' | 'desc' | null>(null)
const rowsPerPage = ref<number>(15)
const currentPage = ref<number>(1)

const _container = ref<HTMLElement | null>(null)
const _scrollArea = ref<HTMLElement | null>(null)
const containerHeight = ref<string>('auto')

const formatDate = (value: string | null) => value
    ? useFormatTime(value, { localeCode: locale.language.code })
    : '-'

const formatClock = (value: string | null) => value
    ? useFormatTime(value, { formatTime: 'hh:mm', localeCode: locale.language.code })
    : '-'

const filteredTimesheets = computed(() => {
    const keyword = searchValue.value.trim().toLowerCase()
    const timesheets = ((props.data?.data ?? []) as Timesheet[]).filter((timesheet) => {
        if (!keyword) {
            return true
        }

        return formatDate(timesheet.date).toLowerCase().includes(keyword)
    })

    if (!sortDirection.value) {
        return timesheets
    }

    const direction = sortDirection.value === 'asc' ? 1 : -1

    return [...timesheets].sort((a, b) => direction * String(a.date ?? '').localeCompare(String(b.date ?? '')))
})

const totalTimesheets = computed(() => filteredTimesheets.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(totalTimesheets.value / rowsPerPage.value)))
const firstIndex = computed(() => (currentPage.value - 1) * rowsPerPage.value)
const lastIndex = computed(() => Math.min(firstIndex.value + rowsPerPage.value, totalTimesheets.value))
const pagedTimesheets = computed(() => filteredTimesheets.value.slice(firstIndex.value, lastIndex.value))

const visiblePages = computed(() => {
    const maxButtons = 5
    const start = Math.max(1, Math.min(currentPage.value - Math.floor(maxButtons / 2), totalPages.value - maxButtons + 1))
    const end = Math.min(totalPages.value, start + maxButtons - 1)

    return Array.from({ length: end - start + 1 }, (_, index) => start + index)
})

const sortIcon = computed(() => {
    if (sortDirection.value === 'asc') {
        return 'fal fa-sort-amount-down'
    }
    if (sortDirection.value === 'desc') {
        return 'fal fa-sort-amount-up'
    }

    return 'fal fa-sort'
})

const paginationReport = computed(() => ctrans('Showing :first to :last of :total timesheets', {
    first: String(totalTimesheets.value ? firstIndex.value + 1 : 0),
    last: String(lastIndex.value),
    total: String(totalTimesheets.value),
}))

const toggleSort = () => {
    sortDirection.value = sortDirection.value === null ? 'asc' : sortDirection.value === 'asc' ? 'desc' : null
}

const goToPage = (page: number) => {
    currentPage.value = Math.min(Math.max(1, page), totalPages.value)
}

const timesheetRoute = (timesheet: Timesheet) => {
    const params = route().params as Record<string, string | undefined>
    const organisation = params.organisation ?? timesheet.organisation_slug

    if (!organisation) {
        return null
    }

    if (route().current() === 'grp.org.hr.employees.show' && params.employee) {
        return route('grp.org.hr.employees.show.timesheets.show', {
            organisation,
            employee: params.employee,
            timesheet: timesheet.id,
        })
    }

    return route('grp.org.hr.timesheets.show', {
        organisation,
        timesheet: timesheet.id,
    })
}

const fitToViewport = () => {
    if (!_container.value) {
        return
    }

    const stackedPanelBottomPadding = 24
    const available = window.innerHeight - _container.value.getBoundingClientRect().top - stackedPanelBottomPadding
    containerHeight.value = `${Math.max(320, available)}px`
}

watch([searchValue, rowsPerPage, sortDirection], () => {
    currentPage.value = 1
})

watch(() => props.layoutVersion, async () => {
    await nextTick()
    fitToViewport()
})

watch(currentPage, () => {
    _scrollArea.value?.scrollTo({ top: 0 })
})

onMounted(async () => {
    await nextTick()
    fitToViewport()
    window.addEventListener('resize', fitToViewport)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', fitToViewport)
})
</script>

<template>
    <div ref="_container" class="px-4 flex flex-col min-h-0" :style="{ height: containerHeight }">
        <div class="shrink-0 border-b border-gray-200">
            <div class="py-3 w-full max-w-xs">
                <PureInput v-model="searchValue" :placeholder="ctrans('Search timesheets')" :prefix="{ icon: 'fal fa-search', label: '' }" />
            </div>

            <div class="flex items-center gap-x-4 px-4 py-3 border-t border-gray-200 bg-gray-50 text-sm font-semibold text-gray-700">
                <button type="button" @click="toggleSort" class="w-40 shrink-0 flex items-center gap-x-2 hover:text-indigo-600 transition-colors">
                    {{ ctrans('Date') }}
                    <FontAwesomeIcon :icon="sortIcon" class="text-xs" :class="sortDirection ? 'text-indigo-600' : 'text-gray-400'" fixed-width aria-hidden="true" />
                </button>
                <span class="w-24 shrink-0 text-right">{{ ctrans('Start at') }}</span>
                <span class="w-24 shrink-0 text-right">{{ ctrans('End at') }}</span>
                <span class="flex-1 text-right">{{ ctrans('Working duration') }}</span>
                <span class="w-32 shrink-0 text-right">{{ ctrans('Breaks duration') }}</span>
            </div>
        </div>

        <div ref="_scrollArea" class="flex-1 min-h-0 overflow-y-auto divide-y divide-gray-100">
            <div v-for="timesheet in pagedTimesheets" :key="timesheet.id"
                class="flex items-center gap-x-4 px-4 py-3 text-sm hover:bg-gray-50 transition-colors">
                <div class="w-40 shrink-0">
                    <Link v-if="timesheetRoute(timesheet)" :href="timesheetRoute(timesheet) as string" class="whitespace-nowrap primaryLink">
                        {{ formatDate(timesheet.date) }}
                    </Link>
                    <span v-else class="whitespace-nowrap text-gray-500">{{ formatDate(timesheet.date) }}</span>
                </div>
                <div class="w-24 shrink-0 text-right tabular-nums whitespace-nowrap">{{ formatClock(timesheet.start_at) }}</div>
                <div class="w-24 shrink-0 text-right tabular-nums whitespace-nowrap">{{ formatClock(timesheet.end_at) }}</div>
                <div class="flex-1 text-right tabular-nums">{{ useSecondsToMS(timesheet.working_duration) }}</div>
                <div class="w-32 shrink-0 text-right tabular-nums">{{ useSecondsToMS(timesheet.breaks_duration) }}</div>
            </div>

            <div v-if="!pagedTimesheets.length" class="h-full min-h-40 flex flex-col items-center justify-center gap-y-2 text-gray-400">
                <FontAwesomeIcon icon="fal fa-clock" class="text-2xl" aria-hidden="true" />
                <span class="text-sm italic">{{ searchValue ? ctrans('No timesheets match your search') : ctrans('You have no timesheets') }}</span>
            </div>
        </div>

        <div class="shrink-0 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-gray-200 py-3 text-sm text-gray-600">
            <div class="flex items-center gap-x-1">
                <button type="button" @click="goToPage(1)" :disabled="currentPage === 1" :aria-label="ctrans('First page')"
                    class="h-8 w-8 rounded-full flex items-center justify-center hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent">
                    <FontAwesomeIcon icon="fal fa-chevron-double-left" class="text-xs" aria-hidden="true" />
                </button>
                <button type="button" @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" :aria-label="ctrans('Previous page')"
                    class="h-8 w-8 rounded-full flex items-center justify-center hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent">
                    <FontAwesomeIcon icon="fal fa-chevron-left" class="text-xs" aria-hidden="true" />
                </button>

                <button v-for="page in visiblePages" :key="page" type="button" @click="goToPage(page)"
                    class="h-8 min-w-8 px-2 rounded-full tabular-nums transition-colors"
                    :class="page === currentPage ? 'bg-indigo-600 text-white font-semibold' : 'hover:bg-gray-100'">
                    {{ page }}
                </button>

                <button type="button" @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" :aria-label="ctrans('Next page')"
                    class="h-8 w-8 rounded-full flex items-center justify-center hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent">
                    <FontAwesomeIcon icon="fal fa-chevron-right" class="text-xs" aria-hidden="true" />
                </button>
                <button type="button" @click="goToPage(totalPages)" :disabled="currentPage === totalPages" :aria-label="ctrans('Last page')"
                    class="h-8 w-8 rounded-full flex items-center justify-center hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent">
                    <FontAwesomeIcon icon="fal fa-chevron-double-right" class="text-xs" aria-hidden="true" />
                </button>
            </div>

            <div class="flex items-center gap-x-3">
                <span class="tabular-nums">{{ paginationReport }}</span>
                <label class="flex items-center gap-x-2">
                    <span class="sr-only">{{ ctrans('Rows per page') }}</span>
                    <select v-model.number="rowsPerPage"
                        class="rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="option in rowsPerPageOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                </label>
            </div>
        </div>
    </div>
</template>
