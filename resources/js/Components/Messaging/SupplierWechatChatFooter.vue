<!--
  - Author: Artha <artha@aw-advantage.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import Select from "primevue/select"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { useStaffMessaging, type StaffConversation } from "@/Stores/staff-messaging"

const props = defineProps<{
    conversation: StaffConversation
    fullScreen?: boolean
}>()

const store = useStaffMessaging()
const isSaving = ref(false)

const projectOptions = computed(() => store.supplierWechatProjects)
const milestoneOptions = computed(() => projectOptions.value.find((project) => project.id === props.conversation.ticket_project_id)?.milestones ?? [])

const selectPt = {
    root: { class: "!w-full !text-xs focus-within:!border-[--app-accent] focus-within:!ring-1 focus-within:!ring-[--app-accent]" },
    label: { class: "!py-1 !px-2 !text-xs" },
    option: { class: "!text-xs" },
}

const save = async (projectId: number | null, milestoneId: number | null) => {
    isSaving.value = true
    try {
        await store.setSupplierWechatProject(props.conversation.ulid, projectId, milestoneId)
    } catch (error: any) {
        notify({
            title: ctrans("Project not changed"),
            text: error?.response?.status === 403 ? ctrans("Only procurement staff who can edit can file supplier chats under a project.") : ctrans("Please try again."),
            type: "error",
        })
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="shrink-0 space-y-2 border-t border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500" :style="fullScreen ? { paddingBottom: 'calc(0.5rem + env(safe-area-inset-bottom))' } : {}">
        <div class="flex items-center gap-1.5">
            <FontAwesomeIcon icon="fab fa-weixin" class="text-green-600" fixed-width aria-hidden="true" />
            <span>{{ ctrans("Read only: this chat happens in WeCom, copied here as it is archived.") }}</span>
        </div>
        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
            <Select
                :model-value="conversation.ticket_project_id ?? null"
                :options="projectOptions"
                option-label="name"
                option-value="id"
                :placeholder="ctrans('File under a project')"
                :aria-label="ctrans('Project')"
                :disabled="isSaving"
                :loading="isSaving"
                filter
                show-clear
                :pt="selectPt"
                @update:model-value="(projectId: number | null) => save(projectId ?? null, null)" />
            <Select
                v-if="conversation.ticket_project_id && milestoneOptions.length"
                :model-value="conversation.ticket_project_milestone_id ?? null"
                :options="milestoneOptions"
                option-label="name"
                option-value="id"
                :placeholder="ctrans('Milestone')"
                :aria-label="ctrans('Milestone')"
                :disabled="isSaving"
                show-clear
                :pt="selectPt"
                @update:model-value="(milestoneId: number | null) => save(conversation.ticket_project_id ?? null, milestoneId ?? null)" />
        </div>
    </div>
</template>
