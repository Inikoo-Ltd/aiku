/*
 * Author Louis Perez
 * Created on 24-11-2026-16h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
 */

import axios from "axios"
import { ref } from "vue"
import type { Notification } from "@/types/Notification"

export const RAIL_NOTIFICATIONS_LIMIT = 50

export const isAllNotificationsOpen = ref(false)

type NotificationRoute = string | { name: string; parameters?: unknown } | null | undefined

interface LayoutWithNotifications {
    notifications: Notification[]
}

export const notificationUrl = (target: NotificationRoute): string | null => {
    if (!target) {
        return null
    }

    return typeof target === "string" ? target : route(target.name, target.parameters as never)
}

export const unreadRailNotifications = (notifications: Notification[] | undefined) =>
    (notifications ?? []).slice(0, RAIL_NOTIFICATIONS_LIMIT).filter((notification) => !notification.read).length

export const trimRailNotifications = (notifications: Notification[]) => {
    notifications.splice(RAIL_NOTIFICATIONS_LIMIT)
}

export const setRailNotificationRead = (layout: LayoutWithNotifications, id: string, isRead: boolean) => {
    const notification = layout.notifications?.find((item) => item.id === id)
    if (notification) {
        notification.read = isRead
    }
}

export const markNotificationRead = async (notification: Notification) => {
    if (notification.read) {
        return
    }

    notification.read = true
    if (!notification.id) {
        return
    }

    try {
        await axios.patch(route("grp.models.notifications.read", notification.id))
    } catch (error) {
        notification.read = false
        throw error
    }
}

export const markAllNotificationsRead = async (layout: LayoutWithNotifications) => {
    const unread = (layout.notifications ?? []).filter((notification) => !notification.read)
    unread.forEach((notification) => notification.read = true)

    try {
        await axios.patch(route("grp.models.notifications.all.read"))
    } catch (error) {
        unread.forEach((notification) => notification.read = false)
        throw error
    }
}

export const openAllNotifications = () => {
    isAllNotificationsOpen.value = true
}
