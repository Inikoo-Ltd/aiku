<script setup lang="ts">
import { computed, ref, nextTick, defineAsyncComponent, onMounted, onUnmounted } from "vue"
import { Head, useForm } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import {
    faPlus,
    faXmark,
    faImage,
    faVideo,
    faFilePdf,
    faLink,
    faPhone,
    faReply,
    faCircleInfo,
    faUpload,
    faCopy,
    faArrowUp,
    faArrowDown,
    faCheck,
    faArrowUpRightFromSquare,
    faFaceSmile,
} from "@fortawesome/free-solid-svg-icons"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import { Textarea, Dialog, Message } from "primevue"
import { capitalize } from "@/Composables/capitalize"
import { useCopyText } from "@/Composables/useCopyText"
import { notify } from "@kyvg/vue3-notification"

const EmojiPicker = defineAsyncComponent(() => import("@/Components/Messaging/EmojiPicker.vue"))

const props = defineProps<{
    title: string
    pageHead: object
    languages: { value: string; label: string; flag?: string | null; is_active?: boolean }[]
    mergeTags: { name: string; value: string; example: string; group: string }[]
    mediaRules: Record<string, { mime_types: string[]; extensions: string[]; max_kb: number; accept: string }>
    submitRoute: { name: string; parameters: (string | number)[] | Record<string, any> }
    isConfigured: boolean
    canUploadMedia: boolean
    businessName: string
}>()

// Static and dynamic links are the same WhatsApp type; splitting them here makes the
// choice obvious, and they are folded back into URL on submit.
type ButtonKind = "QUICK_REPLY" | "URL" | "URL_DYNAMIC" | "PHONE_NUMBER"

interface TemplateButton {
    type: ButtonKind
    text: string
    url?: string
    url_example?: string
    phone_number?: string
}

const form = useForm<{
    name: string
    category: "MARKETING" | "UTILITY"
    language: string
    header_format: "NONE" | "TEXT" | "IMAGE" | "VIDEO" | "DOCUMENT"
    header_text: string
    header_example: string
    header_media: File | null
    body: string
    footer: string
    buttons: TemplateButton[]
}>({
    name: "",
    category: "MARKETING",
    language: props.languages[0]?.value ?? "en_GB",
    header_format: "NONE",
    header_text: "",
    header_example: "",
    header_media: null,
    body: "",
    footer: "",
    buttons: [],
})

const CATEGORIES = [
    {
        value: "MARKETING",
        label: trans("Marketing"),
        hint: trans("Offers, promotions, announcements, newsletters. Customers can opt out."),
    },
    {
        value: "UTILITY",
        label: trans("Utility"),
        hint: trans("About an existing order or account — confirmations, updates, reminders."),
    },
] as const

const HEADER_FORMATS = [
    { value: "NONE", label: trans("None"), icon: null },
    { value: "TEXT", label: trans("Text"), icon: null },
    { value: "IMAGE", label: trans("Image"), icon: faImage },
    { value: "VIDEO", label: trans("Video"), icon: faVideo },
    { value: "DOCUMENT", label: trans("PDF"), icon: faFilePdf },
] as const

const BUTTON_TYPES = [
    {
        value: "QUICK_REPLY",
        label: trans("Quick reply"),
        icon: faReply,
        hint: trans("Send response text — e.g. \"Yes\" or \"More info\""),
    },
    {
        value: "URL",
        label: trans("Static URL"),
        icon: faArrowUpRightFromSquare,
        hint: trans("Static link to a webpage"),
    },
    {
        value: "URL_DYNAMIC",
        label: trans("Dynamic URL"),
        icon: faLink,
        hint: trans("Link ending in a placeholder, e.g. an order id"),
    },
    {
        value: "PHONE_NUMBER",
        label: trans("Phone number"),
        icon: faPhone,
        hint: trans("Initiates a call on click"),
    },
] as const

const buttonTypeOptions = BUTTON_TYPES.map((type) => ({ value: type.value, label: type.label }))

const buttonHint = (type: ButtonKind) => BUTTON_TYPES.find((entry) => entry.value === type)?.hint ?? ""

const buttonIcon = (type: ButtonKind) => BUTTON_TYPES.find((entry) => entry.value === type)?.icon ?? faReply

const isLinkButton = (type: ButtonKind) => type === "URL" || type === "URL_DYNAMIC"

const linkButtonCount = computed(() => form.buttons.filter((button) => isLinkButton(button.type)).length)

const moveButton = (index: number, offset: number) => {
    const target = index + offset

    if (target < 0 || target >= form.buttons.length) return

    const buttons = [...form.buttons]
    const [moved] = buttons.splice(index, 1)
    buttons.splice(target, 0, moved)
    form.buttons = buttons
}

