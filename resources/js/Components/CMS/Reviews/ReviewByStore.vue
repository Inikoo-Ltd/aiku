<script setup lang="ts">
import { inject, computed } from "vue"

// Components
import ReviewsStoreByReviewIO from "@/Components/CMS/Reviews/ReviewsStoreByReviewIO.vue"
import ReviewsStoreByTrustpilot from "@/Components/CMS/Reviews/ReviewStoreByTrustPilot.vue"

interface ProductResource {
    id: number
    code: string
}

const props = defineProps<{
    code: string
}>()


const layout: any = inject("layout", {})
const review = computed(() => layout?.iris?.website?.reviews_settings)

const componentMap: Record<string, any> = {
    "reviews.io": ReviewsStoreByReviewIO,
    "trust_pilot": ReviewsStoreByTrustpilot,
}

const resolvedComponent = computed(() => {
    if (!review.value) return null
    return componentMap[review.value.provider] ?? null
})
</script>

<template>
    <div v-if="!resolvedComponent">
    </div>

    <component v-else :is="resolvedComponent" :review="review" :code="code"/>
</template>

<style scoped></style>