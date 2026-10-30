<script setup lang="ts">
import { computed, ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import { Checkbox, Message, Textarea } from "primevue"
import RichTextField from "@/Components/Catalogue/ProductSetting/RichTextField.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faPaperPlane, faPlus, faTrashAlt, faExclamationTriangle } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import WorkflowSteps from "@/Components/DataDisplay/WorkflowSteps.vue"
import KeywordsInput from "@/Components/Catalogue/ProductSetting/KeywordsInput.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import { seoFieldHelp } from "@/Composables/seoFieldHelp"
import { ctrans } from "@/Composables/useTrans"
import StructuredDataList from "@/Components/Seo/StructuredDataList.vue"
import type { WebpageStructuredDataSource } from "@/Iris/Composables/useWebpageStructuredData"
import { routeType } from "@/types/route"

interface Check {
    ok: boolean
    note: string
}

const props = defineProps<{
    data: {
        can_review: boolean
        store_route: routeType
        sent_count: number
        is_online: boolean
        keywords: string
        checks: Record<string, Check>
        fields: { key: string, label: string, current: string | null, suggestion: string, is_html: boolean, is_long: boolean }[]
        faq: { question: string, answer: string, suggested_question: string, suggested_answer: string, remove: boolean }[]
        new_faq: { question: string, answer: string }[]
        structured_data: string
        structured_data_suggestions: { json_ld: string }[]
    }
    pageScore: {
        steps: { key: string, label: string, owner: string, is_done: boolean }[]
        passed: number
        total: number
        areas: { key: string, label: string, checks: { key: string, label: string, is_passed: boolean | null }[] }[]
    } | null
    structuredDataSource: WebpageStructuredDataSource | null
    canOpenValidation: boolean
}>()

const emit = defineEmits<{
    openWebmasterValidation: []
}>()

const form = useForm({
    keywords: props.data.keywords,
    suggestions: Object.fromEntries(props.data.fields.map((field) => [field.key, field.suggestion])) as Record<string, string>,
    faq: {
        items: props.data.faq.map((item) => ({ question: item.suggested_question, answer: item.suggested_answer, remove: item.remove })),
        new: props.data.new_faq.map((item) => ({ question: item.question, answer: item.answer })),
    },
    structured_data: props.data.structured_data_suggestions.map((item) => ({ json_ld: item.json_ld })),
})

const isFilled = (value?: string | null) => !!value?.replace(/<[^>]*>/g, "").replace(/&nbsp;/g, " ").trim()

const MAX_KEYWORDS = 3
const keywordsOf = (text: string | null) => (text ?? "").split(",").map((keyword) => keyword.trim()).filter(Boolean)
const keywordList = computed({
    get: () => keywordsOf(form.keywords),
    set: (keywords: string[]) => (form.keywords = keywords.join(", ")),
})
const areKeywordsChanged = computed(() => keywordList.value.join(", ") !== keywordsOf(props.data.keywords).join(", "))

const writtenCount = computed(() =>
    Object.values(form.suggestions).filter(isFilled).length
    + form.faq.items.filter((item) => item.remove || isFilled(item.question) || isFilled(item.answer)).length
    + form.faq.new.filter((item) => isFilled(item.question)).length
    + form.structured_data.filter((item) => isFilled(item.json_ld)).length
)

const steps = computed(() => (props.pageScore?.steps ?? []).map((step) => ({ key: step.key, label: step.label, owner: step.owner, done: step.is_done })))
const currentStep = computed(() => steps.value.findIndex((step) => !step.done) + 1)

const errorMessages = computed(() => Object.values(form.errors))

const checkStructuredData = (value: string) => {
    const text = value.trim()
    if (!text) {
        return null
    }

    try {
        const json = JSON.parse(text)
        const nodes = [].concat(json?.["@graph"] ?? json)
        const types: string[] = []
        const collectTypes = (node: any) => {
            if (Array.isArray(node)) {
                node.forEach(collectTypes)
            } else if (node && typeof node === "object") {
                if (node["@type"]) {
                    types.push(...[].concat(node["@type"]).map(String))
                }
                Object.values(node).forEach(collectTypes)
            }
        }
        collectTypes(json)

        return {
            isValid: true,
            types: [...new Set(nodes.map((node: any) => node?.["@type"]).filter(Boolean))].join(", ") || ctrans("none"),
            isOverwritten: types.some((type) => type === "Product" || type === "ProductGroup"),
        }
    } catch (error: any) {
        return { isValid: false, error: error.message }
    }
}

const structuredDataChecks = computed(() => form.structured_data.map((item) => checkStructuredData(item.json_ld)))

const isSent = ref(false)

const send = () => {
    isSent.value = false
    form.post(route(props.data.store_route.name, props.data.store_route.parameters), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isSent.value = true
            form.structured_data = []
        },
    })
}

