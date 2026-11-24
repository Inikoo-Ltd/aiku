<!--
  - Author Louis Perez
  - Created on 24-11-2026-17h-00m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, inject, onMounted, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { watchDebounced } from "@vueuse/core"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import MultiSelect from "primevue/multiselect"
import Checkbox from "primevue/checkbox"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBellSlash, faCheckDouble, faEnvelope, faSearch, faSignOutAlt, faTruckCouch, faFilter } from "@fal"
import { faCircle } from "@fas"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import ProfilePagination from "@/Components/Profile/ProfilePagination.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { isAllNotificationsOpen, markAllNotificationsRead, notificationUrl, setRailNotificationRead } from "@/Composables/useRailNotifications"

interface ListedNotification {
    id: string
    read_at: string | null
    created_at: string | null
    title: string
    body: string
    type: string | null
    slug: string | null
    route: string | { name: string; parameters?: unknown } | null
}

interface NotificationType {
    value: string
    label: string
    group: string
    group_label: string
    count: number
}

type ReadState = "all" | "unread" | "read"

const layout = inject("layout", layoutStructure)

const rowsPerPageOptions = [20, 50, 100]
const recordIcons: Record<string, unknown> = {
    PalletReturn: faSignOutAlt,
    PalletDelivery: faTruckCouch,
}

const searchValue = ref("")
const readState = ref<ReadState>("all")
const selectedTypes = ref<string[]>([])
const rowsPerPage = ref(20)
const currentPage = ref(1)

const notifications = ref<ListedNotification[]>([])
const lastPage = ref(1)
const total = ref(0)
const firstShown = ref(0)
const lastShown = ref(0)
const isLoading = ref(true)
const errorMessage = ref("")

const types = ref<NotificationType[]>([])
const selectedIds = ref<string[]>([])
const isUpdatingSelection = ref(false)
const isMarkingAll = ref(false)

const readStateOptions = computed(() => [
    { value: "all" as ReadState, label: ctrans("All") },
    { value: "unread" as ReadState, label: ctrans("Unread") },
    { value: "read" as ReadState, label: ctrans("Read") },
])

const typeGroups = computed(() => {
    const groups: { label: string; items: NotificationType[] }[] = []
    types.value.forEach((type) => {
        let group = groups.find((candidate) => candidate.label === type.group_label)
        if (!group) {
            group = { label: type.group_label, items: [] }
            groups.push(group)
        }
        group.items.push(type)
    })

    return groups
})

const hasFilters = computed(() => Boolean(searchValue.value.trim()) || readState.value !== "all" || selectedTypes.value.length > 0)

const paginationReport = computed(() => ctrans("Showing :first to :last of :total notifications", {
    first: String(firstShown.value),
    last: String(lastShown.value),
    total: total.value.toLocaleString(),
}))

const isAllPageSelected = computed(() => notifications.value.length > 0 && notifications.value.every((notification) => selectedIds.value.includes(notification.id)))
const selectedNotifications = computed(() => notifications.value.filter((notification) => selectedIds.value.includes(notification.id)))
const hasSelectedUnread = computed(() => selectedNotifications.value.some((notification) => !notification.read_at))
const hasSelectedRead = computed(() => selectedNotifications.value.some((notification) => notification.read_at))

let latestRequest = 0

const fetchNotifications = async () => {
    const requestNumber = ++latestRequest
    isLoading.value = true
    errorMessage.value = ""

    const params: Record<string, string | number> = {
        notificationsPage: currentPage.value,
        notifications_perPage: rowsPerPage.value,
    }
    if (searchValue.value.trim()) {
        params["filter[search]"] = searchValue.value.trim()
    }
    if (readState.value !== "all") {
        params["filter[state]"] = readState.value
    }
    if (selectedTypes.value.length) {
        params["filter[type]"] = selectedTypes.value.join(",")
    }

    try {
        const { data } = await axios.get(route("grp.profile.notifications.index"), { params })
        if (requestNumber !== latestRequest) {
            return
        }

        notifications.value = data.data
        lastPage.value = Math.max(1, data.meta.last_page)
        total.value = data.meta.total
        firstShown.value = data.meta.from ?? 0
        lastShown.value = data.meta.to ?? 0
        selectedIds.value = selectedIds.value.filter((id) => notifications.value.some((notification) => notification.id === id))
    } catch {
        if (requestNumber === latestRequest) {
            errorMessage.value = ctrans("Could not load the notifications, try again")
        }
    } finally {
        if (requestNumber === latestRequest) {
            isLoading.value = false
        }
    }
}

const fetchTypes = async () => {
    try {
        const { data } = await axios.get(route("grp.profile.notifications.types"))
        types.value = data
    } catch {
        types.value = []
    }
}

