<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useForm } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLifeRing, faToolbox, faUserHeadset, faBug, faLightbulb, faTasks, faVial, faLevelUp, faBooks, faDatabase } from "@fal"
import { faExclamationTriangle, faArrowUp, faMinus, faArrowDown } from "@fas"

library.add(faLifeRing, faToolbox, faUserHeadset, faBug, faLightbulb, faTasks, faVial, faLevelUp, faBooks, faDatabase, faExclamationTriangle, faArrowUp, faMinus, faArrowDown)
import { Select } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"

const props = defineProps<{
    storeRoute: { name: string; parameters?: Record<string, unknown> }
    priorities?: { label: string; value: string }[]
    kinds?: { label: string; value: string }[]
    types?: { label: string; value: string }[]
    modules?: { label: string; value: string }[]
}>()

const form = useForm<{ subject: string; description: string; reference_url: string; priority: string; type: string | null; kind: string | null; module: string | null; images: File[] }>({
    subject: "",
    description: "",
    reference_url: "",
    priority: "normal",
    type: props.types?.[0]?.value ?? null,
    kind: props.kinds?.[0]?.value ?? null,
    module: null,
    images: [],
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
    feature: "text-indigo-500",
}

const submit = () =>
    form
        .transform((data) => (data.type ? data : { ...data, type: undefined }))
        .post(route(props.storeRoute.name, props.storeRoute.parameters ?? {}), { forceFormData: true })
</script>

<template>
    <form class="flex h-[calc(100vh-9rem-40px)] flex-col" @submit.prevent="submit">
        <div class="grid min-h-0 flex-1 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="thinScrollbar space-y-4 overflow-y-auto pr-2">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans("Subject") }}</label>
                <input v-model="form.subject" type="text" maxlength="255" class="w-full rounded-md border-gray-300 text-sm focus:border-gray-500 focus:ring-0" :placeholder="trans('One line that says what is wrong')" />
                <p v-if="form.errors.subject" class="text-xs text-red-600 mt-1">{{ form.errors.subject }}</p>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans("Details") }} <span class="text-gray-400">{{ trans("(markdown works: **bold**, lists, links)") }}</span></label>
                <TicketComposer v-model:body="form.description" v-model:images="form.images" :rows="8" />
                <p v-if="form.errors.description || form.errors.images" class="text-xs text-red-600 mt-1">{{ form.errors.description || form.errors.images }}</p>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans("Page where it happens") }}</label>
                <input v-model="form.reference_url" type="url" maxlength="2048" class="w-full rounded-md border-gray-300 text-sm focus:border-gray-500 focus:ring-0" placeholder="https://app.aiku.io/..." />
                <p v-if="form.errors.reference_url" class="text-xs text-red-600 mt-1">{{ form.errors.reference_url }}</p>
            </div>
        </div>

        <aside class="space-y-4 overflow-y-auto lg:border-l lg:border-gray-200 lg:pl-6">
            <div v-if="types?.length">
                <label class="block text-xs text-gray-500 mb-1">{{ trans("Type") }}</label>
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
                <label class="block text-xs text-gray-500 mb-1">{{ trans("Priority") }}</label>
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
                    <label class="block text-xs text-gray-500 mb-1">{{ trans("Kind") }}</label>
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
                    <label class="block text-xs text-gray-500 mb-1">{{ trans("Module") }}</label>
                    <Select v-model="form.module" :options="modules" option-label="label" option-value="value" show-clear filter class="w-full" :placeholder="trans('Which part of aiku')">
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
        </aside>
        </div>

        <div class="mt-4 shrink-0 border-t border-gray-200 pt-4 pb-[40px] flex justify-end">
            <Button :label="trans('Create ticket')" :loading="form.processing" :disabled="!form.subject.trim()" @click="submit" />
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
