<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faCopy, faSlidersH } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import { seoFieldHelp } from "@/Composables/seoFieldHelp"
import { htmlFields, productSettingActionsId, type SeoReview, type SeoSuggestionField } from "@/Components/Catalogue/ProductSetting/types"

const props = defineProps<{
    review: SeoReview
}>()

const emit = defineEmits<{
    openSetting: [field: string]
    validated: []
}>()

const suggestedFields = computed(() => props.review.fields.filter((field) => field.suggestion))
const total = computed(() => suggestedFields.value.length)
const canValidate = computed(() => props.review.can_validate && total.value > 0)
const isValidating = ref(false)
const isConfirmingValidate = ref(false)

const sections = computed(() => [
    { key: "search", title: ctrans("Search appearance"), hint: ctrans("Suggestions from the SEO team") },
    { key: "content", title: ctrans("Product content"), hint: ctrans("Name and descriptions") },
    { key: "structured_data", title: ctrans("Structured data (JSON-LD)"), hint: ctrans("Custom JSON-LD for this product page") },
]
    .map((section) => ({ ...section, fields: suggestedFields.value.filter((field) => field.group === section.key) }))
    .filter((section) => section.fields.length))

const busySuggestionId = ref<number | null>(null)

const isHtml = (field: SeoSuggestionField) => htmlFields.includes(field.field)

const copySuggestion = (field: SeoSuggestionField) => {
    navigator.clipboard.writeText(field.suggestion?.value ?? "").then(() =>
        notify({ title: ctrans("Copied"), text: ctrans("The suggestion for :field is on the clipboard", { field: field.label }), type: "success" })
    )
}

const notifyError = (errors: Record<string, string>) => notify({
    title: ctrans("Not saved"),
    text: Object.values(errors)[0] ?? ctrans("The suggestion could not be updated"),
    type: "error",
})

const implement = (field: SeoSuggestionField) => {
    const suggestion = field.suggestion
    if (!suggestion) {
        return
    }

    busySuggestionId.value = suggestion.id
    router.patch(route(suggestion.implement_route.name, suggestion.implement_route.parameters), {}, {
        preserveScroll: true,
        preserveState: true,
        onError: notifyError,
        onFinish: () => (busySuggestionId.value = null),
    })
}

const validate = () => router.post(route(props.review.validate_route.name, props.review.validate_route.parameters), {}, {
    preserveScroll: true,
    onStart: () => (isValidating.value = true),
    onSuccess: () => {
        isConfirmingValidate.value = false
        emit("validated")
    },
    onError: notifyError,
    onFinish: () => (isValidating.value = false),
})

const requestValidate = () => {
    if (props.review.pending > 0) {
        isConfirmingValidate.value = true
        return
    }

    validate()
}
</script>

<template>
    <div class="space-y-4">
        <Teleport defer :to="`#${productSettingActionsId}`">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[--app-accent-muted] bg-[--app-accent-soft] px-4 py-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">
                        {{ ctrans(":count SEO suggestions to implement", { count: String(total) }) }} · {{ ctrans("Webmaster") }}
                    </div>
                    <div class="text-sm text-gray-600">
                        {{ ctrans("For each one: copy the suggestion, open the setting and paste it, or apply it with Mark Implemented. Implemented: :done of :total.", { done: String(review.implemented), total: String(total) }) }}
                    </div>
                    <div v-if="total && !review.can_validate" class="text-sm font-semibold text-amber-800">
                        {{ ctrans("Only the webmaster can validate.") }}
                    </div>
                </div>
                <Button
                    type="tertiary"
                    :label="ctrans('Validate')"
                    :disabled="!canValidate"
                    :loading="isValidating && !isConfirmingValidate"
                    @click="requestValidate"
                >
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </Teleport>

        <Dialog v-model:visible="isConfirmingValidate" modal :header="ctrans('Validate with suggestions pending?')" class="w-full max-w-md">
            <p class="text-sm text-gray-600">
                {{ ctrans(":pending of :total suggestions are not marked as implemented. Validating will skip them and close the SEO review.", { pending: String(review.pending), total: String(total) }) }}
            </p>
            <template #footer>
                <Button type="tertiary" :label="ctrans('Cancel')" :disabled="isValidating" @click="isConfirmingValidate = false" />
                <Button type="primary" :label="ctrans('Validate anyway')" :loading="isValidating" @click="validate" />
            </template>
        </Dialog>

        <div v-if="!total" class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">
            {{ review.is_validated ? ctrans("The SEO review of this page has been validated") : ctrans("The SEO team has not sent suggestions for this page yet") }}
        </div>

        <section v-for="section in sections" :key="section.key" class="rounded-lg border border-gray-200 bg-white p-4">
            <header class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-900">{{ section.title }}</h3>
                <span class="text-xs text-gray-500">{{ section.hint }}</span>
            </header>
            <div class="space-y-3">
                <div
                    v-for="field in section.fields"
                    :key="field.field"
                    class="rounded-md border p-3"
                    :class="field.suggestion?.is_implemented ? 'border-green-200' : 'border-[--app-accent-muted]'"
                >
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-gray-800">{{ field.label }}<HelpTip v-if="seoFieldHelp(field.field)" :text="seoFieldHelp(field.field)!" /></span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="field.suggestion?.is_implemented ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
                        >{{ field.suggestion?.is_implemented ? ctrans("Implemented") : ctrans("Suggestion pending") }}</span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <div class="mb-1 text-xs font-medium text-gray-600">{{ ctrans("Current") }}</div>
                            <div
                                class="max-h-64 overflow-auto whitespace-pre-line break-words rounded bg-gray-50 px-3 py-2 text-sm text-gray-700"
                                :class="{ 'font-mono text-xs': field.group === 'structured_data' || field.field === 'url' }"
                            >
                                <template v-if="field.current">
                                    <div v-if="isHtml(field)" class="whitespace-normal [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1 [&_ul]:list-disc [&_ul]:pl-5" v-html="field.current" />
                                    <template v-else>{{ field.current }}</template>
                                </template>
                                <span v-else class="italic text-gray-400">{{ ctrans("Empty") }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 text-xs font-medium text-gray-600">{{ ctrans("SEO Suggestion") }}</div>
                            <div
                                class="max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-[--app-accent-soft] px-3 py-2 text-sm text-gray-800"
                                :class="{ 'font-mono text-xs': field.group === 'structured_data' || field.field === 'url' }"
                            >
                                <div v-if="isHtml(field)" class="whitespace-normal [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1 [&_ul]:list-disc [&_ul]:pl-5" v-html="field.suggestion?.value" />
                                <template v-else>{{ field.suggestion?.value }}</template>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button type="tertiary" :label="ctrans('Copy Suggestion')" @click="copySuggestion(field)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faCopy" class="text-gray-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button type="tertiary" :label="ctrans('Open Setting')" @click="emit('openSetting', field.field)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faSlidersH" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button
                            v-if="!field.suggestion?.is_implemented"
                            type="primary"
                            :label="ctrans('Mark Implemented')"
                            :disabled="!field.suggestion?.can_implement || busySuggestionId !== null"
                            :loading="busySuggestionId === field.suggestion?.id"
                            @click="implement(field)"
                        >
                            <template #icon>
                                <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
