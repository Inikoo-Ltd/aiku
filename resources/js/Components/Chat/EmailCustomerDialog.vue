<script setup lang="ts">
import { ref, toRef, watch } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ChatFormattingToolbar from "@/Components/Chat/ChatFormattingToolbar.vue"
import ChatMessageEditor from "@/Components/Chat/ChatMessageEditor.vue"
import EmailAttachmentPicker from "@/Components/Chat/EmailAttachmentPicker.vue"
import { ctrans } from "@/Composables/useTrans"
import { useComposerDraft } from "@/Composables/useComposerDraft"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperPlane, faExclamationTriangle } from "@fal"

library.add(faPaperPlane, faExclamationTriangle)

export interface OutOfStockLine {
    code: string
    name: string
    quantity_ordered: number
    quantity_short: number
}

const visible = defineModel<boolean>("visible", { required: true })

const props = defineProps<{
    sendRoute: routeType
    email?: string | null
    draftKey: string
    subject?: string
    outOfStockLines?: OutOfStockLine[] | null
}>()

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
        :style="{ width: '90vw', maxWidth: '560px' }" :breakpoints="{ '640px': '95vw' }">
        <div class="flex flex-col gap-3">
            <p class="text-xs text-gray-500 leading-snug">
                {{ ctrans("It opens a conversation in the chat inbox, and their reply comes back to it.") }}
            </p>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("To") }}</label>
                <InputText v-model="form.email" :placeholder="ctrans('Email addresses, separated by commas')" fluid />
            </div>
            <InputText v-model="form.subject" :placeholder="ctrans('Subject')" fluid />
            <div>
                <div class="mb-1 flex items-center justify-between gap-2">
                    <ChatFormattingToolbar :editor="messageEditor?.editor" allow-underline />
                    <Button v-if="outOfStockLines?.length" type="tertiary" size="xs" icon="fal fa-exclamation-triangle"
                        :label="ctrans('Insert out-of-stock lines (:count)', { count: String(outOfStockLines.length) })"
                        @click="insertOutOfStockLines" />
                </div>
                <ChatMessageEditor ref="messageEditor" v-model="form.message" :placeholder="ctrans('Message')" allow-underline
                    class="rounded-md border border-gray-300 px-3 py-2 focus-within:border-[--app-accent] [&_.ProseMirror]:min-h-40 [&_.ProseMirror]:max-h-80" />
            </div>
            <EmailAttachmentPicker v-model="form.attachments" :errors="form.errors" />

            <p v-if="form.errors.email" class="text-xs text-red-500 leading-snug">{{ form.errors.email }}</p>
            <p v-if="form.errors.subject" class="text-xs text-red-500 leading-snug">{{ form.errors.subject }}</p>
            <p v-if="form.errors.message" class="text-xs text-red-500 leading-snug">{{ form.errors.message }}</p>

            <div class="flex items-center justify-end gap-2 pt-1">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false" />
                <Button type="primary" icon="fal fa-paper-plane" :label="ctrans('Send')" :loading="form.processing"
                    :disabled="!form.email.trim() || !form.subject.trim() || !form.message.trim()" @click="send" />
            </div>
        </div>
    </Dialog>
</template>
