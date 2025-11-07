<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { ColorPicker } from 'vue-color-kit'
import 'vue-color-kit/dist/vue-color-kit.css'

interface RGBA {
    r: number
    g: number
    b: number
    a: number
}

interface HSV {
    h: number
    s: number
    v: number
}

interface Color {
    rgba: RGBA
    hsv: HSV
    hex: string
}

// To avoid the class (from parent) is inherit to first element
defineOptions({
    inheritAttrs: false
})

const props = withDefaults(defineProps<{
    color: string
}>(), {
    color: 'rgba(0, 0, 0, 0)'
})

const emits = defineEmits<{
    (e: 'changeColor', value: Color): void
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
            <div class="overflow-hidden rounded-lg shadow-lg ring-1 ring-black/5">
                <div class="relative  bg-white p-2.5">
                    <ColorPicker style="width: 220px;" theme="dark" :color="color" :sucker-hide="true"
                        @changeColor="(e) => emits('changeColor', e)" />
                </div>
            </div>
        </PopoverPanel>
    </Popover>
</template>