<script setup lang="ts">
import ListReviews from "@/Components/ListReviews.vue"
import Image from "@/Common/Components/Image.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPhone, faEnvelope, faLocationDot, faGlobe } from "@fortawesome/free-solid-svg-icons"
import type { Image as ImageProxy } from "@/types/Image"
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { inject } from "vue"
import StarRating from "@/Iris/Components/StarRating.vue"


const props = defineProps<{
    type?: "company" | "product"
    webpage_slug?: string
    reviews?: { data?: any[]; meta?: { total?: number } }
    avg_review?: number
    total_reviews: number
    recommend_percent: number
    review_settings: object
    heading: string
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
    if (typeof window === "undefined") {
        return props.tabs?.current || "all"
    }
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
        props.heading ? props.heading : `${props.shop_profile?.name ?? "Shop"} ${props.tabs.current != 'all' ? props.tabs.current : ''} Reviews`
)
const reviewItems = computed(() => props.reviews?.data ?? [])
const hasReviews = computed(() => reviewItems.value.length > 0)
</script>

<template>
    <div class="min-h-screen overflow-x-hidden bg-gray-50">
        <!-- Hero -->
        <section class="border-b bg-white">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:flex-row lg:items-start lg:justify-between lg:px-8">

                <!-- Left -->
                <div class="w-full lg:flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl lg:text-4xl">
                        {{ heroTitle }}
                    </h1>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <StarRating :modelValue="parseFloat(averageRating)" class="text-3xl sm:text-4xl" />
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-3xl bg-gray-50 p-5 shadow-sm">
                            <div class="text-sm text-gray-500">
                                Average Rating
                            </div>

                            <div class="mt-2 text-3xl font-bold text-gray-900">
                                {{ avg_review?.toFixed(1) ?? "0.0" }}/5
                            </div>
                        </div>

                        <div class="rounded-3xl bg-gray-50 p-5 shadow-sm">
                            <div class="text-sm text-gray-500">
                                Total Reviews
                            </div>

                            <div class="mt-2 text-3xl font-bold text-gray-900">
                                {{ total_reviews ?? 0 }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right -->
                <div
                    class="w-full rounded-3xl border border-gray-200 bg-white p-5 shadow-sm lg:w-[280px] lg:flex-shrink-0">

                    <div class="flex flex-col items-center">

                        <span class="text-sm font-medium text-gray-500">
                            {{ ctrans('Recommend Rate') }}
                        </span>

                        <div class="relative mt-5 flex w-full items-center justify-center overflow-hidden">

                            <svg viewBox="0 0 160 160" class="h-28 w-28 -rotate-90 overflow-visible sm:h-32 sm:w-32">

                                <circle cx="80" cy="80" r="60" stroke="#E5E7EB" stroke-width="12" fill="none" />

                                <circle cx="80" cy="80" r="60" stroke="currentColor" stroke-width="12" fill="none"
                                    stroke-linecap="round" class="text-primary transition-all duration-700"
                                    :stroke-dasharray="2 * Math.PI * 60" :stroke-dashoffset="(2 * Math.PI * 60) *
                                        (1 - (recommend_percent ?? 0) / 100)
                                        " />
                            </svg>

                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <div class="text-xl font-bold text-primary sm:text-2xl">
                                    {{ recommend_percent ?? 0 }}%
                                </div>
                            </div>
                        </div>

                        <p class="mt-5 max-w-xs text-center text-sm leading-6 text-gray-600">
                            <span class="font-semibold text-gray-900">
                                {{ recommend_percent ?? 0 }}%
                            </span>

                            {{ ctrans(' of customers recommend this') }}

                            <span class="font-medium">
                                {{ $props.tabs.current != 'all'
                                    ? $props.tabs.current
                                    : 'company'
                                }}
                            </span>
                        </p>

                    </div>
                </div>

            </div>

            <!-- Tabs -->
            <div class="border-t bg-white">

                <div
                    class="mx-auto flex max-w-7xl overflow-x-auto px-4 text-sm font-semibold text-gray-700 sm:px-6 lg:px-8">

                    <button v-for="tab in tabs?.navigation" :key="tab.key" type="button"
                        class="flex-shrink-0 whitespace-nowrap border-b-4 px-5 py-4 transition hover:text-gray-900 sm:flex-1 sm:px-8 sm:py-5"
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
        <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-10">

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-10">

                <!-- Left -->
                <div class="lg:col-span-4">

                    <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">

                        <div class="space-y-6 p-6 text-center">

                            <div
                                class="mx-auto flex h-24 w-24 items-center justify-center overflow-hidden rounded-full bg-gray-50">

                                <Image v-if="props.shop_profile?.logo" :src="props.shop_profile.logo"
                                    :alt="props.shop_profile.name ?? 'Shop Logo'" :imageCover="true"
                                    class="h-full w-full" />

                                <span v-else class="text-3xl font-bold text-gray-400">

                                    {{ props.shop_profile?.name?.charAt(0)?.toUpperCase() ?? "?" }}

                                </span>

                            </div>

                            <div class="break-words text-2xl font-bold text-gray-900">

                                {{ props.shop_profile?.name ?? "Unknown Shop" }}

                            </div>

                        </div>

                        <div class="space-y-4 border-t border-gray-100 px-6 py-6 text-sm text-gray-600">

                            <div v-if="props.shop_profile?.formatted_address" class="flex items-start gap-3">

                                <FontAwesomeIcon :icon="faLocationDot" class="mt-1 w-4 shrink-0 text-gray-400" />

                                <span class="break-words" v-html="props.shop_profile.formatted_address">
                                </span>

                            </div>

                            <div v-if="props.shop_profile?.country" class="flex items-start gap-3">

                                <FontAwesomeIcon :icon="faGlobe" class="mt-1 w-4 shrink-0 text-gray-400" />

                                <span class="break-words">
                                    {{ props.shop_profile.country }}
                                </span>

                            </div>

                            <div v-if="props.shop_profile?.phone" class="flex items-start gap-3">

                                <FontAwesomeIcon :icon="faPhone" class="mt-1 w-4 shrink-0 text-gray-400" />

                                <span class="break-all">
                                    {{ props.shop_profile.phone }}
                                </span>

                            </div>

                            <div v-if="props.shop_profile?.email" class="flex items-start gap-3">

                                <FontAwesomeIcon :icon="faEnvelope" class="mt-1 w-4 shrink-0 text-gray-400" />

                                <span class="break-all">
                                    {{ props.shop_profile.email }}
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Right -->
                <div class="lg:col-span-8">

                    <div
                        class="overflow-x-hidden rounded-3xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6 lg:p-8">

                        <h3 class="text-xl font-semibold text-gray-900">
                            Recent reviews
                        </h3>

                        <div class="mt-6">

                            <ListReviews  :data="props.reviews" :tab="activeTab"
                                :readonly="!layout?.iris?.is_logged_in" :showTagVisibleType="false" :review_settings
                                :reaction_routes="{
                                    name: 'iris.models.review.react'
                                }">

                                <template #image-item="{ item, openImagePreview }">

                                    <button v-for="(image, index) in item.review.review_images" :key="image.id ?? index"
                                        type="button"
                                        class="group relative h-14 w-14 overflow-hidden rounded-xl border border-gray-200 bg-gray-100"
                                        @click="openImagePreview(item.review.review_images, index)">

                                        <Image :src="image.original" :alt="image.name" :imageCover="true"
                                            class="h-full w-full transition duration-300 group-hover:scale-105" />

                                    </button>

                                </template>

                                <template #image-dialog="{ images }">

                                    <Image :src="images.original" :alt="images.name" :imageCover="true"
                                        :style="{ objectFit: 'contain' }" />

                                </template>

                            </ListReviews>


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
