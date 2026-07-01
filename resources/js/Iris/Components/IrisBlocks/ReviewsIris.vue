<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount, inject } from "vue"
import axios from "axios"
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
    webpage_slug? : string
}>()

const reviewsData = ref({ data: [] as any[], meta: { current_page: 0, last_page: 1, total: 0 } })
const reviewSummary = ref<any>(null)
const isFetchingMoreReviews = ref(false)
const minimum_reviews_to_show = inject<number>("minimum_reviews_to_show", 0)
const allow_review_reaction = inject<number>("allow_review_reaction", 0)
const layout = inject("layout", {})

const fetchMoreReviews = async () => {
    const currentPage = reviewsData.value.meta?.current_page ?? 1
    const lastPage = reviewsData.value.meta?.last_page ?? 1

    if (isFetchingMoreReviews.value || currentPage >= lastPage) {
        return
    }

    isFetchingMoreReviews.value = true

    try {
        const { data } = await axios.get(
            route("iris.json.fetch_reviews", { webpage: props.webpage_slug }),
            { params: { page: currentPage + 1 } }
        )

        const fetchedReviews = data?.reviews?.data ?? []
        seedReactions(fetchedReviews)

        reviewsData.value = {
            ...data.reviews,
            data: [...reviewsData.value.data, ...fetchedReviews],
        }
        reviewSummary.value = data?.review_summary ?? reviewSummary.value
        console.log('sdsdsd',data)
    } catch (error) {
        console.error(error)
    } finally {
        isFetchingMoreReviews.value = false
    }
}

const current = ref(0)
const windowWidth = ref(1024) // default width for SSR

const updateWindowWidth = () => {
    if (typeof window !== "undefined") {
        windowWidth.value = window.innerWidth
    }
}


onMounted(() => {
    updateWindowWidth() // get actual width after hydration
    window.addEventListener("resize", updateWindowWidth)
    fetchMoreReviews()
})

