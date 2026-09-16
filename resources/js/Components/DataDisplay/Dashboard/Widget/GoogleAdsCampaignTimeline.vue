<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPencil, faPause, faPlay } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"

library.add(faPencil, faPause, faPlay)

type Step = {
    key: string
    label: string
    tooltip: string
    icon: string
    timestamp: string | null
}

/**
 * The three points a campaign passes through, and where it stands now.
 *
 * A step that has happened carries the moment it did. One that has not is drawn faintly and says so,
 * rather than being hidden: the value of the strip is knowing what is still ahead, which is the
 * question somebody has when a campaign is sitting paused and not spending.
 */
const props = defineProps<{
    state: {
        current: string
        label: string
        description: string
        last_error: string | null
        timeline: Step[]
    }
}>()

const reachedIndex = computed(() => props.state.timeline.findIndex((step) => step.key === props.state.current))

const isReached = (index: number) => index <= reachedIndex.value
const isCurrent = (index: number) => index === reachedIndex.value
</script>

<template>
    <div>
        <ol class="flex flex-wrap items-stretch gap-2">
            <li
                v-for="(step, index) in state.timeline"
                :key="step.key"
                class="flex min-w-[12rem] flex-1 items-start gap-2 rounded-lg p-3"
                :class="isCurrent(index) ? 'bg-indigo-50 ring-1 ring-indigo-300' : 'ring-1 ring-gray-100'"
                :aria-current="isCurrent(index) ? 'step' : undefined">
                <FontAwesomeIcon
                    :icon="step.icon"
                    fixed-width
                    aria-hidden="true"
                    class="mt-0.5"
                    :class="isReached(index) ? 'text-indigo-500' : 'text-gray-300'" />

                <div class="min-w-0">
                    <div class="text-xs font-medium" :class="isReached(index) ? 'text-gray-800' : 'text-gray-400'">
                        {{ step.label }}
                    </div>
                    <div class="mt-0.5 text-xs" :class="isReached(index) ? 'text-gray-500' : 'text-gray-400'">
                        <template v-if="step.timestamp">
                            {{ useFormatTime(step.timestamp, { formatTime: "short-datetime" }) }}
                        </template>
                        <template v-else>{{ trans("not yet") }}</template>
                    </div>
                    <div v-if="isCurrent(index)" class="mt-1 text-xs text-gray-600">{{ state.description }}</div>
                </div>
            </li>
        </ol>

        <p v-if="state.last_error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-[#d03b3b]">
            {{ trans("Google refused the last attempt to publish this") }}: {{ state.last_error }}
        </p>
    </div>
</template>
