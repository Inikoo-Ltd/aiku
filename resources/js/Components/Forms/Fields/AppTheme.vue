<script setup lang='ts'>
// import { ColorPicker } from 'vue-color-kit'
// import 'vue-color-kit/dist/vue-color-kit.css'
import { useColorTheme } from '@/Composables/useStockList'
// import { useLayoutStore } from '@/Stores/layout'
import { inject, onMounted } from 'vue'

const layout: any = inject('layout')

// const layoutStore = useLayoutStore()

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
    }
}>()

// Method: on click the theme
const onClickColor = (colorTheme: string[]) => {
    layout.app.theme = colorTheme
    props.form[props.fieldName] = colorTheme
}

// Method: check if arr1 and arr2 is same
const isArraysEqual = (arr1: string[], arr2: string[]) => {
    if (arr1?.length !== arr2?.length) return false
    
    for (let i = 0; i < arr1?.length; i++) {
        if (arr1[i] !== arr2[i]) return false
    }

    return true
}

onMounted(() => {
    if (!props.form[props.fieldName]) {
        props.form[props.fieldName] = useColorTheme[0]
    }
})
</script>

<template>
    <div class="relative w-full">
        <div class="flex flex-wrap gap-x-2 gap-y-3">
            <div v-for="colorTheme in useColorTheme" @click="() => onClickColor(colorTheme)"
                class="relative h-20 aspect-[16/9] w-fit flex ring-1 ring-gray-300 hover:ring-2 hover:ring-gray-500 shadow-sm rounded-sm overflow-hidden cursor-pointer"
            >
                <div class="absolute h-full left-0 w-1/6" :style="{backgroundColor: colorTheme[0]}" />
                <div class="absolute h-0.5 w-4 left-1 top-2" :style="{backgroundColor: colorTheme[6]}" />
                <div class="absolute h-0.5 w-4 left-1 top-4" :style="{backgroundColor: colorTheme[6]}" />
                <div class="absolute h-0.5 w-4 left-1 top-6" :style="{backgroundColor: colorTheme[6]}" />
                <div class="absolute h-2 w-6 top-3 right-2 rounded-xs" :style="{backgroundColor: colorTheme[0] + '66'}" />
                
                <Transition name="slide-to-right">
                    <div v-if="isArraysEqual(form[fieldName], colorTheme)" class="absolute inset-0 bg-gray-600/30 flex items-center justify-center">
                        Selected
                    </div>
                </Transition>

                <!-- <div class="h-6 aspect-square" :style="{backgroundColor: colorTheme[0]}" />
                <div class="h-6 aspect-square" :style="{backgroundColor: colorTheme[6]}" /> -->
                <!-- <div class="h-6 aspect-square" :style="{backgroundColor: colorTheme[1]}" /> -->
                <!-- <div class="h-6 aspect-square" :style="{backgroundColor: colorTheme[2]}" />
                <div class="h-6 aspect-square" :style="{backgroundColor: colorTheme[3]}" />
                <div class="h-6 aspect-square" :style="{backgroundColor: colorTheme[4]}" />
                <div class="h-6 aspect-square" :style="{backgroundColor: colorTheme[5]}" /> -->
                <!-- <div class="h-6 aspect-square" :style="{backgroundColor: colorTheme[7]}" /> -->
            </div>
        </div>

        <!-- <ColorPicker theme="dark" :color="bgColor" :colors-default="useSolidColor" @changeColor="(e) => onChangeColor(e)"/> -->
    </div>
</template>