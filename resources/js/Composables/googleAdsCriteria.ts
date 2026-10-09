/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

import { ctrans } from "@/Composables/useTrans"

export type Criterion = {
    id: string | null
    type: string | null
    negative: boolean
    status: string | null
    label: string | null
}

/* Google's criterion types in the order a marketer reads them: who is targeted, then where. */
export const criterionTypeOrder = [
    "USER_LIST",
    "CUSTOM_AUDIENCE",
    "COMBINED_AUDIENCE",
    "AUDIENCE",
    "USER_INTEREST",
    "AGE_RANGE",
    "GENDER",
    "PARENTAL_STATUS",
    "INCOME_RANGE",
    "TOPIC",
    "KEYWORD",
    "PLACEMENT",
    "MOBILE_APPLICATION",
    "YOUTUBE_CHANNEL",
    "YOUTUBE_VIDEO",
    "CONTENT_LABEL",
]

export const criterionTypeLabel = (type: string | null): string => {
    switch (type) {
        case "USER_LIST":
            return ctrans("Your audiences")
        case "CUSTOM_AUDIENCE":
            return ctrans("Custom segments")
        case "COMBINED_AUDIENCE":
            return ctrans("Combined audiences")
        case "AUDIENCE":
            return ctrans("Audiences")
        case "USER_INTEREST":
            return ctrans("Interests and in-market")
        case "AGE_RANGE":
            return ctrans("Age")
        case "GENDER":
            return ctrans("Gender")
        case "PARENTAL_STATUS":
            return ctrans("Parental status")
        case "INCOME_RANGE":
            return ctrans("Household income")
        case "TOPIC":
            return ctrans("Topics")
        case "KEYWORD":
            return ctrans("Keywords")
        case "PLACEMENT":
            return ctrans("Placements")
        case "MOBILE_APPLICATION":
            return ctrans("Apps")
        case "YOUTUBE_CHANNEL":
            return ctrans("YouTube channels")
        case "YOUTUBE_VIDEO":
            return ctrans("YouTube videos")
        case "CONTENT_LABEL":
            return ctrans("Content labels")
        default:
            return type ? type.replace(/_/g, " ").toLowerCase() : ctrans("Other")
    }
}

/* Google's enum values read as sentences: AGE_RANGE_18_24 becomes "18 to 24", INCOME_RANGE_TOP_10_PERCENT
   becomes "Top 10 percent". Anything already human, such as a placement URL, is left alone. */
export const criterionLabel = (criterion: Criterion): string => {
    const raw = criterion.label ?? ""

    if (!["AGE_RANGE", "GENDER", "PARENTAL_STATUS", "INCOME_RANGE", "CONTENT_LABEL"].includes(criterion.type ?? "")) {
        return raw
    }

    const stripped = raw.replace(/^(AGE_RANGE_|INCOME_RANGE_|PARENTAL_STATUS_|CONTENT_LABEL_)/, "")

    const range = stripped.match(/^(\d+)_(\d+)$/)
    if (range) return `${range[1]} ${ctrans("to")} ${range[2]}`

    const open = stripped.match(/^(\d+)_UP$/)
    if (open) return `${open[1]} ${ctrans("and over")}`

    if (stripped === "UNDETERMINED") return ctrans("Unknown")

    const words = stripped.replace(/_/g, " ").toLowerCase()

    return words.charAt(0).toUpperCase() + words.slice(1)
}

export const youtubeUrl = (videoId: string) => `https://www.youtube.com/watch?v=${videoId}`
