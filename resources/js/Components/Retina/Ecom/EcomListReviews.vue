<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { routeType } from '@/types/route'
import { Table as TableTS } from '@/types/Table'
import { GridProducts } from "@/Components/Product"
import Card from "primevue/card"
import Tag from "primevue/tag"
import Rating from "primevue/rating"
import Button from "primevue/button"
import Divider from "primevue/divider"
import Avatar from "primevue/avatar"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCube, faThumbsUp, faThumbsDown } from "@fal"
import { faBadgeCheck, faThumbsUp as fasThumbsUp, faThumbsDown as fasThumbsDown } from "@fas"

const props = defineProps<{
    data: any[] | TableTS
    tab: string
    updateRoute: routeType
    state?: string
    readonly?: boolean
}>()

const loadingReaction = ref<Record<number | string, "like" | "dislike">>({})

const toggleReaction = (item: any, type: "like" | "dislike") => {
    /* const review = item.review
    if (!review?.review_id || props.readonly) {
        return
    }

    const isLiked = review.is_liked
    const isDisliked = review.is_disliked

    if (type === "like") {
        review.is_liked = !isLiked
        review.is_disliked = false
    } else {
        review.is_disliked = !isDisliked
        review.is_liked = false
    }

    router.post(
        route("retina.ecom.reviews.reaction", { review: review.review_id }),
        { type, value: type === "like" ? review.is_liked : review.is_disliked },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                loadingReaction.value[review.review_id] = type
            },
            onError: () => {
                review.is_liked = isLiked
                review.is_disliked = isDisliked
            },
            onFinish: () => {
                delete loadingReaction.value[review.review_id]
            },
        }
    ) */
}
</script>

<template>
    <div class="p-4">
        <GridProducts :resource="data" :preserve-scroll="true" class="mt-5 " :name="tab"
            :gridClass="'lg:grid-cols-1 xl:grid-cols-1 grid grid-cols-1 gap-0 rating'">
            <template #card="{ item }">
                <Card class="border border-gray-200 shadow-none">
                    <template #content>
                        <div class="space-y-3">
                            <!-- Product -->
                            <div class="flex items-start gap-3">
                                <Avatar shape="square" size="normal" class="h-10 w-10 bg-gray-100">
                                    <FontAwesomeIcon :icon="faCube" class="text-sm text-gray-500" />
                                </Avatar>

                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-gray-900">
                                        {{ item.name }}
                                    </div>

                                    <div class="text-xs text-gray-500">
                                        {{ item.code }}
                                    </div>
                                </div>

                                <div v-if="item.review?.review_id" class="flex items-center gap-1">
                                    <button type="button" :disabled="!!loadingReaction[item.review.review_id]"
                                        @click="toggleReaction(item, 'like')"
                                        class="flex items-center justify-center h-7 w-7 rounded-full transition-colors hover:bg-gray-100 disabled:opacity-50">
                                        <FontAwesomeIcon :icon="item.review.is_liked ? fasThumbsUp : faThumbsUp"
                                            fixed-width class="text-sm"
                                            :class="item.review.is_liked ? 'text-blue-600' : 'text-gray-400'" />
                                    </button>

                                    <button type="button" :disabled="!!loadingReaction[item.review.review_id]"
                                        @click="toggleReaction(item, 'dislike')"
                                        class="flex items-center justify-center h-7 w-7 rounded-full transition-colors hover:bg-gray-100 disabled:opacity-50">
                                        <FontAwesomeIcon :icon="item.review.is_disliked ? fasThumbsDown : faThumbsDown"
                                            fixed-width class="text-sm"
                                            :class="item.review.is_disliked ? 'text-red-600' : 'text-gray-400'" />
                                    </button>
                                </div>
                            </div>

                            <template v-if="item.review?.review_id">
                                <!-- Rating -->
                                <div class="flex items-center justify-between">
                                    <Rating :modelValue="item.review.rating" readonly :cancel="false" />

                                    <Tag severity="success" rounded class="text-xs">
                                        <template #icon>
                                            <FontAwesomeIcon :icon="faBadgeCheck" class="mr-1 text-[10px]" />
                                        </template>

                                        Verified
                                    </Tag>
                                </div>

                                <!-- Review -->
                                <p class="line-clamp-3 border-l-2 border-green-500 pl-3 text-sm text-gray-600">
                                    {{ item.review.message }}
                                </p>

                                <!-- Footer -->
                                <div class="flex items-center justify-between border-t pt-2">
                                    <small class="text-xs text-gray-400">
                                        2 days ago
                                    </small>
                                </div>
                            </template>
                        </div>
                    </template>
                </Card>
            </template>
        </GridProducts>
    </div>
</template>


<style scoped>
:deep(.rating .p-rating-option-active .p-rating-icon) {
    color: #f59e0b !important;
}
</style>