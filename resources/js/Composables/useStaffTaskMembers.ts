/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

import { computed } from "vue"
import { formatDistanceToNow } from "date-fns"
import { ctrans } from "@/Composables/useTrans"
import { useLiveUsers } from "@/Stores/active-users"
import type { StaffParticipant } from "@/Stores/staff-messaging"

export type StaffTaskRoles = {
    requesterId: number | null
    assigneeId: number | null
    collaboratorIds: number[]
}

export const useStaffTaskMembers = (participants: () => StaffParticipant[], roles: () => StaffTaskRoles) => {
    const liveUsers = useLiveUsers()

    const roleOf = (personId: number) => {
        const { requesterId, assigneeId, collaboratorIds } = roles()
        if (assigneeId === personId) return ctrans("Assignee")
        if (collaboratorIds.includes(personId)) return ctrans("Working on it too")
        if (requesterId === personId) return ctrans("Requester")
        return null
    }

    const presenceOf = (personId: number, lastSeenAt?: string | null) => {
        const live = liveUsers.liveUsers[personId]
        const isOnline = !!live && live.action !== "leave" && live.action !== "logout"
        const lastActive = isOnline ? null : (live?.last_active ?? lastSeenAt ?? null)
        return {
            isOnline,
            page: isOnline ? live.current_page ?? null : null,
            lastActiveLabel: lastActive ? formatDistanceToNow(new Date(lastActive), { addSuffix: true }) : null,
        }
    }

    const members = computed(() =>
        participants()
            .map((participant) => ({ ...participant, role: roleOf(participant.id), presence: presenceOf(participant.id, participant.last_seen_at) }))
            .sort((a, b) => Number(b.presence.isOnline) - Number(a.presence.isOnline) || a.name.localeCompare(b.name))
    )

    const onlineCount = computed(() => members.value.filter((member) => member.presence.isOnline).length)

    return { members, onlineCount }
}
