<script setup lang="ts">
import ListReviews from "@/Components/ListReviews.vue"
import Image from "@/Common/Components/Image.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPhone, faEnvelope, faLocationDot, faGlobe } from "@fortawesome/free-solid-svg-icons"
import type { Image as ImageProxy } from "@/types/Image"
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { inject } from "vue"


const props = defineProps<{
    type?: "company" | "product"
    webpage_slug?: string
    reviews?: { data?: any[]; meta?: { total?: number } }
    avg_review?: number
    total_reviews: number
    recommend_percent: number
    review_settings: object
    heading : string
    tabs: {
        current: string
        navigation: { key: string; label: string }[]
    }
    shop_profile?: {
        name: string
        email?: string
        phone?: string
        logo?: ImageProxy | null
        formatted_address?: string
        country?: string
    }
}>()
const layout = inject("layout", {})
const ratingStars = computed(() => Array.from({ length: 5 }, (_, index) => index + 1))
const averageRating = computed(() => props.avg_review ?? 0)



const initialTab = (): string => {
    const paramTab = new URLSearchParams(window.location.search).get("tab")
    if (paramTab && props.tabs?.navigation?.some((tab) => tab.key === paramTab)) {
        return paramTab
    }
    return props.tabs?.current || "all"
}

const activeTab = ref<string>(initialTab())

