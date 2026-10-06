<script setup lang="ts">
import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faAlignLeft, faAlignCenter, faAlignRight, faMinus, faPlus } from '@fal'

library.add(faAlignLeft, faAlignCenter, faAlignRight, faMinus, faPlus)

const props = withDefaults(defineProps<{
    label: string
    type?: 'text' | 'px' | 'color' | 'select' | 'textarea' | 'toggle' | 'range' | 'number' | 'align'
    options?: Array<{ label: string, value: string }>
    min?: number
    max?: number
    step?: number
    unit?: string
    stacked?: boolean
}>(), {
    type: 'text',
    min: 0,
    max: 100,
    step: 1,
    unit: 'px',
    stacked: false,
})

const model = defineModel<any>()

const numericValue = computed<number>({
    get: () => {
        const parsed = parseFloat(String(model.value ?? ''))
        return Number.isFinite(parsed) ? parsed : 0
    },
    set: (value: number) => {
        const bounded = Math.min(Math.max(Number(value) || 0, props.min), props.type === 'px' ? Number.MAX_SAFE_INTEGER : props.max)
        model.value = props.type === 'px' ? `${bounded}${props.unit}` : bounded
    },
})

const isHexColor = computed(() => /^#[0-9a-f]{6}$/i.test(String(model.value ?? '')))
const isStacked = computed(() => props.stacked || props.type === 'text' || props.type === 'textarea')

const alignments = [
    { value: 'left', icon: 'fal fa-align-left' },
    { value: 'center', icon: 'fal fa-align-center' },
    { value: 'right', icon: 'fal fa-align-right' },
]
</script>

<template>
    <div class="border-b border-gray-100 py-2.5 last:border-b-0" :class="isStacked ? 'space-y-1.5' : 'flex items-center justify-between gap-x-3'">
        <span class="shrink-0 text-[13px] text-gray-600">{{ label }}</span>

        <button v-if="type === 'toggle'" type="button" role="switch" :aria-checked="!!model"
            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full transition-colors"
            :class="model ? 'bg-[var(--theme-color-4)]' : 'bg-gray-300'" @click="model = !model">
            <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform" :class="model ? 'translate-x-[18px]' : 'translate-x-0.5'" />
        </button>

        <div v-else-if="type === 'px' || type === 'number'" class="flex h-8 items-stretch overflow-hidden rounded border border-gray-300 bg-white" :class="isStacked ? 'w-full' : ''">
            <button type="button" class="w-7 shrink-0 text-gray-500 hover:bg-gray-100" @click="numericValue = numericValue - step">
                <FontAwesomeIcon icon="fal fa-minus" class="text-[11px]" fixed-width aria-hidden="true" />
            </button>
            <input v-model.number="numericValue" type="number" :min="min" :aria-label="label"
                class="min-w-0 border-0 border-x border-gray-200 p-0 text-center text-[13px] focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                :class="isStacked ? 'flex-1' : 'w-12'" />
            <span v-if="type === 'px'" class="flex shrink-0 items-center bg-gray-50 px-1.5 text-[11px] text-gray-400">{{ unit }}</span>
            <button type="button" class="w-7 shrink-0 border-l border-gray-200 text-gray-500 hover:bg-gray-100" @click="numericValue = numericValue + step">
                <FontAwesomeIcon icon="fal fa-plus" class="text-[11px]" fixed-width aria-hidden="true" />
            </button>
        </div>

        <div v-else-if="type === 'range'" class="flex w-40 items-center gap-x-2">
            <input v-model.number="model" type="range" :min="min" :max="max" class="w-full accent-[var(--theme-color-4)]" />
            <span class="w-10 text-right text-[12px] text-gray-500">{{ model }}%</span>
        </div>

        <div v-else-if="type === 'align'" class="flex overflow-hidden rounded border border-gray-300">
            <button v-for="alignment in alignments" :key="alignment.value" type="button"
                class="h-8 w-9 border-l border-gray-200 first:border-l-0"
                :class="model === alignment.value ? 'bg-[var(--theme-color-4)] text-[var(--theme-color-5)]' : 'bg-white text-gray-500 hover:bg-gray-100'"
                @click="model = alignment.value">
                <FontAwesomeIcon :icon="alignment.icon" fixed-width aria-hidden="true" />
            </button>
        </div>

        <div v-else-if="type === 'color'" class="flex h-8 items-center overflow-hidden rounded border border-gray-300 bg-white">
            <label class="relative h-full w-8 shrink-0 cursor-pointer border-r border-gray-200"
                :style="{ background: model || 'repeating-conic-gradient(#e5e7eb 0% 25%, #fff 0% 50%) 50% / 8px 8px' }">
                <input type="color" :value="isHexColor ? model : '#ffffff'" class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    @input="model = ($event.target as HTMLInputElement).value" />
            </label>
            <input v-model="model" type="text" class="w-24 border-0 px-2 py-0 text-[13px] focus:ring-0" />
        </div>

        <select v-else-if="type === 'select'" v-model="model"
            class="h-8 max-w-[11rem] rounded border-gray-300 py-0 pl-2 pr-7 text-[13px] focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]">
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>

        <textarea v-else-if="type === 'textarea'" v-model="model" rows="10"
            class="w-full rounded border-gray-300 px-2 py-1 font-mono text-xs focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]" />

        <input v-else v-model="model" type="text"
            class="h-8 w-full rounded border-gray-300 px-2 text-[13px] focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]" />
    </div>
</template>
