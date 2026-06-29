<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { faBadgeCheck, faClock, faStar, faTimesCircle, faClipboardList } from "@fal"

library.add(faClipboardList, faStar, faBadgeCheck, faClock, faTimesCircle)

type ReviewStats = {
    total?: number
    average_rating?: number
    status_approved?: number
    status_pending?: number
    status_rejected?: number
    number_reviews_rating_1?: number
    number_reviews_rating_2?: number
    number_reviews_rating_3?: number
    number_reviews_rating_4?: number
    number_reviews_rating_5?: number
    by_scope?: Record<string, Record<string, number>>
    category_ratings?: Array<{
        dimension: string
        label: string
        average: number
    }>
}

type RatingLabel = {
    dimension: string
    label: string
    is_required?: boolean
    weight?: number
}

const props = defineProps<{
    stats?: ReviewStats
    ratingLabels?: RatingLabel[]
}>()

const normalizedStats = computed(() => ({
    total: Number(props.stats?.total ?? 0),
    averageRating: Number(props.stats?.average_rating ?? 0).toFixed(1),
    statusApproved: Number(props.stats?.status_approved ?? 0),
    statusPending: Number(props.stats?.status_pending ?? 0),
    statusRejected: Number(props.stats?.status_rejected ?? 0),
    rating1: Number(props.stats?.number_reviews_rating_1 ?? 0),
    rating2: Number(props.stats?.number_reviews_rating_2 ?? 0),
    rating3: Number(props.stats?.number_reviews_rating_3 ?? 0),
    rating4: Number(props.stats?.number_reviews_rating_4 ?? 0),
    rating5: Number(props.stats?.number_reviews_rating_5 ?? 0),
}))

const scopes = [
    { key: "overall", label: trans("Overall") },
    { key: "family", label: trans("Family") },
    { key: "product", label: trans("Product") },
]

const cards = computed(() => [
    { label: trans("Total Reviews"), icon: "fal fa-clipboard-list", iconBg: "bg-indigo-50", iconColor: "text-indigo-500", value: normalizedStats.value.total, field: "total" },
    { label: trans("Average Rating"), icon: "fal fa-star", iconBg: "bg-amber-50", iconColor: "text-amber-500", value: normalizedStats.value.averageRating, field: "average_rating", isRating: true },
    { label: trans("Approved"), icon: "fal fa-badge-check", iconBg: "bg-emerald-50", iconColor: "text-emerald-500", value: normalizedStats.value.statusApproved, field: "status_approved" },
    { label: trans("Pending"), icon: "fal fa-clock", iconBg: "bg-amber-50", iconColor: "text-amber-500", value: normalizedStats.value.statusPending, field: "status_pending" },
    { label: trans("Rejected"), icon: "fal fa-times-circle", iconBg: "bg-rose-50", iconColor: "text-rose-500", value: normalizedStats.value.statusRejected, field: "status_rejected" },
])

const scopeMetas = (field: string, isRating = false) => {
    const byScope = props.stats?.by_scope ?? {}
    return scopes.map((scope) => {
        const raw = Number(byScope[scope.key]?.[field] ?? 0)
        return { label: scope.label, value: isRating ? raw.toFixed(1) : raw }
    })
}

const ratingBreakdown = computed(() => [
    { stars: "★★★★★", value: normalizedStats.value.rating5 },
    { stars: "★★★★", value: normalizedStats.value.rating4 },
    { stars: "★★★", value: normalizedStats.value.rating3 },
    { stars: "★★", value: normalizedStats.value.rating2 },
    { stars: "★", value: normalizedStats.value.rating1 },
])

const categoryRatings = computed(() => {
    const fromStats = props.stats?.category_ratings ?? []
    if (fromStats.length > 0) {
        return fromStats.map((item) => ({
            key: item.dimension,
            label: item.label,
            average: Number(item.average ?? 0).toFixed(2),
        }))
    }

    const averagesByDimension: Record<string, number> = {
        a: Number((props.stats as Record<string, unknown> | undefined)?.average_rating_a ?? 0),
        b: Number((props.stats as Record<string, unknown> | undefined)?.average_rating_b ?? 0),
        c: Number((props.stats as Record<string, unknown> | undefined)?.average_rating_c ?? 0),
        d: Number((props.stats as Record<string, unknown> | undefined)?.average_rating_d ?? 0),
        e: Number((props.stats as Record<string, unknown> | undefined)?.average_rating_e ?? 0),
    }

    return (props.ratingLabels ?? [])
        .map((label) => ({
            key: label.dimension,
            label: label.label,
            average: Number(averagesByDimension[label.dimension] ?? 0).toFixed(2),
        }))
})
</script>

<template>
    <div class="w-full">
        <div class="grid w-full grid-cols-5 gap-3">

            <div v-for="card in cards" :key="card.field"
                class="flex flex-col gap-2 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" :class="card.iconBg">
                        <FontAwesomeIcon :icon="card.icon" :class="card.iconColor" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">{{ card.label }}</div>
                        <div class="text-lg font-semibold text-gray-900 tabular-nums">
                            {{ card.value }}
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 border-t border-gray-50 pt-1.5 text-[11px] text-gray-400">
                    <span v-for="meta in scopeMetas(card.field, card.isRating)" :key="meta.label">
                        {{ meta.label }}
                        <span class="font-semibold text-gray-600 tabular-nums">{{ meta.value }}</span>
                    </span>
                </div>
            </div>

        </div>
    </div>
</template>