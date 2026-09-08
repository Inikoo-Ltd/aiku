import { onBeforeUnmount, onMounted, ref } from "vue"

export interface AnnouncementVisibilityData {
    schedule_at?: string | null
    schedule_finish_at?: string | null
    resumes_at?: string | null
    show_pages?: string[]
    hide_pages?: string[]
    settings?: {
        position?: string
        target_users?: {
            auth_state?: 'all' | 'logged_in' | 'logged_out'
        }
    }
}

export const normalizePagePath = (path: string): string => {
    return path.trim().toLowerCase().replace(/^\/+|\/+$/g, '')
}

// Method: 'contain' rule against the page URL; a bare '/' rule only matches the homepage
export const isPageRuleMatch = (rule: string, path: string): boolean => {
    const normalizedRule = normalizePagePath(rule)
    const normalizedPath = normalizePagePath(path)

    if (!normalizedRule) return normalizedPath === ''
    return normalizedPath.includes(normalizedRule)
}

// Method: to check show_pages ('all' or matched rule) minus hide_pages
export const isShownOnPage = (announcement: AnnouncementVisibilityData, path: string): boolean => {
    const showPages = announcement?.show_pages ?? []
    const hidePages = announcement?.hide_pages ?? []

    if (hidePages.some((rule) => isPageRuleMatch(rule, path))) return false
    if (showPages.includes('all')) return true
    if (showPages.length) return showPages.some((rule) => isPageRuleMatch(rule, path))
    return true
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

// Method: to check schedule, pause, page (when given) and all/logged in/logged out
export const isAnnouncementVisible = (announcement: AnnouncementVisibilityData, now: number, isLoggedIn: boolean, path?: string): boolean => {
    if (!isWithinSchedule(announcement, now) || !isPauseOver(announcement, now)) return false
    if (path !== undefined && !isShownOnPage(announcement, path)) return false

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
