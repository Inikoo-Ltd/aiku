<script setup lang="ts">
import { computed } from "vue"
import { isPlainObject } from 'lodash-es'

const props = withDefaults(defineProps<{
    modelValue: any
    screenType?: string
}>(), {})

const valueForField = computed(() => {
    const rawVal = props.modelValue

    if (!isPlainObject(rawVal)) {
        return typeof rawVal === 'string' ? rawVal : ''
    }

    const useResp = rawVal.use_responsive
    const view = props.screenType!

    const val = useResp
        ? rawVal?.[view] ?? rawVal?.desktop
        : rawVal?.desktop

    return typeof val === 'string' ? val : ''
})

</script>

<template>
    <div v-html="valueForField"></div>
</template>