// The name is what Meta stores, and it only accepts lowercase, digits and underscores.
// Normalising as the user types beats rejecting their input afterwards.
const templateName = computed({
    get: () => form.name,
    set: (value: string) => {
        form.name = (value ?? "")
            .toLowerCase()
            .replace(/[^a-z0-9_\s]/g, "")
            .replace(/\s+/g, "_")
    },
})

const placeholders = (text: string): number[] => {
    const found = [...(text ?? "").matchAll(/\{\{(\d+)\}\}/g)]
    return found.map((m) => Number(m[1]))
}

const bodyRef = ref<any>()

// PrimeVue's Textarea is a component, so the underlying element comes from $el.
const bodyElement = (): HTMLTextAreaElement | null => bodyRef.value?.$el ?? bodyRef.value ?? null

const isTagPickerOpen = ref(false)
const tagSearch = ref("")
const tagPickerContainer = ref<HTMLElement | null>(null)

const filteredTags = computed(() => {
    const term = tagSearch.value.trim().toLowerCase()

    return term ? props.mergeTags.filter((tag) => tag.name.toLowerCase().includes(term)) : props.mergeTags
})

// With this many tags a flat list is hard to scan, so the picker keeps the grouping the
// enum already defines: Customer, Shop, Order, Invoice, Delivery, Agent.
const groupedTags = computed(() => {
    const groups: { group: string; tags: typeof props.mergeTags }[] = []

    filteredTags.value.forEach((tag) => {
        const existing = groups.find((entry) => entry.group === tag.group)

        if (existing) {
            existing.tags.push(tag)
        } else {
            groups.push({ group: tag.group, tags: [tag] })
        }
    })

    return groups
})

const insertTag = (token: string) => {
    const el = bodyElement()
    const at = el?.selectionStart ?? form.body.length
    const end = el?.selectionEnd ?? at

    form.body = form.body.slice(0, at) + token + form.body.slice(end)
    isTagPickerOpen.value = false
    tagSearch.value = ""

    nextTick(() => {
        el?.focus()
        const caret = at + token.length
        el?.setSelectionRange(caret, caret)
    })
}

// Only the tags actually used, in first-appearance order — the same order the backend
// turns into {{1}}, {{2}} when it submits to Meta.
const usedTags = computed(() => {
    const found = [...form.body.matchAll(/\[([^\[\]]+)\]/g)].map((match) => match[1])
    const seen: string[] = []

    found.forEach((name) => {
        if (!seen.includes(name)) seen.push(name)
    })

    return seen
        .map((name) => props.mergeTags.find((tag) => tag.value === `[${name}]`))
        .filter(Boolean) as { name: string; value: string; example: string }[]
})

const unknownTags = computed(() => {
    const found = [...form.body.matchAll(/\[([^\[\]]+)\]/g)].map((match) => match[1])

    return [...new Set(found)].filter((name) => !props.mergeTags.some((tag) => tag.value === `[${name}]`))
})

const handleClickOutsideTags = (event: MouseEvent) => {
    if (isTagPickerOpen.value && tagPickerContainer.value && !tagPickerContainer.value.contains(event.target as Node)) {
        isTagPickerOpen.value = false
    }
}

// Meta reviews the template in the language it is written for, and a body in the wrong
// language is a common rejection, so every text field says which language it expects.
const languageLabel = computed(
    () => props.languages.find((language) => language.value === form.language)?.label ?? ""
)

const bodyPlaceholder = computed(() => trans("Enter text in :language", { language: languageLabel.value }))

const headerPlaceholder = computed(() =>
    trans("Add a short line of text to the header of your message in :language", { language: languageLabel.value })
)

const footerPlaceholder = computed(() =>
    trans("Add a short line of text to the bottom of your message in :language", { language: languageLabel.value })
)

/**
 * Wraps the selection in WhatsApp's markers, or drops an empty pair with the caret in
 * the middle so the agent can simply keep typing.
 */
const applyFormat = (marker: string, closing = marker) => {
    const el = bodyElement()

    if (!el) return

    const start = el.selectionStart ?? form.body.length
    const end = el.selectionEnd ?? start
    const selected = form.body.slice(start, end)

    form.body = form.body.slice(0, start) + marker + selected + closing + form.body.slice(end)

    nextTick(() => {
        el.focus()
        const caret = start + marker.length + selected.length
        el.setSelectionRange(caret, caret)
    })
}

