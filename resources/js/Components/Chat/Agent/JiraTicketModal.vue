<script setup lang="ts">
import { ref, watch, computed } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faCheckCircle,
    faExternalLink,
    faImage,
    faFile,
    faXmark,
    faAnglesUp,
    faAngleUp,
    faEquals,
    faAngleDown,
    faAnglesDown,
    faTag,
} from "@fortawesome/free-solid-svg-icons"
import { faJira } from "@fortawesome/free-brands-svg-icons"
import { notify } from "@kyvg/vue3-notification"
import { Select, MultiSelect, InputText, Textarea, Message } from "primevue"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import type { SessionAPI } from "@/types/Chat/chat"

const props = defineProps<{
    isOpen: boolean
    session: SessionAPI | null
    organisation: string
}>()

const emit = defineEmits(["close", "created"])

interface JiraProject {
    id: string
    key: string
    name: string
}

interface JiraIssueType {
    id: string
    name: string
    description: string | null
    subtask: boolean
}

interface JiraPriority {
    id: string
    name: string
}

interface CreatedTicket {
    id: string
    key: string
    url: string | null
    summary: string
    issue_type: string
    project_key: string
    priority: string | null
    priority_name: string | null
    labels: string[]
    reference_url: string | null
    has_attachment: boolean
    attachment_count: number
}

const PRIORITY_ICONS: Record<string, { icon: any; color: string }> = {
    highest: { icon: faAnglesUp, color: "text-red-500" },
    high: { icon: faAngleUp, color: "text-red-400" },
    medium: { icon: faEquals, color: "text-orange-400" },
    low: { icon: faAngleDown, color: "text-blue-400" },
    lowest: { icon: faAnglesDown, color: "text-blue-500" },
}

const priorityVisual = (name: string | undefined) =>
    PRIORITY_ICONS[String(name ?? "").toLowerCase()] ?? { icon: faEquals, color: "text-gray-400" }

const isLoadingProjects = ref(false)
const isLoadingIssueTypes = ref(false)
const isLoadingPriorities = ref(false)
const isLoadingLabels = ref(false)
const isSubmitting = ref(false)
const jiraConfigured = ref(true)

const projects = ref<JiraProject[]>([])
const issueTypes = ref<JiraIssueType[]>([])
const priorities = ref<JiraPriority[]>([])
const labels = ref<string[]>([])

const form = ref({
    project_key: "",
    issue_type: "",
    summary: "",
    description: "",
    priority: "",
    labels: [] as string[],
    reference_url: "",
})

const MAX_FILE_SIZE = 10 * 1024 * 1024
const MAX_FILES = 5

interface AttachmentItem {
    file: File
    previewUrl: string | null
}

const fileInput = ref<HTMLInputElement>()
const attachments = ref<AttachmentItem[]>([])

const createdTicket = ref<CreatedTicket | null>(null)

const sessionName = computed(
    () => props.session?.contact_name || props.session?.guest_identifier || trans("chat session")
)

const canSubmit = computed(
    () => !!form.value.project_key && !!form.value.issue_type && form.value.summary.trim().length > 0
)

const projectOptions = computed(() =>
    projects.value.map((project) => ({
        label: `${project.name} (${project.key})`,
        value: project.key,
    }))
)

const issueTypeOptions = computed(() =>
    issueTypes.value.map((issueType) => ({
        label: issueType.name,
        value: issueType.name,
    }))
)

const labelOptions = computed(() => labels.value.map((label) => ({ label, value: label })))

const baseRoute = (name: string, extra: any[] = []) =>
    route(`grp.org.chat.agents.sessions.jira.${name}`, [props.organisation, props.session?.ulid, ...extra])

const clearAttachments = () => {
    attachments.value.forEach((item) => {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl)
    })
    attachments.value = []
    if (fileInput.value) fileInput.value.value = ""
}

const removeAttachment = (index: number) => {
    const [removed] = attachments.value.splice(index, 1)
    if (removed?.previewUrl) URL.revokeObjectURL(removed.previewUrl)
    if (fileInput.value) fileInput.value.value = ""
}

