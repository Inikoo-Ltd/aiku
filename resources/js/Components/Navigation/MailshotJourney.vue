<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronRight } from '@fal'
import { faCheck } from '@far'
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'

defineProps<{
    steps: { key: string, label: string, current: boolean, done?: boolean, disabled?: boolean, route: routeType }[]
}>()
</script>

<template>
    <div v-if="steps?.length" class="flex items-center gap-x-2 text-sm font-normal whitespace-nowrap">
        <template v-for="(step, index) in steps" :key="step.key">
            <FontAwesomeIcon v-if="index" :icon="faChevronRight" class="text-gray-300 text-[10px]" fixed-width />
            <component :is="step.current || step.disabled ? 'span' : Link"
                :href="step.current || step.disabled ? undefined : route(step.route.name, step.route.parameters)"
                v-tooltip="step.disabled ? trans('Compose the email first') : undefined"
                class="flex items-center gap-x-1.5"
                :class="step.current ? 'text-indigo-600 font-medium' : step.disabled ? 'text-gray-300 cursor-not-allowed' : step.done ? 'text-green-600 hover:text-green-700' : 'text-gray-400 hover:text-gray-600'">
                <span class="h-5 w-5 grid place-items-center rounded-full text-xs leading-none"
                    :class="step.current ? 'bg-indigo-600 text-white' : step.done ? 'bg-green-500 text-white' : 'border border-gray-300'">
                    <FontAwesomeIcon v-if="step.done" :icon="faCheck" class="text-[10px]" />
                    <template v-else>{{ index + 1 }}</template>
                </span>
                {{ step.label }}
            </component>
        </template>
    </div>
</template>
