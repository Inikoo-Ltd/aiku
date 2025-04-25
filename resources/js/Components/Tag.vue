<script setup lang="ts">
// import { computed } from 'vue'
import { useStringToHex } from '@/Composables/useStringToHex'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faTimes)

const props = withDefaults(defineProps<{
    size?: string
    theme?: number
    label?: string
    closeButton?: boolean
    stringToColor?: boolean
    class?: string
    noHoverColor?: boolean
}>(), {
    theme: 99,
    size: 'xs'
})

const emits = defineEmits<{
    (e: 'onClose', event: any): void
}>()

const listTheme: any = {
    1: `bg-blue-100 ${props.noHoverColor ? '' : 'hover:bg-blue-200'} border border-blue-200 text-blue-500`,
    2: `bg-orange-100 ${props.noHoverColor ? '' : 'hover:bg-orange-200'} border border-orange-200 text-orange-500`,
    3: `bg-green-100 ${props.noHoverColor ? '' : 'hover:bg-green-200'} border border-green-200 text-green-600`,
    4: `bg-yellow-200 ${props.noHoverColor ? '' : 'hover:bg-yellow-3s00'} border border-yellow-300 text-yellow-600`,
    5: `bg-indigo-100 ${props.noHoverColor ? '' : 'hover:bg-indigo-200'} border border-indigo-200 text-indigo-500`,
    6: `bg-pink-100 ${props.noHoverColor ? '' : 'hover:bg-pink-200'} border border-pink-200 text-pink-500`,
    7: `bg-red-100 ${props.noHoverColor ? '' : 'hover:bg-red-200'} border border-red-200 text-red-500`,
    8: `bg-amber-100 ${props.noHoverColor ? '' : 'hover:bg-amber-200'} border border-amber-200 text-amber-500`,
    9: `bg-lime-100 ${props.noHoverColor ? '' : 'hover:bg-lime-200'} border border-lime-200 text-lime-500`,
    10: `bg-teal-100 ${props.noHoverColor ? '' : 'hover:bg-teal-200'} border border-teal-200 text-teal-500`,
    11: `bg-purple-100 ${props.noHoverColor ? '' : 'hover:bg-purple-200'} border border-purple-200 text-purple-500`,
    12: `bg-rose-100 ${props.noHoverColor ? '' : 'hover:bg-rose-200'} border border-rose-200 text-rose-500`,
    13: `bg-violet-100 ${props.noHoverColor ? '' : 'hover:bg-violet-200'} border border-violet-200 text-violet-500`,
    14: `bg-emerald-100 ${props.noHoverColor ? '' : 'hover:bg-emerald-200'} border border-emerald-200 text-emerald-500`,
    15: `bg-fuchsia-100 ${props.noHoverColor ? '' : 'hover:bg-fuchsia-200'} border border-fuchsia-200 text-fuchsia-500`,
    16: `bg-cyan-100 ${props.noHoverColor ? '' : 'hover:bg-cyan-200'} border border-cyan-200 text-cyan-500`,
    17: `bg-sky-100 ${props.noHoverColor ? '' : 'hover:bg-sky-200'} border border-sky-200 text-sky-500`,
    99: `bg-slate-100 ${props.noHoverColor ? '' : 'hover:bg-slate-200'} border border-slate-200 text-slate-500`,
}

const compTheme = () => {
    return props.class || listTheme[props.theme] || listTheme[99]
}
</script>

<template>
    <div class="inline-flex items-center gap-x-1 rounded select-none px-1.5 py-1 w-fit font-medium border"
        :class="[
            `text-${size}`,
            stringToColor ? false : compTheme()  // If stringToColor false then take provided style
        ]"
        :style="[
            stringToColor ? [  // if stringToColor true
                `background-color: color-mix(in srgb, ${useStringToHex(label)} 30%, white)`,
                `border: 1px solid color-mix(in srgb, ${useStringToHex(label)} 80%, black)`,
                `color: color-mix(in srgb, ${useStringToHex(label)} 70%, black)`
            ] : ''
        ]"
    >
        <slot name="label">
            {{ label }}
        </slot>

        <!-- Button: Close (X icon) -->
        <div v-if="closeButton"
            @click="(event) => {emits('onClose', event)}"
            class="cursor-pointer bg-white/60 hover:bg-black/10 px-1 rounded-sm">
            <FontAwesomeIcon icon='fal fa-times' class='' aria-hidden='true' />
        </div>
    </div>
</template>
