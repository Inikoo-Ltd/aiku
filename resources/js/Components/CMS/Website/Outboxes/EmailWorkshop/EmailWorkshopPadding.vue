<script setup lang="ts">
import { computed, ref } from 'vue'
import EmailWorkshopField from './EmailWorkshopField.vue'
import { ctrans } from '@/Composables/useTrans'
import { CssStyle } from './emailWorkshopBlocks'

const props = withDefaults(defineProps<{
    target: CssStyle
    label?: string
    keyPrefix?: string
}>(), {
    keyPrefix: 'padding',
})

const sides = ['top', 'right', 'bottom', 'left'] as const
const sideKey = (side: string) => `${props.keyPrefix}-${side}`

const hasDifferentSides = computed(() => new Set(sides.map((side) => String(props.target[sideKey(side)] ?? '0px'))).size > 1)
const isExpanded = ref(hasDifferentSides.value)

const allSides = computed({
    get: () => props.target[sideKey('top')] ?? '0px',
    set: (value) => {
        for (const side of sides) {
            props.target[sideKey(side)] = value
        }
    },
})

const sideLabels: Record<string, string> = {
    top: ctrans('Top'),
    right: ctrans('Right'),
    bottom: ctrans('Bottom'),
    left: ctrans('Left'),
}
</script>

<template>
    <div>
        <EmailWorkshopField v-if="!isExpanded" v-model="allSides" type="px" :label="label ?? ctrans('Padding')" />
        <div v-else class="border-b border-gray-100 py-2.5">
            <div class="mb-2 text-[13px] text-gray-600">{{ label ?? ctrans('Padding') }}</div>
            <div class="grid grid-cols-2 gap-x-3 gap-y-2">
                <EmailWorkshopField v-for="side in sides" :key="side" v-model="target[sideKey(side)]" type="px" stacked
                    :label="sideLabels[side]" class="!border-b-0 !py-0" />
            </div>
        </div>
        <EmailWorkshopField v-model="isExpanded" type="toggle" :label="ctrans('More options')" />
    </div>
</template>
