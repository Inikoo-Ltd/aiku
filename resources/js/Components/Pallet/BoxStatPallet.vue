<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'


defineProps<{
    tooltip?: string
    label?: string | number
    icon?: string | string[]
    percentage?: number
    color?: {
        bgColor?: string
        textColor?: string
    }
}>()
</script>

<template>
    <div class="relative flex flex-col justify-start" >

        
        <!-- Section: Percentage (%) -->
        <div v-if="percentage" class="absolute top-0.5 right-0.5 tabular-nums text-xxs rounded-br-sm px-1">
            {{ percentage }}%
        </div>
        
        <!-- Section: Progress bar -->
        <div v-if="percentage" class="absolute -top-0.5 left-0 h-0.5 bg-green-500 w-full text-xxs rounded-br-sm px-1 transition-all"
            :style="{width: percentage + '%'}"
        />

        <div class="flex flex-col justify-center w-full flex-none gap-x-4 gap-y-1">
            <slot>
                <dl v-if="icon || label" class="flex gap-x-3">
                    <dt v-if="icon" class="flex-none">
                        <span class="sr-only">Contact name</span>
                        <FontAwesomeIcon :icon='icon' class='text-gray-400' fixed-width aria-hidden='true' />
                    </dt>
                    <dd v-if="label" class="text-gray-500">{{ label }}</dd>
                </dl>
            </slot>
        </div>
    </div>
</template>