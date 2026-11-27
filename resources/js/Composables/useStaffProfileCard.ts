import { reactive } from "vue"
import axios from "axios"

export interface StaffProfileCardData {
    id: number
    name: string
    is_me: boolean
    avatar: { original: string; [size: string]: string } | null
    organisations: { slug: string; code: string; name: string }[]
    last_active_at: number | null
}

const OPEN_DELAY_MS = 350
const CLOSE_DELAY_MS = 200

export const staffProfileCard = reactive<{ userId: number | null; anchor: DOMRect | null }>({ userId: null, anchor: null })

const loaded = new Map<number, Promise<StaffProfileCardData>>()
let openTimer: ReturnType<typeof setTimeout> | undefined
let closeTimer: ReturnType<typeof setTimeout> | undefined

export const loadStaffProfileCard = (userId: number): Promise<StaffProfileCardData> => {
    if (!loaded.has(userId)) {
        const request = axios.get(route("grp.chat.staff.people.show", userId)).then(({ data }) => data.data as StaffProfileCardData)
        request.catch(() => loaded.delete(userId))
        loaded.set(userId, request)
    }

    return loaded.get(userId)!
}

export const hoverStaffProfile = (userId: number, target: HTMLElement) => {
    clearTimeout(closeTimer)
    clearTimeout(openTimer)
    loadStaffProfileCard(userId).catch(() => null)
    openTimer = setTimeout(() => {
        staffProfileCard.userId = userId
        staffProfileCard.anchor = target.getBoundingClientRect()
    }, OPEN_DELAY_MS)
}

export const leaveStaffProfile = () => {
    clearTimeout(openTimer)
    closeTimer = setTimeout(closeStaffProfile, CLOSE_DELAY_MS)
}

export const keepStaffProfile = () => clearTimeout(closeTimer)

export const closeStaffProfile = () => {
    clearTimeout(openTimer)
    clearTimeout(closeTimer)
    staffProfileCard.userId = null
    staffProfileCard.anchor = null
}
