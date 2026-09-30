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
            <span class="rounded bg-amber-200 px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wide">
                {{ preOrder.type_label }}
            </span>
            <span class="font-medium">{{ preOrder.dispatch_label }}</span>
        </div>
        <div v-if="(stock ?? 0) > 0" class="mt-1 text-xs">
            {{ ctrans("The first :stock are sent now, the rest when the goods arrive.", { stock: String(stock) }) }}
        </div>
        <div v-if="preOrder.deposit_percentage < 100" class="mt-1 text-xs">
            {{ ctrans(":percentage% deposit at checkout, balance when the goods arrive.", { percentage: String(preOrder.deposit_percentage) }) }}
        </div>
        <div v-if="preOrder.max_quantity" class="mt-1 text-xs">
            {{ ctrans("Maximum :quantity per order.", { quantity: String(preOrder.max_quantity) }) }}
        </div>
        <div v-if="preOrder.pallet_estimate_label" class="mt-1 text-xs">
            {{ preOrder.pallet_estimate_label }}
        </div>
        <div v-if="showTerms && preOrder.terms?.length" class="mt-1">
            <button type="button" class="text-xs underline" @click="isTermsOpen = !isTermsOpen">
                {{ isTermsOpen ? ctrans("Hide pre-order terms") : ctrans("Pre-order terms") }}
            </button>
            <ul v-if="isTermsOpen" class="mt-1 list-disc space-y-0.5 pl-4 text-xs">
                <li v-for="term in preOrder.terms" :key="term">{{ term }}</li>
            </ul>
        </div>
    </div>
</template>
