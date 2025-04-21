<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import ColorPicker from '@/Components/Utils/ColorPicker.vue'
import Select from 'primevue/select'
import PureRadio from '@/Components/Pure/PureRadio.vue'
import { set, values } from 'lodash'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Fieldset from 'primevue/fieldset'
import Button from '../Elements/Buttons/Button.vue'
library.add(faPlus)

interface GradientData {
    value: string
    colors: string[]
    angle: string
    type: string
}

const emits = defineEmits<{
    (e: "onChange", value: GradientData ): void
}>()

const props = withDefaults(defineProps<{
    data: GradientData
}>(), {
    data: () => reactive({
        value: '',
        colors: ['rgba(0, 0, 0, 1)', 'rgba(255, 255, 255, 1)'],
        angle: '45deg',
        type: 'linear'
    })
})

const compGradient = computed(() => {
    if (props.data?.type === 'linear') {
        return `linear-gradient(${props.data?.angle}, ${props.data?.colors.join(', ')})`
    } else {
        return `${props.data?.type}-gradient(${props.data?.angle}, ${props.data?.colors.join(', ')})`
    }
})

watch(compGradient, (newValue) => {
    emits('onChange', {
        ...props.data,
        value: compGradient.value
    })
})

const gradientLinearAngles = [
    { label: 'Top to bottom', value: ['0deg', 'to bottom'] },
    { label: 'Top right to bottom left', value: ['45deg', 'to left bottom'] },
    { label: 'Right to left', value: ['90deg', 'to left'] },
    { label: 'Bottom right to top left', value: ['135deg', 'to left top'] },
    { label: 'Bottom to top', value: ['180deg', 'to top'] },
    { label: 'Bottom left to top right', value: ['225deg', 'to right top'] },
    { label: 'Left to right', value: ['270deg', 'to right'] },
    { label: 'Top left to bottom right', value: ['315deg', 'to bottom right'] }
]
const gradientConicAngles = [
    { label: 'Center', value: 'at center' },
    { label: 'Center Top', value: 'at center top' },
    { label: 'Center Right', value: ['at center right', 'at right center'] },
    { label: 'Center Bottom', value: 'at center bottom' },
    { label: 'Center Left', value: 'at center left' },
    { label: 'Top Left', value: 'at top left' },
    { label: 'Top Right', value: 'at top right' },
    { label: 'Bottom Left', value: 'at bottom left' },
    { label: 'Bottom Right', value: 'at bottom right' }
]


const gradientTypeList = [
    {
        label: 'Linear',
        name: 'linear'
    }, {
        label: 'Radial',
        name: 'radial'
    }, {
        label: 'Conic',
        name: 'conic'
    }
]

// const gradientStockList = [
//     'linear-gradient(to right, rgb(59, 130, 246), rgb(37, 99, 235))',
//     'linear-gradient(to right, rgb(59, 130, 246), rgb(147, 51, 234))',
//     'linear-gradient(to right, rgb(244, 63, 94), rgb(248, 113, 113), rgb(239, 68, 68))',
//     'linear-gradient(to left bottom, rgb(49, 46, 129), rgb(129, 140, 248), rgb(49, 46, 129))',
//     'radial-gradient(at right center, rgb(186, 230, 253), rgb(129, 140, 248))',
//     'linear-gradient(to right, rgb(255, 228, 230), rgb(204, 251, 241))'
// ]

const xxxx = [
    {
        value: 'linear-gradient(to right, rgb(59, 130, 246), rgb(37, 99, 235))',
        type: 'linear',
        colors: ['rgb(59, 130, 246)', 'rgb(37, 99, 235)'],
        angle: 'to right'
    },
    {
        value: 'linear-gradient(to right, rgb(59, 130, 246), rgb(147, 51, 234))',
        type: 'linear',
        colors: ['rgb(59, 130, 246)', 'rgb(147, 51, 234)'],
        angle: 'to right'
    },
    {
        value: 'linear-gradient(to right, rgb(244, 63, 94), rgb(248, 113, 113), rgb(239, 68, 68))',
        type: 'linear',
        colors: ['rgb(244, 63, 94)', 'rgb(248, 113, 113)', 'rgb(239, 68, 68)'],
        angle: 'to right'
    },
    {
        value: 'linear-gradient(to left bottom, rgb(49, 46, 129), rgb(129, 140, 248), rgb(49, 46, 129))',
        type: 'linear',
        colors: ['rgb(49, 46, 129)', 'rgb(129, 140, 248)', 'rgb(49, 46, 129)'],
        angle: 'to left bottom'
    },
    {
        value: 'radial-gradient(at right center, rgb(186, 230, 253), rgb(129, 140, 248))',
        type: 'radial',
        colors: ['rgb(186, 230, 253)', 'rgb(129, 140, 248)'],
        angle: 'at right center'
    },
    {
        value: 'linear-gradient(to right , rgb(255 ,228 ,230), rgb(204 ,251 ,241))',
        type: 'linear',
        colors: ['rgb(255 ,228 ,230)', 'rgb(204 ,251 ,241)'],
        angle: 'to right'
    }
]
</script>

