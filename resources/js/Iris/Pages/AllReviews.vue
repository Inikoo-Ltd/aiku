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
import UnderConstruction from "./Disclosure/UnderConstruction.vue"


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
   <div>
        <UnderConstruction />
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
