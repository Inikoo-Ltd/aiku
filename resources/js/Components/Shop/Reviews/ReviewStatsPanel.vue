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
}

const props = defineProps<{
    stats?: ReviewStats
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
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="grid grid-cols-2 gap-3">
            <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3">
                <FontAwesomeIcon icon="fal fa-clipboard-list" class="text-lg text-indigo-400" />
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ trans("Total Reviews") }}</div>
                    <div class="text-sm font-semibold text-gray-800 tabular-nums">
                        {{ normalizedStats.total }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3">
                <FontAwesomeIcon icon="fal fa-star" class="text-lg text-amber-400" />
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ trans("Average Rating") }}</div>
                    <div class="text-sm font-semibold text-gray-800 tabular-nums">
                        {{ normalizedStats.averageRating }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3">
                <FontAwesomeIcon icon="fal fa-badge-check" class="text-lg text-emerald-500" />
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ trans("Approved") }}</div>
                    <div class="text-sm font-semibold text-gray-800 tabular-nums">
                        {{ normalizedStats.statusApproved }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3">
                <FontAwesomeIcon icon="fal fa-clock" class="text-lg text-amber-500" />
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ trans("Pending") }}</div>
                    <div class="text-sm font-semibold text-gray-800 tabular-nums">
                        {{ normalizedStats.statusPending }}
                    </div>
                </div>
            </div>
            <div class="col-span-2 flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3">
                <FontAwesomeIcon icon="fal fa-times-circle" class="text-lg text-rose-500" />
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ trans("Rejected") }}</div>
                    <div class="text-sm font-semibold text-gray-800 tabular-nums">
                        {{ normalizedStats.statusRejected }}
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <div class="mb-2 text-xs font-medium text-gray-500">{{ trans("Rating Distribution") }}</div>
            <div class="flex flex-col gap-2">
                <div
                    v-for="row in ratingBreakdown"
                    :key="row.stars"
                    class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2"
                >
                    <span class="text-sm text-amber-500">{{ row.stars }}</span>
                    <span class="text-sm font-semibold text-gray-700 tabular-nums">{{ row.value }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
