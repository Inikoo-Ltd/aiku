<script setup lang='ts'>
import { useColorTheme } from '@/Composables/useStockList'
import { inject, onMounted } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheck } from '@fas'
import AppThemeSwatch from '@/Components/Utils/AppThemeSwatch.vue'

library.add(faCheck)

const layout: any = inject('layout')

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
    }
}>()

const onClickColor = (colorTheme: string[]) => {
    layout.app.theme = colorTheme
    props.form[props.fieldName] = colorTheme
}

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
    <div class="grid w-full grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <button v-for="(colorTheme, index) in useColorTheme" :key="index" type="button"
            class="relative rounded-md transition hover:shadow-md focus:outline-none"
            :class="isArraysEqual(form[fieldName], colorTheme) ? 'ring-2 ring-offset-2 ring-[color:var(--app-accent)]' : ''"
            @click="() => onClickColor(colorTheme)">
            <AppThemeSwatch :colors="colorTheme" />
            <span v-if="isArraysEqual(form[fieldName], colorTheme)"
                class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-[color:var(--app-accent)] text-[10px] text-[color:var(--app-accent-text)] shadow">
                <FontAwesomeIcon icon="fas fa-check" fixed-width aria-hidden="true" />
            </span>
        </button>
    </div>
</template>
