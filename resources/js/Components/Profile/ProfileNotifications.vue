<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle } from '@fas'
import { faSearch, faSortAlphaDown, faSortAlphaUp, faSort, faChevronLeft, faChevronRight, faChevronDoubleLeft, faChevronDoubleRight, faBellSlash, faSignOutAlt, faTruckCouch } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import PureInput from '@/Components/Pure/PureInput.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useFormatTime } from '@/Composables/useFormatTime'
library.add(faCircle, faSearch, faSortAlphaDown, faSortAlphaUp, faSort, faChevronLeft, faChevronRight, faChevronDoubleLeft, faChevronDoubleRight, faBellSlash, faSignOutAlt, faTruckCouch)

interface ProfileNotification {
    id: string
    read_at: string | null
    created_at: string | null
    title: string
    body: string
    type: string
    slug: string
    route: string | { name: string, parameters?: any } | null
}

const props = defineProps<{
    data: {
        data: ProfileNotification[]
    }
    layoutVersion?: number
}>()

const layout = inject('layout', layoutStructure)

const rowsPerPageOptions = [5, 10, 20, 40]

const searchValue = ref<string>('')
const sortDirection = ref<'asc' | 'desc' | null>(null)
const rowsPerPage = ref<number>(20)
const currentPage = ref<number>(1)
const selectedNotificationIds = ref<string[]>([])

const _container = ref<HTMLElement | null>(null)
const _scrollArea = ref<HTMLElement | null>(null)
const containerHeight = ref<string>('auto')

const notificationIcons: Record<string, string> = {
    PalletReturn: 'fal fa-sign-out-alt',
    PalletDelivery: 'fal fa-truck-couch',
}

const filteredNotifications = computed(() => {
    const keyword = searchValue.value.trim().toLowerCase()
    const notifications = (props.data?.data ?? []).filter((notification) => {
        if (!keyword) {
            return true
        }

        return `${notification.title ?? ''} ${notification.body ?? ''}`.toLowerCase().includes(keyword)
    })

    if (!sortDirection.value) {
        return notifications
    }

    const direction = sortDirection.value === 'asc' ? 1 : -1

    return [...notifications].sort((a, b) => direction * (a.title ?? '').localeCompare(b.title ?? '', undefined, { numeric: true }))
})

const totalNotifications = computed(() => filteredNotifications.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(totalNotifications.value / rowsPerPage.value)))
const firstIndex = computed(() => (currentPage.value - 1) * rowsPerPage.value)
const lastIndex = computed(() => Math.min(firstIndex.value + rowsPerPage.value, totalNotifications.value))
const pagedNotifications = computed(() => filteredNotifications.value.slice(firstIndex.value, lastIndex.value))

const visiblePages = computed(() => {
    const maxButtons = 5
    const start = Math.max(1, Math.min(currentPage.value - Math.floor(maxButtons / 2), totalPages.value - maxButtons + 1))
    const end = Math.min(totalPages.value, start + maxButtons - 1)

    return Array.from({ length: end - start + 1 }, (_, index) => start + index)
})

const isAllPageSelected = computed(() =>
    pagedNotifications.value.length > 0
    && pagedNotifications.value.every((notification) => selectedNotificationIds.value.includes(notification.id))
)

const sortIcon = computed(() => {
    if (sortDirection.value === 'asc') {
        return 'fal fa-sort-alpha-down'
    }
    if (sortDirection.value === 'desc') {
        return 'fal fa-sort-alpha-up'
    }

    return 'fal fa-sort'
})

const paginationReport = computed(() => trans('Showing :first to :last of :total notifications', {
    first: String(totalNotifications.value ? firstIndex.value + 1 : 0),
    last: String(lastIndex.value),
    total: String(totalNotifications.value),
}))

const toggleSort = () => {
    sortDirection.value = sortDirection.value === null ? 'asc' : sortDirection.value === 'asc' ? 'desc' : null
}

const toggleSelectAllInPage = () => {
    const pageIds = pagedNotifications.value.map((notification) => notification.id)

    selectedNotificationIds.value = isAllPageSelected.value
        ? selectedNotificationIds.value.filter((id) => !pageIds.includes(id))
        : Array.from(new Set([...selectedNotificationIds.value, ...pageIds]))
}

const goToPage = (page: number) => {
    currentPage.value = Math.min(Math.max(1, page), totalPages.value)
}

const resolveNotificationUrl = (notification: ProfileNotification): string | null => {
    if (!notification.route) {
        return null
    }

    return typeof notification.route === 'string'
        ? notification.route
        : route(notification.route.name, notification.route.parameters)
}

const markNotificationAsRead = (notification: ProfileNotification) => {
    if (notification.read_at) {
        return
    }

    notification.read_at = new Date().toISOString()
    const bellNotification = layout.notifications?.find((item: { id: string }) => item.id === notification.id)
    if (bellNotification) {
        bellNotification.read = true
    }

    axios.patch(route('grp.models.notifications.read', notification.id)).catch(() => {
        notification.read_at = null
    })
}

