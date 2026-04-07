<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faThumbtack, faCircle } from "@fal"

library.add(faCircle, faThumbtack, faCheck, faTimes)

type ReviewStats = {
    total?: number
    average_rating?: number
    verified?: number
    like_count?: number
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
    verified: Number(props.stats?.verified ?? 0),
    likeCount: Number(props.stats?.like_count ?? 0),
    statusApproved: Number(props.stats?.status_approved ?? 0),
    statusPending: Number(props.stats?.status_pending ?? 0),
    statusRejected: Number(props.stats?.status_rejected ?? 0),
    rating1: Number(props.stats?.number_reviews_rating_1 ?? 0),
    rating2: Number(props.stats?.number_reviews_rating_2 ?? 0),
    rating3: Number(props.stats?.number_reviews_rating_3 ?? 0),
    rating4: Number(props.stats?.number_reviews_rating_4 ?? 0),
    rating5: Number(props.stats?.number_reviews_rating_5 ?? 0),
}))
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="mb-3 text-base font-semibold">Stats</div>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <div class="rounded-md border border-gray-200 p-2">
                <div class="flex items-center gap-2 text-gray-500">
                    <FontAwesomeIcon icon="fas fa-circle" class="text-[10px]" />
                    <span>Total</span>
                </div>
                <div class="text-lg font-semibold">{{ normalizedStats.total }}</div>
            </div>
            <div class="rounded-md border border-gray-200 p-2">
                <div class="flex items-center gap-2 text-gray-500">
                    <FontAwesomeIcon icon="fas fa-circle" class="text-[10px]" />
                    <span>Avg Rating</span>
                </div>
                <div class="text-lg font-semibold">{{ normalizedStats.averageRating }}</div>
            </div>
            <div class="rounded-md border border-gray-200 p-2">
                <div class="flex items-center gap-2 text-gray-500">
                    <FontAwesomeIcon icon="fas fa-thumbtack" />
                    <span>Like</span>
                </div>
                <div class="text-lg font-semibold">{{ normalizedStats.likeCount }}</div>
            </div>
            <div class="rounded-md border border-gray-200 p-2">
                <div class="flex items-center gap-2 text-gray-500">
                    <FontAwesomeIcon icon="fal fa-check" />
                    <span>Verified</span>
                </div>
                <div class="text-lg font-semibold">{{ normalizedStats.verified }}</div>
            </div>
            <div class="rounded-md border border-gray-200 p-2">
                <div class="flex items-center gap-2 text-gray-500">
                    <FontAwesomeIcon icon="fal fa-check" />
                    <span>Approved</span>
                </div>
                <div class="text-lg font-semibold">{{ normalizedStats.statusApproved }}</div>
            </div>
            <div class="rounded-md border border-gray-200 p-2">
                <div class="flex items-center gap-2 text-gray-500">
                    <FontAwesomeIcon icon="fas fa-circle" class="text-[10px]" />
                    <span>Pending</span>
                </div>
                <div class="text-lg font-semibold">{{ normalizedStats.statusPending }}</div>
            </div>
            <div class="col-span-2 rounded-md border border-gray-200 p-2">
                <div class="flex items-center gap-2 text-gray-500">
                    <FontAwesomeIcon icon="fal fa-times" />
                    <span>Rejected</span>
                </div>
                <div class="text-lg font-semibold">{{ normalizedStats.statusRejected }}</div>
            </div>
            <div class="rounded-md border border-gray-200 p-2">
                <div class="text-yellow-500">★</div>
                <div class="text-lg font-semibold">{{ normalizedStats.rating1 }}</div>
            </div>
            <div class="rounded-md border border-gray-200 p-2">
                <div class="text-yellow-500">★★</div>
                <div class="text-lg font-semibold">{{ normalizedStats.rating2 }}</div>
            </div>
            <div class="rounded-md border border-gray-200 p-2">
                <div class="text-yellow-500">★★★</div>
                <div class="text-lg font-semibold">{{ normalizedStats.rating3 }}</div>
            </div>
            <div class="rounded-md border border-gray-200 p-2">
                <div class="text-yellow-500">★★★★</div>
                <div class="text-lg font-semibold">{{ normalizedStats.rating4 }}</div>
            </div>
            <div class="col-span-2 rounded-md border border-gray-200 p-2">
                <div class="text-yellow-500">★★★★★</div>
                <div class="text-lg font-semibold">{{ normalizedStats.rating5 }}</div>
            </div>
        </div>
    </div>
</template>
