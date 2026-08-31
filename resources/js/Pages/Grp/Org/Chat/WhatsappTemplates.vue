<!--
  - Author: Artha <artha@aw-advantage.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import { Head } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWandMagicSparkles, faPen, faTrash, faSync, faLanguage } from "@fortawesome/free-solid-svg-icons"
import { Dialog, Message } from "primevue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import Table from "@/Components/Table/Table.vue"

interface TemplateRow {
    id: number
    name: string
    language: string
    body: string
    variable_count: number
    merge_tags: string[]
    is_draft?: boolean
}

const props = defineProps<{
    data: object
    title: string
    pageHead: object
    mergeTags: { name: string; value: string; example: string; group: string }[]
    variablesRoute: { name: string; parameters: Record<string, any> }
    editRouteName: string
    deleteRouteName: string
    refreshRouteName: string
    draftRouteName: string
    languageRouteName: string
    routeParameters: Record<string, any>
}>()

const rowRoute = (name: string, id: number) => route(name, { ...props.routeParameters, metaMessageTemplate: id })

const refreshingId = ref<number | null>(null)

const refreshStatus = (template: TemplateRow) => {
    refreshingId.value = template.id
    router.post(rowRoute(props.refreshRouteName, template.id), {}, {
        preserveScroll: true,
        onFinish: () => (refreshingId.value = null),
    })
}

const editing = ref<TemplateRow | null>(null)
const mapping = ref<(string | null)[]>([])
const isSaving = ref(false)

const tagOptions = computed(() =>
    props.mergeTags.map((tag) => ({ value: tag.value.slice(1, -1), label: tag.name }))
)

const slotLabel = (index: number) => `{{${index + 1}}}`

const openMapping = (template: TemplateRow) => {
    editing.value = template
    mapping.value = Array.from(
        { length: template.variable_count },
        (_, index) => template.merge_tags?.[index] ?? null
    )
}

// A half-filled mapping would shift every later slot onto the wrong value, so it is all
// or nothing.
const isComplete = computed(() => mapping.value.every((tag) => !!tag))

const previewLine = computed(() => {
    if (!editing.value) return ""

    let text = editing.value.body

    mapping.value.forEach((tag, index) => {
        const example = props.mergeTags.find((entry) => entry.value === `[${tag}]`)?.example

        if (example) text = text.replaceAll(`{{${index + 1}}}`, example)
    })

    return text
})

const save = () => {
    if (!editing.value) return

    isSaving.value = true

    router.patch(
        route(props.variablesRoute.name, { ...props.variablesRoute.parameters, metaMessageTemplate: editing.value.id }),
        { merge_tags: mapping.value },
        {
            preserveScroll: true,
            onFinish: () => {
                isSaving.value = false
                editing.value = null
            },
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Table :resource="data">
        <template #cell(synchronize_at)="{ item: template }">
            {{ useFormatTime(template.synchronize_at) }}
        </template>

        <template #cell(name)="{ item: template }">
            <div class="flex flex-col">
                <span class="font-medium">{{ template.label || template.name }}</span>
                <span v-if="template.label" class="text-[11px] text-gray-400">{{ template.name }}</span>
            </div>
        </template>

        <template #cell(actions)="{ item: template }">
            <div class="flex items-center justify-end gap-1">
                <a v-if="!template.is_draft" :href="rowRoute(languageRouteName, template.id)"
                    v-tooltip="trans('Add another language')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <FontAwesomeIcon :icon="faLanguage" class="text-[11px]" />
                </a>

                <button v-if="!template.is_draft" type="button" @click="refreshStatus(template)"
                    :disabled="refreshingId === template.id"
                    v-tooltip="trans('Check status on WhatsApp')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-50">
                    <FontAwesomeIcon :icon="faSync" class="text-[10px]"
                        :class="refreshingId === template.id ? 'animate-spin' : ''" />
                </button>

                <a :href="rowRoute(template.is_draft ? draftRouteName : editRouteName, template.id)"
                    v-tooltip="template.is_draft ? trans('Continue draft') : trans('Edit')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <FontAwesomeIcon :icon="faPen" class="text-[10px]" />
                </a>

                <ModalConfirmationDelete :routeDelete="{
                    name: deleteRouteName,
                    parameters: { ...routeParameters, metaMessageTemplate: template.id },
                    method: 'delete',
                }" :title="trans('Delete this template?')"
                    :description="template.is_draft
                        ? trans('This draft was never sent to Meta, so it only disappears from Aiku.')
                        : trans('It is removed from WhatsApp as well, and every language variant goes with it.')"
                    :noLabel="trans('Delete template')" :noIcon="faTrash">
                    <template #default="{ changeModel }">
                        <button type="button" @click="changeModel" v-tooltip="trans('Delete')"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500">
                            <FontAwesomeIcon :icon="faTrash" class="text-[10px]" />
                        </button>
                    </template>
                </ModalConfirmationDelete>
            </div>
        </template>

        <template #cell(variables)="{ item: template }">
            <span v-if="!template.variable_count" class="text-xs text-gray-400">—</span>

            <button v-else type="button" @click="openMapping(template)"
                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] transition"
                :class="template.merge_tags?.length
                    ? 'border-green-200 bg-green-50 text-green-700 hover:border-green-300'
                    : 'border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-300'">
                <FontAwesomeIcon :icon="faWandMagicSparkles" class="text-[9px]" />
                {{ template.merge_tags?.length
                    ? trans("Auto-filled")
                    : trans(":count to map", { count: template.variable_count }) }}
            </button>
        </template>
    </Table>

    <Dialog :visible="!!editing" @update:visible="editing = null" modal
        :header="trans('What do this template\'s variables mean?')" :style="{ width: '32rem' }">
        <p class="text-xs text-gray-500 mb-4">
            {{ trans("Once mapped, Aiku fills these from the conversation and agents no longer type them by hand. The template text is untouched, so Meta does not need to review it again.") }}
        </p>

        <div class="space-y-3">
            <div v-for="(_, index) in mapping" :key="index" class="flex items-center gap-3">
                <span class="w-12 shrink-0 font-mono text-xs text-gray-500">{{ slotLabel(index) }}</span>
                <div class="flex-1">
                    <PureMultiselect v-model="mapping[index]" :options="tagOptions" searchable
                        :placeholder="trans('Choose a variable…')" />
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-lg bg-gray-50 p-3">
            <div class="text-[11px] font-medium text-gray-500 mb-1">{{ trans("Preview with sample values") }}</div>
            <div class="text-xs text-gray-700 whitespace-pre-line">{{ previewLine }}</div>
        </div>

        <Message v-if="!isComplete" severity="warn" :closable="false" class="mt-3 text-xs">
            {{ trans("Map every variable, otherwise agents keep filling them by hand.") }}
        </Message>

        <template #footer>
            <button type="button" class="text-xs text-gray-500 hover:underline mr-3" @click="editing = null">
                {{ trans("Cancel") }}
            </button>
            <Button :label="trans('Save')" :loading="isSaving" :disabled="!isComplete" @click="save" />
        </template>
    </Dialog>
</template>
