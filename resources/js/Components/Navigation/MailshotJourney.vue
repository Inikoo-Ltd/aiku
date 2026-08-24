<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronRight } from '@fal'
import { routeType } from '@/types/route'

defineProps<{
    steps: { key: string, label: string, current: boolean, route: routeType }[]
}>()
</script>

<template>
    <div v-if="steps?.length" class="flex items-center gap-x-2 text-sm font-normal whitespace-nowrap">
        <template v-for="(step, index) in steps" :key="step.key">
            <FontAwesomeIcon v-if="index" :icon="faChevronRight" class="text-gray-300 text-[10px]" fixed-width />
            <component :is="step.current ? 'span' : Link"
                :href="step.current ? undefined : route(step.route.name, step.route.parameters)"
                class="flex items-center gap-x-1.5"
                :class="step.current ? 'text-indigo-600 font-medium' : 'text-gray-400 hover:text-gray-600'">
                <span class="h-5 w-5 grid place-items-center rounded-full text-xs leading-none"
                    :class="step.current ? 'bg-indigo-600 text-white' : 'border border-gray-300'">
                    {{ index + 1 }}
                </span>
                {{ step.label }}
            </component>
        </template>
    </div>
</template>