<template>
    <div class="relative">
        <!-- Preview -->
        <div class="group relative h-12 w-full overflow-hidden rounded shadow" :style="{
            background: compGradient || 'linear-gradient(0deg, rgb(20, 20, 20), rgb(240, 240, 240))'
        }" />

        <div class="border-t border-gray-300 w-full mt-4"></div>
        
        <!-- Section: select type liner, radial, conic -->
        <div class="mt-2">
            <PureRadio
                :modelValue="props.data?.type"
                @update:modelValue="(e) => {
                    set(props.data, 'type', e),
                    e === 'linear' ? set(props.data, 'angle', '90deg') : set(props.data, 'angle', 'at center')
                }"
                mode="compact"
                :options="gradientTypeList"
                by="name"
                label="label"
                key="gradient_type"
            >
                <template #label="{ label, option, index }">
                    <label class="text-sm" :for="`${label}_${index}`">
                        {{ label }}
                    </label>
                </template>
            </PureRadio>
        </div>

        <!-- Section: select angle -->
        <div class="mt-2">
            <Select v-if="props.data?.type === 'linear'"
                :modelValue="props.data?.angle"
                @update:modelValue="(e) => set(props.data, 'angle', e)"
                :options="gradientLinearAngles"
                optionLabel="label"
                :optionValue="(data) => typeof data.value === 'object' ? data.value.includes(props.data?.angle) ? props.data?.angle : data.value[0] : data.value"
                :placeholder="trans('Select the direction')"
                fluid
                checkmark
            />
            <Select v-else
                :modelValue="props.data?.angle"
                @update:modelValue="(e) => set(props.data, 'angle', e)"
                :options="gradientConicAngles"
                optionLabel="label"
                :optionValue="(data) => typeof data.value === 'object' ? data.value.includes(props.data?.angle) ? props.data?.angle : data.value[0] : data.value"
                :placeholder="trans('Select radial place')"
                fluid
            />
        </div>

        <!-- Colors -->
        <div class="flex gap-x-2 mt-2">
            <ColorPicker
                v-for="color, index in props.data?.colors"
                :key="`${props.data?.colors?.length}_${index}`"
                :color="color"
                class=""
                @changeColor="(newColor)=> {
                    // props.data.colors[index] = `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a})`
                    set(props.data, `colors.${index}`, `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a})`)
                }"
                closeButton
            >
                <template #before-main-picker>
                    <Button
                        @click="() => {
                            props.data?.colors.splice(index, 1)
                        }"
                        :style="'negative'"
                        full
                        :key="1"
                        size="s"
                        :label="'Remove color'"
                        class="mb-2"
                    />
                </template>
                <template #button>
                    <div class="group relative h-7 w-7 overflow-hidden rounded shadow cursor-pointer" :style="{
                        backgroundColor: props.data?.colors[index]
                    }" />
                </template>
            </ColorPicker>

            <div @click="() => props.data?.colors.push('rgba(0, 40, 35, 1)')" class="group relative h-7 w-7 overflow-hidden hover:bg-gray-300/40 cursor-pointer rounded border border-gray-300 border-dashed flex items-center justify-center">
                <FontAwesomeIcon icon='fal fa-plus' class='text-gray-500' fixed-width aria-hidden='true' />
            </div>
        </div>

        <div class="border-t border-gray-300 w-full mt-4"></div>

        <!-- Section: gradient stock -->
        <div class="mt-2 space-y-1" legend="Gradient's stock">
            <div class="font-medium">
                {{ trans("Gradient stock") }}
            </div>

            <div class="flex gap-x-1 flex-wrap gap-y-2">
                <div v-for="stock in xxxx"
                    @click="() => {
                        set(props.data, 'value', stock.value),
                        set(props.data, 'type', stock.type),
                        set(props.data, 'angle', stock.angle),
                        set(props.data, 'colors', stock.colors)
                    }"
                    class="group relative h-7 w-full max-w-11 overflow-hidden rounded shadow cursor-pointer"
                    :style="{
                        background: stock.value
                    }"
                />
            </div>
            <!-- <pre>{{ props.data }}</pre> -->

        </div>
    </div>
</template>
