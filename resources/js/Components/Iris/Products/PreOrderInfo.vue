<script setup lang="ts">
import { ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { PreOrder } from "@/types/Iris/Products"

const props = withDefaults(defineProps<{
    preOrder: PreOrder
    stock?: number | null
    showTerms?: boolean
}>(), {
    stock: null,
    showTerms: true,
})

const isTermsOpen = ref(false)
</script>

<template>
    <div class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="font-semibold">{{ preOrder.available_label }}</span>
            <span>·</span>
            <span class="font-medium">{{ preOrder.dispatch_label }}</span>
        </div>
        <div class="mt-1 text-xs font-medium">
            {{ preOrder.payment_label }}
        </div>
        <div v-if="preOrder.max_quantity" class="mt-1 text-xs">
            {{ ctrans("Maximum :quantity per order.", { quantity: String(preOrder.max_quantity) }) }}
        </div>
        <div v-if="preOrder.pallet_estimate_label" class="mt-1 text-xs">
            {{ preOrder.pallet_estimate_label }}
        </div>
        <div v-if="showTerms && preOrder.terms?.length" class="mt-1">
            <button type="button" class="text-xs underline" @click="isTermsOpen = !isTermsOpen">
                {{ preOrder.terms_label }}
            </button>
            <ul v-if="isTermsOpen" class="mt-1 list-disc space-y-0.5 pl-4 text-xs">
                <li v-for="term in preOrder.terms" :key="term">{{ term }}</li>
            </ul>
        </div>
    </div>
</template>
