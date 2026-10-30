<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faPaperPlane, faPlus, faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import { seoFieldHelp } from "@/Composables/seoFieldHelp"
import { Textarea } from "primevue"
import RichTextField from "@/Components/Catalogue/ProductSetting/RichTextField.vue"
import KeywordsInput from "@/Components/Catalogue/ProductSetting/KeywordsInput.vue"
import StructuredDataList from "@/Components/Seo/StructuredDataList.vue"
import type { WebpageStructuredDataSource } from "@/Iris/Composables/useWebpageStructuredData"
import { htmlFields, productSettingActionsId, type SeoReview, type SeoSuggestionField } from "@/Components/Catalogue/ProductSetting/types"

const props = defineProps<{
    review: SeoReview
    canEdit: boolean
    shopCode: string
    structuredDataSource: WebpageStructuredDataSource | null
}>()

const emit = defineEmits<{
    sent: []
}>()

const MAX_KEYWORDS = 3

const keywordsOf = (text: string | null) => (text ?? "").split(",").map((keyword) => keyword.trim()).filter(Boolean)

const keywords = ref<string[]>([])
const suggestions = ref<Record<string, string>>({})
const structuredDataItems = ref<{ json_ld: string }[]>([])
const errors = ref<Record<string, string>>({})
const isSending = ref(false)

const structuredDataField = computed(() => props.review.fields.find((field) => field.group === "structured_data") ?? null)

const resetForm = () => {
    keywords.value = keywordsOf(props.review.keywords)
    suggestions.value = Object.fromEntries(
        props.review.fields.map((field) => [field.field, field.suggestion && !field.suggestion.is_implemented ? field.suggestion.value ?? "" : ""])
    )
    structuredDataItems.value = (structuredDataField.value?.suggestion?.items ?? [])
        .filter((item) => !item.is_implemented)
        .map((item) => ({ json_ld: item.json_ld }))
    errors.value = {}
}

resetForm()
watch(() => props.review, resetForm)

const isReadOnly = computed(() => !props.canEdit)

const isWritten = (field: SeoSuggestionField) => (suggestions.value[field.field] ?? "").replace(/<[^>]*>/g, "").trim() !== ""
const textFields = computed(() => props.review.fields.filter((field) => field.group !== "structured_data"))
const isHtml = (field: SeoSuggestionField) => htmlFields.includes(field.field)

const areKeywordsChanged = computed(() => keywords.value.join(", ") !== keywordsOf(props.review.keywords).join(", "))
const writtenStructuredDataItems = computed(() => structuredDataItems.value.filter((item) => item.json_ld.trim() !== ""))
const writtenCount = computed(() => textFields.value.filter(isWritten).length + writtenStructuredDataItems.value.length)

const isJsonInvalid = (value: string) => {
    try {
        return !!value.trim() && typeof JSON.parse(value) !== "object"
    } catch {
        return true
    }
}

const canSend = computed(() => (writtenCount.value > 0 || keywords.value.length > 0) && !structuredDataItems.value.some((item) => isJsonInvalid(item.json_ld)))

const sections = computed(() => [
    { key: "search", title: ctrans("Search appearance"), hint: ctrans("Meta title, meta description, URL and breadcrumb") },
    { key: "content", title: ctrans("Product content"), hint: ctrans("Name and descriptions shown in the :shop shop", { shop: props.shopCode }) },
].map((section) => ({ ...section, fields: textFields.value.filter((field) => field.group === section.key) })))

const send = () => {
    isSending.value = true
    errors.value = {}

    router.post(
        route(props.review.store_route.name, props.review.store_route.parameters),
        {
            keywords: keywords.value.join(", "),
            suggestions: Object.fromEntries(textFields.value.map((field) => [field.field, isWritten(field) ? suggestions.value[field.field] : ""])),
            structured_data: writtenStructuredDataItems.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => emit("sent"),
            onError: (validationErrors) => (errors.value = validationErrors as Record<string, string>),
            onFinish: () => (isSending.value = false),
        }
    )
}
</script>

