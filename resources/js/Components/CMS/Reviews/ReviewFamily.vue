<script setup lang="ts">
import { inject, computed } from "vue"

// Components
import ReviewsFamilyByReviewIO from "@/Components/CMS/Reviews/ReviewsFamilyByReviewIO.vue"
// import ReviewsProductByTrustpilot from "@/Components/CMS/Reviews/ReviewsProductByTrustpilot.vue"

interface ProductResource {
    id: number
    code: string
}

const props = defineProps<{
    products: ProductResource
    code: string
}>()


const layout: any = inject("layout", {})
const review = computed(() => layout?.iris?.website?.reviews_settings)

const componentMap: Record<string, any> = {
    "reviews.io": ReviewsFamilyByReviewIO,
}

const resolvedComponent = computed(() => {
    if (!review.value) return null
    return componentMap[review.value.provider] ?? null
})
</script>

<template>
    <div v-if="!resolvedComponent">
    </div>

    <component v-else :is="resolvedComponent" :products="products" :review="review" :code="code"/>
</template>

<style scoped></style>