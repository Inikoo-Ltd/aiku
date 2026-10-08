<script setup lang="ts">
import { inject, onMounted, provide, ref } from "vue"
import axios from "axios"
import { cloneDeep, set } from "lodash-es"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ctrans } from "@/Composables/useTrans"
import { getWebsiteDialogComponent } from "@/Composables/useWebsiteDialog"
import type { WebsiteDialogData } from "@/types/WebsiteDialog"

library.add(faCheckCircle)

interface WebsiteDialogTemplate {
    id: number
    code: string
    name: string
    component: string
    fields: Record<string, any>
    container_properties: Record<string, any>
}

const emits = defineEmits<{
    (e: "afterSubmit"): void
}>()

const dialogData = inject<WebsiteDialogData>("websiteDialogData")
provide("openFieldWorkshop", ref(null))

const templates = ref<WebsiteDialogTemplate[]>([])
const isLoading = ref(true)
const keepContent = ref(!!dialogData?.component)

const fetchTemplates = async () => {
    try {
        const response = await axios.get(route("grp.json.website_dialog_templates.index"))
        templates.value = response.data.data
    } catch {
        notify({
            title: ctrans("Something went wrong."),
            text: ctrans("Failed to fetch the dialog templates."),
            type: "error"
        })
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchTemplates)

const applyTemplate = (selectedTemplate: WebsiteDialogTemplate) => {
    if (!dialogData) {
        return
    }

    const template = cloneDeep(selectedTemplate)
    const hasContent = keepContent.value && dialogData.fields && Object.keys(dialogData.fields).length

    dialogData.template_code = template.code
    dialogData.component = template.component
    dialogData.container_properties = template.container_properties
    dialogData.fields = hasContent ? { ...template.fields, ...dialogData.fields } : template.fields

    if (template.component === "dialog-subscribe") {
        set(dialogData, "settings.target_users.auth_state", "logged_out")
    }

    emits("afterSubmit")
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-y-2">
        <label v-if="dialogData?.component" class="flex shrink-0 cursor-pointer select-none items-center gap-x-2 rounded border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs text-slate-600">
            <input v-model="keepContent" type="checkbox" class="h-3.5 w-3.5 rounded border-gray-300 text-[var(--theme-color-0)] accent-[var(--theme-color-0)] focus:ring-[var(--theme-color-0)]" />
            {{ ctrans("Keep my texts, image and button") }}
        </label>

        <div class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-0.5">
            <template v-if="isLoading">
                <div v-for="index in 3" :key="index" class="skeleton h-40 rounded-md" />
            </template>

            <button
                v-for="template in templates"
                v-else
                :key="template.code"
                type="button"
                class="group w-full overflow-hidden rounded-md border text-left transition-colors"
                :class="template.code === dialogData?.template_code ? 'border-[var(--theme-color-0)] ring-1 ring-[var(--theme-color-0)]' : 'border-slate-200 hover:border-slate-400'"
                @click="applyTemplate(template)"
            >
                <div class="pointer-events-none flex h-36 items-center justify-center overflow-hidden bg-slate-700/80">
                    <div class="origin-center scale-[0.32]">
                        <component :is="getWebsiteDialogComponent(template.component)" :dialogData="template" />
                    </div>
                </div>
                <div class="flex items-center justify-between bg-white px-2 py-1.5 text-xs font-medium text-slate-700">
                    {{ template.name }}
                    <FontAwesomeIcon v-if="template.code === dialogData?.template_code" icon="fas fa-check-circle" class="text-[var(--theme-color-0)]" fixed-width aria-hidden="true" />
                </div>
            </button>

            <div v-if="!isLoading && !templates.length" class="py-6 text-center text-xs italic text-slate-400">
                {{ ctrans("No template available") }}
            </div>
        </div>
    </div>
</template>
