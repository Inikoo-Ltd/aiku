<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck } from "@fas"

export type WorkflowStep = {
    key: string
    label: string
    owner?: string
    done: boolean
}

const props = defineProps<{
    steps: WorkflowStep[]
    current: number
    compact?: boolean
    selectableKeys?: string[]
}>()

const emit = defineEmits<{
    select: [key: string]
}>()

const isSelectable = (step: WorkflowStep) => !!props.selectableKeys?.includes(step.key)

const stateOf = (stepNumber: number, step: WorkflowStep, current: number) => {
    if (step.done) {
        return "done"
    }

    return stepNumber === current ? "current" : "pending"
}

const circleClass: Record<string, string> = {
    done: "bg-green-700 text-white",
    current: "bg-[--app-accent] text-[--app-accent-text]",
    pending: "bg-gray-100 text-gray-500",
}
</script>

<template>
    <div v-if="compact" class="flex items-center gap-2 whitespace-nowrap">
        <div class="flex items-center gap-1">
            <span
                v-for="(step, stepIndex) in steps"
                :key="step.key"
                v-tooltip="step.owner ? `${step.label} · ${step.owner}` : step.label"
                class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-semibold"
                :class="circleClass[stateOf(stepIndex + 1, step, current)]"
            >
                <FontAwesomeIcon v-if="step.done" :icon="faCheck" class="text-[9px]" fixed-width aria-hidden="true" />
                <template v-else>{{ stepIndex + 1 }}</template>
            </span>
        </div>
        <span class="text-sm text-gray-700">{{ steps[current - 1]?.label }}</span>
    </div>

    <ol v-else class="grid grid-cols-1 gap-2 rounded-lg border border-gray-200 bg-white p-3 sm:grid-cols-2 xl:grid-cols-4">
        <li
            v-for="(step, stepIndex) in steps"
            :key="step.key"
            class="flex items-center gap-3 rounded-md px-3 py-2"
            :class="[
                stateOf(stepIndex + 1, step, current) === 'current' ? 'border border-[--app-accent-muted] bg-[--app-accent-soft]' : 'border border-transparent',
                isSelectable(step) ? 'cursor-pointer hover:border-[--app-accent]' : '',
            ]"
            :role="isSelectable(step) ? 'button' : undefined"
            :tabindex="isSelectable(step) ? 0 : undefined"
            @click="isSelectable(step) && emit('select', step.key)"
            @keydown.enter="isSelectable(step) && emit('select', step.key)"
        >
            <span
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                :class="circleClass[stateOf(stepIndex + 1, step, current)]"
            >
                <FontAwesomeIcon v-if="step.done" :icon="faCheck" class="text-xs" fixed-width aria-hidden="true" />
                <template v-else>{{ stepIndex + 1 }}</template>
            </span>
            <div class="min-w-0">
                <div
                    class="text-sm"
                    :class="stateOf(stepIndex + 1, step, current) === 'pending' ? 'text-gray-500' : 'font-medium text-gray-800'"
                >{{ step.label }}</div>
                <div v-if="step.owner" class="text-xs text-gray-500">{{ step.owner }}</div>
            </div>
        </li>
    </ol>
</template>
