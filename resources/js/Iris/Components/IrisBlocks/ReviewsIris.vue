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
    faTimes
} from "@fortawesome/free-solid-svg-icons"
import { useFormatTime } from "@/Composables/useFormatTime"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Image from "@/Common/Components/Image.vue"
import StarRating from "@/Iris/Components/StarRating.vue"
import { ctrans } from "@/Composables/useTrans"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { faArrowRight } from "@far"


const props = defineProps<{
    webpage_id?: string
}>()

const reviewsData = ref({ data: [] as any[], meta: { current_page: 0, last_page: 1, total: 0 } })
const reviewSummary = ref<any>(null)
const isFetchingMoreReviews = ref(false)
const minimum_reviews_to_show = inject<number>("minimum_reviews_to_show", 0)
const allow_review_reaction = inject<number>("allow_review_reaction", 0)
const allow_review_reply_reaction = inject<number>("allow_review_reply_reaction", 0)
const show_staff_who_reply = inject<boolean>("show_staff_who_reply", false)
const webpage_data = inject<boolean>("webpage_data", null)
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
            route("iris.json.fetch_reviews_new", { webpage: props.webpage_id }),
            { params: { page: currentPage + 1 } }
        )

        const fetchedReviews = data?.reviews?.data ?? []
        seedReactions(fetchedReviews)

        reviewsData.value = {
            ...data.reviews,
            data: [...reviewsData.value.data, ...fetchedReviews]
        }
        reviewSummary.value = data?.review_summary ?? reviewSummary.value
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
const replyReactions = ref<Record<number, "like" | "dislike" | null>>({})
const reactingKeys = ref<Record<string, boolean>>({})

const seedReactions = (reviewsArr: any[]) => {
    reviewsArr.forEach((review) => {
        if (review?.id !== undefined && !(review.id in reactions.value)) {
            reactions.value[review.id] = review.review_reactions ?? null
            replyReactions.value[review.id] = review.reply_reactions ?? null
        }
    })
}

