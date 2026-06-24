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

            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50">
                    <FontAwesomeIcon icon="fal fa-clipboard-list" class="text-indigo-500" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ trans("Total Reviews") }}</div>
                    <div class="text-lg font-semibold text-gray-900 tabular-nums">
                        {{ normalizedStats.total }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50">
                    <FontAwesomeIcon icon="fal fa-star" class="text-amber-500" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ trans("Average Rating") }}</div>
                    <div class="text-lg font-semibold text-gray-900 tabular-nums">
                        {{ normalizedStats.averageRating }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50">
                    <FontAwesomeIcon icon="fal fa-badge-check" class="text-emerald-500" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ trans("Approved") }}</div>
                    <div class="text-lg font-semibold text-gray-900 tabular-nums">
                        {{ normalizedStats.statusApproved }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50">
                    <FontAwesomeIcon icon="fal fa-clock" class="text-amber-500" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ trans("Pending") }}</div>
                    <div class="text-lg font-semibold text-gray-900 tabular-nums">
                        {{ normalizedStats.statusPending }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-50">
                    <FontAwesomeIcon icon="fal fa-times-circle" class="text-rose-500" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ trans("Rejected") }}</div>
                    <div class="text-lg font-semibold text-gray-900 tabular-nums">
                        {{ normalizedStats.statusRejected }}
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>