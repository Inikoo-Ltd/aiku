<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { Popover, PopoverButton, PopoverPanel, Switch } from '@headlessui/vue'
import { inject, ref } from 'vue'
import { faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faUnlink } from "@fal"
import { faExclamation } from "@fas"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import ColorPicker from '@/Components/Utils/ColorPicker.vue'
import { get, set } from 'lodash-es'
library.add(faExclamation, faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink)

interface Borderproperty {
    color: string
    unit: string
    rounded: {
        unit: string
        topright: {
            value: number
        }
        topleft: {
            value: number
        }
        bottomright: {
            value: number
        }
        bottomleft: {
            value: number
        }
    }
    top: {
        value: number
    }
    right: {
        value: number
    }
    left: {
        value: number
    }
    bottom: {
        value: number
    }
}

const model = defineModel<Borderproperty>({
    required: true
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

/* const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') })  */

const isAllValueAreSame = (scope?: {}) => {
    if (!scope) return false
    
    const aaa: number[] = []
    for (let key in scope) {
        if (scope?.[key as keyof Borderproperty]?.hasOwnProperty('value')) {
            aaa.push(scope[key as keyof Borderproperty].value)
        }
    }
    
    return aaa.every(value => value === aaa[0])
}

// Section: Border same value
const isBorderSameValue = ref(isAllValueAreSame(model.value))
const changeBorderValueToSame = async (newVal: number) => {
    for (let key in model.value) {
        if (model.value?.[key as keyof Borderproperty]?.hasOwnProperty('value')) {
            // model.value[key as keyof Borderproperty].value = newVal
            set(model.value, [key, 'value'], newVal)
        }
    }

    /* onSaveWorkshopFromId(side_editor_block_id, 'border') */
    emits('update:modelValue', model.value)
}

// Section: Rounded same value
const isRoundedSameValue = ref(isAllValueAreSame(model.value?.rounded))
const changeRoundedValueToSame = (newVal: number) => {
    for (let key in model.value?.rounded) {
        if (model.value?.rounded?.[key as keyof Borderproperty]?.hasOwnProperty('value')) {
            // model.value.rounded[key as keyof Borderproperty].value = newVal;
            set(model.value, ['rounded', key, 'value'], newVal)
        }
    }
    emits('update:modelValue', model.value)
}

const iconRoundedCorner = `<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 100 100">
    <!-- Top line (left part - dashed gray) -->
    <path d="M10 20 H50" fill="none" stroke="#888888" stroke-width="8" stroke-dasharray="4,4"/>
    
    <!-- Top-right corner (solid black) -->
    <path d="M50 20 Q80 20 80 50" fill="none" stroke="black" stroke-width="8"/>
    
    <!-- Right line (dashed gray) -->
    <path d="M80 50 V90" fill="none" stroke="#888888" stroke-width="8" stroke-dasharray="4,4"/>
    
    <!-- Bottom line (dashed gray) -->
    <path d="M10 90 H80" fill="none" stroke="#888888" stroke-width="8" stroke-dasharray="4,4"/>
    
    <!-- Left line (dashed gray) -->
    <path d="M10 20 V90" fill="none" stroke="#888888" stroke-width="8" stroke-dasharray="4,4"/>
    </svg>`
    
</script>

<template>
    <div class="flex flex-col pt-1 pb-3">

        <div class="pb-2">
            <div class="px-3 flex justify-between items-center mb-2">
                <div class="text-xs">{{ trans('Color') }}</div>
                <ColorPicker
                    :color="get(model, 'color', 'rgba(0, 0, 0, 1)')"
                    @changeColor="(newColor)=> (set(model, 'color', `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a}`), emits('update:modelValue', model))"
                    closeButton
                >
                    <template #button>
                        <div v-bind="$attrs" class="overflow-hidden h-7 w-7 rounded-md border border-gray-300 shadow cursor-pointer flex justify-center items-center" :style="{
                            border: `4px solid ${get(model, 'color', 'transparent')}`
                        }"
                            v-tooltip="!(get(model, 'top.value', 0) || get(model, 'right.value', 0) || get(model, 'bottom.value', 0) || get(model, 'left.value', 0)) ? trans('Will not show due have no border width') : undefined"
                        >
                            <Transition name="spin-to-down">
                                <FontAwesomeIcon v-if="!(get(model, 'top.value', 0) || get(model, 'right.value', 0) || get(model, 'bottom.value', 0) || get(model, 'left.value', 0))" icon='fas fa-exclamation' class='text-gray-400 text-xs' fixed-width aria-hidden='true' />
                            </Transition>
                        </div>
                    </template>
                </ColorPicker>
            </div>

            <!-- Toggle: Border same value -->
            <div class="px-3 flex justify-between items-center mb-2">
                <div class="text-xs">{{ trans('Border same value') }}</div>
                <Switch
                    v-model="isBorderSameValue"
                    :class="[
                        isBorderSameValue ? 'bg-slate-600' : 'bg-slate-300'
                    ]"
                    class="pr-1 relative inline-flex h-3 w-6 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75"
                >
                    <!-- <span class="sr-only">Use setting</span> -->
                    <span aria-hidden="true" :class="isBorderSameValue ? 'translate-x-3' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-full w-1/2 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out" />
                </Switch>
            </div>

            <!-- Border -->
            <div class="pl-2 pr-4 flex items-center relative mb-3">
                <Transition name="slide-to-up">
                    <!-- Same value -->
                    <div v-if="isBorderSameValue" class="grid grid-cols-5 items-center w-full">
                        <FontAwesomeIcon icon='fad fa-border-outer' v-tooltip="trans('Padding all')" class='' fixed-width aria-hidden='true' />
                        <div class="col-span-4">
                            <PureInputNumber
                                :modelValue="get(model, 'top.value', 0)"
                                @update:modelValue="(newVal: number) => get(model, 'top.value', 0) !== newVal && isBorderSameValue ? (changeBorderValueToSame(newVal)) : false"
                                class=""
                                suffix="px"
                            />
                        </div>
                    </div>

                    <div v-else class="space-y-2 w-full">
                        <div class="grid grid-cols-5 items-center w-full justify-center">
                            <FontAwesomeIcon icon='fad fa-border-top' v-tooltip="trans('Border top')" class='mx-auto' fixed-width aria-hidden='true' />
                            <div class="col-span-4">
                                <PureInputNumber :modelValue="get(model, 'top.value', 0)" @update:modelValue="(e) => (set(model, 'top.value', e), emits('update:modelValue', model))" class="" suffix="px" />
                            </div>
                        </div>
                        <div class="grid grid-cols-5 items-center w-full">
                            <FontAwesomeIcon icon='fad fa-border-bottom' v-tooltip="trans('Border bottom')" class='mx-auto' fixed-width aria-hidden='true' />
                            <div class="col-span-4">
                                <PureInputNumber :modelValue="get(model, 'bottom.value', 0)" @update:modelValue="(e) => (set(model, 'bottom.value', e), emits('update:modelValue', model))" class="" suffix="px" />
                            </div>
                        </div>
                        <div class="grid grid-cols-5 items-center w-full">
                            <FontAwesomeIcon icon='fad fa-border-left' v-tooltip="trans('Border left')" class='mx-auto' fixed-width aria-hidden='true' />
                            <div class="col-span-4">
                                <PureInputNumber :modelValue="get(model, 'left.value', 0)" @update:modelValue="(e) => (set(model, 'left.value', e), emits('update:modelValue', model))" class="" suffix="px" />
                            </div>
                        </div>
                        <div class="grid grid-cols-5 items-center w-full">
                            <FontAwesomeIcon icon='fad fa-border-right' v-tooltip="trans('Border right')" class='mx-auto' fixed-width aria-hidden='true' />
                            <div class="col-span-4">
                                <PureInputNumber :modelValue="get(model, 'right.value', 0)" @update:modelValue="(e) => (set(model, 'right.value', e), emits('update:modelValue', model))" class="" suffix="px" />
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>


            <!-- Toggle: Rounded same value -->
            <div class="px-3 flex justify-between items-center mb-2">
                <div class="text-xs">{{ trans('Rounded same value') }}</div>
                <Switch
                    v-model="isRoundedSameValue"
                    :class="[
                        isRoundedSameValue ? 'bg-slate-600' : 'bg-slate-300'
                    ]"
                    class="pr-1 relative inline-flex h-3 w-6 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75"
                >
                    <!-- <span class="sr-only">Use setting</span> -->
                    <span aria-hidden="true" :class="isRoundedSameValue ? 'translate-x-3' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-full w-1/2 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out" />
                </Switch>
            </div>

            <!-- Rounded -->
            <div class="pl-2 pr-4 flex items-center relative">
                <Transition name="slide-to-up">
                    <div v-if="isRoundedSameValue" class="grid grid-cols-5 items-center w-full">
                        <FontAwesomeIcon icon='fad fa-border-outer' v-tooltip="trans('Padding all')" class='' fixed-width aria-hidden='true' />
                        <div class="col-span-4 w-full">
                            <PureInputNumber :modelValue="get(model, 'rounded.topright.value', 0)" @update:modelValue="(newVal) => isRoundedSameValue ? changeRoundedValueToSame(newVal) : false" class="" suffix="px" />
                        </div>
                    </div>

                    <div v-else class="space-y-2 w-full">
                        <div class="grid grid-cols-5 items-center w-full">
                            <div v-html="iconRoundedCorner" v-tooltip="trans('Corner top right')" class='h-5 w-5 mx-auto' />
                            <div class="col-span-4">
                                <PureInputNumber :modelValue="get(model, 'rounded.topright.value', 0)" @update:modelValue="(e) => (set(model, 'rounded.topright.value', e), emits('update:modelValue', model))" class="" suffix="px" />
                            </div>
                        </div>
                        <div class="grid grid-cols-5 items-center w-full">
                            <div v-html="iconRoundedCorner" v-tooltip="trans('Corner top left')" class='h-5 w-5 mx-auto -rotate-90' />
                            <div class="col-span-4">
                                <PureInputNumber :modelValue="get(model, 'rounded.topleft.value', 0)" @update:modelValue="(e) => (set(model, 'rounded.topleft.value', e), emits('update:modelValue', model))" class="" suffix="px" />
                            </div>
                        </div>
                        <div class="grid grid-cols-5 items-center w-full">
                            <div v-html="iconRoundedCorner" v-tooltip="trans('Corner bottom right')" class='h-5 w-5 mx-auto rotate-90' />
                            <div class="col-span-4">
                                <PureInputNumber :modelValue="get(model, 'rounded.bottomright.value', 0)" @update:modelValue="(e) => (set(model, 'rounded.bottomright.value', e), emits('update:modelValue', model))" class="" suffix="px" />
                            </div>
                        </div>
                        <div class="grid grid-cols-5 items-center w-full">
                            <div v-html="iconRoundedCorner" v-tooltip="trans('Corner bottom left')" class='h-5 w-5 mx-auto rotate-180' />
                            <div class="col-span-4">
                                <PureInputNumber :modelValue="get(model, 'rounded.bottomleft.value', 0)" @update:modelValue="(e) => (set(model, 'rounded.bottomleft.value', e), emits('update:modelValue', model))" class="" suffix="px" />
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </div>
</template>