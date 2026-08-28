<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, useForm, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faLock, faLink, faPhone, faReply, faImage, faVideo, faFilePdf, faTrash, faUpload } from "@fortawesome/free-solid-svg-icons"
import { Message } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import Image from "@common/Components/Image.vue"
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
        header_media?: { name: string; url?: any } | null
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
    headerMediaRoute: { name: string; parameters: Record<string, any> }
    mediaRules: Record<string, { mime_types: string[]; extensions: string[]; max_kb: number; accept: string }>
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

type PreviewSegment = { kind: "text" | "tag"; value: string }


const toSegments = (text: string): PreviewSegment[] => {
    const segments: PreviewSegment[] = []
    const pattern = /\{\{(\d+)\}\}/g

    let cursor = 0
    let match: RegExpExecArray | null

    while ((match = pattern.exec(text)) !== null) {
        if (match.index > cursor) {
            segments.push({ kind: "text", value: text.slice(cursor, match.index) })
        }

        const mapped = mapping.value[Number(match[1]) - 1]
        segments.push({ kind: "tag", value: mapped || match[0] })
        cursor = match.index + match[0].length
    }

    if (cursor < text.length) {
        segments.push({ kind: "text", value: text.slice(cursor) })
    }

    return segments
}

const previewBodySegments = computed(() => toSegments(props.template.body))

const previewHeaderSegments = computed(() => toSegments(props.template.header_text ?? ""))

const headerFormat = computed(() => props.template.header_format || "NONE")

const headerFormatLabel = computed(() => {
    const labels: Record<string, string> = {
        NONE: trans("None"),
        TEXT: trans("Text"),
        IMAGE: trans("Image"),
        VIDEO: trans("Video"),
        DOCUMENT: trans("PDF"),
    }

    return labels[headerFormat.value] ?? headerFormat.value
})

const headerIcon = computed(() => {
    const icons: Record<string, any> = { IMAGE: faImage, VIDEO: faVideo, DOCUMENT: faFilePdf }

    return icons[headerFormat.value] ?? null
})

const statusTone = computed(() => {
    const map: Record<string, string> = {
        APPROVED: "bg-green-50 text-green-700 border-green-200",
        PENDING: "bg-amber-50 text-amber-700 border-amber-200",
        REJECTED: "bg-red-50 text-red-700 border-red-200",
    }

    return map[props.template.status] ?? "bg-gray-50 text-gray-600 border-gray-200"
})


const needsHeaderMedia = computed(() => ["IMAGE", "VIDEO", "DOCUMENT"].includes(headerFormat.value))

const headerMediaRule = computed(() => props.mediaRules?.[headerFormat.value.toLowerCase()] ?? null)

const headerMediaForm = useForm<{ header_media: File | null }>({ header_media: null })

const onHeaderMediaSelect = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input?.files?.[0] ?? null
    const rule = headerMediaRule.value

    if (!file || !rule) return

    if (!rule.mime_types.includes(file.type)) {
        notify({
            title: trans("Failed"),
            text: trans("WhatsApp accepts :formats here.", { formats: rule.extensions.join(", ") }),
            type: "error",
        })
        input.value = ""
        return
    }

    if (file.size > rule.max_kb * 1024) {
        notify({
            title: trans("Failed"),
            text: trans("Maximum size is :size MB.", { size: Math.round(rule.max_kb / 1024) }),
            type: "error",
        })
        input.value = ""
        return
    }

    headerMediaForm.header_media = file
    headerMediaForm.post(route(props.headerMediaRoute.name, props.headerMediaRoute.parameters), {
        preserveScroll: true,
        forceFormData: true,
        onError: (errors) => {
            notify({
                title: trans("Failed"),
                text: errors.header_media ?? trans("The server refused the file — it may be larger than the upload limit."),
                type: "error",
            })
        },
        onFinish: () => (input.value = ""),
    })
}

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

const buttonTypeLabel = (type: string) =>
    type === "URL" ? trans("Link") : type === "PHONE_NUMBER" ? trans("Call") : trans("Quick reply")

