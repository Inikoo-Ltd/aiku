<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle, faExternalLink, faLifeRing } from "@fortawesome/free-solid-svg-icons"
import { notify } from "@kyvg/vue3-notification"
import { Select, InputText, Textarea } from "primevue"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import type { SessionAPI } from "@/types/Chat/chat"

const props = defineProps<{
    isOpen: boolean
    session: SessionAPI | null
    organisation: string
}>()

const emit = defineEmits(["close", "created"])

const priorities = [
    { label: trans("Low"), value: "low" },
    { label: trans("Normal"), value: "normal" },
    { label: trans("High"), value: "high" },
    { label: trans("Urgent"), value: "urgent" },
]

const form = ref({ summary: "", description: "", priority: "normal" })
const isSubmitting = ref(false)
const created = ref<{ key: string; url: string; summary: string } | null>(null)

watch(
    () => props.isOpen,
    (open) => {
        if (!open) return
        created.value = null
        form.value = {
            summary: "",
            description: props.session?.ai_summary?.summary ?? "",
            priority: "normal",
        }
    }
)

const submit = async () => {
    if (!form.value.summary.trim() || !props.session?.ulid) return
    isSubmitting.value = true
    try {
        const { data } = await axios.post(
            route("grp.org.chat.agents.sessions.ticket", [props.organisation, props.session.ulid]),
            { ...form.value, reference_url: window.location.href }
        )
        created.value = data.data
        emit("created", data.data)
    } catch (error: any) {
        notify({ title: trans("Something went wrong"), text: error?.response?.data?.message ?? "", type: "error" })
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="emit('close')" width="w-full max-w-lg">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                <FontAwesomeIcon :icon="faLifeRing" class="text-blue-600" />
            </div>
            <div>
                <h2 class="text-base font-semibold text-gray-800">{{ trans("Create ticket") }}</h2>
                <p class="text-xs text-gray-400">{{ session?.contact_name || session?.guest_identifier || trans("chat session") }}</p>
            </div>
        </div>

        <div v-if="created" class="flex flex-col items-center text-center py-6 px-4">
            <FontAwesomeIcon :icon="faCheckCircle" class="text-emerald-500 text-3xl mb-3" />
            <p class="text-sm font-medium text-gray-700">{{ trans("Ticket created") }}</p>
            <a :href="created.url" target="_blank" rel="noopener" class="inline-flex items-center gap-2 mt-2 text-sm font-semibold text-blue-600 hover:text-blue-700">
                {{ created.key }}
                <FontAwesomeIcon :icon="faExternalLink" class="text-xs" />
            </a>
            <p class="text-xs text-gray-400 mt-1">{{ created.summary }}</p>
            <Button class="mt-4" size="sm" :label="trans('Close')" @click="emit('close')" />
        </div>

        <form v-else class="space-y-3" @submit.prevent="submit">
            <InputText v-model="form.summary" class="w-full" :placeholder="trans('Summary')" maxlength="255" />
            <Textarea v-model="form.description" class="w-full" rows="5" :placeholder="trans('Description')" />
            <Select v-model="form.priority" :options="priorities" option-label="label" option-value="value" class="w-full" />
            <div class="flex items-center justify-end gap-2 pt-2">
                <Button type="tertiary" size="sm" :label="trans('Cancel')" @click="emit('close')" />
                <Button size="sm" :label="trans('Create')" :loading="isSubmitting" :disabled="!form.summary.trim()" @click="submit" />
            </div>
        </form>
    </Modal>
</template>