const handleFileSelect = (e: Event) => {
    const files = Array.from((e.target as HTMLInputElement)?.files ?? [])
    if (!files.length) return

    for (const file of files) {
        if (attachments.value.length >= MAX_FILES) {
            notify({ title: trans("Error"), text: trans("You can attach up to 5 files"), type: "error" })
            break
        }
        if (file.size > MAX_FILE_SIZE) {
            notify({ title: trans("Error"), text: trans(":name exceeds 10MB", { name: file.name }), type: "error" })
            continue
        }
        attachments.value.push({
            file,
            previewUrl: file.type.startsWith("image/") ? URL.createObjectURL(file) : null,
        })
    }

    if (fileInput.value) fileInput.value.value = ""
}

const resetForm = () => {
    createdTicket.value = null
    issueTypes.value = []
    clearAttachments()
    form.value = {
        project_key: "",
        issue_type: "",
        summary: "",
        description: props.session?.ai_summary?.summary ?? "",
        priority: "",
        labels: [],
        reference_url: "",
    }
}

const fetchProjects = async () => {
    if (!props.session?.ulid) return
    isLoadingProjects.value = true
    try {
        const { data } = await axios.get(baseRoute("projects"), { withCredentials: true })
        jiraConfigured.value = data?.configured !== false
        projects.value = data?.data ?? []
        if (projects.value.length === 1) {
            form.value.project_key = projects.value[0].key
        }
    } catch {
        notify({ title: trans("Error"), text: trans("Failed to load Jira projects"), type: "error" })
    } finally {
        isLoadingProjects.value = false
    }
}

const fetchIssueTypes = async (projectKey: string) => {
    if (!projectKey || !props.session?.ulid) return
    isLoadingIssueTypes.value = true
    form.value.issue_type = ""
    try {
        const { data } = await axios.get(baseRoute("issue_types", [projectKey]), { withCredentials: true })
        issueTypes.value = data?.data ?? []
        const preferred = issueTypes.value.find((t) => t.name === "Task") ?? issueTypes.value[0]
        if (preferred) {
            form.value.issue_type = preferred.name
        }
    } catch {
        notify({ title: trans("Error"), text: trans("Failed to load issue types"), type: "error" })
    } finally {
        isLoadingIssueTypes.value = false
    }
}

const fetchPriorities = async () => {
    if (!props.session?.ulid) return
    isLoadingPriorities.value = true
    try {
        const { data } = await axios.get(baseRoute("priorities"), { withCredentials: true })
        priorities.value = data?.data ?? []
        const medium = priorities.value.find((p) => p.name.toLowerCase() === "medium")
        if (medium) {
            form.value.priority = medium.id
        }
    } catch {
        notify({ title: trans("Error"), text: trans("Failed to load priorities"), type: "error" })
    } finally {
        isLoadingPriorities.value = false
    }
}

const fetchLabels = async () => {
    if (!props.session?.ulid) return
    isLoadingLabels.value = true
    try {
        const { data } = await axios.get(baseRoute("labels"), { withCredentials: true })
        labels.value = data?.data ?? []
    } catch {
        notify({ title: trans("Error"), text: trans("Failed to load labels"), type: "error" })
    } finally {
        isLoadingLabels.value = false
    }
}

const submit = async () => {
    if (!canSubmit.value || isSubmitting.value || !props.session?.ulid) return
    isSubmitting.value = true
    try {
        const payload = new FormData()
        payload.append("project_key", form.value.project_key)
        payload.append("issue_type", form.value.issue_type)
        payload.append("summary", form.value.summary.trim())
        payload.append("description", form.value.description.trim())
        if (form.value.priority) {
            payload.append("priority", form.value.priority)
            const priorityName = priorities.value.find((p) => p.id === form.value.priority)?.name
            if (priorityName) {
                payload.append("priority_name", priorityName)
            }
        }
        form.value.labels.forEach((label) => payload.append("labels[]", label))
        if (form.value.reference_url.trim()) {
            payload.append("reference_url", form.value.reference_url.trim())
        }
        attachments.value.forEach((item) => payload.append("attachments[]", item.file))

        const { data } = await axios.post(baseRoute("ticket"), payload, {
            withCredentials: true,
            headers: { "Content-Type": "multipart/form-data" },
        })
        createdTicket.value = data?.data ?? null
        emit("created", createdTicket.value)
        notify({
            title: trans("Jira ticket created"),
            text: createdTicket.value?.key ?? "",
            type: "success",
        })
    } catch (error: any) {
        notify({
            title: trans("Error"),
            text: error?.response?.data?.message ?? trans("Failed to create Jira ticket"),
            type: "error",
        })
    } finally {
        isSubmitting.value = false
    }
}

