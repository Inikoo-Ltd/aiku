<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from "vue"
import Rating from "primevue/rating"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faChevronLeft,
    faChevronRight,
    faThumbsUp,
    faThumbsDown,
} from "@fortawesome/free-solid-svg-icons"
import { useFormatTime } from "@/Composables/useFormatTime"
import { router } from "@inertiajs/vue3"

const props = defineProps<{
    reviews: any
    review_summary : any 
    allow_review_reaction : boolean
}>()

const current = ref(0)
const windowWidth = ref(window.innerWidth)

const updateWindowWidth = () => {
    windowWidth.value = window.innerWidth
}

onMounted(() => {
    window.addEventListener("resize", updateWindowWidth)
})

onBeforeUnmount(() => {
    window.removeEventListener("resize", updateWindowWidth)
})

const perPage = computed(() => {
    if (windowWidth.value >= 1536) {
        return 5 // 2xl
    }

    if (windowWidth.value >= 1024) {
        return 4 // lg
    }

    return 1 // mobile & tablet
})

const visibleReviews = computed(() =>
    props.reviews.data.slice(current.value, current.value + perPage.value)
)

const reactions = ref<Record<number, "like" | "dislike" | null>>({})
const reactingKeys = ref<Record<string, boolean>>({})

const toggleReaction = (item: any, target: "review" | "review_reply", isLike: boolean) => {
	const review = item
	if (!review?.id) {
		return
	}

	const reactionKey = `${review.id}-${target}`
	if (reactingKeys.value[reactionKey]) {
		return
	}

	const likeField = target === "review" ? "likes" : "replay_likes"
	const dislikeField = target === "review" ? "dislikes" : "replay_dislikes"

	review[likeField] = (review[likeField] ?? 0) + (isLike ? 1 : 0)
	review[dislikeField] = (review[dislikeField] ?? 0) + (isLike ? 0 : 1)

	router.post(
		route("iris.models.review.react", { review: review.id }),
		{
			target: target,
			type: isLike ? "like" : "dislike",
		},
		{
			preserveScroll: true,
			preserveState: true,
			onStart: () => {
				reactingKeys.value[reactionKey] = true
			},
			onError: () => {
				review[likeField] -= isLike ? 1 : 0
				review[dislikeField] -= isLike ? 0 : 1
			},
			onFinish: () => {
				delete reactingKeys.value[reactionKey]
			},
		}
	)
}


const prev = () => {
    if (current.value > 0) {
        current.value--
    }
}

const next = () => {
    if (current.value < props.reviews.data.length - perPage.value) {
        current.value++
    }
}


</script>

<template>
    <div class="editor-class overflow-hidden">
        <div class="rating grid grid-cols-1 divide-y divide-gray-200 lg:grid-cols-7 lg:divide-x lg:divide-y-0">
            <!-- Summary -->
            <div class="flex min-h-[150px] flex-col items-center justify-center px-6 py-6 text-center lg:col-span-1">
                <div class="text-sm font-semibold uppercase tracking-wider text-gray-900">
                    {{ ctrans("Customer Rating") }}
                </div>

                <div class="mt-3 flex items-end gap-1">
                    <span class="text-4xl font-bold leading-none">
                         {{ parseInt(review_summary) }}
                    </span>

                    <span class="pb-1 text-base text-gray-500">
                        /5
                    </span>
                </div>

                <Rating :modelValue="parseInt(review_summary)" readonly :cancel="false" class="review-rating mt-3" />

                <div class="mt-3 text-xs text-gray-500">
                    {{ ctrans("Based on :total Reviews", { total: reviews.meta.total }) }}
                </div>
            </div>

            <!-- Reviews -->
            <div class="relative lg:col-span-6">
                <!-- Previous -->
                <button @click="prev" :disabled="current === 0"
                    class="absolute left-2 top-1/2 z-20 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white shadow transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 lg:-left-5">
                    <FontAwesomeIcon :icon="faChevronLeft" class="text-[10px] text-gray-600" />
                </button>

                <!-- Next -->
                <button @click="next" :disabled="current >= reviews.length - perPage"
                    class="absolute right-2 top-1/2 z-20 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white shadow transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 lg:right-3">
                    <FontAwesomeIcon :icon="faChevronRight" class="text-[10px] text-gray-600" />
                </button>

                <div class="grid grid-cols-1 divide-gray-200 lg:grid-cols-4 2xl:grid-cols-5">
                    <div v-for="review in visibleReviews" :key="review.id"
                        class="flex min-h-[170px] flex-col px-5 py-5 transition hover:bg-gray-50">
                        <Rating :modelValue="review.rating" readonly :cancel="false" class="review-rating-small" />

                        <div class="mt-2 text-sm font-semibold text-gray-900">
                            {{ review.name }}
                        </div>

                        <p class="mt-2 h-20 overflow-hidden text-xs leading-5 text-gray-600 line-clamp-4">
                            {{ review.message }}
                        </p>

                        <div class="mt-auto flex items-center justify-between pt-4">
                            <div class="text-[11px] text-gray-400">
                                {{ useFormatTime(review.date) }}
                            </div>

                            <div v-if="allow_review_reaction" class="flex items-center gap-2">
                                <button @click="() => toggleReaction(review, 'review', true)" :disabled="reactingKeys[`${review.id}-review`]"
                                    class="flex h-7 items-center gap-1 rounded px-2 transition" :class="reactions[review.id] === 'like'
                                            ? 'bg-green-50 text-green-600'
                                            : 'text-gray-500 hover:bg-gray-100'
                                        ">
                                    <FontAwesomeIcon :icon="faThumbsUp" class="text-[10px]" />
                                    <span class="text-[11px] font-medium">
                                        {{ review.likes }}
                                    </span>
                                </button>

                                <button @click="() => toggleReaction(review, 'review', false)" :disabled="reactingKeys[`${review.id}-review`]"
                                    class="flex h-7 w-7 items-center justify-center rounded transition" :class="reactions[review.id] === 'dislike'
                                            ? 'bg-red-50 text-red-600'
                                            : 'text-gray-500 hover:bg-gray-100'
                                        ">
                                    <FontAwesomeIcon :icon="faThumbsDown" class="text-[10px]" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>


<style scoped>
:deep(.review-rating .p-rating) {
    gap: 2px;
}

:deep(.review-rating-small .p-rating) {
    gap: 2px;
}

:deep(.review-rating .p-rating-item-icon) {
    color: #f59e0b;
    font-size: 1rem;
}

:deep(.review-rating-small .p-rating-item-icon) {
    color: #f59e0b;
    font-size: 0.8rem;
}

:deep(.p-rating-item) {
    margin-right: 1px;
}

:deep(.rating .p-rating-option-active .p-rating-icon) {
    color: #f59e0b !important;
}
</style>