onBeforeUnmount(() => {
     if (typeof window !== "undefined") {
        window.removeEventListener("resize", updateWindowWidth)
    }
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
    reviewsData.value.data.slice(current.value, current.value + perPage.value)
)

const reactions = ref<Record<number, "like" | "dislike" | null>>({})
const reactingKeys = ref<Record<string, boolean>>({})

const seedReactions = (reviewsArr: any[]) => {
	reviewsArr.forEach((review) => {
		if (review?.id !== undefined && !(review.id in reactions.value)) {
			reactions.value[review.id] = review.review_reactions ?? null
		}
	})
}

const toggleReaction = (item: any, target: "review" | "review_reply", isLike: boolean) => {
	const review = item
	if (!review?.id) {
		return
	}

	const newReaction = isLike ? "like" : "dislike"
	if (reactions.value[review.id] === newReaction) {
		return
	}

	const reactionKey = `${review.id}-${target}`
	if (reactingKeys.value[reactionKey]) {
		return
	}

	const likeField = target === "review" ? "likes" : "replay_likes"
	const dislikeField = target === "review" ? "dislikes" : "replay_dislikes"
	const previousReaction = reactions.value[review.id] ?? null

	review[likeField] = (review[likeField] ?? 0) + (isLike ? 1 : 0) - (previousReaction === "like" ? 1 : 0)
	review[dislikeField] = (review[dislikeField] ?? 0) + (isLike ? 0 : 1) - (previousReaction === "dislike" ? 1 : 0)
	reactions.value[review.id] = newReaction

	router.post(
		route("iris.models.review.react", { review: review.id }),
		{
			target: target,
			type: newReaction,
		},
		{
			preserveScroll: true,
			preserveState: true,
			onStart: () => {
				reactingKeys.value[reactionKey] = true
			},
			onError: () => {
				review[likeField] = (review[likeField] ?? 0) - (isLike ? 1 : 0) + (previousReaction === "like" ? 1 : 0)
				review[dislikeField] = (review[dislikeField] ?? 0) - (isLike ? 0 : 1) + (previousReaction === "dislike" ? 1 : 0)
				reactions.value[review.id] = previousReaction
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

const next = async () => {
    if (current.value < reviewsData.value.data.length - perPage.value) {
        current.value++
        return
    }

    await fetchMoreReviews()

    if (current.value < reviewsData.value.data.length - perPage.value) {
        current.value++
    }
}

const isNextDisabled = computed(() => {
    const hasMoreLocally = current.value < reviewsData.value.data.length - perPage.value
    const hasMoreOnServer = (reviewsData.value.meta?.current_page ?? 1) < (reviewsData.value.meta?.last_page ?? 1)

    return !hasMoreLocally && !hasMoreOnServer
})

const isInitialLoading = computed(() => isFetchingMoreReviews.value && reviewsData.value.data.length === 0)

const totalReviews = computed(() => reviewsData.value.meta?.total ?? 0)


</script>

<template>
    <div class="editor-class overflow-hidden" v-if="isInitialLoading || minimum_reviews_to_show <= totalReviews && visibleReviews.length">
        <div v-if="isInitialLoading" class="rating grid grid-cols-1 divide-y divide-gray-200 lg:grid-cols-7 lg:divide-x lg:divide-y-0">
            <!-- Summary skeleton -->
            <div class="flex min-h-[150px] flex-col items-center justify-center gap-3 px-6 py-6 text-center lg:col-span-1">
                <div class="skeleton h-3 w-28 rounded"></div>
                <div class="skeleton h-9 w-16 rounded"></div>
                <div class="skeleton h-4 w-24 rounded"></div>
                <div class="skeleton h-3 w-32 rounded"></div>
            </div>

            <!-- Reviews skeleton -->
            <div class="grid grid-cols-1 divide-gray-200 lg:col-span-6 lg:grid-cols-4 2xl:grid-cols-5">
                <div v-for="n in perPage" :key="n"
                    class="flex min-h-[170px] flex-col gap-3 px-5 py-5">
                    <div class="skeleton h-4 w-24 rounded"></div>
                    <div class="skeleton h-4 w-32 rounded"></div>
                    <div class="skeleton h-16 w-full rounded"></div>
                    <div class="mt-auto flex items-center justify-between">
                        <div class="skeleton h-3 w-16 rounded"></div>
                        <div class="skeleton h-6 w-16 rounded"></div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="rating grid grid-cols-1 divide-y divide-gray-200 lg:grid-cols-7 lg:divide-x lg:divide-y-0">
            <!-- Summary -->
            <div class="flex min-h-[150px] flex-col items-center justify-center px-6 py-6 text-center lg:col-span-1">
                <div class="text-sm font-semibold uppercase tracking-wider text-gray-900">
                    {{ ctrans("Customer Rating") }}
                </div>

                <div class="mt-3 flex items-end gap-1">
                    <span class="text-4xl font-bold leading-none">
                         {{ parseInt(reviewSummary) }}
                    </span>

                    <span class="pb-1 text-base text-gray-500">
                        /5
                    </span>
                </div>

                <Rating :modelValue="parseInt(reviewSummary)" readonly :cancel="false" class="review-rating mt-3" />

                <div class="mt-3 text-xs text-gray-500">
                    {{ ctrans("Based on :total Reviews", { total: reviewsData?.meta?.total }) }}
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
                <button @click="next" :disabled="isNextDisabled"
                    class="absolute right-2 top-1/2 z-20 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white shadow transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 lg:right-3">
                    <FontAwesomeIcon v-if="isFetchingMoreReviews" :icon="faChevronRight" class="text-[10px] text-gray-600 animate-pulse" />
                    <FontAwesomeIcon v-else :icon="faChevronRight" class="text-[10px] text-gray-600" />
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

                            <div v-if="allow_review_reaction && layout?.iris?.is_logged_in" class="flex items-center gap-2">
                                <button @click="() => toggleReaction(review, 'review', true)"
                                    :disabled="reactingKeys[`${review.id}-review`] || reactions[review.id] === 'like'"
                                    class="flex h-7 items-center gap-1 rounded px-2 transition disabled:cursor-not-allowed" :class="reactions[review.id] === 'like'
                                            ? 'bg-green-50 text-green-600'
                                            : 'text-gray-500 hover:bg-gray-100'
                                        ">
                                    <FontAwesomeIcon :icon="faThumbsUp" class="text-[10px]" />
                                    <span class="text-[11px] font-medium">
                                        {{ review.likes }}
                                    </span>
                                </button>

                                <button @click="() => toggleReaction(review, 'review', false)"
                                    :disabled="reactingKeys[`${review.id}-review`] || reactions[review.id] === 'dislike'"
                                    class="flex h-7 w-7 items-center justify-center rounded transition disabled:cursor-not-allowed" :class="reactions[review.id] === 'dislike'
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