const close = () => emit("close")

watch(
    () => form.value.project_key,
    (projectKey, previous) => {
        if (projectKey && projectKey !== previous) {
            fetchIssueTypes(projectKey)
        }
    }
)

watch(
    () => props.isOpen,
    (open) => {
        if (open) {
            resetForm()
            fetchProjects()
            fetchPriorities()
            fetchLabels()
        }
    }
)
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="close" width="w-full max-w-lg">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                <FontAwesomeIcon :icon="faJira" class="text-blue-600" />
            </div>
            <div>
                <h2 class="text-base font-semibold text-gray-800">{{ trans("Create Jira Ticket") }}</h2>
                <p class="text-xs text-gray-400">{{ sessionName }}</p>
            </div>
        </div>

        <Message v-if="!jiraConfigured" severity="warn" :closable="false" class="mb-2">
            <div class="flex flex-col">
                <span class="text-sm font-medium">{{ trans("Jira is not configured") }}</span>
                <span class="text-xs opacity-80">
                    {{ trans("Ask a group administrator to add Jira credentials in group settings.") }}
                </span>
            </div>
        </Message>

        <div v-else-if="createdTicket" class="flex flex-col items-center text-center py-6 px-4">
            <FontAwesomeIcon :icon="faCheckCircle" class="text-emerald-500 text-3xl mb-3" />
            <p class="text-sm font-medium text-gray-700">{{ trans("Ticket created") }}</p>
            <a
                v-if="createdTicket.url"
                :href="createdTicket.url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 mt-2 text-sm font-semibold text-blue-600 hover:text-blue-700"
            >
                {{ createdTicket.key }}
                <FontAwesomeIcon :icon="faExternalLink" class="text-xs" />
            </a>
            <span v-else class="mt-2 text-sm font-semibold text-blue-600">{{ createdTicket.key }}</span>
            <p class="text-xs text-gray-400 mt-1">{{ createdTicket.summary }}</p>

            <div class="flex flex-wrap items-center justify-center gap-1.5 mt-2">
                <span
                    v-if="createdTicket.priority_name"
                    class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded bg-gray-50 text-gray-600 border border-gray-100"
                >
                    <FontAwesomeIcon
                        :icon="priorityVisual(createdTicket.priority_name).icon"
                        :class="priorityVisual(createdTicket.priority_name).color"
                    />
                    {{ createdTicket.priority_name }}
                </span>
                <span
                    v-for="label in createdTicket.labels"
                    :key="label"
                    class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded bg-gray-50 text-gray-600 border border-gray-100"
                >
                    <FontAwesomeIcon :icon="faTag" class="text-gray-400" />
                    {{ label }}
                </span>
            </div>

            <div v-if="createdTicket.reference_url || createdTicket.has_attachment" class="flex items-center gap-2 mt-2">
                <span
                    v-if="createdTicket.reference_url"
                    class="px-2 py-0.5 text-[10px] font-medium rounded bg-gray-50 text-gray-600 border border-gray-100"
                >
                    {{ trans("URL attached") }}
                </span>
                <span
                    v-if="createdTicket.has_attachment"
                    class="px-2 py-0.5 text-[10px] font-medium rounded bg-gray-50 text-gray-600 border border-gray-100"
                >
                    {{ trans(":count file(s) attached", { count: createdTicket.attachment_count }) }}
                </span>
            </div>

            <div class="flex items-center gap-2 mt-6">
                <Button type="tertiary" size="sm" :label="trans('Create another')" @click="resetForm" />
                <Button size="sm" :label="trans('Done')" @click="close" />
            </div>
        </div>

        <div v-else class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ trans("Project") }}</label>
                <Select
                    v-model="form.project_key"
                    :options="projectOptions"
                    optionLabel="label"
                    optionValue="value"
                    :loading="isLoadingProjects"
                    :placeholder="trans('Select a project')"
                    filter
                    class="w-full"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ trans("Issue type") }}</label>
                <Select
                    v-model="form.issue_type"
                    :options="issueTypeOptions"
                    optionLabel="label"
                    optionValue="value"
                    :loading="isLoadingIssueTypes"
                    :disabled="!form.project_key"
                    :placeholder="trans('Select an issue type')"
                    class="w-full"
                />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ trans("Priority") }}</label>
                    <Select
                        v-model="form.priority"
                        :options="priorities"
                        optionLabel="name"
                        optionValue="id"
                        :loading="isLoadingPriorities"
                        :placeholder="trans('Select priority')"
                        class="w-full"
                    >
                        <template #value="slotProps">
                            <span v-if="slotProps.value" class="flex items-center gap-2">
                                <FontAwesomeIcon
                                    :icon="priorityVisual(priorities.find((p) => p.id === slotProps.value)?.name).icon"
                                    :class="priorityVisual(priorities.find((p) => p.id === slotProps.value)?.name).color"
                                />
                                {{ priorities.find((p) => p.id === slotProps.value)?.name }}
                            </span>
                            <span v-else>{{ slotProps.placeholder }}</span>
                        </template>
                        <template #option="slotProps">
                            <span class="flex items-center gap-2">
                                <FontAwesomeIcon
                                    :icon="priorityVisual(slotProps.option.name).icon"
                                    :class="priorityVisual(slotProps.option.name).color"
                                />
                                {{ slotProps.option.name }}
                            </span>
                        </template>
                    </Select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ trans("Labels") }}</label>
                    <MultiSelect
                        v-model="form.labels"
                        :options="labelOptions"
                        optionLabel="label"
                        optionValue="value"
                        :loading="isLoadingLabels"
                        filter
                        :maxSelectedLabels="2"
                        :placeholder="trans('Select labels')"
                        class="w-full"
                    >
                        <template #option="slotProps">
                            <span class="flex items-center gap-2">
                                <FontAwesomeIcon :icon="faTag" class="text-gray-400 text-xs" />
                                {{ slotProps.option.label }}
                            </span>
                        </template>
                    </MultiSelect>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ trans("Summary") }}</label>
                <InputText
                    v-model="form.summary"
                    maxlength="255"
                    :placeholder="trans('Short summary of the issue')"
                    class="w-full"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ trans("Description") }}</label>
                <Textarea
                    v-model="form.description"
                    rows="4"
                    autoResize
                    :placeholder="trans('Add context for the developer…')"
                    class="w-full"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ trans("Reference URL") }}</label>
                <InputText
                    v-model="form.reference_url"
                    type="url"
                    :placeholder="trans('https://…')"
                    class="w-full"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">
                    {{ trans("Attachments") }}
                    <span class="text-gray-300">({{ attachments.length }}/{{ MAX_FILES }})</span>
                </label>
                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    accept=".webp,.jpg,.jpeg,.png,.avif,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt"
                    class="hidden"
                    @change="handleFileSelect"
                />

                <div v-if="attachments.length" class="flex flex-wrap gap-2 mb-2">
                    <div
                        v-for="(item, index) in attachments"
                        :key="index"
                        class="relative"
                    >
                        <img
                            v-if="item.previewUrl"
                            :src="item.previewUrl"
                            class="h-16 w-16 rounded-lg border object-cover"
                        />
                        <div
                            v-else
                            class="h-16 w-16 rounded-lg border bg-gray-50 flex flex-col items-center justify-center px-1"
                        >
                            <FontAwesomeIcon :icon="faFile" class="text-gray-400" />
                            <span class="mt-1 text-[9px] text-gray-500 truncate w-full text-center">
                                {{ item.file.name }}
                            </span>
                        </div>
                        <button
                            type="button"
                            @click="removeAttachment(index)"
                            class="absolute -top-2 -right-2 bg-white rounded-full shadow p-1 text-gray-500 hover:text-red-500"
                        >
                            <FontAwesomeIcon :icon="faXmark" class="text-[10px]" />
                        </button>
                    </div>
                </div>

                <button
                    v-if="attachments.length < MAX_FILES"
                    type="button"
                    @click="fileInput?.click()"
                    class="flex items-center gap-2 text-sm text-gray-500 border border-dashed border-gray-300 rounded-lg px-3 py-2 w-full hover:border-blue-400 hover:text-blue-600 transition-colors"
                >
                    <FontAwesomeIcon :icon="faImage" />
                    {{ trans("Attach files") }}
                </button>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <Button type="tertiary" size="sm" :label="trans('Cancel')" @click="close" />
                <Button
                    size="sm"
                    :disabled="!canSubmit"
                    :loading="isSubmitting"
                    :label="trans('Create ticket')"
                    :icon="faJira"
                    @click="submit"
                />
            </div>
        </div>
    </Modal>
</template>
