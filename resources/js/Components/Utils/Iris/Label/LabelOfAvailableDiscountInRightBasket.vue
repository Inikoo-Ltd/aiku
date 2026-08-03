<script setup lang='ts'>
import { getBestOffer } from "@/Composables/useOffers"
import { computed } from "vue"

type Offer = {
    id: number
    type: string
    [key: string]: any
}

const props = defineProps<{
    offers_data: {
        products_triggers_label: string
    } | null
}>()

// const bestOffer = computed<Offer | null>(() => {
//     if (props.offers_data?.number_offers <= 0) return null

//     return getBestOffer(props.offers_data)
// })
</script>

<template>
    <span v-html="offers_data?.products_triggers_label">
        
    </span>
</template>