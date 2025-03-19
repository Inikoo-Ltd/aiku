<script setup lang="ts">
import { computed, watch } from 'vue'
import PaddingMarginProperty from '@/Components/Workshop/Properties/PaddingMarginProperty.vue'
import BackgroundProperty from '@/Components/Workshop/Properties/BackgroundProperty.vue'
import BorderProperty from '@/Components/Workshop/Properties/BorderProperty.vue'
import TextProperty from '@/Components/Workshop/Properties/TextProperty.vue'
import DimensionProperty from '@/Components/Workshop/Properties/DimensionProperty.vue'
import ButtonsProperty from '@/Components/CMS/Fields/ButtonProperties.vue'
import { trans } from 'laravel-vue-i18n'
import PureInput from '@/Components/Pure/PureInput.vue'
import ColorPicker from '@/Components/Utils/ColorPicker.vue'
import Link from '@/Components/CMS/Fields/Link.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faText } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import { get, set } from 'lodash-es'
library.add(faText)

interface ModelButtonProperties {
    text: string
    link: string
    container: {
        properties: {
            dimension: {
                width: number
                height: number
            }
            background: {
                color: string
            }
            text: {
                color: string
            }
            border: {
                width: number
                style: string
                color: string
                radius: number
            }
            padding: {
                top: number
                right: number
                bottom: number
                left: number
            }
            margin: {
                top: number
                right: number
                bottom: number
                left: number
            }
        }
    }
}

const model = defineModel<ModelButtonProperties>()
// console.log('mmomom', model.value)
// const compModel = computed(() => {
//     // To check does the data if changed
//     return JSON.stringify(model.value)
// })

// const emit = defineEmits();
// watch(compModel, () => {
//     console.log('on change compModel')
//     emit('update:modelValue', model.value)
// })

// console.log('sd',model.value)

</script>

<template>
    <div  class="py-1.5">
        <!-- <div class="w-full text-center py-2 font-semibold select-none bg-gray-100 mb-3">{{ trans('Link') }}</div> -->
        <div  class="w-full my-2 py-1 font-semibold select-none text-sm border-b border-gray-300">{{ trans("Link") }}</div>
        <div class="">
            <Link :modelValue="get(model, 'link', '')" @update:modelValue=" (newVal: string) => set(model, 'link', newVal)" />
            <!-- <PureInput v-model="model.link" /> -->
        </div>
    </div>

    <div v-if="model?.container?.properties.dimension" class=" pb-3">
       <!--  <div class="w-full text-center py-1 font-semibold select-none">{{ trans('Dimension') }}</div> -->
       <div  class="w-full my-2  py-1 font-semibold select-none text-sm border-b border-gray-300">{{ trans('Dimension') }}</div>
        <DimensionProperty v-model="model.container.properties.dimension" />
    </div>

    <div v-if="model?.container?.properties?.background" class=" pb-3">
        <!-- <div class="w-full text-center py-1 font-semibold select-none">{{ trans('Background') }}</div> -->
        <div  class="w-full my-2  py-1 font-semibold select-none text-sm border-b border-gray-300">{{ trans('Background') }}</div>
        <BackgroundProperty v-model="model.container.properties.background" />
    </div>

    <div v-if="model?.container?.properties?.text" class=" pb-3">
      <!--   <div class="w-full text-center py-1 font-semibold select-none">{{ trans('Text') }}</div> -->
        <TextProperty v-model="model.container.properties.text" />
        <div class="px-3 flex gap-x-2 flex-nowrap">
            <PureInput
                placeholder="Enter a text"
                :modelValue="get(model, 'text', '')"
                @update:modelValue="(text: string) => set(model, 'text', text)"
            />

            <!-- <div v-if="model?.container?.properties?.text?.color" class="h-full">
                <ColorPicker
                    :color="model?.container?.properties?.text.color"
                    @changeColor="(newColor) => model.container.properties.text.color = `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a})`"
                    closeButton>
                    <template #button>
                        <div v-bind="$attrs"
                            class="bg-gray-100 overflow-hidden h-full aspect-square w-10 rounded-md border border-gray-300 cursor-pointer flex justify-center items-center"
                            >
                            <FontAwesomeIcon icon='far fa-text' class='' fixed-width aria-hidden='true'
                                :style="{
                                    color: `${model.container.properties.text.color}`
                                }"
                            />
                        </div>
                    </template>
                </ColorPicker>
            </div> -->
        </div>
    </div>

    <div v-if="model?.container?.properties?.border" class="">
    <!--     <div class="w-full text-center py-1 font-semibold select-none">{{ trans('Border') }}</div> -->
    <div  class="w-full my-2  py-1 font-semibold select-none text-sm border-b border-gray-300">{{ trans('Border') }}</div>
        <BorderProperty v-model="model.container.properties.border" />
    </div>

    <div v-if="model?.container?.properties?.padding" class="">
       <!--  <div class="w-full text-center py-1 font-semibold select-none">{{ trans('Padding') }}</div> -->
       <div  class="w-full my-2  py-1 font-semibold select-none text-sm border-b border-gray-300">{{ trans('Padding') }}</div>
        <PaddingMarginProperty v-model="model.container.properties.padding" />
    </div>

    <div v-if="model?.container?.properties?.margin" class="">
   <!--      <div class="w-full text-center py-1 font-semibold select-none">{{ trans('Margin') }}</div> -->
   <div  class="w-full my-2  py-1 font-semibold select-none text-sm border-b border-gray-300">{{ trans('Margin') }}</div>
        <PaddingMarginProperty v-model="model.container.properties.margin" />
    </div>

</template>

<style scoped></style>
