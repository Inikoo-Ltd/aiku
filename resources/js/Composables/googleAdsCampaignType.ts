/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

const labels: Record<string, string> = {
    SEARCH:          "Search",
    PERFORMANCE_MAX: "Performance Max",
    SHOPPING:        "Shopping",
    DISPLAY:         "Display",
    DEMAND_GEN:      "Demand Gen",
    DISCOVERY:       "Discovery",
    VIDEO:           "Video",
    MULTI_CHANNEL:   "App",
    SMART:           "Smart",
    LOCAL:           "Local",
    LOCAL_SERVICES:  "Local Services",
    HOTEL:           "Hotel",
    TRAVEL:          "Travel",
}

export const campaignTypeLabel = (type: string | null | undefined): string | null => {
    if (!type) return null

    return (
        labels[type] ??
        type
            .toLowerCase()
            .split("_")
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(" ")
    )
}
