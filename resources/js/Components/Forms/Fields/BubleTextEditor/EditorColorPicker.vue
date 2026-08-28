<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import Popover from 'primevue/popover'
import { ColorPicker } from 'vue-color-kit'
import 'vue-color-kit/dist/vue-color-kit.css'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPlus, faTimes } from '@fal'
import { trans } from 'laravel-vue-i18n'
import { normaliseColor } from './savedColors'
import { useSavedColors } from './useSavedColors'

interface PickedColor {
    rgba: { r: number; g: number; b: number; a: number }
    hsv: { h: number; s: number; v: number }
    hex: string
}

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

const presetColors = [
    '#000000', '#374151', '#6b7280', '#d1d5db', '#ffffff', '#ff1900', '#f47365', '#ffb243',
    '#ffe623', '#6eff2a', '#1bc7b1', '#00beff', '#2e81ff', '#5d61ff', '#bf3dce', '#ff89cf',
]

const { savedColors, saveColor, forgetColor, isColorSaved } = useSavedColors()

const root = ref<HTMLElement | null>(null)
const overlayPanel = ref<InstanceType<typeof Popover> | null>(null)
const isOpen = ref(false)
const pickerSession = ref(0)
const draftColor = ref<string | null>(null)

const initialPickerColor = computed(() => normaliseColor(props.color) ?? props.color ?? '#000000')

const currentColor = computed(() => draftColor.value ?? normaliseColor(props.color))

const canSaveCurrentColor = computed(() => currentColor.value !== null && !isColorSaved(currentColor.value))

const alphaToHex = (alpha: number): string => Math.round(alpha * 255).toString(16).padStart(2, '0')

const toHexWithAlpha = ({ hex, rgba }: PickedColor): string =>
    rgba.a >= 1 ? hex.toLowerCase() : `${hex.toLowerCase()}${alphaToHex(rgba.a)}`

const pickColor = (color: string): void => {
    draftColor.value = normaliseColor(color)
    emits('changeColor', color)
}

const applyPaletteColor = (color: string): void => {
    pickColor(color)
    pickerSession.value += 1
}

const closePicker = (): void => overlayPanel.value?.hide()

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

const startPicking = (event: MouseEvent): void => {
    event.preventDefault()

    if (isOpen.value) {
        return
    }

    emits('open')
}

const togglePicker = (event: MouseEvent): void => {
    draftColor.value = normaliseColor(props.color)
    pickerSession.value += 1
    overlayPanel.value?.toggle(event)
}

const onPickerShown = (): void => {
    isOpen.value = true
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
            <div class="color-picker-panel w-max p-3">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        {{ label }}
                    </span>

                    <button type="button" :aria-label="trans('Close')" @click="closePicker"
                        class="-mr-1 flex h-5 w-5 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600">
                        <FontAwesomeIcon :icon="faTimes" class="h-3 w-3" aria-hidden="true" />
                    </button>
                </div>

                <ColorPicker :key="pickerSession" theme="light" :color="initialPickerColor" :sucker-hide="true"
                    @changeColor="(picked: PickedColor) => pickColor(toHexWithAlpha(picked))" />

                <div class="mt-3 grid grid-cols-8 gap-1.5">
                    <button v-for="preset in presetColors" :key="preset" type="button" :title="preset"
                        :aria-label="preset" :style="{ backgroundColor: preset }"
                        class="h-5 w-5 rounded border border-gray-200 transition-transform hover:scale-110"
                        @click="applyPaletteColor(preset)" />
                </div>

                <div class="mt-3 border-t border-gray-200 pt-2">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                            {{ trans('Saved') }}
                        </span>

                        <button type="button" :disabled="!canSaveCurrentColor" @click="saveColor(currentColor)"
                            class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-medium text-gray-600 transition-colors hover:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-transparent">
                            <FontAwesomeIcon :icon="faPlus" class="h-2.5 w-2.5" aria-hidden="true" />
                            {{ trans('Save') }}
                        </button>
                    </div>

                    <div v-if="savedColors.length" class="mt-1.5 grid grid-cols-8 gap-1.5">
                        <div v-for="saved in savedColors" :key="saved" class="group relative">
                            <button type="button" :title="saved" :aria-label="saved"
                                class="h-5 w-5 rounded border border-gray-200 transition-transform hover:scale-110"
                                :style="{ backgroundColor: saved }" @click="applyPaletteColor(saved)" />

                            <button type="button" :aria-label="trans('Remove :color', { color: saved })"
                                class="absolute -right-1 -top-1 hidden h-3 w-3 items-center justify-center rounded-full bg-gray-400 text-white hover:bg-red-500 group-hover:flex"
                                @click.stop="forgetColor(saved)">
                                <FontAwesomeIcon :icon="faTimes" class="h-1.5 w-1.5" aria-hidden="true" />
                            </button>
                        </div>
                    </div>

                    <p v-else class="mt-1.5 text-[10px] leading-tight text-gray-400">
                        {{ trans('Pick a color, then press Save to keep it here.') }}
                    </p>
                </div>
            </div>
        </Popover>
    </div>
</template>

<style scoped>
.color-picker-panel :deep(.hu-color-picker) {
    padding: 0;
    background: transparent;
    box-shadow: none;
    border-radius: 0;
}

.color-picker-panel :deep(.hu-color-picker .color-show) {
    margin-top: 10px;
}

.color-picker-panel :deep(.hu-color-picker .color-type) {
    margin-top: 6px;
    font-size: 11px;
}

.color-picker-panel :deep(.hu-color-picker .color-type .name) {
    width: 46px;
    height: 26px;
    color: #6b7280;
    background: #f3f4f6;
    border-radius: 4px 0 0 4px;
}

.color-picker-panel :deep(.hu-color-picker .color-type .value) {
    height: 26px;
    min-width: 0;
    padding: 0 8px;
    color: #374151;
    background: #f9fafb;
    border-radius: 0 4px 4px 0;
}

.color-picker-panel :deep(.hu-color-picker .colors) {
    display: none;
}
</style>
