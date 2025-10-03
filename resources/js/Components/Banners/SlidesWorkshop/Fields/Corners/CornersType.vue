<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { ref, watch, computed } from 'vue'
import Input from '@/Components/Banners/SlidesWorkshop/Fields/PrimitiveInput.vue'
import Colorpicker from '@/Components/Banners/SlidesWorkshop/Fields/ColorPicker.vue'
import Radio from '@/Components/Banners/SlidesWorkshop/Fields/PrimitiveRadio.vue'
import { get, cloneDeep, set } from 'lodash-es'
import { faLock } from '@fas'
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
library.add(faLock, faTimes)
const emits = defineEmits(['clear']);
const props = defineProps<{
    section: any
    fieldData : any
}>()

const optionType = [
    {
        label: 'Corner text',
        value: 'cornerText',
        fields: [
            {
                name: 'title',
                type: 'input',
                label: trans('title'),
                value: null,
                placeholder: "Holiday Sales!"
            },
            {
                name: 'subtitle',
                type: 'input',
                label: trans('subtitle'),
                value: null,
                placeholder: "Holiday sales up to 80% all items."
            },
            {
                name: 'linkOfText',
                type: 'input',
                label: trans('Link'),
                value: null,
                defaultValue : 'https://',
                placeholder: "https://www.example.com"
            },
            {
                name: 'width',
                type: 'number',
                label: trans('width'),
                value: 100,
                placeholder: "100",
                suffix: "%",
            },
            {
                name: 'color',
                type: 'colorPicker',
                label: trans('text color'),
                icon: 'far fa-text',
                value: null
            },
            {
                name: "fontSize",
                type: "radio",
                label: trans("Font Size"),
                value: null,
                defaultValue: { fontTitle: "text-[25px] lg:text-[44px]", fontSubtitle: "text-[12px] lg:text-[20px]" },
                options: [
                    { label: "Extra Small", value: {
                            fontTitle: "text-[13px] lg:text-[21px]",
                            fontSubtitle: "text-[8px] lg:text-[12px]"
                        }
                    },
                    {
                        label: "Small",
                        value: {
                            fontTitle: "text-[18px] lg:text-[32px]",
                            fontSubtitle: "text-[10px] lg:text-[15px]"
                        }
                    },
                    {
                        label: "Normal",
                        value: {
                            fontTitle: "text-[25px] lg:text-[44px]",
                            fontSubtitle: "text-[12px] lg:text-[20px]"
                        }
                    },
                    {
                        label: "Large", value: {
                            fontTitle: "text-[30px] lg:text-[60px]",
                            fontSubtitle: "text-[15px] lg:text-[25px]"
                        }
                    },
                    {
                        label: "Extra Large",
                        value: {
                            fontTitle: "text-[40px] lg:text-[70px]",
                            fontSubtitle: "text-[20px] lg:text-[30px]"
                        },
                    },
                ],
            },
        ]
    },
    {
        label: 'Link button',
        value: 'linkButton',
        fields: [
            {
                name: 'text',
                type: 'input',
                label: trans('Title'),
                value: null,
                placeholder: "Buy Now!"
            },
            {
                name: 'target',
                type: 'input',
                label: trans('Link'),
                value: 'null',
                defaultValue : 'https://',
                placeholder: 'https://www.example.com'
                // info : 'use https:// or http://',
                // rules:{
                //     pattern : '^(http|https)://',
                //     message : 'please input https:// or http://'
                // }
            },
            {
                name: 'button_color',
                type: 'colorPicker',
                label: trans('Button color'),
                value: 'rgb(244, 63, 94)'
            },
            {
                name: 'text_color',
                type: 'colorPicker',
                label: trans('Text color'),
                icon: 'far fa-text',
                value: 'rgb(244, 63, 94)'
            },
        ]
    },
    // {
    //     label: 'Slide Controls',
    //     value: "slideControls",
    //     fields: [],
    // },
    {
        label: 'Ribbon',
        value: "ribbon",
        fields: [
            {
                name: 'text',
                type: 'input',
                label: trans('Text'),
                value: null,
                placeholder: 'Holiday Sales!'
            },
            {
                name: 'ribbon_color',
                type: 'colorPicker',
                label: trans('Ribbon color'),
                value: 'rgb(244, 63, 94)'
            },
            {
                name: 'text_color',
                type: 'colorPicker',
                label: trans('Text color'),
                icon: 'far fa-text',
                value: 'rgb(0, 0, 0)'
            },
        ]
    },
]



const clickTypeCorner = (index, value) => {
    activeType.value = value
    props.section.valueForm = {
        ...props.section.valueForm,
        data: get(props.section,['valueForm','temporaryData',value.value],null),
        type: value.value
    }
}