const reloadFromFirstPage = () => {
    if (currentPage.value === 1) {
        fetchNotifications()
        return
    }
    currentPage.value = 1
}

watchDebounced(searchValue, reloadFromFirstPage, { debounce: 350 })
watch([readState, selectedTypes, rowsPerPage], reloadFromFirstPage, { deep: true })
watch(currentPage, fetchNotifications)

const clearFilters = () => {
    searchValue.value = ""
    readState.value = "all"
    selectedTypes.value = []
}

const toggleSelectAllInPage = () => {
    const pageIds = notifications.value.map((notification) => notification.id)
    selectedIds.value = isAllPageSelected.value
        ? selectedIds.value.filter((id) => !pageIds.includes(id))
        : Array.from(new Set([...selectedIds.value, ...pageIds]))
}

const setRead = async (notification: ListedNotification, isRead: boolean) => {
    if (Boolean(notification.read_at) === isRead) {
        return
    }

    const previousReadAt = notification.read_at
    notification.read_at = isRead ? new Date().toISOString() : null
    setRailNotificationRead(layout, notification.id, isRead)

    try {
        await axios.patch(route(isRead ? "grp.models.notifications.read" : "grp.models.notifications.unread", notification.id))
    } catch (error) {
        notification.read_at = previousReadAt
        setRailNotificationRead(layout, notification.id, !isRead)
        throw error
    }
}

const setSelectedRead = async (isRead: boolean) => {
    isUpdatingSelection.value = true
    errorMessage.value = ""
    const results = await Promise.allSettled(selectedNotifications.value.map((notification) => setRead(notification, isRead)))
    if (results.some((result) => result.status === "rejected")) {
        errorMessage.value = ctrans("Some notifications could not be updated, try again")
    } else {
        selectedIds.value = []
    }
    isUpdatingSelection.value = false
}

const markAll = async () => {
    isMarkingAll.value = true
    errorMessage.value = ""
    try {
        await markAllNotificationsRead(layout)
        notifications.value.forEach((notification) => notification.read_at = notification.read_at ?? new Date().toISOString())
        if (readState.value === "unread") {
            reloadFromFirstPage()
        }
    } catch {
        errorMessage.value = ctrans("Could not mark the notifications as read, try again")
    } finally {
        isMarkingAll.value = false
    }
}

const openNotification = (notification: ListedNotification) => {
    setRead(notification, true).catch(() => {
        errorMessage.value = ctrans("Could not mark the notification as read, try again")
    })

    const url = notificationUrl(notification.route)
    if (url) {
        router.visit(url, { onSuccess: () => isAllNotificationsOpen.value = false })
    }
}

onMounted(() => {
    fetchNotifications()
    fetchTypes()
})
</script>

