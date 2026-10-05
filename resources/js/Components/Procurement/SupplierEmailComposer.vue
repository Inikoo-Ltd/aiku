<script setup lang="ts">
import { ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperclip, faPaperPlane, faTimes } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
    route: routeType
    to?: string[]
    cc?: string[]
    subject?: string | null
    counterpart?: string | null
    isReply?: boolean
}>()

const emit = defineEmits<{ (e: "sent"): void }>()

const splitAddresses = (value: string) => value.split(/[,;\s]+/).map((address) => address.trim()).filter(Boolean)

const toInput = ref((props.to ?? []).join(", "))
const ccInput = ref((props.cc ?? []).join(", "))
const fileInput = ref<HTMLInputElement | null>(null)

const form = useForm({
    to: [] as string[],
    cc: [] as string[],
    subject: props.subject ?? "",
    body: "",
    attachments: [] as File[],
    counterpart: props.counterpart ?? null,
})

const addFiles = (event: Event) => {
    const files = (event.target as HTMLInputElement).files

    if (files) {
        form.attachments = [...form.attachments, ...Array.from(files)]
    }

    if (fileInput.value) {
        fileInput.value.value = ""
    }
}

const removeFile = (index: number) => {
    form.attachments = form.attachments.filter((_, fileIndex) => fileIndex !== index)
}

const send = () => {
    form.to = splitAddresses(toInput.value)
    form.cc = splitAddresses(ccInput.value)

    form.post(route(props.route.name, props.route.parameters), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset("body", "attachments")
            emit("sent")
        },
    })
}

const firstError = () => Object.values(form.errors)[0]
</script>

<template>
    <div class="space-y-2">
        <div class="grid grid-cols-[3.5rem_1fr] items-center gap-2 text-sm">
            <label class="text-gray-500">{{ ctrans("To") }}</label>
            <PureInput v-model="toInput" :placeholder="ctrans('supplier@example.com')" />
            <label class="text-gray-500">{{ ctrans("Cc") }}</label>
            <PureInput v-model="ccInput" />
            <label class="text-gray-500">{{ ctrans("Subject") }}</label>
            <PureInput v-model="form.subject" />
        </div>

        <PureTextarea v-model="form.body" :rows="isReply ? 5 : 9" :placeholder="isReply ? ctrans('Write a reply') : ctrans('Write your email')" />

        <div v-if="form.attachments.length" class="flex flex-wrap gap-2">
            <span v-for="(file, index) in form.attachments" :key="index" class="inline-flex items-center gap-1 rounded border border-gray-200 px-2 py-1 text-xs text-gray-700">
                <FontAwesomeIcon :icon="faPaperclip" class="text-gray-400" fixed-width />
                {{ file.name }}
                <button type="button" class="text-gray-400 hover:text-red-500" @click="removeFile(index)">
                    <FontAwesomeIcon :icon="faTimes" fixed-width />
                </button>
            </span>
        </div>

        <div class="flex items-center gap-2">
            <input ref="fileInput" type="file" multiple class="hidden" @change="addFiles" />
            <Button :label="ctrans('Attach')" :icon="faPaperclip" type="tertiary" size="xs" @click="fileInput?.click()" />
            <span v-if="firstError()" class="text-xs text-red-600">{{ firstError() }}</span>
            <Button :label="ctrans('Send')" :icon="faPaperPlane" type="primary" size="xs" class="ml-auto"
                :loading="form.processing" :disabled="!form.body.trim() || !toInput.trim()" @click="send" />
        </div>
    </div>
</template>