const FORMAT_ACTIONS = [
    { label: "B", marker: "*", title: trans("Bold"), class: "font-bold" },
    { label: "I", marker: "_", title: trans("Italic"), class: "italic" },
    { label: "S", marker: "~", title: trans("Strikethrough"), class: "line-through" },
    { label: "</>", marker: "```", title: trans("Monospace"), class: "font-mono text-[10px]" },
]

const showEmojiPicker = ref(false)
const emojiPickerContainer = ref<HTMLElement | null>(null)

const pickEmoji = (emoji: string) => {
    const el = bodyElement()

    if (!el) {
        form.body += emoji
        return
    }

    const start = el.selectionStart ?? form.body.length
    const end = el.selectionEnd ?? start

    form.body = form.body.slice(0, start) + emoji + form.body.slice(end)

    nextTick(() => {
        el.focus()
        const caret = start + emoji.length
        el.setSelectionRange(caret, caret)
    })
}

const handleClickOutsideEmoji = (event: MouseEvent) => {
    if (showEmojiPicker.value && emojiPickerContainer.value && !emojiPickerContainer.value.contains(event.target as Node)) {
        showEmojiPicker.value = false
    }
}

onMounted(() => {
    document.addEventListener("click", handleClickOutsideEmoji)
    document.addEventListener("click", handleClickOutsideTags)
})

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutsideEmoji)
    document.removeEventListener("click", handleClickOutsideTags)
})

const isFormatHelpOpen = ref(false)

// WhatsApp renders these markers on the customer's phone; in Aiku the agent sees the raw
// characters, which is exactly why this cheatsheet is worth showing. Each row styles its
// own label so the effect is demonstrated rather than described.
const FORMAT_HELP = [
    { style: trans("Italic"), syntax: "_text_", class: "italic" },
    { style: trans("Bold"), syntax: "*text*", class: "font-bold" },
    { style: trans("Strikethrough"), syntax: "~text~", class: "line-through" },
    { style: trans("Monospace"), syntax: "```text```", class: "font-mono" },
    { style: trans("Bullet list"), syntax: "- text", class: "", prefix: "•" },
    { style: trans("Numbered list"), syntax: "1. text", class: "", prefix: "1." },
    { style: trans("Quote"), syntax: "> text", class: "", quote: true },
    { style: trans("Inline code"), syntax: "`text`", class: "font-mono text-[#c7254e] bg-[#f9f2f4] rounded px-1 py-0.5" },
]

const addButton = (type: ButtonKind) => {
    if (form.buttons.length >= 10) return

    form.buttons.push({
        type,
        text: "",
        url: type === "URL_DYNAMIC" ? "https://www.ancientwisdom.biz/order/{{1}}" : "",
        url_example: "",
        phone_number: "",
    })
}

const removeButton = (index: number) => form.buttons.splice(index, 1)

const mediaPreview = ref<string | null>(null)


const headerMediaRule = computed(() => {
    const key = form.header_format.toLowerCase()

    return props.mediaRules?.[key === "document" ? "document" : key] ?? null
})

const headerAccept = computed(() => headerMediaRule.value?.accept ?? "")

const onMediaSelect = (event: Event) => {
    const file = (event.target as HTMLInputElement)?.files?.[0] ?? null

    if (file && headerMediaRule.value) {
        const rule = headerMediaRule.value

        if (!rule.mime_types.includes(file.type)) {
            notify({
                title: trans("Failed"),
                text: trans("WhatsApp accepts :formats here.", { formats: rule.extensions.join(", ") }),
                type: "error",
            })
            ;(event.target as HTMLInputElement).value = ""
            return
        }

        if (file.size > rule.max_kb * 1024) {
            notify({
                title: trans("Failed"),
                text: trans("Maximum size is :size MB.", { size: Math.round(rule.max_kb / 1024) }),
                type: "error",
            })
            ;(event.target as HTMLInputElement).value = ""
            return
        }
    }

    form.header_media = file

    if (mediaPreview.value) URL.revokeObjectURL(mediaPreview.value)
    mediaPreview.value = file && form.header_format === "IMAGE" ? URL.createObjectURL(file) : null
}

type PreviewSegment = { kind: "text" | "tag"; value: string }

/**
 * The preview keeps merge tags visible as chips rather than swapping in a sample value:
 * the agent is checking the wording, and a real name in there would hide where the
 * variable actually sits.
 */
