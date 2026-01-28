<script setup lang="ts">
import { computed, defineAsyncComponent } from "vue"

type Offer = {
    id: number
    type: string
    [key: string]: any
}

const props = defineProps<{
    offers_data: {
        number_offers: number
        offers: Offer[]
        best_percentage_off?: {
            offer_id: number
            percentage_off: string
        }
    }
}>()

const componentsMap = {
    'Category Quantity Ordered Order Interval': defineAsyncComponent(() => import("@/Components/Utils/Iris/AvailableVolOfferLabel.vue")),
    'Category Ordered': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/DiscountCategoryOrdered.vue")),
   /*  'Amount AND Order Number': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/BasicDiscount.vue")),
    'Category Quantity Ordered': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/ACDiscount.vue")), */
} as const

const fallbackComponent = null

const bestOffer = computed<Offer | null>(() => {
  const offerId = props.offers_data?.best_percentage_off?.offer_id
  if (!offerId) return null

  const offers = props.offers_data?.offers
  if (!offers) return null

  const list = Array.isArray(offers)
    ? offers
    : Object.values(offers)

  return list.find(offer => offer.id === offerId) ?? null
})


const resolvedComponent = computed(() => {
    if (!bestOffer.value) return fallbackComponent

    return (
        componentsMap[
            bestOffer.value.type as keyof typeof componentsMap
        ] ?? fallbackComponent
    )
})
console.log('offers',props.offers_data)
</script>


<template>
    <component
        v-if="bestOffer && offers_data.number_offers > 0"
        :is="resolvedComponent"
        :offer="bestOffer"
        :type="bestOffer.type"
    />
</template>
