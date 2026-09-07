<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import Popover from 'primevue/popover'
import ColorPickerPanel from '@/Components/Utils/ColorPickerPanel.vue'
import type { ColorValue } from '@/Components/Utils/colorValue'

const props = withDefaults(defineProps<{
    color?: string | null
    label?: string
}>(), {
    color: null,
    label: ''
})

const emits = defineEmits<{
    (e: 'changeColor', color: string): void
    (e: 'open'): void
    (e: 'close'): void
}>()

const MIN_SPACE_ABOVE_BUBBLE = 12

const root = ref<HTMLElement | null>(null)
const overlayPanel = ref<InstanceType<typeof Popover> | null>(null)
const isOpen = ref(false)

const closePicker = (): void => overlayPanel.value?.hide()

const bubbleElement = (): HTMLElement | null =>
    (root.value?.closest('[data-tippy-root]') as HTMLElement | null) ?? root.value

const panelElement = (): Element | null => (overlayPanel.value as unknown as { container?: Element })?.container ?? null

const isInsidePicker = (target: Node): boolean => {
    if (root.value?.contains(target)) {
        return true
    }

    const panel = panelElement()

    return panel ? panel.contains(target) : Boolean((target as Element).closest?.('.p-popover'))
}

const onDocumentPointerDown = (event: PointerEvent): void => {
    const target = event.target as Node | null

    if (!target || isInsidePicker(target)) {
        return
    }

    closePicker()
}

const onDocumentKeyDown = (event: KeyboardEvent): void => {
    if (['Shift', 'Control', 'Alt', 'Meta'].includes(event.key)) {
        return
    }

    const target = event.target as Node | null

    if (target && isInsidePicker(target)) {
        return
    }

    closePicker()
}

const bindDismissListeners = (): void => {
    document.addEventListener('pointerdown', onDocumentPointerDown, true)
    document.addEventListener('keydown', onDocumentKeyDown, true)
}

const unbindDismissListeners = (): void => {
    document.removeEventListener('pointerdown', onDocumentPointerDown, true)
    document.removeEventListener('keydown', onDocumentKeyDown, true)
}

const placeAboveBubble = (): void => {
    const panel = panelElement() as HTMLElement | null
    const bubble = bubbleElement()

    if (!panel || !bubble) {
        return
    }

    const bubbleTop = bubble.getBoundingClientRect().top

    if (bubbleTop - panel.offsetHeight < MIN_SPACE_ABOVE_BUBBLE) {
        return
    }

    panel.style.top = `${bubbleTop + window.scrollY - panel.offsetHeight}px`
    panel.setAttribute('data-p-popover-flipped', 'true')
    panel.classList.add('p-popover-flipped')
}

const pickColor = (value: ColorValue): void => emits('changeColor', value.hex)

const startPicking = (event: MouseEvent): void => {
    event.preventDefault()

    if (isOpen.value) {
        return
    }

    emits('open')
}

const togglePicker = (event: MouseEvent): void => overlayPanel.value?.toggle(event)

const onPickerShown = (): void => {
    isOpen.value = true
    placeAboveBubble()
    bindDismissListeners()
}

const onPickerHidden = (): void => {
    isOpen.value = false
    unbindDismissListeners()
    emits('close')
}

onBeforeUnmount(() => {
    if (isOpen.value) {
        onPickerHidden()
    }
})
</script>

<template>
    <div ref="root" class="relative">
        <div @mousedown="startPicking" @click="togglePicker">
            <slot name="button" />
        </div>

        <Popover ref="overlayPanel" :dismissable="false" :pt="{ content: { style: 'padding: 0' } }" @show="onPickerShown"
            @hide="onPickerHidden">
            <ColorPickerPanel class="p-3" :color="color" :label="label" closable
                @changeColor="pickColor" @close="closePicker" />
        </Popover>
    </div>
</template>
