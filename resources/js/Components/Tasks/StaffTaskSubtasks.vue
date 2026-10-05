<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import axios from "axios"
import { Button, InputText } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faSpinner, faPlus, faChevronDown, faTimes, faCircle, faCheckCircle } from "@fal"
library.add(faCheck, faSpinner, faPlus, faChevronDown, faTimes, faCircle, faCheckCircle)

type SubtaskStatus = "todo" | "in_progress" | "done"
type Subtask = { title: string; status: SubtaskStatus }

const props = defineProps<{
    reference: string
    subtasks: Subtask[]
    canEdit: boolean
}>()

const emit = defineEmits<{
    saved: []
}>()

const subtasks = ref<Subtask[]>([])
watch(() => props.subtasks, (list) => (subtasks.value = list.map((subtask) => ({ ...subtask }))), { immediate: true })

const doneCount = computed(() => subtasks.value.filter((subtask) => subtask.status === "done").length)
const progress = computed(() => subtasks.value.length ? Math.round(doneCount.value / subtasks.value.length * 100) : 0)

const statuses = computed<Record<SubtaskStatus, { label: string; icon: string; class: string }>>(() => ({
    todo: { label: ctrans("Not yet"), icon: "fal fa-circle", class: "bg-gray-100 text-gray-600" },
    in_progress: { label: ctrans("In progress"), icon: "fal fa-spinner", class: "bg-blue-100 text-blue-700" },
    done: { label: ctrans("Done"), icon: "fal fa-check-circle", class: "bg-green-100 text-green-700" },
}))

const nextStatus: Record<SubtaskStatus, SubtaskStatus> = { todo: "in_progress", in_progress: "done", done: "todo" }

const isSaving = ref(false)

const save = async (nextSubtasks: Subtask[]) => {
    const previousSubtasks = subtasks.value
    subtasks.value = nextSubtasks
    isSaving.value = true
    try {
        await axios.patch(route("grp.tasks.subtasks.update", props.reference), { subtasks: nextSubtasks })
        emit("saved")
    } catch (error: any) {
        subtasks.value = previousSubtasks
        notify({ title: ctrans("Could not update sub tasks"), text: error.response?.data?.message, type: "error" })
    } finally {
        isSaving.value = false
    }
}

const cycleStatus = (index: number) =>
    save(subtasks.value.map((subtask, position) => position === index ? { ...subtask, status: nextStatus[subtask.status] } : subtask))

const remove = (index: number) => save(subtasks.value.filter((_, position) => position !== index))

const readListState = () => {
    try {
        return localStorage.getItem("staff-task-subtasks") !== "closed"
    } catch {
        return true
    }
}

const isListOpen = ref(readListState())

const toggleList = () => {
    isListOpen.value = !isListOpen.value
    try {
        localStorage.setItem("staff-task-subtasks", isListOpen.value ? "open" : "closed")
    } catch {
        return
    }
}

const isAdding = ref(false)
const newTitle = ref("")
const input = ref()

const startAdding = async () => {
    isListOpen.value = true
    isAdding.value = true
    await nextTick()
    input.value?.$el?.focus()
}

const add = () => {
    const title = newTitle.value.trim()
    if (!title) return
    newTitle.value = ""
    save([...subtasks.value, { title, status: "todo" }])
}

const stopAdding = () => {
    isAdding.value = false
    newTitle.value = ""
}

defineExpose({ isSaving })
</script>

<template>
    <div v-if="subtasks.length || canEdit" class="mt-4 border-t border-gray-200 pt-3">
        <div class="flex items-center gap-3 text-sm">
            <div v-tooltip="ctrans(':done of :total sub tasks done', { done: doneCount, total: subtasks.length })" class="flex min-w-0 flex-1 items-center gap-2">
                <span class="w-10 shrink-0 font-semibold tabular-nums" :class="progress === 100 ? 'text-green-600' : 'text-gray-700'">{{ progress }}%</span>
                <span class="flex h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                    <span class="bg-green-500 transition-all duration-300" :style="{ width: `${progress}%` }" />
                </span>
            </div>
            <span class="h-5 w-px bg-gray-200" aria-hidden="true" />
            <span class="inline-flex items-center gap-1 tabular-nums text-gray-600">
                <FontAwesomeIcon :icon="isSaving ? 'fal fa-spinner' : 'fal fa-check'" :spin="isSaving" class="text-green-600" fixed-width />
                {{ doneCount }}/{{ subtasks.length }}
            </span>
            <template v-if="canEdit">
                <span class="h-5 w-px bg-gray-200" aria-hidden="true" />
                <button type="button" class="inline-flex items-center gap-1 rounded px-1.5 py-1 text-[--app-accent-strong] transition duration-200 hover:bg-gray-100 active:!bg-gray-200" @click="startAdding">
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width />
                    {{ ctrans("Add sub task") }}
                </button>
            </template>
            <span class="h-5 w-px bg-gray-200" aria-hidden="true" />
            <button
                type="button"
                v-tooltip="isListOpen ? ctrans('Hide sub tasks') : ctrans('Show sub tasks')"
                class="rounded p-1 text-gray-400 transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                @click="toggleList">
                <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width class="transition-transform duration-200" :class="!isListOpen && '-rotate-90'" />
            </button>
        </div>

        <div v-show="isListOpen" class="mt-2">
            <ol v-if="subtasks.length" class="divide-y divide-gray-100">
                <li v-for="(subtask, index) in subtasks" :key="index" class="group flex items-center gap-3 py-2 text-sm">
                    <span class="w-6 shrink-0 text-right text-xs tabular-nums text-gray-400">{{ index + 1 }}.</span>
                    <span class="min-w-0 flex-1 break-words" :class="subtask.status === 'done' ? 'text-gray-400 line-through' : 'text-gray-800'">{{ subtask.title }}</span>
                    <component
                        :is="canEdit ? 'button' : 'span'"
                        :type="canEdit ? 'button' : undefined"
                        v-tooltip="canEdit ? ctrans('Click to change') : undefined"
                        class="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium transition duration-200"
                        :class="[statuses[subtask.status].class, canEdit && 'hover:brightness-95 active:brightness-90']"
                        @click="canEdit && cycleStatus(index)">
                        <FontAwesomeIcon :icon="statuses[subtask.status].icon" fixed-width />
                        {{ statuses[subtask.status].label }}
                    </component>
                    <button
                        v-if="canEdit"
                        type="button"
                        v-tooltip="ctrans('Remove')"
                        class="shrink-0 rounded p-1 text-gray-300 opacity-0 transition duration-200 hover:text-red-500 focus:opacity-100 group-hover:opacity-100"
                        @click="remove(index)">
                        <FontAwesomeIcon icon="fal fa-times" fixed-width />
                    </button>
                </li>
            </ol>
            <p v-else-if="!isAdding" class="py-2 text-sm text-gray-400">{{ ctrans("No sub tasks yet") }}</p>

            <form v-if="isAdding" class="mt-2 flex items-center gap-2" @submit.prevent="add">
                <InputText
                    ref="input"
                    v-model="newTitle"
                    size="small"
                    maxlength="255"
                    fluid
                    :placeholder="ctrans('What needs doing? Enter to add another')"
                    @keydown.esc="stopAdding" />
                <Button type="submit" size="small" :label="ctrans('Add')" :disabled="!newTitle.trim()" />
                <Button type="button" size="small" text severity="secondary" :label="ctrans('Close')" @click="stopAdding" />
            </form>
        </div>
    </div>
</template>
