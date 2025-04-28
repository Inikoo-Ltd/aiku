<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { Popover, PopoverButton, PopoverPanel, Switch } from '@headlessui/vue'
import { inject, ref } from 'vue'
import { faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faUnlink } from "@fal"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { get, set } from 'lodash-es'
library.add(faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink)

const model = defineModel<{
    unit: string
    top: {
        value: number | null
    }
    left: {
        value: number | null
    }
    right: {
        value: number | null
    }
    bottom: {
        value: number | null
    }
}>()
const props = defineProps<{
    scope?: string
    additionalData?: {
        [key: string]: {
            disabled: boolean
            tooltip: string
        }
    }
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

/* const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') }) */

// Check if all padding values are the same
const arePaddingValuesSame = (padding) => {
    if (!padding) return false
    const values = Object.values(padding)
        .map(item => item?.value) // Extract the value properties
        .filter(value => value !== undefined); // Filter out undefined values

    // Check if all values are the same
    return values.every(value => value === values[0]);
}
const isPaddingUnitLinked = ref(arePaddingValuesSame(model.value))
const changePaddingToSameValue = (newVal: number) => {
    for (let key in model.value) {
        if (model?.value?.[key].hasOwnProperty('value')) {
            model.value[key].value = newVal; // Set value to 99
        }
    }
   /*  onSaveWorkshopFromId(side_editor_block_id) */
    emits('update:modelValue', model.value)
}

</script>

<template>
    <div class="flex flex-col pt-1 pb-3">
        <div class="pb-2">
            <div class="px-3 flex justify-between items-center mb-2">
                <div class="text-xs">{{ trans('Unit') }}</div>
                <Popover v-slot="{ open }" class="relative">
                    <PopoverButton
                        :class="open ? 'text-indigo-500' : ''"
                        class="underline"
                    >
                        {{ model?.unit }}
                    </PopoverButton>

                    <transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-1 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-1 opacity-0"
                    >
                        <PopoverPanel v-slot="{ close }" class="bg-white shadow mt-3 absolute top-full right-0 z-10 w-32 transform rounded overflow-hidden">
                            <div @click="() => {set(model, 'unit','px'), close(), emits('update:modelValue', model)}" class="px-4 py-1.5 cursor-pointer" :class="model?.unit == 'px' ? 'bg-indigo-500 text-white' : 'hover:bg-indigo-100'">px</div>
                            <div @click="() => {set(model, 'unit','%'), close(), emits('update:modelValue', model)}" class="px-4 py-1.5 cursor-pointer" :class="model?.unit == '%' ? 'bg-indigo-500 text-white' : 'hover:bg-indigo-100'">%</div>
                        </PopoverPanel>
                    </transition>
                </Popover>
            </div>

            <!-- Toggle: custom -->
            <div class="px-3 flex justify-between items-center mb-2">
                <div class="text-xs">{{ trans('Same value') }}</div>
                <Switch
                    v-model="isPaddingUnitLinked"
                    :class="[
                        isPaddingUnitLinked ? 'bg-slate-600' : 'bg-slate-300'
                    ]"
                    class="pr-1 relative inline-flex h-3 w-6 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75"
                >
                    <!-- <span class="sr-only">Use setting</span> -->
                    <span aria-hidden="true" :class="isPaddingUnitLinked ? 'translate-x-3' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-full w-1/2 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out" />
                </Switch>
            </div>

            <div class="pl-2 pr-4 flex items-center relative">
                <div class="relative w-full">
                    <Transition name="slide-to-up">
                        <div v-if="isPaddingUnitLinked">
                            <div class="grid grid-cols-5 items-center">
                                <FontAwesomeIcon icon='fad fa-border-outer' v-tooltip="scope + ' ' + trans('all')" class='' fixed-width aria-hidden='true' />
                                <div class="col-span-4">
                                    <PureInputNumber
                                        :modelValue="get(model, 'top.value', 0)"
                                        @update:modelValue="(newVal) => isPaddingUnitLinked ? changePaddingToSameValue(newVal) : false"
                                        class=""
                                        :suffix="model?.unit"
                                    />
                                </div>
                            </div>
                        </div>

                        <div v-else class="space-y-2">
                            <div class="grid grid-cols-5 items-center">
                                <FontAwesomeIcon icon='fad fa-border-top' v-tooltip="scope + ' ' + trans('top')" class='' fixed-width aria-hidden='true' />
                                <div class="col-span-4">
                                    <PureInputNumber :modelValue="get(model, 'top.value', 0)" @update:modelValue="(e) => (set(model, 'top.value', e), emits('update:modelValue', model))" class="" :suffix="model?.unit" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-5 items-center">
                                <FontAwesomeIcon icon='fad fa-border-bottom' v-tooltip="scope + ' ' + trans('bottom')" class='' fixed-width aria-hidden='true' />
                                <div class="col-span-4">
                                    <PureInputNumber :modelValue="get(model, 'bottom.value', 0)" @update:modelValue="(e) => (set(model, 'bottom.value', e), emits('update:modelValue', model))" class="" :suffix="model?.unit" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-5 items-center">
                                <FontAwesomeIcon icon='fad fa-border-left' v-tooltip="scope + ' ' + trans('left')" class='' fixed-width aria-hidden='true' />
                                <div class="col-span-4">
                                    <PureInputNumber
                                        :modelValue="get(model, 'left.value', 0)" @update:modelValue="(e) => (set(model, 'left.value', e),emits('update:modelValue', model))"
                                        class=""
                                        :suffix="model?.unit"
                                        :disabled="additionalData?.left?.disabled"
                                        v-tooltip="additionalData?.left?.tooltip"
                                    />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-5 items-center">
                                <FontAwesomeIcon icon='fad fa-border-right' v-tooltip="scope + ' ' + trans('right')" class='' fixed-width aria-hidden='true' />
                                <div class="col-span-4">
                                    <PureInputNumber
                                        :modelValue="get(model, 'right.value', 0)" @update:modelValue="(e) => (set(model, 'right.value', e),emits('update:modelValue', model))"
                                        class=""
                                        :suffix="model?.unit"
                                        :disabled="additionalData?.right?.disabled"
                                        v-tooltip="additionalData?.right?.tooltip"
                                    />
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </div>
</template>