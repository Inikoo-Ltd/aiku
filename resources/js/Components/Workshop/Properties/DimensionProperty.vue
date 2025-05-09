<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { Popover, PopoverButton, PopoverPanel, Switch } from '@headlessui/vue'
import { inject, ref } from 'vue'
import { faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faArrowsAltV, faArrowsH } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faUnlink } from "@fal"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { get, set } from 'lodash-es'
library.add(faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink, faArrowsAltV, faArrowsH )

const model = defineModel()

/* const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') }) */

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

</script>

<template>
    <div class="flex flex-col pt-1 pb-3">

        <div class="pb-2">
            <div class="px-3 flex justify-between items-center mb-2">
                <div class="text-xs">{{ trans('Height') }}</div>
                <Popover v-slot="{ open }" class="relative">
                    <PopoverButton :class="open ? 'text-indigo-500' : ''" class="underline">
                        {{ model?.height?.unit ? model?.height.unit : '%' }}
                    </PopoverButton>

                    <transition enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-1 opacity-0" enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-1 opacity-0">
                        <PopoverPanel v-slot="{ close }"
                            class="bg-white shadow mt-3 absolute top-full right-0 z-10 w-32 transform rounded overflow-hidden">
                            <div @click="() => { model.height.unit = 'px', emits('update:modelValue',model), close() }" class="px-4 py-1.5 cursor-pointer"
                                :class="model?.height.unit == 'px' ? 'bg-indigo-500 text-white' : 'hover:bg-indigo-100'">px
                            </div>
                            <div @click="() => { model.height.unit = '%', emits('update:modelValue',model), close() }" class="px-4 py-1.5 cursor-pointer"
                                :class="model?.height.unit == '%' ? 'bg-indigo-500 text-white' : 'hover:bg-indigo-100'">%</div>
                        </PopoverPanel>
                    </transition>
                </Popover>
            </div>


            <div class="pl-2 pr-4 flex items-center relative">
                <div class="relative">
                    <Transition name="slide-to-up">
                        <div>
                            <div class="grid grid-cols-5 items-center">
                                <FontAwesomeIcon icon='fad fa-border-outer' v-tooltip="trans('Height')" class=''
                                    fixed-width aria-hidden='true' />
                                <div class="col-span-4">
                                    <PureInputNumber
                                        :modelValue="get(model, 'height.value', 0)"
                                        @update:modelValue="(newVal) => (set(model, 'height.value', newVal), emits('update:modelValue',model))"
                                        class=""
                                        :suffix="model?.height?.unit ? model?.height.unit : '%'"
                                    />
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>


        <div class="pb-2">
            <div class="px-3 flex justify-between items-center mb-2">
                <div class="text-xs">{{ trans('Width') }}</div>
                <Popover v-slot="{ open }" class="relative">
                    <PopoverButton :class="open ? 'text-indigo-500' : ''" class="underline">
                        {{ model?.width?.unit ? model?.width.unit : '%' }}
                    </PopoverButton>

                    <transition enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-1 opacity-0" enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-1 opacity-0">
                        <PopoverPanel v-slot="{ close }"
                            class="bg-white shadow mt-3 absolute top-full right-0 z-10 w-32 transform rounded overflow-hidden">
                            <div @click="() => { model.width.unit = 'px',emits('update:modelValue',model) , close() }" class="px-4 py-1.5 cursor-pointer"
                                :class="model?.width.unit == 'px' ? 'bg-indigo-500 text-white' : 'hover:bg-indigo-100'">px
                            </div>
                            <div @click="() => { model.width.unit = '%', emits('update:modelValue',model), close() }" class="px-4 py-1.5 cursor-pointer"
                                :class="model?.width.unit == '%' ? 'bg-indigo-500 text-white' : 'hover:bg-indigo-100'">%</div>
                        </PopoverPanel>
                    </transition>
                </Popover>
            </div>


            <div class="pl-2 pr-4 flex items-center relative">
                <div class="relative">
                    <Transition name="slide-to-up">
                        <div>
                            <div class="grid grid-cols-5 items-center">
                                <FontAwesomeIcon icon='fad fa-border-outer' v-tooltip="trans('Width')" class=''
                                    fixed-width aria-hidden='true' />
                                <div class="col-span-4">
                                    <PureInputNumber
                                        :modelValue="get(model, 'width.value', 0)"
                                        @update:modelValue="(newVal) => (set(model, 'width.value', newVal),emits('update:modelValue',model))"
                                        class=""
                                        :suffix="model?.width?.unit ? model?.width.unit : '%'"
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