<template>
    <div class="flex h-[min(78vh,48rem)] flex-col">
        <div class="flex shrink-0 flex-wrap items-center gap-3 pb-3">
            <IconField class="w-full sm:w-80">
                <InputIcon>
                    <FontAwesomeIcon :icon="faSearch" fixed-width aria-hidden="true" />
                </InputIcon>
                <InputText v-model="searchValue" class="w-full" size="small" :placeholder="ctrans('Search all notifications')" :aria-label="ctrans('Search all notifications')" />
            </IconField>

            <MultiSelect
                v-model="selectedTypes"
                :options="typeGroups"
                optionGroupLabel="label"
                optionGroupChildren="items"
                optionLabel="label"
                optionValue="value"
                filter
                :filterPlaceholder="ctrans('Find a type')"
                :placeholder="ctrans('All types')"
                :maxSelectedLabels="2"
                :selectedItemsLabel="ctrans(':count types', { count: '{0}' })"
                :showToggleAll="false"
                size="small"
                class="w-full sm:w-72"
                :aria-label="ctrans('Notification types')">
                <template #dropdownicon>
                    <FontAwesomeIcon :icon="faFilter" class="text-gray-400" fixed-width aria-hidden="true" />
                </template>
                <template #optiongroup="{ option }">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ option.label }}</span>
                </template>
                <template #option="{ option }">
                    <span class="flex w-full items-center justify-between gap-x-3">
                        <span>{{ option.label }}</span>
                        <span class="text-xs tabular-nums text-gray-500">{{ option.count.toLocaleString() }}</span>
                    </span>
                </template>
            </MultiSelect>

            <SegmentedToggle v-model="readState" :options="readStateOptions" :aria-label="ctrans('Read or unread')" />

            <button v-if="hasFilters" type="button" class="text-xs text-[var(--app-accent-strong)] hover:underline" @click="clearFilters">
                {{ ctrans("Clear filters") }}
            </button>

            <Button class="ml-auto" type="tertiary" size="xs" :label="ctrans('Mark all read')" :loading="isMarkingAll" @click="markAll">
                <template #icon>
                    <FontAwesomeIcon :icon="faCheckDouble" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>

        <p v-if="errorMessage" class="shrink-0 pb-2 text-xs text-red-600">{{ errorMessage }}</p>

        <div class="flex shrink-0 items-center gap-x-4 whitespace-nowrap border-y border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-700">
            <Checkbox :modelValue="isAllPageSelected" binary :disabled="!notifications.length" :aria-label="ctrans('Select all notifications on this page')" @update:modelValue="toggleSelectAllInPage" />
            <span>{{ ctrans("Notification") }}</span>
            <span v-if="selectedIds.length" class="ml-auto flex items-center gap-x-3">
                <span class="text-xs font-medium text-[var(--app-accent-strong)]">{{ ctrans(":count selected", { count: String(selectedIds.length) }) }}</span>
                <Button v-if="hasSelectedUnread" type="tertiary" size="xs" :label="ctrans('Mark as read')" :loading="isUpdatingSelection" @click="setSelectedRead(true)">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheckDouble" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button v-if="hasSelectedRead" type="tertiary" size="xs" :label="ctrans('Mark as unread')" :loading="isUpdatingSelection" @click="setSelectedRead(false)">
                    <template #icon>
                        <FontAwesomeIcon :icon="faEnvelope" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </span>
            <span :class="selectedIds.length ? '' : 'ml-auto'">{{ ctrans("Date") }}</span>
        </div>

        <div class="relative min-h-0 flex-1 overflow-y-auto">
            <div v-if="isLoading && !notifications.length" class="space-y-2 p-4">
                <div v-for="placeholder in 8" :key="placeholder" class="skeleton h-12 rounded" />
            </div>

            <template v-else>
                <div v-if="isLoading" class="absolute inset-x-0 top-0 z-10 flex justify-center pt-6">
                    <span class="rounded-full bg-white px-3 py-1 shadow"><LoadingIcon /></span>
                </div>

                <div class="divide-y divide-gray-100" :class="isLoading ? 'opacity-50' : ''">
                    <div
                        v-for="notification in notifications"
                        :key="notification.id"
                        role="link"
                        tabindex="0"
                        class="flex cursor-pointer items-start gap-x-4 px-4 py-3 transition-colors hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50"
                        :class="selectedIds.includes(notification.id) ? 'bg-[var(--app-accent-soft)]' : ''"
                        @click="openNotification(notification)"
                        @keydown.enter.self="openNotification(notification)">
                        <div class="-m-2 shrink-0 p-2" @click.stop @keydown.enter.stop>
                            <Checkbox v-model="selectedIds" :value="notification.id" :aria-label="ctrans('Select notification')" />
                        </div>

                        <div class="w-5 shrink-0 pt-0.5 text-center text-gray-400">
                            <FontAwesomeIcon v-if="notification.type && recordIcons[notification.type]" :icon="recordIcons[notification.type]" fixed-width aria-hidden="true" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-x-1.5">
                                <span class="truncate leading-snug" :class="notification.read_at ? 'text-gray-500' : 'font-medium text-gray-900'">{{ notification.title }}</span>
                                <FontAwesomeIcon v-if="!notification.read_at" :icon="faCircle" class="shrink-0 text-[6px] text-blue-500" fixed-width aria-hidden="true" />
                            </div>
                            <div class="line-clamp-2 text-sm text-gray-500">{{ notification.body }}</div>
                        </div>

                        <time v-if="notification.created_at" :datetime="notification.created_at" class="shrink-0 whitespace-nowrap pt-0.5 text-xs tabular-nums text-gray-500">
                            {{ useFormatTime(notification.created_at, { formatTime: "hm" }) }}
                        </time>
                    </div>
                </div>

                <div v-if="!notifications.length" class="flex h-full min-h-40 flex-col items-center justify-center gap-y-2 text-gray-400">
                    <FontAwesomeIcon :icon="faBellSlash" class="text-2xl" fixed-width aria-hidden="true" />
                    <span class="text-sm italic">{{ hasFilters ? ctrans("No notifications match these filters") : ctrans("You have no notifications") }}</span>
                </div>
            </template>
        </div>

        <ProfilePagination
            v-model:rows-per-page="rowsPerPage"
            :current-page="currentPage"
            :last-page="lastPage"
            :report="paginationReport"
            :rows-per-page-options="rowsPerPageOptions"
            :disabled="isLoading"
            @page="(page: number) => currentPage = page" />
    </div>
</template>
