<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
// library.add(faClipboard)
import { faArrowLeft, faArrowRight } from "@fal"
import CheckoutSummary from "./CheckoutSummary.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"



const data = [
    {
        image: 'https://via.placeholder.com/150',
        code: 'Item 1',
        description: 'Description 1',
        quantity: 2,
        amount_net: 100
    },
    {
        image: 'https://via.placeholder.com/150',
        code: 'Item 2',
        description: 'Description 2',
        quantity: 1,
        amount_net: 50
    }
]
</script>

<template>
    <div class="w-full max-w-6xl mx-auto mt-8">
        <div class="px-4 text-xl">
            <span class="text-gray-500">Order number</span> <span class="font-bold">#GB550706</span>
        </div>
        
        <CheckoutSummary></CheckoutSummary>

        <div class="border border-t border-orange-300"></div>

        

        <div class="flex justify-end gap-x-4 mt-4 px-4">
            
            <ButtonWithLink
                :icon="faArrowLeft"
                type="tertiary"
                label="Back to basket"
                :routeTarget="{
                    name: 'retina.ecom.baskets.index'
                }"
            />
        </div>
    </div>
</template>