const toSegments = (text: string): PreviewSegment[] => {
    const names = props.mergeTags.map((tag) => tag.value.slice(1, -1))
    const segments: PreviewSegment[] = []
    const pattern = /\[([^[\]]+)\]/g

    let cursor = 0
    let match: RegExpExecArray | null

    while ((match = pattern.exec(text)) !== null) {
        if (!names.includes(match[1])) continue

        if (match.index > cursor) {
            segments.push({ kind: "text", value: text.slice(cursor, match.index) })
        }

        segments.push({ kind: "tag", value: match[1] })
        cursor = match.index + match[0].length
    }

    if (cursor < text.length) {
        segments.push({ kind: "text", value: text.slice(cursor) })
    }

    return segments
}

const previewBodySegments = computed(() =>
    toSegments(form.body || trans("Your message will appear here…"))
)

const previewHeaderSegments = computed(() =>
    form.header_format === "TEXT" ? toSegments(form.header_text) : []
)

const nameError = computed(() => {
    if (!form.name) return ""
    return /^[a-z0-9_]+$/.test(form.name) ? "" : trans("Only lowercase letters, numbers and underscores.")
})

const canSubmit = computed(() =>
    props.isConfigured
    && !!form.name
    && !nameError.value
    && !!form.body.trim()
    && unknownTags.value.length === 0
    && linkButtonCount.value <= 2
    && form.buttons.every((button) =>
        !!button.text.trim()
        && (!isLinkButton(button.type) || !!button.url?.trim())
        && (button.type !== "URL_DYNAMIC" || (!!button.url_example?.trim() && button.url!.trim().endsWith("{{1}}")))
        && (button.type !== "PHONE_NUMBER" || !!button.phone_number?.trim())
    )
)

const submit = () => {
    form.transform((data) => ({
        ...data,
        header_text: data.header_format === "TEXT" ? data.header_text : "",
        header_media: ["IMAGE", "VIDEO", "DOCUMENT"].includes(data.header_format) ? data.header_media : null,
        buttons: data.buttons.map((button) => ({
            ...button,
            type: button.type === "URL_DYNAMIC" ? "URL" : button.type,
        })),
    })).post(route(props.submitRoute.name, props.submitRoute.parameters), {
        preserveScroll: true,
        forceFormData: true,
    })
}

const now = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })

// These carry literal {{n}} tokens, which Vue would try to interpret if they sat in the
// template, so they are built here and only referenced above.
const COPY = {
    dynamicUrlHint: trans("The link must end with {{1}} — WhatsApp appends the value, it does not replace inside the URL."),
    tagsExplainer: trans("WhatsApp only understands numbered slots, so Aiku turns the names above into {{1}}, {{2}} when submitting. The samples are what Meta's reviewer sees."),
}

const variableToken = (index: number) => `{{${index + 1}}}`

// Accent colours come from the tenant theme (the layout exposes them as CSS variables),
// so the page follows whatever palette the workspace uses instead of a hardcoded brand.
const themeText = { color: "var(--theme-color-4)" }

const themeActiveCard = {
    borderColor: "var(--theme-color-4)",
    backgroundColor: "color-mix(in srgb, var(--theme-color-4) 5%, white)",
}

