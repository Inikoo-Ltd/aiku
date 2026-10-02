<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faChevronDown, faCircle, faSpinner, faCheckCircle } from "@fal"
import type { StaffConversationTask } from "@/Stores/staff-messaging"
library.add(faCheck, faChevronDown, faCircle, faSpinner, faCheckCircle)

const props = defineProps<{
    subtasks: StaffConversationTask["subtasks"]
}>()

const emit = defineEmits<{
    opened: []
}>()

const isOpen = ref(false)

const toggle = () => {
    isOpen.value = !isOpen.value
    if (isOpen.value) emit("opened")
}

const doneCount = computed(() => props.subtasks.filter((subtask) => subtask.status === "done").length)
const progress = computed(() => props.subtasks.length ? Math.round(doneCount.value / props.subtasks.length * 100) : 0)

const statuses = computed(() => ({
    todo: { label: ctrans("Not yet"), icon: "fal fa-circle", class: "bg-gray-100 text-gray-600" },
    in_progress: { label: ctrans("In progress"), icon: "fal fa-spinner", class: "bg-blue-100 text-blue-700" },
    done: { label: ctrans("Done"), icon: "fal fa-check-circle", class: "bg-green-100 text-green-700" },
}))
</script>

<template>
    <div class="shrink-0 border-b border-gray-200 bg-white text-xs">
        <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left transition duration-200 hover:bg-gray-50" :aria-expanded="isOpen" @click="toggle">
            <span class="w-9 shrink-0 font-semibold tabular-nums" :class="progress === 100 ? 'text-green-600' : 'text-gray-700'">{{ progress }}%</span>
            <span class="flex h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-gray-100">
                <span class="bg-green-500 transition-all duration-300" :style="{ width: `${progress}%` }" />
            </span>
            <span class="inline-flex shrink-0 items-center gap-1 tabular-nums text-gray-600">
                <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width />
                {{ doneCount }}/{{ subtasks.length }}
            </span>
            <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width class="shrink-0 text-gray-400 transition-transform duration-200" :class="!isOpen && '-rotate-90'" />
        </button>
        <ol v-show="isOpen" class="max-h-48 divide-y divide-gray-100 overflow-y-auto border-t border-gray-100">
            <li v-for="(subtask, index) in subtasks" :key="index" class="flex items-center gap-2 px-3 py-1.5">
                <span class="w-5 shrink-0 text-right tabular-nums text-gray-400">{{ index + 1 }}.</span>
                <span class="min-w-0 flex-1 truncate" :class="subtask.status === 'done' ? 'text-gray-400 line-through' : 'text-gray-800'" :title="subtask.title">{{ subtask.title }}</span>
                <span class="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 font-medium" :class="statuses[subtask.status].class">
                    <FontAwesomeIcon :icon="statuses[subtask.status].icon" fixed-width />
                    {{ statuses[subtask.status].label }}
                </span>
            </li>
        </ol>
    </div>
</template>