const onUpdateFieldCorner =(field, newValue)=>{
   set(props.section,["valueForm","temporaryData",activeType.value.value,field.name],newValue)
   set(props.section,["valueForm","data",field.name],newValue)
}


const filterType = () => {
    let FinalData = optionType
    if (props.fieldData?.optionType) {
        const data = optionType.filter((item) => {
            // Check if the item's value is present in the optionType array
            return props.fieldData?.optionType.includes(item.value);
        });
        FinalData = data;
    }
    if(props.section.id == "topMiddle" || props.section.id == "bottomMiddle" ){
       const index = FinalData.findIndex((item)=>item.value == 'ribbon')
       if(index) FinalData.splice(index,1)
    }
    return FinalData
}

const Type = ref(filterType())

const onClear =()=>{
    emits("clear",props.section);
    set(props.section,["valueForm"],null)
}

const findDefaultActive=(data)=>{
    const nextactive = Type.value.find((item)=>item.value == get(props.section,['valueForm','type']))
    return nextactive
}

const activeType = ref(findDefaultActive())

watch(props.section, (newValue) => {
    Type.value = filterType()
    const nextactive = Type.value.find((item)=>item.value == get(newValue,['valueForm','type']))
    activeType.value = nextactive
})

</script>

<template>
    <!-- Choose: The type of component (after select Corners) -->
    <div class="h-full">
        <!-- Choose: Card -->
        <div class="w-full flex">
            <span class="isolate flex w-full rounded-md gap-x-2">
                <!-- Select the type of Corner: Text, Link Button, Slide Controls, Ribbon -->
                <button v-for="(item, index) in Type" :key="item + section.id + index" type="button"
                    @click="clickTypeCorner(index, item)" :class="[
                        'py-2', 'px-4', 'rounded',
                        get(activeType,'value') == item.value ? 'bg-gray-300 text-gray-600 ring-2 ring-gray-500' : 'hover:bg-gray-200/70 border border-gray-400'
                    ]">
                    {{ item.label }}
                </button>

                <!-- Button: clear -->
                <div v-if="get(activeType,'value')"  @click="onClear" class="px-1.5 flex items-center gap-x-1 text-red-500 hover:text-red-600 cursor-pointer" >
                    <FontAwesomeIcon icon='fal fa-times' class='text-sm' aria-hidden='true' />
                    <span>{{ trans('Clear') }}</span>
                </div>
            </span>
        </div>

        <!-- Field -->
        <div class="mt-6 block" v-if="activeType">
            <dl v-for="(field, index ) in activeType.fields" :key="field.name + index"
                class="pb-4 flex flex-col max-w-lg gap-1">
                <dt class="text-sm font-medium text-gray-500">
                    <div class="inline-flex items-start leading-none">
                        <span>{{ field.label }}</span>
                    </div>
                </dt>
                <dd class="sm:col-span-2">
                    <!-- Available Field on Corners -->
                    <div class="mt-1 flex text-sm text-gray-700 sm:mt-0">
                        <div v-if="field.type == 'input' || field.type == 'number'" class="relative flex-grow">
                            <Input :key="field.label + section.id + index"
                                :value="get(section, ['valueForm', 'data', field.name],get(section, ['valueForm', 'temporaryData', activeType.value, field.name]))"
                                :fieldData="{...field,...get(section, ['valueForm', 'temporaryData', activeType.value])}" 
                                @onChange="(newValue)=>onUpdateFieldCorner(field, newValue)" />
                        </div>
                        <div v-if="field.type == 'colorPicker'" class="relative flex-grow">
                            <Colorpicker :key="field.label + section.id + index"
                                :color="get(section, ['valueForm', 'data', field.name],get(section, ['valueForm', 'temporaryData', activeType.value, field.name]))"
                                :fieldData="{...field,...get(section, ['valueForm', 'temporaryData', activeType.value])}" 
                                @onChange="(newValue)=>onUpdateFieldCorner(field, newValue)"
                                />
                        </div>
                        <div v-if="field.type == 'radio'" class="relative flex-grow">
                            <Radio :key="field.label + index + index"
                                :radioValue="get(section, ['valueForm', 'data', field.name],get(section, ['valueForm', 'temporaryData', activeType.value, field.name]))"
                                :fieldData="{ options: field.options, defaultValue: field.defaultValue }" 
                                @onChange="(newValue)=>onUpdateFieldCorner(field, newValue)"/>
                        </div>
                    </div>
                </dd>
        </dl>
    </div>
</div></template>

