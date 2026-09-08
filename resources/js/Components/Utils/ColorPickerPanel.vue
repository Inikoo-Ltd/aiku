<script setup lang="ts">
import { computed, ref } from 'vue'
import { ColorPicker } from 'vue-color-kit'
import 'vue-color-kit/dist/vue-color-kit.css'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPlus, faTimes } from '@fal'
import { trans } from 'laravel-vue-i18n'
import { normaliseColor } from './savedColors'
import { useSavedColors } from './useSavedColors'
import { colorValueToHex, hexToColorValue, type ColorValue } from './colorValue'

const props = withDefaults(defineProps<{
    color?: string | null
    label?: string
    closable?: boolean
    isEditable?: boolean
}>(), {
    color: null,
    label: '',
    closable: false,
    isEditable: true
})

const emits = defineEmits<{
    (e: 'changeColor', value: ColorValue): void
    (e: 'close'): void
}>()

const presetColors = [
    '#000000', '#374151', '#6b7280', '#d1d5db', '#ffffff', '#ff1900', '#f47365', '#ffb243',
    '#ffe623', '#6eff2a', '#1bc7b1', '#00beff', '#2e81ff', '#5d61ff', '#bf3dce', '#ff89cf',
]

const { savedColors, saveColor, forgetColor, isColorSaved } = useSavedColors()

const pickerSession = ref(0)
const draftColor = ref<string | null>(null)

const initialPickerColor = computed(() => normaliseColor(props.color) ?? props.color ?? '#000000')

const currentColor = computed(() => draftColor.value ?? normaliseColor(props.color))

const canSaveCurrentColor = computed(() => currentColor.value !== null && !isColorSaved(currentColor.value))

const emitColor = (value: ColorValue): void => {
    draftColor.value = value.hex
    emits('changeColor', value)
}

const onPickerChange = (picked: ColorValue): void => emitColor({ ...picked, hex: colorValueToHex(picked) })

const applyPaletteColor = (color: string): void => {
    const value = hexToColorValue(color)

    if (!value) {
        return
    }

    emitColor(value)
    pickerSession.value += 1
}
</script>

<template>
    <div class="color-picker-panel w-max">
        <div v-if="label || closable" class="mb-2 flex items-center justify-between gap-3">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                {{ label }}
            </span>

            <button v-if="closable" type="button" :aria-label="trans('Close')" @click="emits('close')"
                class="-mr-1 flex h-5 w-5 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600">
                <FontAwesomeIcon :icon="faTimes" class="h-3 w-3" aria-hidden="true" />
            </button>
        </div>

        <slot name="before" />

        <div class="relative">
            <ColorPicker :key="pickerSession" theme="light" :color="initialPickerColor" :sucker-hide="true"
                @changeColor="onPickerChange" />

            <div class="mt-3 grid grid-cols-8 gap-1.5">
                <button v-for="preset in presetColors" :key="preset" type="button" :title="preset" :aria-label="preset"
                    :style="{ backgroundColor: preset }"
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

            <div v-if="!isEditable" class="absolute inset-0 rounded bg-white/60" />
        </div>

        <slot name="after" />
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
