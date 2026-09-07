<script setup lang="ts">
import { ref } from 'vue'
import Popover from 'primevue/popover'
import ColorPickerPanel from '@/Components/Utils/ColorPickerPanel.vue'
import type { ColorValue } from '@/Components/Utils/colorValue'

withDefaults(defineProps<{
    color: string | null
    closeButton?: boolean
    isEditable?: boolean
}>(), {
    color: 'rgba(0, 0, 0, 0)',
    isEditable: true
})

const emits = defineEmits<{
    (e: 'changeColor', value: ColorValue): void
}>()

const overlayPanel = ref<InstanceType<typeof Popover> | null>(null)
</script>

<template>
    <div class="relative">
        <div @click="overlayPanel?.toggle($event)">
            <slot name="button">
                <div v-bind="$attrs" class="h-12 w-12 cursor-pointer" :style="{ backgroundColor: color }" />
            </slot>
        </div>

        <Popover ref="overlayPanel" :pt="{ content: { style: 'padding: 0' } }">
            <ColorPickerPanel class="p-3" :color="color" :is-editable="isEditable" closable
                @changeColor="(value) => emits('changeColor', value)" @close="overlayPanel?.hide()">
                <template #before>
                    <slot name="before-main-picker" />
                </template>

                <template #after>
                    <slot name="after-main-picker" />
                </template>
            </ColorPickerPanel>
        </Popover>
    </div>
</template>