const toggleReaction = (item: any, target: "review" | "review_reply", isLike: boolean) => {
    const review = item
    if (!review?.id) {
        return
    }

    const reactionsRef = target === "review" ? reactions : replyReactions
    const newReaction = isLike ? "like" : "dislike"
    if (reactionsRef.value[review.id] === newReaction) {
        return
    }

    const reactionKey = `${review.id}-${target}`
    if (reactingKeys.value[reactionKey]) {
        return
    }

    const likeField = target === "review" ? "likes" : "reply_likes"
    const dislikeField = target === "review" ? "dislikes" : "reply_dislikes"
    const previousReaction = reactionsRef.value[review.id] ?? null

    review[likeField] = (review[likeField] ?? 0) + (isLike ? 1 : 0) - (previousReaction === "like" ? 1 : 0)
    review[dislikeField] = (review[dislikeField] ?? 0) + (isLike ? 0 : 1) - (previousReaction === "dislike" ? 1 : 0)
    reactionsRef.value[review.id] = newReaction

    router.post(
        route("iris.models.review.react", { review: review.id }),
        {
            target: target,
            type: newReaction
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
                reactionsRef.value[review.id] = previousReaction
            },
            onFinish: () => {
                delete reactingKeys.value[reactionKey]
            }
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

const selectedReview = ref<any>(null)
const reviewModalVisible = ref(false)

const showOriginal = ref<Record<number, boolean>>({})

const hasMessageTranslation = (review: any) =>
    !!review?.message_translated && review.message_translated !== review.message

const hasReplyTranslation = (review: any) =>
    !!review?.reply_translated && review.reply_translated !== review.reply

const hasTranslation = (review: any) =>
    hasMessageTranslation(review) || hasReplyTranslation(review)

const displayMessage = (review: any) =>
    hasMessageTranslation(review) && !showOriginal.value[review.id]
        ? review.message_translated
        : review.message

const displayReply = (review: any) =>
    hasReplyTranslation(review) && !showOriginal.value[review.id]
        ? review.reply_translated
        : review.reply

const toggleTranslation = (review: any) => {
    showOriginal.value[review.id] = !showOriginal.value[review.id]
}

const openReview = (review: any) => {
    selectedReview.value = review
    reviewModalVisible.value = true
}

const reviewLink = computed(() => {
    switch (webpage_data.sub_type) {
        case "family":
            return {
                href: `/reviews/family/${webpage_data.model_slug}`,
                text: ctrans("See All Reviews Family")
            }

        case "product":
            return {
                href: `/reviews/product/${webpage_data.model_slug}`,
                text: ctrans("See All Reviews Product")
            }

        default:
            return {
                href: "/reviews",
                text: ctrans("See All Reviews")
            }
    }
})

</script>

<template>
    <div class="editor-class overflow-hidden"
         v-if="isInitialLoading || minimum_reviews_to_show <= totalReviews && visibleReviews.length">
        <div v-if="isInitialLoading"
             class="rating grid grid-cols-1 divide-y divide-gray-200 lg:grid-cols-7 lg:divide-x lg:divide-y-0">
            <!-- Summary skeleton -->
            <div
                class="flex min-h-[150px] flex-col items-center justify-center gap-3 px-6 py-6 text-center lg:col-span-1">
                <div class="skeleton h-3 w-28 rounded"></div>
                <div class="skeleton h-9 w-16 rounded"></div>
                <div class="skeleton h-4 w-24 rounded"></div>
                <div class="skeleton h-3 w-32 rounded"></div>
            </div>

            <!-- Reviews skeleton -->
            <div class="grid grid-cols-1 divide-gray-200 lg:col-span-6 lg:grid-cols-4 2xl:grid-cols-5">
                <div v-for="n in perPage" :key="n" class="flex min-h-[170px] flex-col gap-3 px-5 py-5">
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
            <div
                class="flex min-h-[150px] flex-col items-center justify-center px-6 py-2 text-center lg:col-span-1">
                <h2 class="!text-sm !ont-semibold !uppercase !tracking-wider !text-gray-900">
                    {{ ctrans("Customer Rating") }}
                </h2>

                <div class="mt-3 flex items-end gap-1">
                    <span class="text-4xl font-bold leading-none">
                        {{ parseFloat(reviewSummary).toFixed(2) }}
                    </span>

                    <span class="pb-1 text-base text-gray-500">
                        /5
                    </span>
                </div>

                <StarRating :modelValue="parseFloat(reviewSummary)" class="review-rating mt-3 text-2xl" />

                <div class="mt-3 text-xs text-gray-500">
                    {{ ctrans("Based on :total Reviews", { total: reviewsData?.meta?.total }) }}
                </div>

                <div class="mt-2 flex flex-col items-center gap-2" >
                   <a :href="reviewLink.href"
                        class="group inline-flex items-center gap-2 text-xs  font-bold hover:underline">
                        {{ reviewLink.text }}
                        <FontAwesomeIcon :icon="faArrowRight"
                            class="text-xs transition-transform group-hover:translate-x-1" />
                    </a>

                    <a v-if="['family', 'product'].includes(webpage_data.sub_type)" :href="'/reviews'"
                        class="group inline-flex items-center gap-2 text-xs  font-bold hover:underline">
                        {{ ctrans("See All Reviews") }}
                    </a>
                </div>

            </div>


            <!-- Reviews -->
            <div class="relative lg:col-span-6">
                <!-- Previous -->
                <button @click="prev" :disabled="current === 0" :aria-label="ctrans('Previous')"
                        class="absolute left-2 top-1/2 z-20 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white shadow transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 lg:-left-5">
                    <FontAwesomeIcon :icon="faChevronLeft" class="text-[10px] text-gray-600" />
                </button>

                <!-- Next -->
                <button @click="next" :disabled="isNextDisabled" :aria-label="ctrans('Next')"
                        class="absolute right-2 top-1/2 z-20 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white shadow transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 lg:right-3">
                    <FontAwesomeIcon v-if="isFetchingMoreReviews" :icon="faChevronRight"
                                     class="text-[10px] text-gray-600 animate-pulse" />
                    <FontAwesomeIcon v-else :icon="faChevronRight" class="text-[10px] text-gray-600" />
                </button>

                <div class="grid grid-cols-1 divide-gray-200 lg:grid-cols-4 2xl:grid-cols-5 px-8 lg:px-0">
                    <div v-for="review in visibleReviews" :key="review.id" @click="openReview(review)"
                         class="flex min-h-[170px] flex-col px-5 py-5 transition hover:bg-gray-50">
                        <Rating :modelValue="review.rating" readonly :cancel="false" class="review-rating-small" />

                        <div class="mt-2 text-sm font-semibold text-gray-900">
                            {{ review.name }}
                        </div>

                        <p class="mt-2 h-20 overflow-hidden text-xs leading-5 text-gray-600 line-clamp-4">
                            {{ displayMessage(review) }}
                        </p>

                        <div class="flex justify-between mt-auto text-[11px] text-gray-500 w-full">
                            <div class="flex items-center w-fit">
                                <AddressLocation :data="review['customer_location']" :use_flag="review?.customer_location?.[1] != layout?.iris?.shop?.location?.[1]" />
                            </div>
                            <div v-if="hasTranslation(review)" @click.stop="toggleTranslation(review)"
                                 class="text-gray-500 hover:text-gray-700 cursor-pointer">
                                {{ showOriginal[review.id] ? ctrans("See translation") : ctrans("See original") }}
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] text-gray-500">
                                {{ useFormatTime(review.date) }}
                            </div>

                            <div v-if="allow_review_reaction && layout?.iris?.is_logged_in"
                                 class="flex items-center gap-2">
                                <button @click.stop="() => toggleReaction(review, 'review', true)"
                                        :disabled="reactingKeys[`${review.id}-review`] || reactions[review.id] === 'like'"
                                        class="flex h-7 items-center gap-1 rounded px-2 transition disabled:cursor-not-allowed"
                                        :class="reactions[review.id] === 'like'
                                        ? 'bg-green-50 text-green-600'
                                        : 'text-gray-500 hover:bg-gray-100'
                                        ">
                                    <FontAwesomeIcon :icon="faThumbsUp" class="text-[10px]" />
                                    <span class="text-[11px] font-medium">
                                        {{ review.likes }}
                                    </span>
                                </button>

                                <button @click.stop="() => toggleReaction(review, 'review', false)"
                                        :disabled="reactingKeys[`${review.id}-review`] || reactions[review.id] === 'dislike'"
                                        class="flex h-7 w-7 items-center justify-center rounded transition disabled:cursor-not-allowed"
                                        :class="reactions[review.id] === 'dislike'
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


    <Dialog v-model:visible="reviewModalVisible" modal dismissableMask :draggable="false" :closable="false"
            :style="{ width: '640px', maxWidth: '95vw' }" :breakpoints="{ '640px': '100vw' }" :pt="{
            root: { class: 'overflow-hidden rounded-xl' },
            header: { class: 'p-0' },
            content: { class: 'p-0' }
        }">
        <template #header>
            <div class="flex items-start gap-3 border-b border-gray-100 px-5 py-4 w-full">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-500 text-sm font-semibold text-white">
                    {{ selectedReview.name?.charAt(0)?.toUpperCase() ?? "?" }}
                </div>

                <div class="min-w-0 flex-1">

                    <div class="flex items-center gap-2">
                        <div class="truncate text-sm font-semibold text-gray-900">
                            {{ selectedReview.name }}
                        </div>

                        <span class="text-xs text-gray-500">
                            •
                        </span>

                        <span class="text-xs text-gray-500">
                            {{ useFormatTime(selectedReview.date) }}
                        </span>
                    </div>

                    <div class="mt-1 flex items-center gap-2">
                        <Rating :modelValue="selectedReview.rating" readonly :cancel="false"
                                class="review-rating-small rating" />

                        <span class="text-xs font-medium text-gray-600">
                            {{ selectedReview.rating }}/5
                        </span>
                    </div>

                </div>

                <button @click="reviewModalVisible = false"
                        class="flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                    <FontAwesomeIcon :icon="faTimes" class="text-xs" />
                </button>

            </div>
        </template>

        <div class="px-5 pb-4">
            <p class="whitespace-pre-line text-sm leading-6 text-gray-700">
                {{ displayMessage(selectedReview) }}
            </p>
            <div v-if="hasMessageTranslation(selectedReview)" @click="toggleTranslation(selectedReview)"
                 class="mt-1 text-xs text-gray-500 hover:text-gray-700 cursor-pointer">
                {{ showOriginal[selectedReview.id] ? ctrans("See translation") : ctrans("See original") }}
            </div>
            <div v-if="selectedReview.web_images?.length" class="flex gap-3">
                <button v-for="(image, index) in selectedReview.web_images" :key="image.id ?? index" type="button"
                        class="group relative aspect-square w-12 h-12 cursor-zoom-in overflow-hidden rounded-xl border border-gray-200 bg-gray-100">
                    <Image :src="image.original" :alt="selectedReview.name" :imageCover="true"
                           class="h-full w-full flex items-center justify-center transition duration-300 group-hover:scale-105" />
                </button>
            </div>

            <div v-if="allow_review_reaction && layout?.iris?.is_logged_in"
                 class="mt-1 flex items-center justify-between border-b border-gray-100 pt-3">
                <span class="text-xs text-gray-500">
                    Helpful?
                </span>
                <div class="flex items-center gap-2">
                    <button :disabled="reactingKeys[`${selectedReview.id}-review`]"
                            @click="() => toggleReaction(selectedReview, 'review', true)" :class="[
                            'flex h-8 items-center gap-1 rounded-full px-3 text-xs transition',
                            reactions[selectedReview.id] === 'like'
                                ? ' text-green-600'
                                : 'text-gray-500 hover:bg-gray-100'
                        ]">
                        <FontAwesomeIcon :icon="faThumbsUp" />
                        {{ selectedReview.likes ?? 0 }}
                    </button>
                    <button :disabled="reactingKeys[`${selectedReview.id}-review`]"
                            @click="() => toggleReaction(selectedReview, 'review', false)" :class="[
                            'flex h-8 items-center gap-1 rounded-full px-3 text-xs transition',
                            reactions[selectedReview.id] === 'dislike'
                                ? ' text-red-600'
                                : 'text-gray-500 hover:bg-gray-100'
                        ]">
                        <FontAwesomeIcon :icon="faThumbsDown" />
                        {{ selectedReview.dislikes ?? 0 }}
                    </button>

                </div>
            </div>
            <div v-if="selectedReview.reply" class="mt-4 border-l-2 border-orange-300 pl-3">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-orange-600">
                    {{
                        show_staff_who_reply
                            ? selectedReview.reply_by
                            : layout?.iris?.shop?.name
                    }}
                </div>

                <p class="whitespace-pre-line text-sm leading-6 text-gray-700">
                    {{ displayReply(selectedReview) }}
                </p>
                <div v-if="hasReplyTranslation(selectedReview)" @click="toggleReplyTranslation(selectedReview)"
                     class="mt-1 text-xs text-gray-500 hover:text-gray-700 cursor-pointer">
                    {{ showOriginalReply[selectedReview.id] ? ctrans("See translation") : ctrans("See original") }}
                </div>
                <div class="flex items-center w-full justify-end gap-2"
                     v-if="allow_review_reply_reaction && layout?.iris?.is_logged_in">
                    <button :disabled="reactingKeys[`${selectedReview.id}-review_reply`]"
                            @click="() => toggleReaction(selectedReview, 'review_reply', true)" :class="[
                            'flex h-8 items-center gap-1 rounded-full px-3 text-xs transition',
                            replyReactions[selectedReview.id] === 'like'
                                ? ' text-green-600'
                                : 'text-gray-500 hover:bg-gray-100'
                        ]">
                        <FontAwesomeIcon :icon="faThumbsUp" />
                        {{ selectedReview.reply_likes ?? 0 }}
                    </button>
                    <button :disabled="reactingKeys[`${selectedReview.id}-review_reply`]"
                            @click="() => toggleReaction(selectedReview, 'review_reply', false)" :class="[
                            'flex h-8 items-center gap-1 rounded-full px-3 text-xs transition',
                            replyReactions[selectedReview.id] === 'dislike'
                                ? ' text-red-600'
                                : 'text-gray-500 hover:bg-gray-100'
                        ]">
                        <FontAwesomeIcon :icon="faThumbsDown" />
                        {{ selectedReview.reply_dislikes ?? 0 }}
                    </button>

                </div>
            </div>

        </div>
    </Dialog>
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