<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, nextTick, ref, watch } from "vue"
import axios from "axios"
import { format } from "date-fns"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import Button from "primevue/button"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"
import StaffTaskPeoplePicker from "@/Components/Tasks/StaffTaskPeoplePicker.vue"
import VerticalScrollFade from "@/Components/Utils/VerticalScrollFade.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faTimes } from "@fal"
import type { StaffCoworker } from "@/Stores/staff-messaging"
library.add(faPlus, faTimes)

type Person = Pick<StaffCoworker, "id" | "name" | "avatar">

const props = defineProps<{
    isOpen: boolean
    subject?: string
    sourceMessageId?: number | null
    modelType?: string | null
    modelId?: number | null
    storeUrl?: string
    ticketProjectId?: number | null
    ticketProjectMilestoneId?: number | null
}>()

const emit = defineEmits<{
    close: []
    created: [task: any]
}>()

const layout: any = inject("layout", {})
const departments = ref<{ value: string; label: string }[]>([])
const priorities = ref<{ value: string; label: string }[]>([])
const people = ref<Person[]>([])
const isLoadingPeople = ref(false)

const emptyForm = () => ({
    subject: props.subject ?? "",
    description: "",
    department: null as string | null,
    assignee_id: null as number | null,
    collaborator_ids: [] as number[],
    subtasks: [] as string[],
    priority: "normal",
    due_at: null as Date | null,
    images: [] as File[],
})

const form = ref(emptyForm())
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const imageError = computed(() => Object.entries(errors.value).find(([field]) => field === "description" || field.startsWith("images"))?.[1]?.[0])

const me = computed<Person | null>(() => layout.user?.id
    ? { id: layout.user.id, name: layout.user.nickname || layout.user.contact_name || layout.user.username, avatar: null }
    : null)

const assigneeOptions = computed<Person[]>(() => me.value ? [me.value, ...people.value] : people.value)

const loadOptions = async () => {
    if (departments.value.length) return
    const { data } = await axios.get(route("grp.tasks.options"))
    departments.value = data.departments
    priorities.value = data.priorities
}

const loadPeople = async () => {
    isLoadingPeople.value = true
    try {
        const params = props.modelType && props.modelId ? { model_type: props.modelType, model_id: props.modelId } : {}
        const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params })
        people.value = data.data
    } finally {
        isLoadingPeople.value = false
    }
}

const newSubtaskTitle = ref("")
const subtaskScroller = ref<InstanceType<typeof VerticalScrollFade> | null>(null)

watch(() => form.value.subtasks.length, async (count, previousCount) => {
    await nextTick()
    if (count > (previousCount ?? 0)) subtaskScroller.value?.scrollToBottom()
})

watch(() => props.isOpen, (open) => {
    if (!open) return
    form.value = emptyForm()
    errors.value = {}
    newSubtaskTitle.value = ""
    loadOptions()
    loadPeople()
}, { immediate: true })

watch(() => form.value.assignee_id, (assigneeId) => {
    if (!assigneeId) return
    form.value.department = null
    form.value.collaborator_ids = form.value.collaborator_ids.filter((id) => id !== assigneeId)
})

watch(() => form.value.department, (department) => {
    if (department) form.value.assignee_id = null
})

const pickMe = () => {
    if (me.value) form.value.assignee_id = me.value.id
}

const addSubtask = () => {
    const title = newSubtaskTitle.value.trim()
    if (!title) return
    form.value.subtasks.push(title)
    newSubtaskTitle.value = ""
}

const removeSubtask = (index: number) => form.value.subtasks.splice(index, 1)

const onVisibleChange = (visible: boolean) => {
    if (!visible) emit("close")
}

