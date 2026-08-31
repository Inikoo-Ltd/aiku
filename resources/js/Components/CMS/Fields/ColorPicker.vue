<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import ColorPickerPanel from '@/Components/Utils/ColorPickerPanel.vue'
import type { ColorValue } from '@/Components/Utils/colorValue'

// To avoid the class (from parent) is inherit to first element
defineOptions({
    inheritAttrs: false
})

withDefaults(defineProps<{
    color: string
}>(), {
    color: 'rgba(0, 0, 0, 0)'
})

const emits = defineEmits<{
    (e: 'changeColor', value: ColorValue): void
}>()
</script>


<template>
    <Popover v-slot="{ open }" class="relative">
        <PopoverButton>
            <div v-bind="$attrs" :style="{
                backgroundColor: color
            }">
                <slot />
            </div>
        </PopoverButton>

        <PopoverPanel class="absolute left-8 top-0 z-10 mt-3">
            <div class="overflow-hidden rounded-lg shadow-lg ring-1 ring-black ring-opacity-5">
                <ColorPickerPanel class="bg-white p-3" :color="color"
                    @changeColor="(value) => emits('changeColor', value)" />
            </div>
        </PopoverPanel>
    </Popover>
</template>
