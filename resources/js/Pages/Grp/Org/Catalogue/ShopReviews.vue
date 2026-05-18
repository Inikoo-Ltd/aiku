<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableReviews from "@/Components/Shop/Reviews/TableReviews.vue"
import ModalCreateCategoryReviews from "@/Components/Reviews/ModalCreateCategoryReviews.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    shop_id: number
    reviews: Record<string, any>
}>()

const reviewCustomers = computed(() => {
    const reviewsData = props.reviews as Record<string, any> | undefined
    return reviewsData?.customers ?? {
        data: [],
        meta: {
            current_page: 1,
            per_page: 20,
            next_page: null,
            has_more: false,
        },
    }
})

const reviewRatingLabels = computed(() => {
    const reviewsData = props.reviews as Record<string, any> | undefined
    return Array.isArray(reviewsData?.rating_labels) ? reviewsData.rating_labels : []
})
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #otherBefore>
           <!--  <ModalCreateCategoryReviews
                :product_category_id="props.shop_id"
                :reviewable_id="props.shop_id"
                reviewable_type="Shop"
                :customers="reviewCustomers"
                :rating_labels="reviewRatingLabels"
                v-tooltip="'Create New Review'"
            /> -->
        </template>
    </PageHeading>

    <TableReviews
        :data="props.reviews"
        tab="reviews"
        :reviewable_id="props.shop_id"
        reviewable_type="Shop"
        :customers="reviewCustomers"
        :rating_labels="reviewRatingLabels"
    />
</template>
