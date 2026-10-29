<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperPlane, faTimes } from "@fal"

library.add(faPaperPlane, faTimes)

const visible = defineModel<boolean>("visible", { required: true })

const props = defineProps<{
    organisation: string
    sessionUlid?: string | null
}>()

const emit = defineEmits<{
    (e: "forwarded", data: any): void
}>()

const email = ref("")
const note = ref("")
const subject = ref("")
const forwarded = ref("")
const isLoadingPreview = ref(false)
const isSending = ref(false)
const errors = ref<Record<string, string>>({})

const preview = computed(() => (note.value.trim() ? `${note.value.trim()}\n\n${forwarded.value}` : forwarded.value))

const loadPreview = async () => {
    if (!props.sessionUlid) return

    isLoadingPreview.value = true
    try {
        const { data } = await axios.get(route("grp.org.chat.agents.sessions.forward_email.preview", [props.organisation, props.sessionUlid]))
        subject.value = data?.subject ?? ""
        forwarded.value = data?.forwarded ?? ""
    } catch (e: any) {
        errors.value = { general: e?.response?.data?.message ?? ctrans("Could not load the conversation") }
    } finally {
        isLoadingPreview.value = false
    }
}

const send = async () => {
    if (!email.value.trim() || isSending.value || !props.sessionUlid) return

    isSending.value = true
    errors.value = {}
    try {
        const { data } = await axios.post(route("grp.org.chat.agents.sessions.forward_email", [props.organisation, props.sessionUlid]), {
            email: email.value,
            note: note.value,
        })
        emit("forwarded", data)
        visible.value = false
    } catch (e: any) {
        const fieldErrors = e?.response?.data?.errors ?? {}
        errors.value = Object.fromEntries(Object.entries(fieldErrors).map(([field, messages]: [string, any]) => [field, Array.isArray(messages) ? messages[0] : String(messages)]))

        if (!Object.keys(errors.value).length) {
            errors.value = { general: e?.response?.data?.message ?? ctrans("Failed to forward this conversation") }
        }
    } finally {
        isSending.value = false
    }
}

watch(visible, (isVisible) => {
    if (isVisible) {
        email.value = ""
        note.value = ""
        subject.value = ""
        forwarded.value = ""
        errors.value = {}
        loadPreview()
    }
})
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('Forward by email')" :style="{ width: '90vw', maxWidth: '640px' }"
        :breakpoints="{ '640px': '95vw' }">
        <div class="flex flex-col gap-3">
            <p class="text-xs leading-snug text-gray-500">
                {{ ctrans("Sent from the shop's mailbox as a new email. Any answer comes back to the chat inbox.") }}
            </p>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("To") }}</label>
                <InputText v-model="email" :placeholder="ctrans('Email addresses, separated by commas')" fluid />
                <p v-if="errors.email" class="text-xs leading-snug text-red-500">{{ errors.email }}</p>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("Note") }}</label>
                <Textarea v-model="note" rows="3" auto-resize :placeholder="ctrans('Optional, written above the forwarded conversation')" fluid />
                <p v-if="errors.note" class="text-xs leading-snug text-red-500">{{ errors.note }}</p>
            </div>

            <div class="flex flex-col gap-1">
                <span class="text-xs font-medium text-gray-600">{{ ctrans("Preview") }}</span>
                <div class="rounded-md border border-gray-200 bg-gray-50">
                    <div class="border-b border-gray-200 px-3 py-1.5 text-xs text-gray-600">
                        <span class="font-medium">{{ ctrans("Subject") }}:</span> {{ subject }}
                    </div>
                    <div v-if="isLoadingPreview" class="flex justify-center py-6">
                        <LoadingIcon />
                    </div>
                    <pre v-else class="max-h-72 overflow-y-auto whitespace-pre-wrap break-words px-3 py-2 font-sans text-xs leading-relaxed text-gray-700">{{ preview }}</pre>
                </div>
                <p class="text-[11px] leading-snug text-gray-400">
                    {{ ctrans("Files are not attached, only named in the forwarded text.") }}
                </p>
            </div>

            <p v-if="errors.message" class="text-xs leading-snug text-red-500">{{ errors.message }}</p>
            <p v-if="errors.general" class="text-xs leading-snug text-red-500">{{ errors.general }}</p>

            <div class="flex items-center justify-end gap-2 pt-1">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="tertiary" :label="ctrans('Send')" :loading="isSending" :disabled="!email.trim() || isLoadingPreview" @click="send">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-paper-plane" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </div>
    </Dialog>
</template>
