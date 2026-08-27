<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, useForm, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faLock, faLink, faPhone, faReply, faImage, faVideo, faFilePdf, faTrash } from "@fortawesome/free-solid-svg-icons"
import { Message } from "primevue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { capitalize } from "@/Composables/capitalize"

interface TemplateButton {
    type: string
    text: string
    url?: string
    phone_number?: string
}

const props = defineProps<{
    title: string
    pageHead: object
    businessName: string
    template: {
        id: number
        name: string
        label: string
        language: string
        category: string
        status: string
        rejected_reason?: string | null
        header_format?: string | null
        header_text?: string | null
        body: string
        footer?: string | null
        buttons: TemplateButton[]
        merge_tags: string[]
    }
    mergeTags: { name: string; value: string; example: string; group: string }[]
    updateRoute: { name: string; parameters: Record<string, any> }
    variablesRoute: { name: string; parameters: Record<string, any> }
    deleteRoute: { name: string; parameters: Record<string, any> }
}>()

const form = useForm({ label: props.template.label ?? "" })

const slotLabel = (index: number) => `{{${index + 1}}}`

const variableCount = computed(() => {
    const found = [...props.template.body.matchAll(/\{\{(\d+)\}\}/g)].map((match) => Number(match[1]))

    return found.length ? Math.max(...found) : 0
})

const mapping = ref<(string | null)[]>(
    Array.from({ length: 0 }, () => null)
)

mapping.value = Array.from(
    { length: variableCount.value },
    (_, index) => props.template.merge_tags?.[index] ?? null
)

const tagOptions = computed(() =>
    props.mergeTags.map((tag) => ({ value: tag.value.slice(1, -1), label: tag.name }))
)

const isMappingComplete = computed(() => mapping.value.length > 0 && mapping.value.every((tag) => !!tag))

// The body is shown with its samples filled in, which reads far better than raw {{1}}.
const previewBody = computed(() => {
    let text = props.template.body

    mapping.value.forEach((tag, index) => {
        const example = props.mergeTags.find((entry) => entry.value === `[${tag}]`)?.example

        if (example) text = text.replaceAll(`{{${index + 1}}}`, example)
    })

    return text
})

const statusTone = computed(() => {
    const map: Record<string, string> = {
        APPROVED: "bg-green-50 text-green-700 border-green-200",
        PENDING: "bg-amber-50 text-amber-700 border-amber-200",
        REJECTED: "bg-red-50 text-red-700 border-red-200",
    }

    return map[props.template.status] ?? "bg-gray-50 text-gray-600 border-gray-200"
})

const isSavingVariables = ref(false)

const saveVariables = () => {
    isSavingVariables.value = true

    router.patch(
        route(props.variablesRoute.name, props.variablesRoute.parameters),
        { merge_tags: mapping.value },
        { preserveScroll: true, onFinish: () => (isSavingVariables.value = false) }
    )
}

const save = () => form.patch(route(props.updateRoute.name, props.updateRoute.parameters), { preserveScroll: true })

const buttonIcon = (type: string) =>
    type === "URL" ? faLink : type === "PHONE_NUMBER" ? faPhone : faReply

const now = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })

const doodleStyle = computed(() => {
    const doodle = `<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">
        <g fill="none" stroke="#D9CFC4" stroke-width="1.4" stroke-linecap="round" opacity="0.55">
            <circle cx="18" cy="22" r="7"/>
            <path d="M44 14h16v11H52l-4 5v-5h-4z"/>
            <path d="M78 18c4-5 11-5 14 0"/>
            <path d="M100 40v12M94 46h12"/>
            <circle cx="30" cy="62" r="5"/>
            <path d="M54 58l7 7-7 7-7-7z"/>
            <path d="M84 66h18M84 72h12"/>
            <path d="M14 96c5-6 13-6 18 0"/>
            <circle cx="62" cy="100" r="6"/>
            <path d="M92 94h14v10H98l-3 4v-4h-3z"/>
        </g>
    </svg>`

    return {
        backgroundImage: `url("data:image/svg+xml,${encodeURIComponent(doodle)}")`,
        backgroundSize: "120px 120px",
    }
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="w-full max-w-5xl mx-auto px-4 sm:px-6 py-8">
        <Message v-if="template.status === 'REJECTED'" severity="error" :closable="false" class="mb-6">
            {{ trans("Meta rejected this template") }}<span v-if="template.rejected_reason">: {{ template.rejected_reason }}</span>
        </Message>

        <div class="flex flex-col lg:flex-row gap-10 xl:gap-14">
            <div class="flex-1 min-w-0 lg:max-w-[600px]">
                <section class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">{{ trans("Template name") }}</label>
                        <p class="text-xs text-gray-400 mt-0.5 mb-2">
                            {{ trans("Used inside Aiku only — rename it to something your team recognises") }}
                        </p>
                        <PureInput v-model="form.label" :placeholder="template.name" />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-800">{{ trans("Meta template name") }}</label>
                        <p class="text-xs text-gray-400 mt-0.5 mb-2">
                            {{ trans("The name WhatsApp knows this template by") }}
                        </p>
                        <div
                            class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                            <FontAwesomeIcon :icon="faLock" class="text-[10px] text-gray-400" />
                            {{ template.name }}
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ trans("Category") }}</label>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                                {{ capitalize(template.category?.toLowerCase() ?? '') }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ trans("Language") }}</label>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                                {{ template.language }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ trans("Status") }}</label>
                            <div class="rounded-lg border px-3 py-2 text-sm font-medium" :class="statusTone">
                                {{ capitalize(template.status?.toLowerCase() ?? '') }}
                            </div>
                        </div>
                    </div>
                </section>

                <hr class="border-gray-100 my-8" />

                <section class="space-y-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">{{ trans("Message") }}</label>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ trans("Approved wording is frozen — to change it, create a new template") }}
                        </p>
                    </div>

                    <div v-if="template.header_text"
                        class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-500">
                        {{ template.header_text }}
                    </div>

                    <div
                        class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500 whitespace-pre-line">
                        {{ template.body }}
                    </div>

                    <div v-if="template.footer"
                        class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-400">
                        {{ template.footer }}
                    </div>

                    <div v-if="template.buttons?.length" class="flex flex-wrap gap-2">
                        <span v-for="(button, index) in template.buttons" :key="index"
                            class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-500">
                            <FontAwesomeIcon :icon="buttonIcon(button.type)" class="text-[10px]" />
                            {{ button.text }}
                        </span>
                    </div>
                </section>

                <template v-if="variableCount">
                    <hr class="border-gray-100 my-8" />

                    <section class="space-y-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-800">{{ trans("Variables") }}</label>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ trans("Say what each slot means and Aiku fills it from the conversation, so agents never type it") }}
                            </p>
                        </div>

                        <div v-for="(_, index) in mapping" :key="index" class="flex items-center gap-3">
                            <span class="w-12 shrink-0 font-mono text-xs text-gray-500">{{ slotLabel(index) }}</span>
                            <div class="flex-1">
                                <PureMultiselect v-model="mapping[index]" :options="tagOptions" searchable
                                    :placeholder="trans('Choose a variable…')" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 pt-1">
                            <span class="text-[11px]" :class="isMappingComplete ? 'text-gray-400' : 'text-amber-600'">
                                {{ isMappingComplete
                                    ? trans("Agents send this without filling anything in.")
                                    : trans("Map every slot, otherwise agents keep typing the values by hand.") }}
                            </span>
                            <Button :label="trans('Save variables')" :loading="isSavingVariables"
                                :disabled="!isMappingComplete" @click="saveVariables" />
                        </div>
                    </section>
                </template>

                <div class="mt-8 pt-5 border-t border-gray-100 flex items-center justify-between gap-4">
                    <ModalConfirmationDelete :routeDelete="{
                        name: deleteRoute.name,
                        parameters: deleteRoute.parameters,
                        method: 'delete',
                    }" :title="trans('Delete this template?')"
                        :description="trans('It is removed from WhatsApp as well, and any language variant goes with it. Approved templates cannot be restored — you would have to submit a new one.')"
                        :noLabel="trans('Delete template')" :noIcon="faTrash">
                        <template #default="{ changeModel }">
                            <button type="button" @click="changeModel"
                                class="inline-flex items-center gap-1.5 text-xs text-red-500 hover:text-red-600 hover:underline">
                                <FontAwesomeIcon :icon="faTrash" class="text-[10px]" />
                                {{ trans("Delete template") }}
                            </button>
                        </template>
                    </ModalConfirmationDelete>

                    <Button :label="trans('Save')" :loading="form.processing" @click="save" />
                </div>
            </div>

            <div class="w-full lg:w-[330px] shrink-0">
                <div class="lg:sticky lg:top-6">
                    <div class="rounded-2xl overflow-hidden border border-gray-200 shadow-sm">
                        <div class="flex items-center gap-2 bg-[#075E54] px-4 py-2.5 text-white">
                            <FontAwesomeIcon :icon="faWhatsapp" class="text-sm" />
                            <span class="text-sm font-semibold">{{ trans("Preview") }}</span>
                            <span class="ml-auto text-[11px] opacity-70 truncate max-w-[130px]">{{ businessName }}</span>
                        </div>

                        <div class="px-3.5 py-6 bg-[#ECE5DD]" :style="doodleStyle">
                            <div class="relative max-w-[90%]">
                                <span class="absolute -left-1.5 top-0 w-3 h-3 bg-white"
                                    style="clip-path: polygon(100% 0, 100% 100%, 0 0)"></span>

                                <div
                                    class="relative rounded-lg rounded-tl-none bg-white shadow-[0_1px_1px_rgba(0,0,0,0.12)] overflow-hidden">
                                    <div v-if="template.header_format && template.header_format !== 'TEXT'"
                                        class="p-1.5 pb-0">
                                        <div class="h-32 rounded-md bg-gray-100 flex items-center justify-center">
                                            <FontAwesomeIcon
                                                :icon="template.header_format === 'VIDEO' ? faVideo : (template.header_format === 'DOCUMENT' ? faFilePdf : faImage)"
                                                class="text-gray-300 text-3xl" />
                                        </div>
                                    </div>

                                    <div class="px-2.5 py-2 space-y-1">
                                        <div v-if="template.header_text"
                                            class="text-[14px] font-semibold text-[#111B21] leading-snug break-words">
                                            {{ template.header_text }}
                                        </div>

                                        <div
                                            class="text-[14px] leading-[19px] text-[#111B21] whitespace-pre-wrap break-words">
                                            {{ previewBody }}
                                        </div>

                                        <div v-if="template.footer" class="text-[12px] text-[#667781] break-words pt-0.5">
                                            {{ template.footer }}
                                        </div>

                                        <div class="flex justify-end -mb-0.5">
                                            <span class="text-[10px] text-[#667781]">{{ now }}</span>
                                        </div>
                                    </div>

                                    <div v-if="template.buttons?.length" class="border-t border-[#E9EDEF]">
                                        <div v-for="(button, index) in template.buttons" :key="index"
                                            class="flex items-center justify-center gap-1.5 border-b border-[#E9EDEF] last:border-b-0 py-2.5 text-[14px] text-[#0091EA] font-medium">
                                            <FontAwesomeIcon :icon="buttonIcon(button.type)" class="text-[11px]" />
                                            {{ button.text }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
