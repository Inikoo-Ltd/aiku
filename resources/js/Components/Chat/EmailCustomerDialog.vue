<script setup lang="ts">
import { computed, ref, toRef, watch } from "vue"
import { router, useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ChatFormattingToolbar from "@/Components/Chat/ChatFormattingToolbar.vue"
import ChatMessageEditor from "@/Components/Chat/ChatMessageEditor.vue"
import PolishMessageButton from "@/Components/Chat/PolishMessageButton.vue"
import EditorWritingQualityCheck from "@/Components/WritingQuality/EditorWritingQualityCheck.vue"
import EmailAttachmentPicker from "@/Components/Chat/EmailAttachmentPicker.vue"
import { ctrans } from "@/Composables/useTrans"
import { useComposerDraft } from "@/Composables/useComposerDraft"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperPlane, faExclamationTriangle, faSave, faTimes } from "@fal"

library.add(faPaperPlane, faExclamationTriangle, faSave, faTimes)

export interface OutOfStockLine {
    code: string
    name: string
    quantity_ordered: number
    quantity_short: number
}

export interface CustomerEmailTemplate {
    id: number
    name: string
    subject: string
    body: string
    update_route: routeType
}

const visible = defineModel<boolean>("visible", { required: true })

const props = defineProps<{
    sendRoute: routeType
    email?: string | null
    draftKey: string
    subject?: string
    outOfStockLines?: OutOfStockLine[] | null
    templates?: CustomerEmailTemplate[]
    templateValues?: Record<string, string>
}>()

const noTemplate = { name: ctrans("No template"), subject: "", body: "" } as CustomerEmailTemplate
const templateOptions = computed(() => [noTemplate, ...(props.templates ?? [])])
const selectedTemplate = ref<CustomerEmailTemplate | null>(null)
const isSavingTemplate = ref(false)

const applyTemplate = (template: CustomerEmailTemplate | null) => {
    if (!template) return
    form.subject = template.subject
    form.message = template.body
}

const saveToTemplate = () => {
    const template = selectedTemplate.value
    if (!template?.update_route) return
    router.patch(route(template.update_route.name, template.update_route.parameters), {
        subject: form.subject,
        body: form.message,
    }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (isSavingTemplate.value = true),
        onFinish: () => (isSavingTemplate.value = false),
        onSuccess: () => {
            template.subject = form.subject
            template.body = form.message
        },
    })
}

const messageEditor = ref<InstanceType<typeof ChatMessageEditor> | null>(null)

const form = useForm({
    email: props.email ?? "",
    subject: "",
    message: "",
    attachments: [] as File[],
})

const clearSubjectDraft = useComposerDraft(() => `${props.draftKey}:subject`, toRef(form, "subject"))
const clearMessageDraft = useComposerDraft(() => `${props.draftKey}:message`, toRef(form, "message"))

watch(visible, (isVisible) => {
    if (isVisible && !form.subject && props.subject) {
        form.subject = props.subject
    }
})

const insertOutOfStockLines = () => {
    const lines = (props.outOfStockLines ?? []).map((line) =>
        "- " + ctrans(":code :name: :short of :ordered not available", {
            code: line.code,
            name: line.name,
            short: String(line.quantity_short),
            ordered: String(line.quantity_ordered),
        })
    )
    form.message = [form.message.trimEnd(), ...lines].filter(Boolean).join("\n")
}

const send = () => {
    form.post(route(props.sendRoute.name, props.sendRoute.parameters), {
        preserveScroll: true,
        onSuccess: () => {
            clearSubjectDraft()
            clearMessageDraft()
            form.reset("subject", "message", "attachments")
            visible.value = false
        },
    })
}
</script>

<template>
    <Dialog v-model:visible="visible" :modal="false" position="right" draggable :header="ctrans('Email customer')"
        :style="{ width: '90vw', maxWidth: '720px' }" :breakpoints="{ '640px': '95vw' }">
        <div class="flex flex-col gap-3">
            <p class="text-xs text-gray-500 leading-snug">
                {{ ctrans("Their reply comes back to the chat inbox.") }}
            </p>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("To") }}</label>
                <InputText v-model="form.email" :placeholder="ctrans('Email addresses, separated by commas')" fluid />
            </div>
            <div v-if="templates?.length" class="flex items-center gap-2">
                <Select v-model="selectedTemplate" :options="templateOptions" option-label="name" :placeholder="ctrans('Choose a template')"
                    class="flex-1" @update:model-value="applyTemplate" />
                <Button v-if="selectedTemplate?.update_route" type="tertiary" size="xs" icon="fal fa-save" :label="ctrans('Save to template')"
                    :loading="isSavingTemplate" :disabled="!form.subject.trim() || !form.message.trim()" @click="saveToTemplate" />
            </div>
            <InputText v-model="form.subject" :placeholder="ctrans('Subject')" fluid />
            <div>
                <div class="mb-1 flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-1">
                        <ChatFormattingToolbar :editor="messageEditor?.editor" allow-underline />
                        <div class="mx-1 h-5 w-px bg-gray-200" />
                        <PolishMessageButton v-model="form.message" channel="email" />
                        <EditorWritingQualityCheck compact :editor="messageEditor?.editor" context="customer_email" />
                    </div>
                    <Button v-if="outOfStockLines?.length" type="tertiary" size="xs" icon="fal fa-exclamation-triangle"
                        :label="ctrans('Insert out-of-stock lines (:count)', { count: String(outOfStockLines.length) })"
                        @click="insertOutOfStockLines" />
                </div>
                <ChatMessageEditor ref="messageEditor" v-model="form.message" :placeholder="ctrans('Message')" allow-underline
                    class="rounded-md border border-gray-300 px-3 py-2 focus-within:border-[--app-accent] [&_.ProseMirror]:min-h-64 [&_.ProseMirror]:max-h-[60vh]" />
            </div>
            <div v-if="templates?.length && templateValues" class="text-xs text-gray-500 leading-snug">
                <p>{{ ctrans("These are filled in when the email is sent:") }}</p>
                <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5">
                    <template v-for="(value, placeholder) in templateValues" :key="placeholder">
                        <dt class="font-mono text-gray-600">{{ placeholder }}</dt>
                        <dd class="truncate" :title="value">{{ value.split("\n")[0] || "—" }}</dd>
                    </template>
                </dl>
            </div>
            <EmailAttachmentPicker v-model="form.attachments" :errors="form.errors" />

            <p v-if="form.errors.email" class="text-xs text-red-500 leading-snug">{{ form.errors.email }}</p>
            <p v-if="form.errors.subject" class="text-xs text-red-500 leading-snug">{{ form.errors.subject }}</p>
            <p v-if="form.errors.message" class="text-xs text-red-500 leading-snug">{{ form.errors.message }}</p>

            <div class="flex items-center justify-end gap-2 pt-1">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="tertiary" :label="ctrans('Send')" :loading="form.processing"
                    :disabled="!form.email.trim() || !form.subject.trim() || !form.message.trim()" @click="send">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-paper-plane" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </div>
    </Dialog>
</template>
