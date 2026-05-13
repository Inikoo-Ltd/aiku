<script setup lang="ts">
import { computed, defineAsyncComponent } from "vue"
import { getBestOffer } from "@/Composables/useOffers";
type Offer = {
    id: number
    type: string
    [key: string]: any
}

const props = defineProps<{
    template : string  // 'active-inactive-gr', 'triggers_labels'
    offers_data: {
        number_offers: number
        offers: Offer[]
        best_percentage_off?: {
            offer_id: number
            percentage_off: string
        }
    }
    use_duration?: boolean
}>()

const componentsMap = {
    'Category Quantity Ordered Order Interval': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/CategoryQuantityOrderedOrderInterval/OfferPivotGr.vue")),
    'Category Ordered': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/CategoryOrdered/OfferPivotCategoryOrdered.vue")),
    'First Order Bonus': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/FirstOrder/OfferPivotFirstOrder.vue")),
    'Category Amount Ordered': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/CategoryOrdered/OfferPivotCategoryOrdered.vue")),
    'Department Quantity Ordered': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/CategoryOrdered/OfferPivotCategoryOrdered.vue")),
    'Subdepartment Quantity Ordered': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/CategoryOrdered/OfferPivotCategoryOrdered.vue")),
    'Department Ordered': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/CategoryOrdered/OfferPivotCategoryOrdered.vue")),
    'Subdepartment Ordered': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/CategoryOrdered/OfferPivotCategoryOrdered.vue")),

} as const

const fallbackComponent = null

const bestOffer = computed<Offer | null>(() => {
  if (props.offers_data?.number_offers <= 0) return null

  return getBestOffer(props.offers_data)
})

const resolvedComponent = computed(() => {
    if (!bestOffer.value) return fallbackComponent

    return (
        componentsMap[
            bestOffer.value.type as keyof typeof componentsMap
        ] ?? fallbackComponent
    )
})
</script>


<template>
    <component
        v-if="bestOffer && offers_data.number_offers > 0"
        :is="resolvedComponent"
        :offer="bestOffer"
        :template
        :use_duration="use_duration"
    />
</template>