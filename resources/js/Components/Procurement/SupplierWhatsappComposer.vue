<script setup lang="ts">
import { computed } from "vue"
import { useForm } from "@inertiajs/vue3"
import { faPaperPlane } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
    route: routeType
    phone?: string | null
    counterpart?: string | null
    windowOpen?: boolean
    hasTemplate?: boolean
    template?: { name: string; language: string | null; status: string | null; body: string | null } | null
    fixedPhone?: boolean
}>()

const emit = defineEmits<{ (e: "sent"): void }>()

const form = useForm({
    phone: props.phone ?? "",
    body: "",
    counterpart: props.counterpart ?? null,
})

const isTemplateUnapproved = computed(() => !props.windowOpen && !!props.template?.status && props.template.status !== "APPROVED")

const templatePreview = computed(() => props.template?.body?.replace("{{1}}", form.body.trim() || ctrans("[your message]")) ?? null)

const send = () => {
    form.post(route(props.route.name, props.route.parameters), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset("body")
            emit("sent")
        },
    })
}
</script>

<template>
    <div class="space-y-2">
        <div v-if="!fixedPhone" class="grid grid-cols-[3.5rem_1fr] items-center gap-2 text-sm">
            <label class="text-gray-500">{{ ctrans("To") }}</label>
            <PureInput v-model="form.phone" placeholder="+86 138 0000 0000" />
        </div>

        <p v-if="!windowOpen && template?.status !== 'APPROVED'" class="rounded bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <template v-if="hasTemplate">{{ ctrans("They have not written in the last 24 hours, so this goes inside the approved message template.") }}</template>
            <template v-else>{{ ctrans("They have not written in the last 24 hours. WhatsApp only accepts an approved template then; set one in Procurement settings.") }}</template>
        </p>

        <PureTextarea v-model="form.body" :rows="4" :placeholder="ctrans('Write a WhatsApp message')" />

        <div v-if="!windowOpen && template" class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs">
            <div class="mb-1 flex items-center gap-2 text-gray-500">
                <span>{{ ctrans("Template") }} <span class="font-medium text-gray-700">{{ template.name }}</span></span>
                <span v-if="template.language">· {{ template.language }}</span>
                <span v-if="template.status" :class="template.status === 'APPROVED' ? 'text-green-700' : 'text-amber-700'">· {{ template.status }}</span>
            </div>
            <p v-if="templatePreview" class="whitespace-pre-line text-gray-800">{{ templatePreview }}</p>
            <p v-else class="text-gray-500">{{ ctrans("Save the template name again in Procurement settings to fetch its text from Meta.") }}</p>
            <p v-if="isTemplateUnapproved" class="mt-1 text-amber-800">{{ ctrans("Meta has not approved this template, so WhatsApp will not deliver it.") }}</p>
        </div>

        <div class="flex items-center gap-2">
            <span v-if="Object.values(form.errors)[0]" class="text-xs text-red-600">{{ Object.values(form.errors)[0] }}</span>
            <Button :label="ctrans('Send')" :icon="faPaperPlane" type="primary" size="xs" class="ml-auto"
                :loading="form.processing" :disabled="!form.body.trim() || !form.phone.trim() || (!windowOpen && !hasTemplate) || isTemplateUnapproved" @click="send" />
        </div>
    </div>
</template>
