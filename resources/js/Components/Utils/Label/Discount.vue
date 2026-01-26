<script setup lang="ts">
import { computed, defineAsyncComponent } from "vue"

const props = defineProps<{
    template : string
    offers_data: {
        v: number
        o: {
            oc: number  // Offer Campaign id
            o: number  // Offer id
            oa: number  // Offer Allowance id
            t: string  // Type: "percentage"
            p: string  // Percentage: "10.0%"
            l: string  // Label
        }
    }
}>()

const componentsMap = {
  basic: defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/BasicDiscount.vue")),
  agnes_and_cat: defineAsyncComponent(() => import("@/Components/Utils/Label/DiscountTemplate/ACDiscount.vue")),
} as const


const resolvedComponent = computed(() => {
  return componentsMap[props.template as keyof typeof componentsMap]
    ?? componentsMap.basic
})


</script>

<template>
    <component 
        :is="resolvedComponent"
        :key="template"
        :offers_data="offers_data"
    />
</template>