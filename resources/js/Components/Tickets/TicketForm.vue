<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useForm } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLifeRing, faToolbox, faUserHeadset, faBug, faLightbulb, faTasks, faVial, faLevelUp, faBooks, faDatabase, faSearch, faBell, faBellSlash, faHistory, faProjectDiagram, faLink } from "@fal"
import { faExclamationTriangle, faArrowUp, faMinus, faArrowDown } from "@fas"

library.add(faLifeRing, faToolbox, faUserHeadset, faBug, faLightbulb, faTasks, faVial, faLevelUp, faBooks, faDatabase, faSearch, faExclamationTriangle, faArrowUp, faMinus, faArrowDown, faBell, faBellSlash, faHistory, faProjectDiagram, faLink)
import { Select } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"
import TicketDraftSimilar from "@/Components/Tickets/TicketDraftSimilar.vue"

const props = defineProps<{
    fillScreen?: boolean
    storeRoute: { name: string; parameters?: Record<string, unknown> }
    priorities?: { label: string; value: string }[]
    kinds?: { label: string; value: string }[]
    types?: { label: string; value: string }[]
    modules?: { label: string; value: string }[]
    project?: { id: number; name: string } | null
    linkFrom?: { id: number; reference: string; subject: string; types: { value: string; label: string }[] } | null
    stay?: boolean
}>()

const emit = defineEmits<{
    (e: "created"): void
}>()

const form = useForm<{ subject: string; description: string; reference_url: string; priority: string; type: string | null; kind: string | null; module: string | null; images: File[]; reporter_muted: boolean; ticket_project_id: number | null; link_ticket_id: number | null; link_type: string | null }>({
    subject: "",
    description: "",
    reference_url: "",
    priority: "normal",
    type: props.linkFrom ? "engineer" : props.types?.[0]?.value ?? null,
    link_ticket_id: props.linkFrom?.id ?? null,
    link_type: props.linkFrom ? "relates" : null,
    kind: props.kinds?.[0]?.value ?? null,
    module: null,
    images: [],
    reporter_muted: false,
    ticket_project_id: props.project?.id ?? null,
})


const optionIcons: Record<string, string> = {
    help: "fal fa-life-ring",
    engineer: "fal fa-toolbox",
    customer: "fal fa-user-headset",
    bug: "fal fa-bug",
    feature: "fal fa-lightbulb",
    task: "fal fa-tasks",
    qa: "fal fa-vial",
    escalation: "fal fa-level-up",
    documentation: "fal fa-books",
    data_integrity: "fal fa-database",
    support: "fal fa-search",
    aurora: "fal fa-history",
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
    support: "text-teal-600",
    feature: "text-[--app-accent-strong]",
}

const submit = () =>
    form
        .transform((data) => {
            const payload: Record<string, unknown> = data.type ? { ...data } : { ...data, type: undefined }
            if (!data.link_ticket_id) {
                delete payload.link_ticket_id
                delete payload.link_type
            }
            if (props.stay) payload.stay = true
            return payload
        })
        .post(route(props.storeRoute.name, props.storeRoute.parameters ?? {}), {
            forceFormData: true,
            preserveScroll: props.stay,
            onSuccess: () => {
                if (!props.stay) return
                form.reset()
                emit("created")
            },
        })
</script>