const submit = async () => {
    addSubtask()
    saving.value = true
    errors.value = {}
    try {
        const { data } = await axios.postForm(props.storeUrl ?? route("grp.tasks.store"), {
            subject: form.value.subject,
            description: form.value.description || null,
            department: form.value.assignee_id ? null : form.value.department,
            assignee_id: form.value.assignee_id,
            collaborator_ids: form.value.collaborator_ids,
            subtasks: form.value.subtasks.map((title) => ({ title, status: "todo" })),
            priority: form.value.priority,
            due_at: form.value.due_at ? format(form.value.due_at, "yyyy-MM-dd") : null,
            model_type: props.modelType ?? null,
            model_id: props.modelId ?? null,
            source_message_id: props.sourceMessageId ?? null,
            ...(props.ticketProjectId ? { ticket_project_id: props.ticketProjectId } : {}),
            ...(props.ticketProjectMilestoneId ? { ticket_project_milestone_id: props.ticketProjectMilestoneId } : {}),
            images: form.value.images,
        })
        emit("close")
        emit("created", data.data)
        notify({ title: ctrans("Task raised"), text: data.data.reference, type: "success" })
    } catch (error: any) {
        errors.value = error.response?.data?.errors ?? {}
        if (!Object.keys(errors.value).length) {
            notify({ title: ctrans("Something went wrong"), type: "error" })
        }
    } finally {
        saving.value = false
    }
}

const labelClass = "block text-sm font-semibold text-gray-700 mb-1"
</script>

