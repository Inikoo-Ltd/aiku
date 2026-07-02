<script setup lang="ts">
import ListReviews from '@/Components/ListReviews.vue'


const props = defineProps<{
    webpage_slug?: string
    reviews?:any
    avg_review? : number
    total_reviews : number
    recommend_percent : number
}>()

const review_settings = {
  show_staff_who_reply : true
}
</script>

<template>
  <ListReviews 
    :review_settings="review_settings" 
    :data="props.reviews" 
    :tab="'reviews'"
  />
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