<script setup lang="ts">
import { inject, computed } from "vue"

// Components
import ReviewsProductByReviewIO from "@/Components/CMS/Reviews/ReviewsProductByReviewIO.vue"
// import ReviewsProductByTrustpilot from "@/Components/CMS/Reviews/ReviewsProductByTrustpilot.vue"

interface ProductResource {
    id: number
    code: string
}

const props = defineProps<{
    product: ProductResource
}>()


const layout: any = inject("layout", {})
const review = computed(() => layout?.iris?.website?.reviews_settings)

const componentMap: Record<string, any> = {
    "reviews.io": ReviewsProductByReviewIO,
}

const resolvedComponent = computed(() => {
    if (!review.value) return null
    return componentMap[review.value.provider] ?? null
})
</script>

<template>
    <div v-if="!resolvedComponent"></div>
    <component v-else :is="resolvedComponent" :product="product" :review="review" />
</template>

<style scoped></style>