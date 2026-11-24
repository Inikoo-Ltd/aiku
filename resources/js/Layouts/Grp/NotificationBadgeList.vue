<!--
  - Author Louis Perez
  - Created on 24-11-2026-16h-50m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBellSlash, faList } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { markAllNotificationsRead, markNotificationRead, notificationUrl, openAllNotifications, RAIL_NOTIFICATIONS_LIMIT, unreadRailNotifications } from "@/Composables/useRailNotifications"
import type { Notification } from "@/types/Notification"

const props = defineProps<{
    close: () => void
}>()

const layout = inject("layout", layoutStructure)

const notifications = computed(() => (layout.notifications ?? []).slice(0, RAIL_NOTIFICATIONS_LIMIT))
const unreadCount = computed(() => unreadRailNotifications(layout.notifications))

const errorMessage = ref("")
const isMarkingAll = ref(false)

const markAll = async () => {
    errorMessage.value = ""
    isMarkingAll.value = true
    try {
        await markAllNotificationsRead(layout)
    } catch {
        errorMessage.value = ctrans("Could not mark the notifications as read, try again")
    } finally {
        isMarkingAll.value = false
    }
}

const openNotification = (notification: Notification) => {
    errorMessage.value = ""
    markNotificationRead(notification).catch(() => {
        errorMessage.value = ctrans("Could not mark the notification as read, try again")
    })

    const url = notificationUrl(notification.route as never)
    if (url) {
        props.close()
        router.visit(url)
    }
}

const viewAll = () => {
    props.close()
    openAllNotifications()
}
</script>

<template>
    <div class="flex w-full flex-col text-sm">
        <div class="mb-1 flex items-center justify-between gap-x-3 border-b border-gray-100 pb-2">
            <span class="font-semibold text-gray-900">
                {{ ctrans("Notifications") }}
                <span v-if="unreadCount" class="font-medium text-gray-500">· {{ ctrans(":count unread", { count: String(unreadCount) }) }}</span>
            </span>
            <button
                type="button"
                class="flex items-center gap-x-1 text-xs text-[var(--app-accent-strong)] hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline"
                :disabled="!unreadCount || isMarkingAll"
                @click="markAll">
                <LoadingIcon v-if="isMarkingAll" />
                {{ ctrans("Mark all read") }}
            </button>
        </div>

        <p v-if="errorMessage" class="mb-1 text-xs text-red-600">{{ errorMessage }}</p>

        <div v-if="notifications.length" class="-mx-1 max-h-[min(60vh,28rem)] overflow-y-auto overscroll-contain">
            <button
                v-for="(notification, index) in notifications"
                :key="(notification.id || 'live') + index"
                v-tooltip="[notification.title, notification.body].filter(Boolean).join(' — ')"
                type="button"
                class="block w-full rounded px-2 py-1.5 text-left transition-colors hover:bg-gray-50"
                @click="openNotification(notification)">
                <span class="flex items-center justify-between gap-x-2">
                    <span class="flex min-w-0 items-center gap-x-1.5">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="notification.read ? 'bg-transparent' : 'bg-blue-500'" aria-hidden="true" />
                        <span class="truncate" :class="notification.read ? 'text-gray-500' : 'font-medium text-gray-900'">{{ notification.title }}</span>
                    </span>
                    <span class="shrink-0 text-[10px] tabular-nums text-gray-500">{{ useFormatTime(notification.created_at) }}</span>
                </span>
                <span v-if="notification.body" class="block truncate pl-3 text-xs text-gray-500">{{ notification.body }}</span>
            </button>
        </div>

        <div v-else class="flex flex-col items-center gap-y-2 py-6 text-gray-400">
            <FontAwesomeIcon :icon="faBellSlash" class="text-xl" fixed-width aria-hidden="true" />
            <span class="text-xs italic">{{ ctrans("You have no notifications") }}</span>
        </div>

        <div class="mt-2 border-t border-gray-100 pt-2">
            <Button type="tertiary" size="xs" full :label="ctrans('View all')" @click="viewAll">
                <template #icon>
                    <FontAwesomeIcon :icon="faList" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>
    </div>
</template>
