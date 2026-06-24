<template>
    <select name="per_page" :dusk="dusk" :value="value"
        class="block focus:ring-gray-500 focus:border-gray-500 min-w-max shadow-sm text-sm border-gray-300 rounded-md"
        @change="onChange($event.target.value)">
        <option v-for="option in perPageOptions" :key="option" :value="option">
            {{ option }} {{ translations.per_page }}
        </option>
    </select>
</template>

<script setup lang="ts">
import { computed } from "vue"
import uniq from "lodash-es/uniq"
import { getTranslations } from "./translations.js"

const translations = getTranslations()

const props = withDefaults(defineProps<{
    dusk: string
    value?: number
    options: number[]
    onChange: Function
}>(), {
    dusk: '',
    value: 15,
    options: () => [10, 50, 100, 500, 1000],
})

const perPageOptions = computed(() => {
    let options = [...props.options]

    options.push(parseInt(props.value))

    return uniq(options).sort((a: number, b: number) => a - b)
})
</script>