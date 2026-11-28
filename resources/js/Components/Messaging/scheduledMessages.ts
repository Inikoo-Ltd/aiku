/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { computed, inject, ref } from "vue"
import axios from "axios"
import { layoutStructure } from "@/Composables/useLayoutStructure"

export interface StaffMessageSchedule {
    id: number
    state: "pending" | "sending" | "sent" | "failed" | "cancelled"
    text: string
    has_mentions: boolean
    is_reply: boolean
    send_at: string
    send_at_local: string
    timezone: string
    failure_reason: string | null
    target: { kind: "slack" | "verba"; id: number; name: string; url: string }
}

const pad = (value: number) => String(value).padStart(2, "0")

export const wallClock = (date: Date, timeZone?: string | null): string => {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat("en-CA", { timeZone: timeZone ?? undefined, year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", hourCycle: "h23" })
            .formatToParts(date)
            .map((part) => [part.type, part.value])
    )
    return `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}`
}

export const inAnHour = (timeZone?: string | null) => wallClock(new Date(Date.now() + 3600_000), timeZone)

export const tomorrowAtNine = (timeZone?: string | null) => wallClock(new Date(Date.now() + 86_400_000), timeZone).slice(0, 10) + " 09:00"

export const fromPicker = (date: Date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`

export const toPicker = (local: string) => new Date(local.replace(" ", "T"))

export const wallClockLabel = (local: string) =>
    toPicker(local).toLocaleString(undefined, { weekday: "short", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit", hourCycle: "h23" })

export const useUserTimezone = () => {
    const layout = inject("layout", layoutStructure)
    return computed(() => layout?.user?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone)
}

const schedules = ref<StaffMessageSchedule[]>([])
let loading: Promise<void> | null = null

export const useScheduledMessages = () => {
    const load = () => {
        loading ??= axios.get(route("grp.chat.staff.scheduled.index"))
            .then(({ data }) => {
                schedules.value = data.data
            })
            .catch(() => undefined)
            .finally(() => {
                loading = null
            })
        return loading
    }
    const waiting = computed(() => schedules.value.filter((schedule) => schedule.state === "pending" || schedule.state === "sending").length)
    const failed = computed(() => schedules.value.filter((schedule) => schedule.state === "failed").length)
    return { schedules, load, waiting, failed }
}

export const errorOf = (exception: any, fallback: string): string => {
    const errors = exception?.response?.data?.errors
    return (errors && (Object.values(errors)[0] as string[] | undefined)?.[0]) ?? exception?.response?.data?.message ?? fallback
}
