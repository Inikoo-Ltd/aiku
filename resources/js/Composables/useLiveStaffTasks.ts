/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

import { onBeforeUnmount, onMounted } from "vue"
import { usePage } from "@inertiajs/vue3"

export interface StaffTaskChangedEvent {
    id: number
    reference: string
}

export const useLiveStaffTasks = (onChange: (event: StaffTaskChangedEvent) => void, isRelevant: (event: StaffTaskChangedEvent) => boolean = () => true) => {
    const groupId = (usePage().props.layout as any)?.group?.id
    const channelName = `grp.${groupId}.general`
    const eventName = ".staff-task-changed"

    let debounceTimer: ReturnType<typeof setTimeout> | undefined

    const handler = (event: StaffTaskChangedEvent) => {
        if (!isRelevant(event)) return
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => onChange(event), 500)
    }

    onMounted(() => {
        if (groupId) window.Echo.private(channelName).listen(eventName, handler)
    })

    onBeforeUnmount(() => {
        clearTimeout(debounceTimer)
        if (groupId) window.Echo.private(channelName).stopListening(eventName, handler)
    })
}
