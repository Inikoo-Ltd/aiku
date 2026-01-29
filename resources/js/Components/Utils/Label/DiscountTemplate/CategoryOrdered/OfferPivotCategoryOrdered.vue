<script setup lang="ts">
import { computed, defineAsyncComponent } from "vue"

type Offer = {
    id: number
    type: string
    [key: string]: any
}

const props = defineProps<{
    template : string
    offer: {
        offers: Offer[]
    }
}>()

const componentsMap = {
    'max_discount' : defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/CategoryOrdered/CategoryOrderedByMaxDiscount.vue")),
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
    />
</template>
