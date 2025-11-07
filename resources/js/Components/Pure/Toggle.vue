<script setup lang="ts">
import { Switch } from '@headlessui/vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes } from "@fal"
import { faCheck } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faTimes, faCheck)

const props = defineProps<{
    modelValue: boolean
    classes?: string
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
}>()

</script>


<template>
    <Switch
        :modelValue="modelValue"
        @update:modelValue="(val) => emit('update:modelValue', val)"
        :class="[
            modelValue ? 'bg-green-500' : 'bg-slate-300',
            classes,
        ]"
        class="pr-1 relative inline-flex h-6 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
    >
        <span aria-hidden="true" :class="modelValue ? 'translate-x-6 bg-white ' : 'translate-x-0 bg-gray-50'"
            class="flex items-center justify-center pointer-events-none h-full w-1/2 transform rounded-full shadow-lg ring-0 transition">
            <FontAwesomeIcon v-if="modelValue" icon='far fa-check' class='text-sm text-green-600' fixed-width aria-hidden='true' />
            <FontAwesomeIcon v-else icon='fal fa-times' class='text-sm text-red-500' fixed-width aria-hidden='true' />
        </span>

    </Switch>
</template>