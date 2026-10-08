/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 18:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { formatInTimeZone } from "date-fns-tz"

export const hoursLabel = (seconds: number | null | undefined, empty = "–"): string => {
    if (!seconds) {
        return empty
    }
    const minutes = Math.round(seconds / 60)
    return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, "0")}m`
}

export const durationSince = (iso: string | null, now: Date = new Date()): string => {
    if (!iso) {
        return ""
    }
    const minutes = Math.max(0, Math.round((now.getTime() - new Date(iso).getTime()) / 60000))
    if (minutes < 60) {
        return `${minutes}m`
    }
    if (minutes < 60 * 48) {
        return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, "0")}m`
    }
    return `${Math.floor(minutes / 1440)}d`
}

export const timeIn = (iso: string | null, timezone: string, pattern = "HH:mm", empty = "–"): string =>
    iso ? formatInTimeZone(iso, timezone, pattern) : empty

export const seriesColors = {
    picked: "#2a78d6",
    packed: "#eb6834",
    usual: "#9ca3af",
    productivity: "#1baf7a",
}
