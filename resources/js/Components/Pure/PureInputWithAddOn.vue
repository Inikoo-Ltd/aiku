<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useAttrs } from 'vue'
const model = defineModel()

const props = defineProps<{
    leftAddOn?: {
        label?: string
        icon?: string | string[]
    }
    rightAddOn?: {
        label?: string
        icon?: string | string[]
    }
    placeholder?: string
    readonly?: boolean
    inputName?: string
    isError?: boolean
}>()

const { value, ...attrs } = useAttrs()
defineOptions({
    inheritAttrs: false
})
</script>

<template>
   <div
        class="bg-white w-full px-2 flex relative ring-1 ring-gray-300 focus-within:ring-2 focus-within:ring-gray-500 rounded-md overflow-hidden"
        :class="isError ? 'errorShake' : ''"
    >
        <div class="flex w-full">
            <div v-if="leftAddOn" class="flex items-center gap-x-1.5">
                <div class="flex select-none items-center text-gray-400 sm:text-sm whitespace-nowrap">
                    <FontAwesomeIcon v-if="leftAddOn.icon" :icon="leftAddOn.icon" fixed-width aria-hidden="true" />
                    <span v-if="leftAddOn.label" class="leading-none">{{ leftAddOn.label }}</span>
                </div>
            </div>

            <input
                v-model="model"
                v-bind="attrs"
                type="text"
                class="remove-arrows-input bg-transparent py-2.5 block w-full px-0
                    text-gray-600 sm:text-sm placeholder:text-gray-400
                    border-transparent
                    focus:ring-0 focus:ring-gray-500 focus:outline-0 focus:border-transparent
                    read-only:bg-gray-100 read-only:ring-0 read-only:ring-transparent read-only:focus:border-transparent read-only:focus:border-gray-300 read-only:text-gray-500
                "
                :placeholder="placeholder || 'Enter value'"
                :readonly="readonly"
            />
        </div>

        <!-- Add On: Right -->
        <div v-if="rightAddOn?.icon || rightAddOn?.label" class="flex py-3 items-center gap-x-1.5">
            <div class="flex select-none items-center text-gray-400 sm:text-sm whitespace-nowrap">
                <FontAwesomeIcon v-if="rightAddOn.icon" :icon="rightAddOn.icon" fixed-width aria-hidden="true" />
                <span v-if="rightAddOn.label" class="leading-none">{{ rightAddOn.label }}</span>
            </div>
        </div>

        <!-- Slot: to add icon state (success, fail, loading) on FieldForm -->
        <slot />
    </div>
</template>