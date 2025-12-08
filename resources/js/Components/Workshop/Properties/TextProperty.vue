<script setup lang='ts'>
import { computed, inject } from 'vue'
import { trans } from 'laravel-vue-i18n'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import { faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faUnlink } from "@fal"
import { faExclamation } from "@fas"
import ColorPicker from '@/Components/Utils/ColorPicker.vue'
import { useFontFamilyList } from '@/Composables/useFont'
import { set, get } from 'lodash-es'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'

library.add(faExclamation, faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink)

interface TextProperty {
    color: string,
    fontFamily: String
    fontSize: Number | string
    fontStyle : string
}

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

/* const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') }) */

const model = defineModel<TextProperty | any>({
    required: true
})


const localModel = computed<TextProperty>({
    get: () => model.value ?? { color: '#000000', fontFamily: 'Arial', fontSize: null , fontStyle : null },
    set: (newVal) => {
        if (model.value && JSON.stringify(model.value) !== JSON.stringify(newVal)) {
            model.value = newVal
        }
    }
})

const fontFamilies = [...useFontFamilyList];


const fontStyleOptions = [
	{ label: 'Normal', value: 'normal' },
	{ label: 'Italic', value: 'italic' },
    { label: 'Oblique', value: 'oblique', information: trans('Similar to italic but with a slanting effect') },
]

const textDecorationOptions = [
	{ label: 'Underline', value: 'underline' },
	{ label: 'Line Through', value: 'line-through' },
]

const textTransformOptions = [
	{ label: 'Uppercase', value: 'uppercase', information: trans('Transforms all characters to uppercase') },
	{ label: 'Lowercase', value: 'lowercase', information: trans('Transforms all characters to lowercase') },
	{ label: 'Capitalize', value: 'capitalize', information: trans('Transforms the first character of each word to uppercase') },
]

</script>

<template>
    <div class="flex flex-col pt-1 pb-3">
        <div class="pb-2">
            <div class="px-3 flex justify-between items-center mb-2">
                <div class="text-xs">{{ trans('Text Color') }}</div>
                <ColorPicker :color="get(localModel, 'color', null)" @changeColor="(newColor) => {
                    const finalColor = newColor ? `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a})` : null
                    set(localModel, 'color', finalColor)
                    emits('update:modelValue', localModel)
                }" closeButton>
                    <template #button>
                        <div v-bind="$attrs"
                            class="relative h-7 w-7 rounded-md border border-gray-300 shadow cursor-pointer flex justify-center items-center"
                            :style="{ background: localModel.color || 'transparent' }">
                            <span v-if="!localModel.color" class="text-gray-400 text-xs">—</span>

                            <!-- Tombol reset -->
                            <button v-if="localModel.color"
                                @click.stop.prevent="set(localModel, 'color', null); emits('update:modelValue', localModel)"
                                class="absolute -top-2 -right-2 bg-white rounded-full text-xs text-red-500 shadow p-1 leading-none"
                                title="Reset color">
                                ×
                            </button>
                        </div>
                    </template>

                </ColorPicker>

            </div>

            <div class="px-3 items-center mb-2">
                <div class="text-xs mb-2">{{ trans('Font') }}</div>
                <div class="col-span-4">
                    <PureMultiselect v-model="localModel.fontFamily"
                        @update:modelValue="(e) => (set(localModel, 'fontFamily', e), emits('update:modelValue', localModel))"
                        :options="fontFamilies">
                        <template #option="{ option, isSelected, isPointed, search }">
                            <span :style="{
                                fontFamily: option.value
                            }">
                                {{ option.label }}
                            </span>
                        </template>
                        <template #label="{ value }">
                            <div class="multiselect-single-label" :style="{
                                fontFamily: value.value
                            }">
                                {{ value.label }}
                            </div>
                        </template>
                    </PureMultiselect>
                </div>
            </div>


            <!-- Section: Font style (normal, italic, oblique) -->
            <div class="px-3 items-center mb-3">
                <div class="text-xs mb-1">{{ trans('Font Style') }}</div>
                <div class="col-span-4">
                    <PureMultiselect
                        v-model="localModel.fontStyle"
                        @update:modelValue="(e) => (set(localModel, 'fontStyle', e), emits('update:modelValue', localModel))"
                        :options="fontStyleOptions"
                    >
                        <template #option="{ option, isSelected, isPointed, search }">
                            <span>
                                {{ option.label }} <span class="opacity-50" :style="{ fontStyle: option.value }">(Example)</span>
                                <InformationIcon v-if="option.information" :information="option.information" class="ml-1" />
                            </span>
                        </template>
                        <template #label="{ value: option }">
                            <div class="multiselect-single-label">
                                {{ option.label }} <span class="ml-1 opacity-50" :style="{ fontStyle: option.value }">(Example)</span>
                            </div>
                        </template>
                    </PureMultiselect>
                </div>
            </div>

            <!-- Section: Font Decoration (underline, line-through) -->
            <div class="px-3 items-center mb-3">
                <div class="text-xs mb-1">{{ trans('Text Decoration') }}</div>
                <div class="col-span-4">
                    <PureMultiselect
                        v-model="localModel.textDecoration"
                        @update:modelValue="(e) => (set(localModel, 'textDecoration', e), emits('update:modelValue', localModel))"
                        :options="textDecorationOptions"
                    >
                        <template #option="{ option, isSelected, isPointed, search }">
                            <span>
                                {{ option.label }} <span class="opacity-50" :style="{ textDecoration: option.value }">(Example)</span>
                                <InformationIcon v-if="option.information" :information="option.information" class="ml-1" />
                            </span>
                        </template>
                        <template #label="{ value: option }">
                            <div class="multiselect-single-label">
                                <span>
                                    {{ option.label }} <span class="opacity-50" :style="{ textDecoration: option.value }">(Example)</span>
                                </span>
                            </div>
                        </template>
                    </PureMultiselect>
                </div>
            </div>

            <!-- Section: Font Transform (uppercase, lowercase, capitalize) -->
            <div class="px-3 items-center mb-3">
                <div class="text-xs mb-1">{{ trans('Text Transform') }}</div>
                <div class="col-span-4">
                    <PureMultiselect
                        v-model="localModel.textTransform"
                        @update:modelValue="(e) => (set(localModel, 'textTransform', e), emits('update:modelValue', localModel))"
                        :options="textTransformOptions"
                    >
                        <template #option="{ option, isSelected, isPointed, search }">
                            <span>
                                {{ option.label }} <span class="opacity-50" :style="{ textTransform: option.value }">(Example)</span>
                                <InformationIcon v-if="option.information" :information="option.information" class="ml-1" />
                            </span>
                        </template>
                        <template #label="{ value: option }">
                            <div class="multiselect-single-label">
                                <span>
                                    {{ option.label }} <span class="opacity-50" :style="{ textTransform: option.value }">(Example)</span>
                                </span>
                            </div>
                        </template>
                    </PureMultiselect>
                </div>
            </div>

            <!-- Section: Font size -->
            <div class="px-3 items-center">
                <div class="text-xs mb-2">{{ trans('Font size') }}</div>
                <div class="col-span-4">
                    <PureInputNumber
                        v-model="localModel.fontSize"
                        @update:modelValue="(e) => (set(localModel, 'fontSize', e), emits('update:modelValue', localModel))"
                        suffix="px" />
                </div>
            </div>

        </div>
    </div>
</template>