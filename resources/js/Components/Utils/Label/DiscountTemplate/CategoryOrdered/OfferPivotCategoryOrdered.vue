<script setup lang="ts">
import { computed, defineAsyncComponent } from "vue"

type Offer = {
    id: number
    type: string
    [key: string]: any
}

const props = defineProps<{
    template : string
    use_duration?: boolean
    offer: {
        offers: Offer[]
    }
}>()

const componentsMap = {
    'max_discount' : defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/CategoryOrdered/CategoryOrderedByMaxDiscount.vue")),
    'max_discount_2' : defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/SpecialOffer.vue")),
    'max_discount_3' : defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/SpecialOffer3.vue")),
    'triggers_labels': defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/CategoryQuantityOrderedOrderInterval/FamilyOfferLabelDiscount.vue")),
} as const

const fallbackComponent = null

const resolvedComponent = computed(() => {
    if (!props.offer) return fallbackComponent

    return (
        componentsMap[ props.template as keyof typeof componentsMap ] ?? fallbackComponent
    )
})
</script>


<template>
    <component
        :is="resolvedComponent"
        :offer="offer"
        :use_duration="use_duration"
    />
</template>