<template>
    <Dialog
        :visible="isOpen"
        modal
        dismissableMask
        :header="ctrans('New task')"
        :style="{ width: '60rem' }"
        :breakpoints="{ '1024px': '90vw', '640px': '95vw' }"
        :pt="{ header: { class: '!pb-2' } }"
        @update:visible="onVisibleChange">
        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-3">
                <div class="space-y-3 pb-5 md:col-span-2 md:pb-0 md:pr-6">
                    <div>
                        <InputText
                            v-model="form.subject"
                            maxlength="255"
                            autofocus
                            fluid
                            :invalid="!!errors.subject"
                            :placeholder="ctrans('What needs doing?')" />
                        <small v-if="errors.subject" class="block text-red-600 mt-1">{{ errors.subject[0] }}</small>
                    </div>

                    <div>
                        <TicketComposer
                            v-model:body="form.description"
                            v-model:images="form.images"
                            :rows="6"
                            :placeholder="ctrans('Details (optional). Paste a screenshot or drop files here.')" />
                        <small v-if="imageError" class="mt-1 block text-red-600">{{ imageError }}</small>
                    </div>

                    <div class="overflow-hidden rounded-md border border-gray-300">
                        <div class="flex items-center justify-between bg-gray-50 px-3 py-2">
                            <span class="text-sm font-semibold text-gray-700">{{ ctrans("Sub tasks") }}</span>
                            <span v-if="form.subtasks.length" class="rounded-full bg-gray-200 px-2 text-xs font-medium tabular-nums text-gray-600">{{ form.subtasks.length }}</span>
                        </div>

                        <VerticalScrollFade v-if="form.subtasks.length" ref="subtaskScroller" max-height-class="max-h-[7.5rem]" class="border-t border-gray-200">
                            <ol class="divide-y divide-gray-100">
                                <li v-for="(subtask, index) in form.subtasks" :key="index" class="group flex h-10 items-center gap-3 px-3 text-sm">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 text-[10px] font-medium tabular-nums text-gray-500">{{ index + 1 }}</span>
                                    <span class="min-w-0 flex-1 truncate text-gray-800" :title="subtask">{{ subtask }}</span>
                                    <button
                                        type="button"
                                        v-tooltip="ctrans('Remove')"
                                        class="shrink-0 rounded p-1 text-gray-300 opacity-0 transition duration-200 hover:text-red-500 focus:opacity-100 group-hover:opacity-100"
                                        @click="removeSubtask(index)">
                                        <FontAwesomeIcon icon="fal fa-times" fixed-width />
                                    </button>
                                </li>
                            </ol>
                        </VerticalScrollFade>

                        <div class="flex items-center gap-2 border-t border-gray-200 px-3">
                            <FontAwesomeIcon icon="fal fa-plus" class="text-gray-400" fixed-width />
                            <InputText
                                v-model="newSubtaskTitle"
                                maxlength="255"
                                fluid
                                class="!rounded-none !border-0 !bg-transparent !px-0 !shadow-none"
                                :placeholder="ctrans('Add a sub task and press Enter')"
                                @keydown.enter.prevent="addSubtask" />
                            <button
                                v-if="newSubtaskTitle.trim()"
                                type="button"
                                class="shrink-0 rounded px-2 py-1 text-xs font-medium text-[--app-accent-strong] transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                                @click="addSubtask">
                                {{ ctrans("Add") }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 border-t border-gray-200 pt-5 md:border-l md:border-t-0 md:pl-6 md:pt-0">
                    <div>
                        <div class="space-y-4">
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <label for="staff-task-assignee" class="text-sm font-semibold text-gray-700">{{ ctrans('Ask a person') }}</label>
                                    <Button v-if="me" type="button" link size="small" class="!p-0 !text-xs" :label="ctrans('For me')" @click="pickMe" />
                                </div>
                                <Select
                                    v-model="form.assignee_id"
                                    input-id="staff-task-assignee"
                                    :options="assigneeOptions"
                                    optionLabel="name"
                                    optionValue="id"
                                    filter
                                    resetFilterOnHide
                                    showClear
                                    fluid
                                    :loading="isLoadingPeople"
                                    :invalid="!!(errors.assignee_id || errors.department)"
                                    :placeholder="ctrans('Search colleague…')"
                                    :filterPlaceholder="ctrans('Search colleague…')"
                                    :emptyMessage="ctrans('No colleague can see this')"
                                    :emptyFilterMessage="ctrans('No colleague found')">
                                    <template #option="{ option }">
                                        <div class="flex min-w-0 items-center gap-x-2">
                                            <TicketUserAvatar :name="option.name" :avatar="option.avatar" size="sm" />
                                            <span class="truncate">{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Select>
                            </div>
                            <div>
                                <label for="staff-task-department" :class="labelClass">{{ ctrans('or a department') }}</label>
                                <Select
                                    v-model="form.department"
                                    input-id="staff-task-department"
                                    :options="departments"
                                    optionLabel="label"
                                    optionValue="value"
                                    showClear
                                    fluid
                                    :invalid="!!(errors.assignee_id || errors.department)"
                                    placeholder="—" />
                            </div>
                        </div>
                        <small v-if="errors.assignee_id || errors.department" class="mt-1 block text-red-600">{{ ctrans('Pick a person or a department') }}</small>
                    </div>

                    <div>
                        <span :class="labelClass">{{ ctrans('Working on it too') }}</span>
                        <StaffTaskPeoplePicker
                            v-model="form.collaborator_ids"
                            :options="people"
                            :excludeIds="form.assignee_id ? [form.assignee_id] : []" />
                        <small v-if="errors.collaborator_ids" class="mt-1 block text-red-600">{{ errors.collaborator_ids[0] }}</small>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="staff-task-due" :class="labelClass">{{ ctrans('Due') }}</label>
                            <DatePicker
                                v-model="form.due_at"
                                input-id="staff-task-due"
                                dateFormat="dd/mm/yy"
                                showIcon
                                iconDisplay="input"
                                showButtonBar
                                fluid
                                :invalid="!!errors.due_at"
                                placeholder="dd/mm/yyyy" />
                        </div>
                        <div>
                            <label for="staff-task-priority" :class="labelClass">{{ ctrans('Priority') }}</label>
                            <Select
                                v-model="form.priority"
                                input-id="staff-task-priority"
                                :options="priorities"
                                optionLabel="label"
                                optionValue="value"
                                fluid />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-x-2 pt-2">
                <Button type="button" text severity="secondary" :label="ctrans('Cancel')" @click="emit('close')" />
                <Button type="submit" :label="ctrans('Raise task')" :loading="saving" :disabled="saving || !form.subject.trim()" />
            </div>
        </form>
    </Dialog>
</template>