const openNotification = (notification: ProfileNotification) => {
    const url = resolveNotificationUrl(notification)
    if (!url) {
        markNotificationAsRead(notification)
        return
    }

    router.visit(url, {
        onSuccess: () => {
            markNotificationAsRead(notification)
            layout.stackedComponents = []
        },
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
                <PureInput v-model="searchValue" :placeholder="trans('Search notifications')" :prefix="{ icon: 'fal fa-search', label: '' }" />
            </div>

            <div class="flex items-center gap-x-4 px-4 py-3 border-t border-gray-200 bg-gray-50 text-sm font-semibold text-gray-700">
                <input type="checkbox" :checked="isAllPageSelected" @change="toggleSelectAllInPage"
                    :aria-label="trans('Select all notifications on this page')"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                <span class="w-6 shrink-0" />
                <button type="button" @click="toggleSort" class="flex items-center gap-x-2 hover:text-indigo-600 transition-colors">
                    {{ trans('Notification') }}
                    <FontAwesomeIcon :icon="sortIcon" class="text-xs" :class="sortDirection ? 'text-indigo-600' : 'text-gray-400'" fixed-width aria-hidden="true" />
                </button>
                <span v-if="selectedNotificationIds.length" class="ml-auto text-xs font-medium text-indigo-600">
                    {{ selectedNotificationIds.length }} {{ trans('selected') }}
                </span>
                <span class="text-right" :class="selectedNotificationIds.length ? '' : 'ml-auto'">{{ trans('Date') }}</span>
            </div>
        </div>

        <div ref="_scrollArea" class="flex-1 min-h-0 overflow-y-auto divide-y divide-gray-100">
            <div v-for="notification in pagedNotifications" :key="notification.id"
                @click="openNotification(notification)"
                @keydown.enter.self="openNotification(notification)"
                role="link" tabindex="0"
                class="flex items-start gap-x-4 px-4 py-3 cursor-pointer hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50 transition-colors"
                :class="selectedNotificationIds.includes(notification.id) ? 'bg-indigo-50/60' : ''">
                <input type="checkbox" v-model="selectedNotificationIds" :value="notification.id" @click.stop"
                    :aria-label="trans('Select notification')"
                    class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />

                <div class="w-6 shrink-0 pt-0.5 text-gray-400 text-center">
                    <FontAwesomeIcon v-if="notificationIcons[notification.type]" :icon="notificationIcons[notification.type]" fixed-width aria-hidden="true" />
                    <span v-else>-</span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start gap-x-1">
                        <span class="leading-snug truncate" :class="notification.read_at ? 'text-gray-500' : 'font-medium'">{{ notification.title }}</span>
                        <FontAwesomeIcon v-if="!notification.read_at" icon="fas fa-circle" class="animate-pulse h-2 mt-1 shrink-0 text-blue-500" fixed-width aria-hidden="true" />
                    </div>
                    <div class="text-gray-400 text-sm line-clamp-2">{{ notification.body }}</div>
                </div>

                <time v-if="notification.created_at" :datetime="notification.created_at"
                    class="shrink-0 pt-0.5 text-xs text-gray-400 whitespace-nowrap tabular-nums">
                    {{ useFormatTime(notification.created_at, { formatTime: 'hm' }) }}
                </time>
            </div>

            <div v-if="!pagedNotifications.length" class="h-full min-h-40 flex flex-col items-center justify-center gap-y-2 text-gray-400">
                <FontAwesomeIcon icon="fal fa-bell-slash" class="text-2xl" aria-hidden="true" />
                <span class="text-sm italic">{{ searchValue ? trans('No notifications match your search') : trans('You have no notifications') }}</span>
            </div>
        </div>

        <div class="shrink-0 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-gray-200 py-3 text-sm text-gray-600">
            <div class="flex items-center gap-x-1">
                <button type="button" @click="goToPage(1)" :disabled="currentPage === 1" :aria-label="trans('First page')"
                    class="h-8 w-8 rounded-full flex items-center justify-center hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent">
                    <FontAwesomeIcon icon="fal fa-chevron-double-left" class="text-xs" aria-hidden="true" />
                </button>
                <button type="button" @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" :aria-label="trans('Previous page')"
                    class="h-8 w-8 rounded-full flex items-center justify-center hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent">
                    <FontAwesomeIcon icon="fal fa-chevron-left" class="text-xs" aria-hidden="true" />
                </button>

                <button v-for="page in visiblePages" :key="page" type="button" @click="goToPage(page)"
                    class="h-8 min-w-8 px-2 rounded-full tabular-nums transition-colors"
                    :class="page === currentPage ? 'bg-indigo-600 text-white font-semibold' : 'hover:bg-gray-100'">
                    {{ page }}
                </button>

                <button type="button" @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" :aria-label="trans('Next page')"
                    class="h-8 w-8 rounded-full flex items-center justify-center hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent">
                    <FontAwesomeIcon icon="fal fa-chevron-right" class="text-xs" aria-hidden="true" />
                </button>
                <button type="button" @click="goToPage(totalPages)" :disabled="currentPage === totalPages" :aria-label="trans('Last page')"
                    class="h-8 w-8 rounded-full flex items-center justify-center hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent">
                    <FontAwesomeIcon icon="fal fa-chevron-double-right" class="text-xs" aria-hidden="true" />
                </button>
            </div>

            <div class="flex items-center gap-x-3">
                <span class="tabular-nums">{{ paginationReport }}</span>
                <label class="flex items-center gap-x-2">
                    <span class="sr-only">{{ trans('Rows per page') }}</span>
                    <select v-model.number="rowsPerPage"
                        class="rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="option in rowsPerPageOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                </label>
            </div>
        </div>
    </div>
</template>
