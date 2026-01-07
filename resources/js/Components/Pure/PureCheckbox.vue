<script setup lang="ts">
import { inject } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'

const model = defineModel()


const layout = inject('layout', layoutStructure)

defineOptions({
    inheritAttrs: false
})
</script>

<template>
    <div>
        <input
            v-model="model"
            v-bind="$attrs"
            type="checkbox"
            class="h-6 w-6 rounded-sm cursor-pointer custom-checkbox" />
    </div>
</template>

<style scoped lang="scss">
.custom-checkbox {
    background-color: transparent;
    border: v-bind('`1px solid color-mix(in srgb, ${layout?.app?.theme[4]} 80%, black)`');

    &:checked {

        background-color: v-bind('layout?.app?.theme[4]') !important;
        color: v-bind('layout?.app?.theme[5]') !important;
    }
    &:checked:hover {

        background-color: v-bind('layout?.app?.theme[4]') !important;
        color: v-bind('layout?.app?.theme[5]') !important;
    }

    &:hover {
        background-color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 15%, transparent)`') !important;
    }

    &:focus {
        box-shadow: 0 0 0 2px v-bind('layout?.app?.theme[4]') !important;
    }

    &:disabled {
        background-color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 50%, grey)`') !important;
    }
}
</style>