<template>
    <div class="space-y-4">
        <Teleport defer :to="`#${productSettingActionsId}`">
            <div class="rounded-lg border border-[--app-accent-muted] bg-[--app-accent-soft] px-4 py-3">
                <div class="text-sm font-semibold text-gray-900">{{ ctrans("Set keywords and write your suggestions") }} · {{ ctrans("SEO team") }}</div>
                <div class="text-sm text-gray-600">
                    {{ ctrans("Write a suggestion next to each value you want changed. Leave it empty if the current value is fine, then send them to the webmaster at the bottom of the page.") }}
                    <template v-if="review.pending">{{ ctrans(":count are waiting for the webmaster; sending again replaces them.", { count: String(review.pending) }) }}</template>
                </div>
            </div>
        </Teleport>

        <p v-if="errors.suggestions" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errors.suggestions }}</p>
        <p v-if="!canEdit" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
            {{ ctrans("Only the SEO team can write suggestions for this shop.") }}
        </p>

        <section class="rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-3 font-semibold text-gray-900">1 · {{ ctrans("Targeted keywords") }}</h3>
            <div class="rounded-md border border-gray-200 p-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-gray-800">{{ ctrans("Targeted keywords") }}<HelpTip :text="seoFieldHelp('keywords')!" /></span>
                    <span v-if="areKeywordsChanged" class="flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />{{ ctrans("Suggestion written") }}
                    </span>
                </div>
                <label for="seo-keywords" class="block text-sm font-medium text-gray-800">{{ ctrans("Add 1–:max keywords relevant to this page", { max: String(MAX_KEYWORDS) }) }}</label>
                <p class="mb-2 text-xs text-gray-500">{{ ctrans("Type one keyword, or several separated by commas, then press Add.") }}</p>
                <KeywordsInput v-model="keywords" inputId="seo-keywords" :max="MAX_KEYWORDS" :disabled="isReadOnly" :invalid="!!errors.keywords" />
                <p v-if="errors.keywords" class="mt-1 text-xs text-red-600">{{ errors.keywords }}</p>
            </div>
        </section>

        <section v-for="(section, sectionIndex) in sections" :key="section.key" class="rounded-lg border border-gray-200 bg-white p-4">
            <header class="mb-3 flex items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-gray-900">{{ sectionIndex + 2 }} · {{ section.title }}</h3>
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                        :class="section.fields.some(isWritten) ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ ctrans(":written of :total suggestions written", { written: String(section.fields.filter(isWritten).length), total: String(section.fields.length) }) }}
                    </span>
                </div>
                <span class="text-xs text-gray-500">{{ section.hint }}</span>
            </header>
            <div class="space-y-3">
                <div v-for="field in section.fields" :key="field.field" class="rounded-md border border-gray-200 p-3">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-gray-800">{{ field.label }}<HelpTip v-if="seoFieldHelp(field.field)" :text="seoFieldHelp(field.field)!" /></span>
                        <span v-if="isWritten(field)" class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                            {{ ctrans("Suggestion written") }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <div class="mb-1 text-xs font-medium text-gray-600">{{ ctrans("Current") }}</div>
                            <div
                                class="max-h-48 overflow-auto whitespace-pre-line break-words rounded bg-gray-50 px-3 py-2 text-sm text-gray-700"
                                :class="{ 'font-mono text-xs': field.field === 'url' }"
                            >
                                <template v-if="field.current">
                                    <div v-if="isHtml(field)" class="[&_h1]:text-lg [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1 [&_ul]:list-disc [&_ul]:pl-5" v-html="field.current" />
                                    <template v-else>{{ field.current }}</template>
                                </template>
                                <span v-else class="italic text-gray-400">{{ ctrans("Empty") }}</span>
                            </div>
                        </div>
                        <div>
                            <label :for="`seo-suggestion-${field.field}`" class="mb-1 block text-xs font-medium text-gray-600">{{ ctrans("SEO Suggestion") }}</label>
                            <RichTextField
                                v-if="isHtml(field)"
                                :key="`${shopCode}-${field.field}-${review.pending}`"
                                v-model="suggestions[field.field]"
                                withHeadings
                                :placeholder="isReadOnly ? '' : ctrans('Write a suggestion, or leave empty if no change is needed')"
                                :disabled="isReadOnly"
                                :isError="!!errors[`suggestions.${field.field}`]"
                            />
                            <Textarea
                                v-else
                                :id="`seo-suggestion-${field.field}`"
                                v-model="suggestions[field.field]"
                                :rows="2"
                                autoResize
                                fluid
                                :class="{ 'font-mono text-xs leading-5': field.field === 'url' }"
                                :placeholder="isReadOnly ? '' : ctrans('Write a suggestion, or leave empty if no change is needed')"
                                :disabled="isReadOnly"
                                :invalid="!!errors[`suggestions.${field.field}`]"
                            />
                            <p v-if="errors[`suggestions.${field.field}`]" class="mt-1 text-xs text-red-600">{{ errors[`suggestions.${field.field}`] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="structuredDataField" class="rounded-lg border border-gray-200 bg-white p-4">
            <header class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-900">{{ sections.length + 2 }} · {{ ctrans("Structured data (JSON-LD)") }}<HelpTip :text="seoFieldHelp('structured_data')!" /></h3>
                <span class="text-xs text-gray-500">{{ ctrans("Added to the page when you send, without webmaster validation") }}</span>
            </header>
            <div class="space-y-3">
                <StructuredDataList :source="structuredDataSource" :customJson="structuredDataField.current" />

                <div v-for="(item, index) in structuredDataItems" :key="`structured-data-${index}`"
                    class="space-y-2 rounded-md border border-[--app-accent-muted] bg-[--app-accent-soft] p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label :for="`seo-suggestion-structured_data-${index}`" class="text-sm font-semibold text-gray-800">
                            {{ ctrans("New structured data :number", { number: String(index + 1) }) }}
                        </label>
                        <Button v-if="!isReadOnly" type="tertiary" size="xs" :label="ctrans('Discard')" @click="structuredDataItems.splice(index, 1)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                    <Textarea :id="`seo-suggestion-structured_data-${index}`" v-model="item.json_ld" rows="10" spellcheck="false" wrap="off" fluid
                        class="font-mono text-xs leading-5" :placeholder="ctrans('Paste or write JSON-LD here')" :disabled="isReadOnly"
                        :invalid="isJsonInvalid(item.json_ld) || !!errors[`structured_data.${index}.json_ld`]" />
                    <p v-if="isJsonInvalid(item.json_ld) || errors[`structured_data.${index}.json_ld`]" class="text-xs text-red-600">
                        {{ errors[`structured_data.${index}.json_ld`] ?? ctrans("This is not valid JSON") }}
                    </p>
                </div>

                <Button v-if="!isReadOnly" type="tertiary" :label="ctrans('Add new structured data')" @click="structuredDataItems.push({ json_ld: '' })">
                    <template #icon>
                        <FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </section>

        <div v-if="!isReadOnly" class="sticky bottom-0 z-10 flex flex-wrap items-center justify-end gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-md">
            <span class="text-sm text-gray-600">{{ ctrans(":count suggestions written", { count: String(writtenCount) }) }}</span>
            <Button
                type="tertiary"
                :label="ctrans('Send suggestions to webmaster')"
                :disabled="!canSend || isSending"
                :loading="isSending"
                @click="send"
            >
                <template #icon>
                    <FontAwesomeIcon :icon="faPaperPlane" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>
    </div>
</template>