// A quick reply has nowhere to go, so only links and calls carry a destination.
const buttonDestination = (button: TemplateButton) => button.url || button.phone_number || ""

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

                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-medium text-gray-600">{{ trans("Header") }}</span>
                            <span
                                class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] text-gray-500">
                                <FontAwesomeIcon v-if="headerIcon" :icon="headerIcon" class="text-[9px]" />
                                {{ headerFormatLabel }}
                            </span>
                        </div>

                        <div v-if="headerFormat === 'NONE'"
                            class="rounded-lg border border-dashed border-gray-200 px-3 py-2 text-xs text-gray-400">
                            {{ trans("This template was created without a header.") }}
                        </div>

                        <div v-else-if="headerFormat === 'TEXT'"
                            class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-500">
                            {{ template.header_text }}
                        </div>

                        <div v-else class="space-y-2">
                            <div v-if="template.header_media"
                                class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-2">
                                <div
                                    class="w-16 h-16 shrink-0 rounded-md overflow-hidden bg-white border border-gray-200 flex items-center justify-center p-1">
                                    <Image v-if="headerFormat === 'IMAGE' && template.header_media.url"
                                        :src="template.header_media.url"
                                        class="w-full h-full flex items-center justify-center"
                                        :style="{ width: '100%', height: '100%', objectFit: 'contain' }" />
                                    <FontAwesomeIcon v-else-if="headerIcon" :icon="headerIcon"
                                        class="text-xl text-gray-300" />
                                </div>
                                <span class="min-w-0 flex-1 truncate text-xs text-gray-500">
                                    {{ template.header_media.name }}
                                </span>
                            </div>

                            <Message v-else severity="warn" :closable="false" class="text-xs">
                                {{ trans("No file set — sending this template will fail until one is uploaded.") }}
                            </Message>

                            <label
                                class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 cursor-pointer hover:border-gray-400">
                                <FontAwesomeIcon :icon="faUpload" class="text-[11px]" />
                                {{ template.header_media ? trans("Replace file") : trans("Upload the file to send") }}
                                <input type="file" class="hidden" :accept="headerMediaRule?.accept"
                                    @change="onHeaderMediaSelect" />
                            </label>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-medium text-gray-600 mb-1">{{ trans("Body") }}</div>
                        <div
                            class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500 whitespace-pre-line">
                            {{ template.body }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-medium text-gray-600 mb-1">{{ trans("Footer") }}</div>
                        <div v-if="template.footer"
                            class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-400">
                            {{ template.footer }}
                        </div>
                        <div v-else
                            class="rounded-lg border border-dashed border-gray-200 px-3 py-2 text-xs text-gray-400">
                            {{ trans("This template was created without a footer.") }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-medium text-gray-600 mb-1">{{ trans("Buttons") }}</div>
                        <div v-if="!template.buttons?.length"
                            class="rounded-lg border border-dashed border-gray-200 px-3 py-2 text-xs text-gray-400">
                            {{ trans("This template was created without buttons.") }}
                        </div>
                        <div v-else class="space-y-2">
                        <div v-for="(button, index) in template.buttons" :key="index"
                            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                            <FontAwesomeIcon :icon="buttonIcon(button.type)" class="text-[11px] text-gray-400 shrink-0" />

                            <div class="min-w-0 flex-1">
                                <div class="text-sm text-gray-600">{{ button.text }}</div>
                                <div v-if="buttonDestination(button)"
                                    class="text-[11px] text-gray-400 truncate">
                                    {{ buttonDestination(button) }}
                                </div>
                            </div>

                            <span class="shrink-0 text-[10px] uppercase tracking-wide text-gray-400">
                                {{ buttonTypeLabel(button.type) }}
                            </span>
                            </div>
                        </div>
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
                                    <div v-if="needsHeaderMedia" class="p-1.5 pb-0">
                                        <div class="h-36 rounded-md bg-gray-100 flex items-center justify-center overflow-hidden">
                                            <Image v-if="headerFormat === 'IMAGE' && template.header_media?.url"
                                                :src="template.header_media.url" image-cover
                                                class="w-full h-full" />
                                            <FontAwesomeIcon v-else :icon="headerIcon ?? faImage"
                                                class="text-gray-300 text-3xl" />
                                        </div>
                                    </div>

                                    <div class="px-2.5 py-2 space-y-1">
                                        <div v-if="previewHeaderSegments.length"
                                            class="text-[14px] font-semibold text-[#111B21] leading-snug break-words">
                                            <template v-for="(segment, index) in previewHeaderSegments" :key="index">
                                                <span v-if="segment.kind === 'text'">{{ segment.value }}</span>
                                                <span v-else
                                                    class="inline-block rounded-full bg-[#E7F3FF] text-[#0091EA] font-normal text-[12px] px-2 py-0.5 mx-0.5 align-middle">
                                                    {{ segment.value }}
                                                </span>
                                            </template>
                                        </div>

                                        <div
                                            class="text-[14px] leading-[19px] text-[#111B21] whitespace-pre-wrap break-words">
                                            <template v-for="(segment, index) in previewBodySegments" :key="index">
                                                <span v-if="segment.kind === 'text'">{{ segment.value }}</span>
                                                <span v-else
                                                    class="inline-block rounded-full bg-[#E7F3FF] text-[#0091EA] text-[12px] px-2 py-0.5 mx-0.5 align-middle">
                                                    {{ segment.value }}
                                                </span>
                                            </template>
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
