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
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBug, faBooks, faDatabase, faQuestionCircle } from "@fal"
import { faExclamationTriangle, faArrowUp, faMinus, faArrowDown } from "@fas"

import { notify } from "@kyvg/vue3-notification"
import { Select, InputText } from "primevue"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"
import { capitalize } from "@/Composables/capitalize"
import type { SessionAPI } from "@/types/Chat/chat"

library.add(faBug, faBooks, faDatabase, faQuestionCircle, faExclamationTriangle, faArrowUp, faMinus, faArrowDown)

const props = defineProps<{
    isOpen: boolean
    session: SessionAPI | null
    organisation: string
    channel?: string
}>()

const emit = defineEmits(["close", "created"])

const priorities = [
    { label: trans("Low"), value: "low" },
    { label: trans("Normal"), value: "normal" },
    { label: trans("High"), value: "high" },
    { label: trans("Urgent"), value: "urgent" },
]

const optionIcons: Record<string, string> = {
    bug: "fal fa-bug",
    documentation: "fal fa-books",
    data_integrity: "fal fa-database",
    urgent: "fas fa-exclamation-triangle",
    high: "fas fa-arrow-up",
    normal: "fas fa-minus",
    low: "fas fa-arrow-down",
}

const optionIconClasses: Record<string, string> = {
    urgent: "text-red-500",
    high: "text-amber-500",
    normal: "text-gray-400",
    low: "text-sky-500",
    bug: "text-red-500",
    documentation: "text-sky-600",
    data_integrity: "text-amber-600",
}

const kinds = [
    { label: trans("Bug"), value: "bug" },
    { label: trans("Documentation"), value: "documentation" },
    { label: trans("Data integrity"), value: "data_integrity" },
]

const form = ref<{ summary: string; description: string; priority: string; kind: string | null; blocksSource: boolean; images: File[] }>({
    summary: "",
    description: "",
    priority: "normal",
    kind: null,
    blocksSource: false,
    images: [],
})

const blockedTooltip = trans("While this is ticked, this chat cannot be closed until the ticket is resolved or cancelled.")

// A bug is the case where the customer is left waiting on us, so it starts blocked. Ticked,
// not enforced: the agent knows when a bug report is a note for later rather than a promise.
watch(
    () => form.value.kind,
    (kind) => {
        if (kind === "bug") {
            form.value.blocksSource = true
        }
    }
)
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
            kind: null,
            blocksSource: false,
            images: [],
        }
    }
)

const submit = async () => {
    if (!form.value.summary.trim() || !props.session?.ulid) return
    isSubmitting.value = true
    try {
        const routeName = props.channel === "whatsapp"
            ? "grp.org.chat.agents.whatsapp.sessions.ticket"
            : "grp.org.chat.agents.sessions.ticket"
        const payload = new FormData()
        payload.append("summary", form.value.summary)
        payload.append("description", form.value.description ?? "")
        payload.append("priority", form.value.priority)
        payload.append("reference_url", window.location.href)
        payload.append("blocks_source", form.value.blocksSource ? "1" : "0")
        if (form.value.kind) {
            payload.append("kind", form.value.kind)
        }
        form.value.images.forEach((image) => payload.append("images[]", image))

        const { data } = await axios.post(
            route(routeName, [props.organisation, props.session.ulid]),
            payload,
            { headers: { "Content-Type": "multipart/form-data" } }
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
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">{{ trans("Subject") }}</label>
                <InputText v-model="form.summary" class="w-full" :placeholder="trans('One line that says what is wrong')" maxlength="255" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">
                    {{ trans("Details") }}
                    <span class="font-normal text-gray-400">{{ trans("(markdown works: **bold**, lists, links)") }}</span>
                </label>
                <TicketComposer
                    v-model:body="form.description"
                    v-model:images="form.images"
                    :rows="5"
                    :placeholder="trans('What the customer reported. Paste a screenshot or drop images here.')" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">{{ trans("Priority") }}</label>
                    <Select v-model="form.priority" :options="priorities" option-label="label" option-value="value" class="w-full">
                        <template #value="{ value, placeholder }">
                            <span v-if="value" class="flex items-center gap-2">
                                <FontAwesomeIcon v-if="optionIcons[value]" :icon="optionIcons[value]" :class="optionIconClasses[value]" fixed-width aria-hidden="true" />
                                {{ capitalize(priorities.find((option) => option.value === value)?.label) }}
                            </span>
                            <span v-else class="text-gray-400">{{ placeholder }}</span>
                        </template>
                        <template #option="{ option }">
                            <span class="flex items-center gap-2">
                                <FontAwesomeIcon v-if="optionIcons[option.value]" :icon="optionIcons[option.value]" :class="optionIconClasses[option.value]" fixed-width aria-hidden="true" />
                                {{ capitalize(option.label) }}
                            </span>
                        </template>
                    </Select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">{{ trans("Kind") }}</label>
                    <Select v-model="form.kind" :options="kinds" option-label="label" option-value="value" class="w-full" show-clear :placeholder="trans('Not set')">
                        <template #value="{ value, placeholder }">
                            <span v-if="value" class="flex items-center gap-2">
                                <FontAwesomeIcon v-if="optionIcons[value]" :icon="optionIcons[value]" :class="optionIconClasses[value]" fixed-width aria-hidden="true" />
                                {{ capitalize(kinds.find((option) => option.value === value)?.label) }}
                            </span>
                            <span v-else class="text-gray-400">{{ placeholder }}</span>
                        </template>
                        <template #option="{ option }">
                            <span class="flex items-center gap-2">
                                <FontAwesomeIcon v-if="optionIcons[option.value]" :icon="optionIcons[option.value]" :class="optionIconClasses[option.value]" fixed-width aria-hidden="true" />
                                {{ capitalize(option.label) }}
                            </span>
                        </template>
                    </Select>
                </div>
            </div>
            <label class="flex cursor-pointer select-none items-start gap-2 rounded-md border p-3 transition duration-200"
                :class="form.blocksSource ? 'border-amber-300 bg-amber-50' : 'border-gray-200'">
                <input v-model="form.blocksSource" type="checkbox" class="mt-0.5 cursor-pointer rounded border-gray-300 text-amber-500 focus:ring-amber-400" />
                <span class="text-xs">
                    <span class="flex items-center gap-1.5 font-medium" :class="form.blocksSource ? 'text-amber-800' : 'text-gray-700'">
                        {{ trans("Mark as blocked") }}
                        <FontAwesomeIcon :icon="faQuestionCircle" v-tooltip="blockedTooltip" class="text-gray-400" />
                    </span>
                    <span class="mt-0.5 block" :class="form.blocksSource ? 'text-amber-700' : 'text-gray-400'">
                        {{ form.blocksSource
                            ? trans("This chat stays open until this ticket is resolved or cancelled.")
                            : trans("The chat can be closed while this ticket is still open.") }}
                    </span>
                </span>
            </label>

            <p class="text-xs text-gray-400">{{ trans("Raised as a Customer support ticket.") }}</p>
            <div class="flex items-center justify-end gap-2 pt-2">
                <Button type="tertiary" size="sm" :label="trans('Cancel')" @click="emit('close')" />
                <Button size="sm" :label="trans('Create')" :loading="isSubmitting" :disabled="!form.summary.trim()" @click="submit" />
            </div>
        </form>
    </Modal>
</template>
