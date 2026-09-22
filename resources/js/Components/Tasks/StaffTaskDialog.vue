<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import Image from "@/Common/Components/Image.vue"
import StaffTaskCollaborators from "@/Components/Tasks/StaffTaskCollaborators.vue"
import type { StaffCoworker } from "@/Stores/staff-messaging"

const props = defineProps<{
    isOpen: boolean
    subject?: string
    sourceMessageId?: number | null
    modelType?: string | null
    modelId?: number | null
}>()

const emit = defineEmits<{
    close: []
    created: [task: any]
}>()

const departments = ref<{ value: string; label: string }[]>([])
const priorities = ref<{ value: string; label: string }[]>([])
const form = ref({ subject: "", description: "", department: "", assignee: null as StaffCoworker | null, collaborators: [] as any[], priority: "normal", due_at: "" })
const assigneeQuery = ref("")
const assigneeResults = ref<StaffCoworker[]>([])
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const loadOptions = async () => {
    if (departments.value.length) return
    const { data } = await axios.get(route("grp.tasks.options"))
    departments.value = data.departments
    priorities.value = data.priorities
}

watch(() => props.isOpen, (open) => {
    if (!open) return
    form.value = { subject: props.subject ?? "", description: "", department: "", assignee: null, collaborators: [], priority: "normal", due_at: "" }
    errors.value = {}
    assigneeQuery.value = ""
    assigneeResults.value = []
    loadOptions()
})

const onAssigneeInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    form.value.assignee = null
    if (assigneeQuery.value.trim().length < 2) {
        assigneeResults.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: { q: assigneeQuery.value.trim() } })
        assigneeResults.value = data.data
    }, 250)
}

const pickAssignee = (coworker: StaffCoworker) => {
    form.value.assignee = coworker
    form.value.department = ""
    assigneeQuery.value = coworker.name
    assigneeResults.value = []
}

const submit = async () => {
    saving.value = true
    errors.value = {}
    try {
        const { data } = await axios.post(route("grp.tasks.store"), {
            subject: form.value.subject,
            description: form.value.description || null,
            department: form.value.assignee ? null : form.value.department || null,
            assignee_id: form.value.assignee?.id ?? null,
            collaborator_ids: form.value.collaborators.map((person) => person.id),
            priority: form.value.priority,
            due_at: form.value.due_at || null,
            model_type: props.modelType ?? null,
            model_id: props.modelId ?? null,
            source_message_id: props.sourceMessageId ?? null,
        })
        emit("close")
        emit("created", data.data)
        notify({ title: trans("Task raised"), text: data.data.reference, type: "success" })
    } catch (error: any) {
        errors.value = error.response?.data?.errors ?? {}
        if (!Object.keys(errors.value).length) {
            notify({ title: trans("Something went wrong"), type: "error" })
        }
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-30 flex items-center justify-center bg-black/40 p-4" @click.self="emit('close')" @keydown.esc="emit('close')">
        <form class="w-full max-w-lg bg-white rounded-2xl p-6 shadow-xl text-left space-y-4" @submit.prevent="submit">
            <h3 class="text-base font-semibold text-gray-900">{{ trans('New task') }}</h3>

            <div>
                <input
                    v-model="form.subject"
                    type="text"
                    maxlength="255"
                    autofocus
                    :placeholder="trans('What needs doing?')"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]" />
                <p v-if="errors.subject" class="text-xs text-red-600 mt-1">{{ errors.subject[0] }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="relative">
                    <label class="block text-xs text-gray-500 mb-1">{{ trans('Ask a person') }}</label>
                    <input
                        v-model="assigneeQuery"
                        type="text"
                        :placeholder="trans('Search colleague…')"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]"
                        @input="onAssigneeInput" />
                    <div v-if="assigneeResults.length" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-md shadow max-h-48 overflow-y-auto">
                        <button v-for="coworker in assigneeResults" :key="coworker.id" type="button" class="w-full flex items-center gap-x-2 px-3 py-2 hover:bg-gray-50 text-left" @click="pickAssignee(coworker)">
                            <div class="h-6 w-6 rounded-full overflow-hidden bg-gray-200 shrink-0">
                                <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                            </div>
                            <span class="text-sm truncate">{{ coworker.name }}</span>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ trans('or a department') }}</label>
                    <select v-model="form.department" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]" @change="form.assignee = null; assigneeQuery = ''">
                        <option value="">—</option>
                        <option v-for="department in departments" :key="department.value" :value="department.value">{{ department.label }}</option>
                    </select>
                </div>
            </div>
            <p v-if="errors.assignee_id || errors.department" class="text-xs text-red-600 -mt-2">{{ trans('Pick a person or a department') }}</p>

            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans('Working on it too') }}</label>
                <StaffTaskCollaborators v-model="form.collaborators" :exclude-ids="form.assignee ? [form.assignee.id] : []" />
            </div>

            <textarea
                v-model="form.description"
                rows="3"
                maxlength="5000"
                :placeholder="trans('Details (optional)')"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]" />

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ trans('Due') }}</label>
                    <input v-model="form.due_at" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ trans('Priority') }}</label>
                    <select v-model="form.priority" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]">
                        <option v-for="priority in priorities" :key="priority.value" :value="priority.value">{{ priority.label }}</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-x-2 pt-2">
                <button type="button" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900" @click="emit('close')">{{ trans('Cancel') }}</button>
                <button type="submit" :disabled="saving || !form.subject.trim()" class="px-4 py-2 text-sm rounded-md bg-[--app-accent] text-[--app-accent-text] hover:bg-[--app-accent-strong] disabled:opacity-40">{{ trans('Raise task') }}</button>
            </div>
        </form>
    </div>
</template>