const faqParts = [
    { key: "question" as const, label: ctrans("Question"), suggestedKey: "suggested_question" as const },
    { key: "answer" as const, label: ctrans("Answer"), suggestedKey: "suggested_answer" as const },
]
</script>

<template>
    <div class="flex flex-col gap-4">
        <WorkflowSteps v-if="steps.length" :steps="steps" :current="currentStep" :selectableKeys="canOpenValidation ? ['implemented'] : []"
            @select="emit('openWebmasterValidation')" />

        <section class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[--app-accent-muted] bg-[--app-accent-soft] px-4 py-3">
            <div class="flex flex-col gap-0.5">
                <h2 class="text-sm font-semibold text-gray-900">{{ ctrans("Set keywords and write your suggestions") }} · {{ ctrans("SEO team") }}</h2>
                <p class="text-sm text-gray-600">
                    {{ ctrans("Write a suggestion next to each value you want changed. Leave it empty if the current value is fine, then send them to the webmaster at the bottom of the page.") }}
                </p>
                <p v-if="data.sent_count" class="text-sm text-gray-600">
                    {{ ctrans("Suggestions sent earlier are waiting for the webmaster. Sending again replaces them.") }}
                </p>
                <p v-if="!data.can_review" class="text-sm font-semibold text-amber-800">{{ ctrans("Only the SEO team can write suggestions.") }}</p>
            </div>
        </section>

        <Message v-if="errorMessages.length" severity="error">
            <ul>
                <li v-for="message in errorMessages" :key="message">{{ message }}</li>
            </ul>
        </Message>

        <fieldset :disabled="!data.can_review || form.processing" class="flex min-w-0 flex-col gap-4">              
            <section class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-gray-900">{{ ctrans("Targeted keywords") }}</h2>
                    <span class="text-xs text-gray-500">{{ ctrans("Owned by the SEO team") }}</span>
                </div>
                <article class="flex flex-col gap-2 rounded-md border border-gray-200 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Targeted keywords") }}<HelpTip :text="seoFieldHelp('keywords')!" /></h3>
                        <span v-if="areKeywordsChanged" class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">
                            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />{{ ctrans("Suggestion written") }}
                        </span>
                    </div>
                    <div>
                        <label for="seo-review-keywords" class="block text-sm font-medium text-gray-800">{{ ctrans("Add 1–:max keywords relevant to this page", { max: String(MAX_KEYWORDS) }) }}</label>
                        <p class="text-xs text-gray-500">{{ ctrans("Type one keyword, or several separated by commas, then press Add.") }}</p>
                    </div>
                    <KeywordsInput v-model="keywordList" inputId="seo-review-keywords" :max="MAX_KEYWORDS" :disabled="!data.can_review" />
                </article>
            </section>

            <section class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-gray-900">{{ ctrans("Page content") }}</h2>
                    <span class="text-xs text-gray-500">{{ ctrans("Filled in by the webmaster when the page is created") }}</span>
                </div>
                <article v-for="field in data.fields" :key="field.key" class="flex flex-col gap-2 rounded-md border border-gray-200 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-col gap-0.5">
                            <h3 class="text-sm font-semibold text-gray-800">{{ field.label }}<HelpTip v-if="seoFieldHelp(field.key)" :text="seoFieldHelp(field.key)!" /></h3>
                            <span class="text-xs text-gray-500">{{ ctrans("Filled in by webmaster") }}</span>
                        </div>
                        <span v-if="data.checks[field.key]" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="data.checks[field.key].ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                            <FontAwesomeIcon :icon="data.checks[field.key].ok ? faCheck : faTimes" fixed-width aria-hidden="true" />
                            {{ data.checks[field.key].note }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="text-xs font-medium text-gray-600">{{ ctrans("Current") }}</span>
                            <div v-if="isFilled(field.current) && field.is_html" v-html="field.current"
                                class="max-h-64 overflow-auto break-words rounded bg-gray-50 px-3 py-2 text-sm text-gray-700 [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_h3]:font-semibold [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1" />
                            <div v-else-if="isFilled(field.current)" class="whitespace-pre-line break-words rounded bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ field.current }}</div>
                            <div v-else class="rounded bg-gray-50 px-3 py-2 text-sm italic text-gray-400">{{ ctrans("Empty") }}</div>
                        </div>
                        <div class="flex min-w-0 flex-col gap-1">
                            <label :for="`seo-review-${field.key}`" class="text-xs font-medium text-gray-600">{{ ctrans("SEO Suggestion") }}</label>
                            <RichTextField v-if="field.is_html" :id="`seo-review-${field.key}`" v-model="form.suggestions[field.key]" withHeadings
                                :placeholder="ctrans('Write a suggestion, or leave empty if no change is needed')" />
                            <Textarea v-else :id="`seo-review-${field.key}`" v-model="form.suggestions[field.key]" :rows="field.is_long ? 6 : 2" autoResize
                                :placeholder="ctrans('Write a suggestion, or leave empty if no change is needed')" class="w-full" />
                        </div>
                    </div>
                </article>
            </section>

            <section class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-col gap-0.5">
                        <h2 class="font-semibold text-gray-900">{{ ctrans("FAQ") }}<HelpTip :text="seoFieldHelp('faq')!" /></h2>
                        <span class="text-xs text-gray-500">{{ ctrans("Suggest changes per question, suggest removing one, or suggest new questions.") }}</span>
                    </div>
                    <span v-if="data.checks.faq" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                        :class="data.checks.faq.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                        <FontAwesomeIcon :icon="data.checks.faq.ok ? faCheck : faTimes" fixed-width aria-hidden="true" />
                        {{ data.checks.faq.note }}
                    </span>
                </div>

                <article v-for="(item, index) in data.faq" :key="`faq-${index}`" class="flex flex-col gap-2 rounded-md border border-gray-200 p-3">
                    <h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Question :number", { number: index + 1 }) }}</h3>
                    <div v-for="part in faqParts" :key="part.key" class="flex flex-col gap-1.5">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ part.label }}</span>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="flex min-w-0 flex-col gap-1">
                                <span class="text-xs font-medium text-gray-600">{{ ctrans("Current") }}</span>
                                <div v-if="isFilled(item[part.key])" v-html="item[part.key]" class="break-words rounded bg-gray-50 px-3 py-2 text-sm text-gray-700" />
                                <div v-else class="rounded bg-gray-50 px-3 py-2 text-sm italic text-gray-400">{{ ctrans("Empty") }}</div>
                            </div>
                            <div class="flex min-w-0 flex-col gap-1">
                                <label :for="`seo-review-faq-${part.key}-${index}`" class="text-xs font-medium text-gray-600">{{ ctrans("SEO Suggestion") }}</label>
                                <Textarea :id="`seo-review-faq-${part.key}-${index}`" v-model="form.faq.items[index][part.key]" :rows="part.key === 'answer' ? 4 : 2" autoResize
                                    :placeholder="ctrans('Leave empty if no change is needed')" class="w-full" />
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-700">
                        <Checkbox v-model="form.faq.items[index].remove" binary :inputId="`seo-review-faq-remove-${index}`" />
                        <label :for="`seo-review-faq-remove-${index}`">{{ ctrans("Suggest removing this question") }}</label>
                    </div>
                </article>

                <article v-for="(item, index) in form.faq.new" :key="`new-faq-${index}`" class="flex flex-col gap-2 rounded-md border border-gray-200 p-3">
                    <h3 class="text-sm font-semibold text-gray-800">{{ ctrans("New question") }}</h3>
                    <label :for="`seo-review-new-question-${index}`" class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Suggested question") }}</label>
                    <Textarea :id="`seo-review-new-question-${index}`" v-model="item.question" rows="2" autoResize class="w-full" />
                    <label :for="`seo-review-new-answer-${index}`" class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Suggested answer") }}</label>
                    <Textarea :id="`seo-review-new-answer-${index}`" v-model="item.answer" rows="4" autoResize class="w-full" />
                    <div>
                        <Button type="tertiary" :label="ctrans('Discard this new question')" @click="form.faq.new.splice(index, 1)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faTrashAlt" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                </article>

                <div>
                    <Button type="tertiary" :label="ctrans('Suggest a new question')" @click="form.faq.new.push({ question: '', answer: '' })">
                        <template #icon>
                            <FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex flex-col gap-0.5">
                    <h2 class="font-semibold text-gray-900">{{ ctrans("Structured data (JSON-LD)") }}<HelpTip :text="seoFieldHelp('structured_data')!" /></h2>
                    <span class="text-xs text-gray-500">{{ ctrans("Structured data generated by the system cannot be edited here. New JSON-LD is added to the page when you send, without webmaster validation.") }}</span>
                </div>

                <StructuredDataList :source="structuredDataSource" :customJson="data.structured_data" />

                <div v-for="(item, index) in form.structured_data" :key="`structured-data-${index}`"
                    class="flex flex-col gap-2 rounded-md border border-[--app-accent-muted] bg-[--app-accent-soft] p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-gray-800">{{ ctrans("New structured data :number", { number: index + 1 }) }}</span>
                        <Button type="tertiary" :label="ctrans('Discard')" @click="form.structured_data.splice(index, 1)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                    <label :for="`seo-review-structured-data-${index}`" class="text-xs font-medium text-gray-600">{{ ctrans("Suggested JSON-LD") }}</label>
                    <Textarea :id="`seo-review-structured-data-${index}`" v-model="item.json_ld" rows="10" spellcheck="false" wrap="off"
                        :placeholder="ctrans('Paste or write JSON-LD here')" class="w-full font-mono text-xs leading-5" />
                    <span v-if="structuredDataChecks[index]?.isValid" class="text-xs font-medium text-green-700">
                        {{ ctrans("Valid JSON · types: :types", { types: structuredDataChecks[index].types }) }}
                    </span>
                    <span v-else-if="structuredDataChecks[index]" class="text-xs font-medium text-red-700">
                        {{ ctrans("Invalid JSON: :error", { error: structuredDataChecks[index].error }) }}
                    </span>
                    <p v-if="form.errors[`structured_data.${index}.json_ld`]" class="text-xs text-red-600">{{ form.errors[`structured_data.${index}.json_ld`] }}</p>
                    <div v-if="structuredDataChecks[index]?.isOverwritten" class="flex gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
                        <span>{{ ctrans("@type 'ProductGroup' or 'Product' will have no effect, as it will be overwritten by the system.") }}</span>
                    </div>
                </div>

                <div>
                    <Button type="tertiary" :label="ctrans('Add new structured data')" @click="form.structured_data.push({ json_ld: '' })">
                        <template #icon>
                            <FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </section>
        </fieldset>

        <div v-if="data.can_review" class="sticky bottom-0 z-10 flex flex-wrap items-center justify-end gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-md">
            <span v-if="isSent" class="mr-auto text-sm font-semibold text-green-700">{{ ctrans("Suggestions sent. The webmaster can now implement them.") }}</span>
            <span class="text-sm text-gray-600">{{ ctrans(":count suggestions written", { count: String(writtenCount) }) }}</span>
            <Button type="tertiary" :label="ctrans('Send suggestions to webmaster')" :loading="form.processing" @click="send">
                <template #icon>
                    <FontAwesomeIcon :icon="faPaperPlane" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>
    </div>
</template>
