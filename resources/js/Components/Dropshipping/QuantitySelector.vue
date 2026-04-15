<script setup lang="ts">
import { faPlus, faMinus } from "@fas"
library.add(faPlus, faMinus)
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

const props = defineProps<{
    modelValue: number
    min?: number
}>()

const emit = defineEmits(['update:modelValue'])

const increment = () => {
    emit('update:modelValue', (props.modelValue || 1) + 1)
}

const decrement = () => {
    if ((props.modelValue || 1) > (props.min || 1)) {
        emit('update:modelValue', props.modelValue - 1)
    }
}
</script>

<template>
    <div class="inline-flex items-center overflow-hidden shadow-sm">
        <div class="text-[10px] text-gray-400 leading-none">
            Qty
        </div>
        <!-- MINUS -->
        <button @click.stop="decrement" class="px-2 py-1 text-gray-600 hover:bg-gray-100 transition disabled:opacity-40"
            :disabled="modelValue <= (min || 1)">
            <FontAwesomeIcon icon='fas fa-minus' fixed-width aria-hidden='true' />
        </button>

        <!-- VALUE -->
        <div class="px-3 text-sm font-semibold text-gray-800 min-w-[32px] text-center">
            {{ modelValue || 0 }}
        </div>

        <!-- PLUS -->
        <button @click.stop="increment" class="px-2 py-1 text-gray-600 hover:bg-gray-100 transition">
            <FontAwesomeIcon icon='fas fa-plus' fixed-width aria-hidden='true' />
        </button>

    </div>
</template>