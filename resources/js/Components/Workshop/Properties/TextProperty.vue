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

library.add(faExclamation, faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink)

interface TextProperty {
    color: string,
    fontFamily: String
}


const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') })  // Get the block id that use this property

const model = defineModel<TextProperty>({
    required: true
})


const localModel = computed<TextProperty>({
    get: () => model.value ?? { color: '#000000', fontFamily: 'Arial' },
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
                <div class="text-xs">{{ trans('Color') }}</div>
                <ColorPicker
                    :color="get(localModel, 'color', 'rgba(0, 0, 0, 1)')"
                    @changeColor="(newColor)=> (set(localModel, 'color', `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a}`), onSaveWorkshopFromId(side_editor_block_id, 'textPorperty'))"
                    closeButton
                >
                    <template #button>
                        <div v-bind="$attrs" class="overflow-hidden h-7 w-7 rounded-md border border-gray-300 shadow cursor-pointer flex justify-center items-center" :style="{
                            background: `${get(localModel, 'color', 'transparent')}`
                        }"
                        >
                        </div>
                    </template>
                </ColorPicker>
                <!-- <ColorPicker :color="localModel?.color"
                    @changeColor="(newColor) => localModel.color = `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a})`"
                    closeButton
                    :isEditable="!localModel?.color?.includes('var')"
                >
                    <template #button>
                        <div v-bind="$attrs"
                            class="overflow-hidden h-7 w-7 rounded border border-gray-300 cursor-pointer flex justify-center items-center"
                            :style="{
                                background: `${localModel.color}`
                            }">
                        </div>
                    </template>

                    <template #before-main-picker>
                        <div class="flex items-center gap-2">
                            <RadioButton size="small" v-model="localModel.color" inputId="bg-color-picker-1" name="bg-color-picker" value="var(--iris-color-primary)" />
                            <label class="cursor-pointer" for="bg-color-picker-1">{{ trans("Primary color") }} 
                                <a :href="route(route().params.shop ? 'grp.org.shops.show.web.websites.workshop' : 'grp.org.fulfilments.show.web.websites.workshop', {...route().params, tab: 'website_layout', section: 'theme_colors'})" as="a" target="_blank" class="text-xs text-blue-600">{{ trans("themes") }}</a></label>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <RadioButton size="small"
                                :modelValue="!localModel.color?.includes('var') ? '#111111' : null"
                                @update:modelValue="(e) => localModel.color?.includes('var') ? localModel.color = '#111111' : false"
                                inputId="bg-color-picker-3"
                                name="bg-color-picker"
                                value="#111111" />
                            <label class="cursor-pointer" for="bg-color-picker-3">{{ trans("Custom") }}</label>
                        </div>
                    </template>
                </ColorPicker> -->
            </div>

            <div class="px-3 items-center">
                <div class="text-xs mb-2">{{ trans('Font') }}</div>
                <div class="col-span-4">
                    <PureMultiselect v-model="localModel.fontFamily"  @update:modelValue="(e) => (set(localModel, 'fontFamily', e),onSaveWorkshopFromId(side_editor_block_id, 'textStyle'))" required :options="fontFamilies">
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

        </div>
    </div>
</template>