<template>
    <form class="flex flex-col" :class="fillScreen && 'h-[calc(100vh-9rem-40px)]'" @submit.prevent="submit">
        <div class="grid min-h-0 flex-1 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="thinScrollbar space-y-4 pr-2" :class="fillScreen && 'overflow-y-auto'">
            <div v-if="linkFrom" class="flex flex-wrap items-center gap-2 rounded-md border border-[--app-accent-muted] bg-[--app-accent-soft] px-3 py-2 text-sm text-gray-700">
                <FontAwesomeIcon icon="fal fa-link" fixed-width class="text-[--app-accent-strong]" />
                <span class="font-medium text-[--app-accent-strong]">{{ linkFrom.reference }}</span>
                <Select v-model="form.link_type" :options="linkFrom.types" option-label="label" option-value="value" size="small" class="min-w-[9rem]" />
                <span>{{ ctrans("this new ticket") }}</span>
                <span class="w-full truncate text-xs text-gray-500">{{ linkFrom.subject }}</span>
            </div>
            <p v-if="project" class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700">
                <FontAwesomeIcon icon="fal fa-project-diagram" fixed-width class="mr-1 text-gray-400" />
                {{ ctrans("This ticket goes into the project :name", { name: project.name }) }}
            </p>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs text-gray-500">{{ ctrans("Subject") }}</label>
                    <button
                        type="button"
                        v-tooltip="form.reporter_muted ? ctrans('Muted: no sound, mini-modal or email for you on this ticket') : ctrans('Mute this ticket for me')"
                        class="flex h-6 w-6 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        :class="form.reporter_muted && '!text-amber-600'"
                        @click="form.reporter_muted = !form.reporter_muted">
                        <FontAwesomeIcon :icon="form.reporter_muted ? 'fal fa-bell-slash' : 'fal fa-bell'" fixed-width />
                    </button>
                </div>
                <input v-model="form.subject" type="text" maxlength="255" class="w-full rounded-md border-gray-300 text-sm focus:border-gray-500 focus:ring-0" :placeholder="ctrans('One line that says what is wrong')" />
                <p v-if="form.errors.subject" class="text-xs text-red-600 mt-1">{{ form.errors.subject }}</p>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans("Details") }} <span class="text-gray-400">{{ ctrans("(markdown works: **bold**, lists, links)") }}</span></label>
                <TicketComposer v-model:body="form.description" v-model:images="form.images" :rows="8" />
                <p v-if="form.errors.description || form.errors.images" class="text-xs text-red-600 mt-1">{{ form.errors.description || form.errors.images }}</p>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans("Page where it happens") }}</label>
                <input v-model="form.reference_url" type="url" maxlength="2048" class="w-full rounded-md border-gray-300 text-sm focus:border-gray-500 focus:ring-0" placeholder="https://app.aiku.io/..." />
                <p v-if="form.errors.reference_url" class="text-xs text-red-600 mt-1">{{ form.errors.reference_url }}</p>
            </div>
        </div>

        <aside class="space-y-4 lg:border-l lg:border-gray-200 lg:pl-6" :class="fillScreen && 'overflow-y-auto'">
            <div v-if="types?.length">
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans("Type") }}</label>
                <Select v-model="form.type" :options="types" option-label="label" option-value="value" class="w-full">
                    <template #value="{ value, placeholder }">
                        <span v-if="value" class="flex items-center gap-2">
                            <FontAwesomeIcon v-if="optionIcons[value]" :icon="optionIcons[value]" :class="optionIconClasses[value]" fixed-width aria-hidden="true" />
                            {{ capitalize(types.find((option) => option.value === value)?.label) }}
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
            <div v-if="priorities?.length">
                <label class="block text-xs text-gray-500 mb-1">{{ ctrans("Priority") }}</label>
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
            <template v-if="kinds?.length">
                <div class="border-t border-gray-200 pt-4">
                    <label class="block text-xs text-gray-500 mb-1">{{ ctrans("Kind") }}</label>
                    <Select v-model="form.kind" :options="kinds" option-label="label" option-value="value" class="w-full">
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
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ ctrans("Module") }}</label>
                    <Select v-model="form.module" :options="modules" option-label="label" option-value="value" show-clear filter class="w-full" :placeholder="ctrans('Which part of aiku')">
                    <template #value="{ value, placeholder }">
                        <span v-if="value" class="flex items-center gap-2">
                            <FontAwesomeIcon v-if="optionIcons[value]" :icon="optionIcons[value]" :class="optionIconClasses[value]" fixed-width aria-hidden="true" />
                            {{ capitalize(modules.find((option) => option.value === value)?.label) }}
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
            </template>
            <div class="border-t border-gray-200 pt-4">
                <TicketDraftSimilar :subject="form.subject" :description="form.description" />
            </div>
        </aside>
        </div>

        <div class="mt-4 flex shrink-0 justify-end border-t border-gray-200 pt-4" :class="fillScreen && 'pb-[40px]'">
            <Button :label="ctrans('Create ticket')" :loading="form.processing" :disabled="!form.subject.trim()" @click="submit" />
        </div>
    </form>
</template>

<style scoped>
.thinScrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgb(209 213 219) transparent;
}

.thinScrollbar::-webkit-scrollbar {
    width: 6px;
}

.thinScrollbar::-webkit-scrollbar-thumb {
    background-color: rgb(209 213 219);
    border-radius: 9999px;
}

.thinScrollbar::-webkit-scrollbar-track {
    background: transparent;
}
</style>
