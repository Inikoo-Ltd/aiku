<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import { Dialog, Textarea, Button } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlay, faPause, faCheck, faTimes, faUndo, faSpinner } from "@fal"
library.add(faPlay, faPause, faCheck, faTimes, faUndo, faSpinner)

type StatusAction = { status: string; label: string; icon: string; class: string }

const props = defineProps<{
    task: { reference: string; status: string }
    canEdit: boolean
    canClaim?: boolean
}>()

const emit = defineEmits<{
    updated: [task: any]
}>()

const start: StatusAction = { status: "in_progress", label: ctrans("Working on it"), icon: "fal fa-play", class: "text-blue-500" }
const claim: StatusAction = { ...start, label: ctrans("I will do it") }
const done: StatusAction = { status: "done", label: ctrans("Done"), icon: "fal fa-check", class: "text-green-600" }
const cancel: StatusAction = { status: "cancelled", label: ctrans("Can't be done"), icon: "fal fa-times", class: "text-red-500" }
const backToTodo: StatusAction = { status: "todo", label: ctrans("Back to todo"), icon: "fal fa-pause", class: "text-gray-500" }
const reopen: StatusAction = { status: "todo", label: ctrans("Reopen"), icon: "fal fa-undo", class: "text-gray-500" }

const actions = computed<StatusAction[]>(() => {
    if (!props.canEdit) {
        return props.canClaim && props.task.status === "todo" ? [claim] : []
    }

    return ({
        todo: [done, cancel, start],
        in_progress: [done, cancel, backToTodo],
        done: [reopen],
        cancelled: [reopen],
    } as Record<string, StatusAction[]>)[props.task.status] ?? []
})

const pendingStatus = ref<string | null>(null)

const patch = async (payload: Record<string, unknown>) => {
    if (pendingStatus.value) return
    pendingStatus.value = payload.status as string
    try {
        const { data } = await axios.patch(route("grp.tasks.update", props.task.reference), payload)
        emit("updated", data.data)
    } catch (error: any) {
        notify({ title: ctrans("Could not update task"), text: error.response?.data?.message, type: "error" })
    } finally {
        pendingStatus.value = null
    }
}

const cancelNoteOpen = ref(false)
const cancelNote = ref("")

const run = (action: StatusAction) => {
    if (action.status === "cancelled") {
        cancelNote.value = ""
        cancelNoteOpen.value = true
        return
    }
    patch({ status: action.status })
}

const confirmCancel = () => {
    if (!cancelNote.value.trim()) return
    cancelNoteOpen.value = false
    patch({ status: "cancelled", note: cancelNote.value.trim() })
}
</script>

<template>
    <div v-if="actions.length" class="flex items-center gap-0.5">
        <button
            v-for="action in actions"
            :key="action.status + action.icon"
            v-tooltip="action.label"
            type="button"
            :aria-label="action.label"
            :disabled="!!pendingStatus"
            class="rounded-md p-1.5 transition duration-200 hover:bg-gray-100 active:!bg-gray-200 disabled:cursor-wait"
            :class="[action.class, pendingStatus === action.status && '!bg-gray-100']"
            @click.stop="run(action)">
            <FontAwesomeIcon :icon="pendingStatus === action.status ? 'fal fa-spinner' : action.icon" :spin="pendingStatus === action.status" fixed-width />
        </button>

        <Dialog
            v-model:visible="cancelNoteOpen"
            modal
            dismissableMask
            :header="ctrans(`Why can't :reference be done?`, { reference: task.reference })"
            :style="{ width: '28rem' }"
            :breakpoints="{ '640px': '95vw' }">
            <form class="space-y-3" @submit.prevent="confirmCancel">
                <Textarea v-model="cancelNote" rows="3" maxlength="1000" autofocus autoResize fluid />
                <div class="flex justify-end gap-x-2">
                    <Button type="button" text severity="secondary" :label="ctrans('Back')" @click="cancelNoteOpen = false" />
                    <Button type="submit" severity="danger" :label="ctrans('Confirm')" :disabled="!cancelNote.trim()" />
                </div>
            </form>
        </Dialog>
    </div>
</template>
