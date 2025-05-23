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
import { set, get } from 'lodash'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'

library.add(faExclamation, faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink)

interface TextProperty {
    color: string,
    fontFamily: String
    fontSize: Number | string
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
    get: () => model.value ?? { color: '#000000', fontFamily: 'Arial', fontSize: null },
    set: (newVal) => {
        if (model.value && JSON.stringify(model.value) !== JSON.stringify(newVal)) {
            model.value = newVal
        }
    }
})

const fontFamilies = [...useFontFamilyList];

</script>

<template>
    <div class="flex flex-col pt-1 pb-3">
        <div class="pb-2">
            <div class="px-3 flex justify-between items-center mb-2">
                <div class="text-xs">{{ trans('Text Color') }}</div>
                <ColorPicker :color="get(localModel, 'color', 'rgba(0, 0, 0, 1)')"
                    @changeColor="(newColor) => (set(localModel, 'color', `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a}`), emits('update:modelValue', localModel))"
                    closeButton>
                    <template #button>
                        <div v-bind="$attrs"
                            class="overflow-hidden h-7 w-7 rounded-md border border-gray-300 shadow cursor-pointer flex justify-center items-center"
                            :style="{
                                background: `${get(localModel, 'color', 'transparent')}`
                            }">
                        </div>
                    </template>
                </ColorPicker>
            </div>

            <div class="px-3 items-center mb-2">
                <div class="text-xs mb-2">{{ trans('Font') }}</div>
                <div class="col-span-4">
                    <PureMultiselect v-model="localModel.fontFamily"
                        @update:modelValue="(e) => (set(localModel, 'fontFamily', e), emits('update:modelValue', localModel))"
                        required :options="fontFamilies">
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


            <div class="px-3 items-center">
                <div class="text-xs mb-2">{{ trans('Fontsize') }}</div>
                <div class="col-span-4">
                    <PureInputNumber v-model="localModel.fontSize"
                        @update:modelValue="(e) => (set(localModel, 'fontSize', e), emits('update:modelValue', localModel))"
                        suffix="px" />
                </div>
            </div>

        </div>
    </div>
</template>