// WhatsApp's chat wallpaper, drawn inline rather than shipped as an asset: it is a few
// faint strokes, and an SVG data URI keeps the preview self-contained.
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
        backgroundSize: '120px 120px',
    }
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="w-full max-w-5xl mx-auto px-4 sm:px-6 py-8">
        <Message v-if="!isConfigured" severity="warn" :closable="false" class="mb-6">
            {{ trans("Set the WhatsApp WABA ID in the shop settings before creating templates.") }}
        </Message>

        <div class="flex flex-col lg:flex-row gap-10 xl:gap-14">
            <!-- LEFT: builder -->
            <div class="flex-1 min-w-0 lg:max-w-[600px]">
                <!-- Basics -->
                <section class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">{{ trans("Template name") }}</label>
                        <p class="text-xs text-gray-400 mt-0.5 mb-2">
                            {{ trans("The name is used for internal purposes only") }}
                        </p>
                        <PureInput v-model="templateName" placeholder="birthday_30_percent" />
                        <p v-if="nameError" class="mt-1.5 text-xs text-red-500">{{ nameError }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-800">{{ trans("Template category") }}</label>
                        <p class="text-xs text-gray-400 mt-0.5 mb-2">
                            {{ trans("Choose what kind of message this is — Meta reviews it against the category") }}
                        </p>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <button v-for="category in CATEGORIES" :key="category.value" type="button"
                                @click="form.category = category.value"
                                class="relative text-left rounded-xl border p-3.5 transition"
                                :class="form.category === category.value ? '' : 'border-gray-200 hover:border-gray-300'"
                                :style="form.category === category.value ? themeActiveCard : {}">
                                <span v-if="form.category === category.value"
                                    class="absolute top-3 right-3 w-4 h-4 rounded-full flex items-center justify-center text-white"
                                    :style="{ backgroundColor: 'var(--theme-color-4)' }">
                                    <FontAwesomeIcon :icon="faCheck" class="text-[8px]" />
                                </span>
                                <div class="text-sm font-medium text-gray-800 pr-6">{{ category.label }}</div>
                                <div class="text-xs text-gray-500 mt-1 leading-snug">{{ category.hint }}</div>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-800">{{ trans("Language") }}</label>
                        <p class="text-xs text-gray-400 mt-0.5 mb-2">
                            {{ trans("Languages your shops already use are listed first") }}
                        </p>
                        <PureMultiselect v-model="form.language" :options="languages" searchable
                            :placeholder="trans('Search language…')" />
                    </div>
                </section>

                <hr class="border-gray-100 my-8" />

                <!-- Header -->
                <section class="space-y-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">
                            {{ trans("Header") }}
                            <span class="ml-1.5 text-xs font-normal text-gray-400">{{ trans("Optional") }}</span>
                        </label>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ trans("A short title or a picture shown above the message") }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button v-for="format in HEADER_FORMATS" :key="format.value" type="button"
                            @click="form.header_format = format.value"
                            :disabled="!canUploadMedia && ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(format.value)"
                            class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs transition disabled:opacity-40 disabled:cursor-not-allowed"
                            :class="form.header_format === format.value ? 'font-medium' : 'border-gray-200 text-gray-600 hover:border-gray-300'"
                            :style="form.header_format === format.value ? { ...themeActiveCard, ...themeText } : {}">
                            <FontAwesomeIcon v-if="format.icon" :icon="format.icon" class="text-[11px]" />
                            {{ format.label }}
                        </button>
                    </div>

                    <p v-if="!canUploadMedia" class="flex items-start gap-1.5 text-xs text-gray-400">
                        <FontAwesomeIcon :icon="faCircleInfo" class="mt-0.5" />
                        {{ trans("Media headers need WHATSAPP_APP_ID configured, so only text headers are available right now.") }}
                    </p>

                    <div v-if="form.header_format === 'TEXT'" class="space-y-2">
                        <PureInput v-model="form.header_text" :maxLength="60" counter
                            :placeholder="headerPlaceholder" />
                        <div class="text-xs text-gray-400">
                            {{ trans("You can use one variable here — insert it from the message toolbar below.") }}
                        </div>
                    </div>

                    <div v-else-if="form.header_format !== 'NONE'" class="space-y-2">
                        <label
                            class="flex items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 px-4 py-6 text-xs text-gray-500 cursor-pointer hover:border-gray-400 hover:text-gray-600 transition">
                            <FontAwesomeIcon :icon="faUpload" class="text-sm" />
                            {{ form.header_media ? form.header_media.name : trans("Upload a sample file for Meta to review") }}
                            <input type="file" class="hidden" @change="onMediaSelect"
                                :accept="headerAccept" />
                        </label>
                        <p class="text-xs text-gray-400">
                            {{ trans("The real file is chosen when the template is sent.") }}
                        </p>
                    </div>
                </section>

                <hr class="border-gray-100 my-8" />

                <!-- Message -->
                <section class="space-y-3">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-800">{{ trans("Message") }}</label>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ trans("This is the text your customer receives") }}
                            </p>
                        </div>
                        <button type="button" @click="isFormatHelpOpen = true"
                            class="text-xs hover:underline shrink-0 pb-0.5" :style="themeText">
                            {{ trans("Format text") }}
                        </button>
                    </div>

                    <div class="rounded-lg border border-gray-300 focus-within:border-gray-400 transition-colors">
                        <Textarea ref="bodyRef" v-model="form.body" rows="6" maxlength="1024"
                            :placeholder="bodyPlaceholder"
                            class="w-full text-sm !border-0 !rounded-b-none !shadow-none focus:!ring-0" />

                        <div class="flex items-center gap-1 border-t border-gray-200 bg-gray-50 rounded-b-lg px-2 py-1.5">
                            <div ref="emojiPickerContainer" class="relative">
                                <button type="button" @click.stop="showEmojiPicker = !showEmojiPicker"
                                    v-tooltip="trans('Emoji')"
                                    class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-200 transition-colors"
                                    :class="showEmojiPicker ? 'bg-gray-200 text-gray-700' : 'text-gray-500'">
                                    <FontAwesomeIcon :icon="faFaceSmile" class="text-xs" />
                                </button>

                                <div v-if="showEmojiPicker" class="absolute bottom-full left-0 mb-1.5 z-30">
                                    <EmojiPicker @pick="pickEmoji" />
                                </div>
                            </div>

                            <span class="w-px h-4 bg-gray-300 mx-0.5"></span>

                            <button v-for="action in FORMAT_ACTIONS" :key="action.label" type="button"
                                @click="applyFormat(action.marker)" v-tooltip="action.title"
                                class="w-7 h-7 flex items-center justify-center rounded text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition-colors text-xs"
                                :class="action.class">
                                {{ action.label }}
                            </button>

                            <div ref="tagPickerContainer" class="relative ml-auto">
                                <button type="button" @click.stop="isTagPickerOpen = !isTagPickerOpen"
                                    class="inline-flex items-center gap-1 px-2 h-7 rounded text-xs hover:bg-gray-200 transition-colors"
                                    :style="themeText">
                                    <FontAwesomeIcon :icon="faPlus" class="text-[9px]" />
                                    {{ trans("Add variable") }}
                                </button>

                                <div v-if="isTagPickerOpen"
                                    class="absolute bottom-full right-0 mb-1.5 z-40 w-72 rounded-lg border border-gray-200 bg-white shadow-xl overflow-hidden">
                                    <div class="p-2 border-b border-gray-100">
                                        <input v-model="tagSearch" type="text" :placeholder="trans('Search…')" autofocus
                                            class="w-full rounded-md border-gray-200 text-xs focus:border-gray-300 focus:ring-0" />
                                    </div>
                                    <div class="max-h-72 overflow-y-auto py-1">
                                        <template v-for="entry in groupedTags" :key="entry.group">
                                            <div
                                                class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                                {{ entry.group }}
                                            </div>
                                            <button v-for="tag in entry.tags" :key="tag.value" type="button"
                                                @click="insertTag(tag.value)"
                                                class="w-full flex items-center justify-between gap-2 px-3 py-1.5 text-left text-xs hover:bg-gray-50 transition-colors">
                                                <span class="text-gray-700">{{ tag.name }}</span>
                                                <span class="text-[10px] text-gray-400 truncate max-w-[110px]">{{ tag.example }}</span>
                                            </button>
                                        </template>
                                        <div v-if="!filteredTags.length" class="px-3 py-3 text-xs text-gray-400 text-center">
                                            {{ trans("No variable found") }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-400">{{ trans("Variables are replaced with real values when sending.") }}</span>
                        <span class="text-gray-400">{{ form.body.length }}/1024</span>
                    </div>

                    <Message v-if="unknownTags.length" severity="error" :closable="false" class="text-xs">
                        {{ trans("Not a known variable:") }} {{ unknownTags.map((tag) => `[${tag}]`).join(", ") }}
                    </Message>

                    <div v-if="usedTags.length" class="rounded-xl bg-gray-50 p-4 space-y-2">
                        <div class="text-xs font-medium text-gray-600">
                            {{ trans("Variables in this message") }}
                        </div>
                        <div v-for="(tag, index) in usedTags" :key="tag.value"
                            class="flex items-center gap-3 text-xs">
                            <span class="w-8 shrink-0 font-mono text-gray-400">{{ variableToken(index) }}</span>
                            <span class="font-medium text-gray-700">{{ tag.name }}</span>
                            <span class="ml-auto text-gray-400 truncate">{{ tag.example }}</span>
                        </div>
                        <p class="text-[11px] text-gray-400 pt-1 border-t border-gray-200">
                            {{ COPY.tagsExplainer }}
                        </p>
                    </div>
                </section>



                <hr class="border-gray-100 my-8" />

                <!-- Footer -->
                <section class="space-y-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">
                            {{ trans("Footer") }}
                            <span class="ml-1.5 text-xs font-normal text-gray-400">{{ trans("Optional") }}</span>
                        </label>
                        <p class="text-xs text-gray-400 mt-0.5 mb-2">
                            {{ trans("A small line under the message, e.g. how to opt out") }}
                        </p>
                    </div>
                    <PureInput v-model="form.footer" :maxLength="60" counter
                        :placeholder="footerPlaceholder" />
                </section>

                <hr class="border-gray-100 my-8" />

                <!-- Buttons -->
                <section class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">
                            {{ trans("Buttons") }}
                            <span class="ml-1.5 text-xs font-normal text-gray-400">{{ trans("Optional, up to 10") }}</span>
                        </label>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ trans("Let the customer respond or act with one tap") }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button v-for="type in BUTTON_TYPES" :key="type.value" type="button" @click="addButton(type.value)"
                            v-tooltip="type.hint"
                            class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 px-3.5 py-1.5 text-xs text-gray-600 hover:border-gray-300 transition">
                            <FontAwesomeIcon :icon="type.icon" class="text-[11px] text-gray-400" />
                            {{ type.label }}
                        </button>
                    </div>

                    <Message v-if="linkButtonCount > 2" severity="error" :closable="false" class="text-xs">
                        {{ trans("WhatsApp allows at most two link buttons.") }}
                    </Message>

                    <div v-for="(button, index) in form.buttons" :key="index"
                        class="rounded-xl border border-gray-200 p-4 space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-sm font-semibold text-gray-800">
                                    {{ trans("Button") }} {{ index + 1 }}
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ trans("Shown in position :position of :total", { position: index + 1, total: form.buttons.length }) }}
                                </div>
                            </div>

                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" @click="moveButton(index, -1)" :disabled="index === 0"
                                    v-tooltip="trans('Move up')"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-30 disabled:hover:bg-transparent">
                                    <FontAwesomeIcon :icon="faArrowUp" class="text-[10px]" />
                                </button>
                                <button type="button" @click="moveButton(index, 1)"
                                    :disabled="index === form.buttons.length - 1" v-tooltip="trans('Move down')"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-30 disabled:hover:bg-transparent">
                                    <FontAwesomeIcon :icon="faArrowDown" class="text-[10px]" />
                                </button>
                                <button type="button" @click="removeButton(index)" v-tooltip="trans('Remove')"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-500">
                                    <FontAwesomeIcon :icon="faXmark" class="text-xs" />
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ trans("Type") }}</label>
                            <PureMultiselect v-model="button.type" :options="buttonTypeOptions" :caret="true" />
                            <p class="mt-1 text-xs text-gray-400">{{ buttonHint(button.type) }}</p>
                        </div>

                        <PureInput v-model="button.text" :maxLength="25" counter :placeholder="trans('Button label')" />

                        <template v-if="isLinkButton(button.type)">
                            <PureInput v-model="button.url" type="url"
                                :placeholder="button.type === 'URL_DYNAMIC'
                                    ? 'https://www.ancientwisdom.biz/order/{{1}}'
                                    : 'https://www.ancientwisdom.biz/promotion'" />

                            <template v-if="button.type === 'URL_DYNAMIC'">
                                <p class="text-xs"
                                    :class="button.url?.trim().endsWith('{{1}}') ? 'text-gray-400' : 'text-red-500'">
                                    {{ COPY.dynamicUrlHint }}
                                </p>
                                <PureInput v-model="button.url_example"
                                    :placeholder="trans('Sample link, e.g. https://www.ancientwisdom.biz/order/12345')" />
                            </template>
                        </template>

                        <PureInput v-if="button.type === 'PHONE_NUMBER'" v-model="button.phone_number" type="tel"
                            placeholder="+44 1234 567890" />
                    </div>
                </section>

                <Message v-if="Object.keys(form.errors).length" severity="error" :closable="false" class="mt-6">
                    <div v-for="(error, key) in form.errors" :key="key" class="text-xs">{{ error }}</div>
                </Message>

                <div class="mt-8 pt-5 border-t border-gray-100 flex items-center justify-end gap-4">
                    <span class="text-xs text-gray-400">
                        {{ trans("Meta reviews every template before it can be sent.") }}
                    </span>
                    <Button :label="trans('Submit for review')" :loading="form.processing" :disabled="!canSubmit"
                        @click="submit" />
                </div>
            </div>

            <!-- RIGHT: live preview -->
            <div class="w-full lg:w-[330px] shrink-0">
                <div class="lg:sticky lg:top-6 space-y-4">
                    <div class="rounded-2xl overflow-hidden border border-gray-200 shadow-sm">
                        <div class="flex items-center gap-2 bg-[#075E54] px-4 py-2.5 text-white">
                            <FontAwesomeIcon :icon="faWhatsapp" class="text-sm" />
                            <span class="text-sm font-semibold">{{ trans("Preview") }}</span>
                            <span class="ml-auto text-[11px] opacity-70 truncate max-w-[130px]">{{ businessName }}</span>
                        </div>

                        <div class="px-3.5 py-6 bg-[#ECE5DD]" :style="doodleStyle">
                            <div class="relative max-w-[90%]">
                                <!-- the little tail that makes it read as a WhatsApp bubble -->
                                <span class="absolute -left-1.5 top-0 w-3 h-3 bg-white"
                                    style="clip-path: polygon(100% 0, 100% 100%, 0 0)"></span>

                                <div class="relative rounded-lg rounded-tl-none bg-white shadow-[0_1px_1px_rgba(0,0,0,0.12)] overflow-hidden">
                                    <div class="p-1.5 pb-0" v-if="form.header_format !== 'NONE' && form.header_format !== 'TEXT'">
                                        <div v-if="form.header_format === 'IMAGE'"
                                            class="h-36 rounded-md bg-gray-100 flex items-center justify-center overflow-hidden">
                                            <img v-if="mediaPreview" :src="mediaPreview" class="h-full w-full object-cover" />
                                            <FontAwesomeIcon v-else :icon="faImage" class="text-gray-300 text-3xl" />
                                        </div>
                                        <div v-else-if="form.header_format === 'VIDEO'"
                                            class="h-36 rounded-md bg-gray-100 flex items-center justify-center">
                                            <FontAwesomeIcon :icon="faVideo" class="text-gray-300 text-3xl" />
                                        </div>
                                        <div v-else
                                            class="h-20 rounded-md bg-gray-100 flex items-center justify-center gap-2 text-gray-400">
                                            <FontAwesomeIcon :icon="faFilePdf" class="text-2xl" />
                                            <span class="text-[11px]">{{ trans("PDF") }}</span>
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

                                        <div class="text-[14px] leading-[19px] whitespace-pre-wrap break-words"
                                            :class="form.body ? 'text-[#111B21]' : 'text-gray-400 italic'">
                                            <template v-for="(segment, index) in previewBodySegments" :key="index">
                                                <span v-if="segment.kind === 'text'">{{ segment.value }}</span>
                                                <span v-else
                                                    class="inline-block rounded-full bg-[#E7F3FF] text-[#0091EA] text-[12px] px-2 py-0.5 mx-0.5 align-middle">
                                                    {{ segment.value }}
                                                </span>
                                            </template>
                                        </div>

                                        <div v-if="form.footer"
                                            class="text-[12px] text-[#667781] break-words pt-0.5">
                                            {{ form.footer }}
                                        </div>

                                        <div class="flex justify-end -mb-0.5">
                                            <span class="text-[10px] text-[#667781]">{{ now }}</span>
                                        </div>
                                    </div>

                                    <div v-if="form.buttons.length" class="border-t border-[#E9EDEF]">
                                        <div v-for="(button, index) in form.buttons" :key="index"
                                            class="flex items-center justify-center gap-1.5 border-b border-[#E9EDEF] last:border-b-0 py-2.5 text-[14px] text-[#0091EA] font-medium">
                                            <FontAwesomeIcon
                                                :icon="buttonIcon(button.type)"
                                                class="text-[11px]" />
                                            {{ button.text || trans("Button") }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-xs text-gray-500 leading-relaxed">
                        <div class="font-medium text-gray-600 mb-1.5">{{ trans("Before you submit") }}</div>
                        <ul class="list-disc list-inside space-y-1">
                            <li>{{ trans("Marketing templates need customers to have opted in.") }}</li>
                            <li>{{ trans("Avoid promises Meta cannot verify, and keep links on your own domain.") }}</li>
                            <li>{{ trans("Sample values are only for the reviewer, never sent to customers.") }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:visible="isFormatHelpOpen" modal :header="trans('Text formatting for WhatsApp')"
        :style="{ width: '30rem' }">
        <p class="text-xs text-gray-500 mb-3">
            {{ trans("Formatting only shows on WhatsApp. In Aiku you see the characters, your customer sees the styling.") }}
        </p>
        <div class="rounded-lg border border-gray-200 overflow-hidden">
            <div
                class="grid grid-cols-[1fr_auto_28px] items-center gap-3 bg-gray-50 px-3 py-2 text-[11px] font-medium text-gray-500 border-b border-gray-200">
                <span>{{ trans("Style") }}</span>
                <span>{{ trans("How to write it") }}</span>
                <span></span>
            </div>

            <button v-for="row in FORMAT_HELP" :key="row.syntax" type="button" @click="useCopyText(row.syntax)"
                class="group w-full grid grid-cols-[1fr_auto_28px] items-center gap-3 px-3 py-2.5 border-b border-gray-100 last:border-b-0 text-sm text-left hover:bg-gray-50 transition-colors">
                <span class="flex items-center gap-2 min-w-0"
                    :class="row.quote ? 'border-l-[3px] border-gray-300 pl-2 text-gray-500' : ''">
                    <span v-if="row.prefix" class="text-gray-500">{{ row.prefix }}</span>
                    <span :class="row.class" class="text-gray-800">{{ row.style }}</span>
                </span>
                <code class="text-xs text-gray-500 whitespace-nowrap">{{ row.syntax }}</code>
                <FontAwesomeIcon :icon="faCopy"
                    class="justify-self-end text-xs text-gray-300 group-hover:text-gray-500 transition-colors" />
            </button>
        </div>
    </Dialog>
</template>