const selectTab = (key: string) => {
    if (activeTab.value === key) {
        return
    }
    activeTab.value = key
    router.get(
        window.location.pathname,
        { tab: key },
        { preserveState: true, preserveScroll: true, replace: true }
    )
}
const heroTitle = computed(
    () =>
       props.heading ?  props.heading : `${props.shop_profile?.name ?? "Shop"} ${props.tabs.current != 'all' ? props.tabs.current : ''} Reviews`
)
const reviewItems = computed(() => props.reviews?.data ?? [])
const hasReviews = computed(() => reviewItems.value.length > 0)
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Hero -->
        <section class="bg-white border-b">
            <div class="max-w-7xl mx-auto px-8 py-10 lg:flex lg:items-start lg:justify-between">
                <div class="lg:max-w-2xl">
                    <h1 class="text-4xl font-bold text-gray-900">
                        {{ heroTitle }}
                    </h1>

                    <div class="flex items-center gap-2 mt-6">
                        <span v-for="star in ratingStars" :key="star" :class="star <= Math.round(averageRating)
                            ? 'text-yellow-400'
                            : 'text-gray-200'
                            " class="text-4xl">
                            ★
                        </span>
                        <span class="text-sm text-gray-500">
                            ({{ averageRating.toFixed(1) }}/5)
                        </span>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-3xl bg-gray-50 p-6 shadow-sm">
                            <div class="text-sm text-gray-500">Average Rating</div>
                            <div class="mt-2 text-3xl font-bold text-gray-900">
                                {{ avg_review?.toFixed(1) ?? "0.0" }}/5
                            </div>
                        </div>

                        <div class="rounded-3xl bg-gray-50 p-6 shadow-sm">
                            <div class="text-sm text-gray-500">Total Reviews</div>
                            <div class="mt-2 text-3xl font-bold text-gray-900">
                                {{ total_reviews ?? 0 }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-10 lg:mt-0 lg:min-w-[260px] rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col items-center">
                        <span class="text-sm font-medium text-gray-500">
                            {{ ctrans('Recommend Rate') }}
                        </span>

                        <div class="relative mt-5 flex items-center justify-center">
                            <svg class="h-36 w-36 -rotate-90">
                                <!-- Background -->
                                <circle cx="72" cy="72" r="56" stroke="#E5E7EB" stroke-width="12" fill="none" />

                                <!-- Progress -->
                                <circle cx="72" cy="72" r="56" stroke="currentColor" stroke-width="12" fill="none"
                                    class="text-primary transition-all duration-700" stroke-linecap="round"
                                    :stroke-dasharray="2 * Math.PI * 56" :stroke-dashoffset="(2 * Math.PI * 56) *
                                        (1 - (recommend_percent ?? 0) / 100)
                                        " />
                            </svg>

                            <div class="absolute text-center">
                                <div class="text-2xl font-bold text-primary">
                                    {{ recommend_percent ?? 0 }}%
                                </div>
                                <div class="mt-1 text-[10px] text-gray-500">
                                    Recommended
                                </div>
                            </div>
                        </div>

                        <p class="mt-6 max-w-[180px] text-center text-sm leading-6 text-gray-600">
                            <span class="font-semibold text-gray-900">
                                {{ recommend_percent ?? 0 }}%
                            </span>
                            of customers recommend this
                            <span class="font-medium">
                                {{ $props.tabs.current != 'all' ? $props.tabs.current : 'company' }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-t bg-white">
                
                <div class="max-w-7xl mx-auto flex overflow-x-auto text-sm font-semibold text-gray-700">
                    <button v-for="tab in tabs?.navigation" :key="tab.key" type="button"
                        class="flex-1 whitespace-nowrap border-b-4 px-8 py-6 text-center transition hover:text-gray-900"
                        :class="{
                            'border-primary text-primary': activeTab === tab.key,
                            'border-transparent': activeTab !== tab.key,
                        }" @click="selectTab(tab.key)">
                        {{ tab.label }}
                    </button>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-12">
                <!-- Left -->
                <div class="lg:col-span-4">
                    <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
                        <div class="space-y-6 p-8 text-center">
                            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-gray-50">
                                <Image v-if="props.shop_profile?.logo" :src="props.shop_profile.logo"
                                    :alt="props.shop_profile.name ?? 'Shop Logo'" :imageCover="true"
                                    class="h-full w-full" />
                                <span v-else class="text-3xl font-bold text-gray-400">
                                    {{ props.shop_profile?.name?.charAt(0)?.toUpperCase() ?? "?" }}
                                </span>
                            </div>

                            <div>
                                <div class="text-2xl font-bold text-gray-900">
                                    {{ props.shop_profile?.name ?? "Unknown Shop" }}
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    {{
                                        props.type === "product"
                                            ? "Product reviews summary"
                                            : "Company reviews summary"
                                    }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 border-t border-gray-100 px-8 py-8 text-gray-600">
                            <div v-if="props.shop_profile?.formatted_address" class="flex items-start gap-3">
                                <FontAwesomeIcon :icon="faLocationDot" class="mt-1 w-4 text-gray-400" />
                                <span v-html="props.shop_profile.formatted_address"></span>
                            </div>

                            <div v-if="props.shop_profile?.country" class="flex items-start gap-3">
                                <FontAwesomeIcon :icon="faGlobe" class="mt-1 w-4 text-gray-400" />
                                <span>{{ props.shop_profile.country }}</span>
                            </div>

                            <div v-if="props.shop_profile?.phone" class="flex items-start gap-3">
                                <FontAwesomeIcon :icon="faPhone" class="mt-1 w-4 text-gray-400" />
                                <span>{{ props.shop_profile.phone }}</span>
                            </div>

                            <div v-if="props.shop_profile?.email" class="flex items-start gap-3">
                                <FontAwesomeIcon :icon="faEnvelope" class="mt-1 w-4 text-gray-400" />
                                <span>{{ props.shop_profile.email }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right -->
                <div class="lg:col-span-8">
                    <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white p-8 pt-0 shadow-sm">
                        <div class="mt-10">
                            <h3 class="text-xl font-semibold text-gray-900">Recent reviews</h3>
                            <div class="mt-0">
                                <ListReviews v-if="hasReviews" :data="props.reviews" :tab="activeTab"
                                    :readonly="!layout?.iris?.is_logged_in" :showTagVisibleType="false" :review_settings
                                    :reaction_routes="{
                                        name: 'iris.models.review.react'
                                    }">
                                    <template #image-item="{ item, openImagePreview }">
                                        <button type="button" v-for="(image, index) in item.review.review_images"
                                            :key="image.id ?? index"
                                            class="group relative aspect-square w-12 h-12 cursor-zoom-in overflow-hidden rounded-xl border border-gray-200 bg-gray-100"
                                            @click="openImagePreview(item.review.review_images, index)">

                                            <Image :src="image.original" :alt="image.name" :imageCover="true"
                                                class="h-full w-full flex items-center justify-center transition duration-300 group-hover:scale-105" />
                                        </button>
                                    </template>
                                    <template #image-dialog="{ images }">
                                        <Image :src="images.original" :alt="images.name" :imageCover="true"
                                            :style="{ objectFit: 'contain' }" />
                                    </template>
                                </ListReviews>

                                <div v-else
                                    class="rounded-3xl border border-dashed border-gray-200 bg-gray-50 p-8 text-center text-sm text-gray-500">
                                    No reviews have been published yet.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
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
