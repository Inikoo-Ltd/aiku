<script setup lang="ts">
import { computed } from 'vue'
import { Switch } from '@headlessui/vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes } from "@fal"
import { faCheck } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import LoadingIcon from '../Utils/LoadingIcon.vue'
library.add(faTimes, faCheck)

const props = withDefaults(defineProps<{
    modelValue: boolean|undefined
    classes?: string
    loading?: boolean
    disabled?: boolean
    size?: 'sm' | 'md' | 'lg'
}>(), {
    size: 'lg'
})

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
}>()

const sizeClasses = {
    sm: {
        track: 'h-4 w-8',
        knob: 'translate-x-4',
        icon: 'text-[8px]'
    },
    md: {
        track: 'h-5 w-10',
        knob: 'translate-x-5',
        icon: 'text-xs'
    },
    lg: {
        track: 'h-6 w-12',
        knob: 'translate-x-6',
        icon: 'text-sm'
    }
}

const currentSize = computed(() => sizeClasses[props.size] ?? sizeClasses.lg)
</script>


<template>
    <Switch
        :modelValue="modelValue"
        @update:modelValue="(val) => emit('update:modelValue', val)"
        :class="[
            loading
                ? 'bg-slate-500 cursor-not-allowed'
                : modelValue
                    ? 'bg-green-500'
                    : 'bg-slate-300',
            currentSize.track,
            classes,
        ]"
        :disabled
        class="pr-1 relative inline-flex shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75"
    >
        <span aria-hidden="true" :class="modelValue ? [currentSize.knob, 'bg-white'] : ['translate-x-0', 'bg-gray-50']"
            class="flex items-center justify-center pointer-events-none h-full w-1/2 transform rounded-full shadow-lg ring-0 transition">
            <LoadingIcon v-if="loading" :class="currentSize.icon" />
            <FontAwesomeIcon v-else-if="modelValue" icon='far fa-check' :class="['text-green-600', currentSize.icon]" fixed-width aria-hidden='true' />
            <FontAwesomeIcon v-else icon='fal fa-times' :class="['text-red-500', currentSize.icon]" fixed-width aria-hidden='true' />
        </span>
    </Switch>
</template>