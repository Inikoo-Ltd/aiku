import { onBeforeUnmount, onMounted, ref } from "vue"

export interface AnnouncementVisibilityData {
    schedule_at?: string | null
    schedule_finish_at?: string | null
    resumes_at?: string | null
    settings?: {
        position?: string
        target_users?: {
            auth_state?: 'all' | 'logged_in' | 'logged_out'
        }
    }
}

export const isWithinSchedule = (announcement: AnnouncementVisibilityData, now: number): boolean => {
    const start = announcement?.schedule_at ? new Date(announcement.schedule_at).getTime() : null
    const finish = announcement?.schedule_finish_at ? new Date(announcement.schedule_finish_at).getTime() : null

    if (start !== null && !Number.isNaN(start) && now < start) return false
    if (finish !== null && !Number.isNaN(finish) && now >= finish) return false
    return true
}

// Method: to check a pause taken by another announcement has run out
export const isPauseOver = (announcement: AnnouncementVisibilityData, now: number): boolean => {
    if (!announcement?.resumes_at) return true

    const resumesAt = new Date(announcement.resumes_at).getTime()

    return !Number.isNaN(resumesAt) && now >= resumesAt
}

// Method: to check all/logged in/logged out
export const isAnnouncementVisible = (announcement: AnnouncementVisibilityData, now: number, isLoggedIn: boolean): boolean => {
    if (!isWithinSchedule(announcement, now) || !isPauseOver(announcement, now)) return false

    const authState = announcement?.settings?.target_users?.auth_state
    if (authState === 'all') return true
    if (authState === 'logged_in') return isLoggedIn
    if (authState === 'logged_out') return !isLoggedIn
    return false
}

export const useAnnouncementClock = (intervalMs: number = 20_000) => {
    const now = ref(Date.now())
    let tickTimer: ReturnType<typeof setInterval> | null = null

    onMounted(() => {
        tickTimer = setInterval(() => { now.value = Date.now() }, intervalMs)
    })

    onBeforeUnmount(() => {
        if (tickTimer) clearInterval(tickTimer)
    })

    return now
}
