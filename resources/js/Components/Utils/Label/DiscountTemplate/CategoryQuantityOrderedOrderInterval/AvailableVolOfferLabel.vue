<script setup lang="ts">
import { computed, inject } from 'vue';
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { getGoldenProductTriggerLabel } from '@/Composables/useOffers'
import GoldenProductBadge from '@/Components/CMS/Webpage/Products/GoldenProductBadge.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'

const props = defineProps<{
    isGoldenProduct?: boolean
    offer: {
        products_triggers_label: string
        allowances?: {
            percentage_off?: number
        }[]
    }
}>()

const layout = inject("layout", retinaLayoutStructure)

const goldenProductLabel = computed(() => {
    return props.isGoldenProduct ? getGoldenProductTriggerLabel(props.offer) : ""
})
</script>

<template>
    <div v-if="goldenProductLabel" class="md:mt-1 flex gap-x-2 items-center text-[#E97929]">
        <GoldenProductBadge v-if="props.isGoldenProduct" class="!h-8 !w-8 md:!h-9 md:!w-9"/>
        <span class="font-bold">{{ goldenProductLabel }}</span>
        <InformationIcon :information="ctrans('Add a Golden Product to your basket to unlock the volume discount across the entire product family.')" />
    </div>
    <div v-else class="offer-trigger-label">
        <span v-html="offer?.products_triggers_label ?? '-'" />
    </div>
</template>

<style scoped>
.offer-trigger-label {
  @apply bg-gray-50 border border-b-4 rounded-md px-2 py-1 leading-3 text-xxs;
  border-color: var(--theme-color-4);
  color : var(--theme-color-4);
}
</style>