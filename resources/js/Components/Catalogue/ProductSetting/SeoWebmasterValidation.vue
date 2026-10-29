<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
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

type ValidationTask = {
    key: string
    field: SeoSuggestionField
    label: string
    value: string
    why: string | null
    isImplemented: boolean
    implementData: Record<string, number>
}

const tasks = computed<ValidationTask[]>(() => props.review.fields.flatMap((field) => {
    const suggestion = field.suggestion
    if (!suggestion) {
        return []
    }

    if (suggestion.items) {
        return suggestion.items.map((item, index) => ({
            key: `${field.field}-${index}`,
            field,
            label: ctrans("New structured data :number", { number: String(index + 1) }),
            value: item.json_ld,
            why: item.why,
            isImplemented: item.is_implemented,
            implementData: { item: index },
        }))
    }

    return [{ key: field.field, field, label: field.label, value: suggestion.value ?? "", why: null, isImplemented: suggestion.is_implemented, implementData: {} }]
}))
const total = computed(() => tasks.value.length)
const canValidate = computed(() => props.review.can_validate && total.value > 0)
const isValidating = ref(false)
const isConfirmingValidate = ref(false)

const sections = computed(() => [
    { key: "search", title: ctrans("Search appearance"), hint: ctrans("Suggestions from the SEO team") },
    { key: "content", title: ctrans("Product content"), hint: ctrans("Name and descriptions") },
    { key: "structured_data", title: ctrans("Structured data (JSON-LD)"), hint: ctrans("Custom JSON-LD for this product page") },
]
    .map((section) => ({ ...section, tasks: tasks.value.filter((task) => task.field.group === section.key) }))
    .filter((section) => section.tasks.length))

const busyTaskKey = ref<string | null>(null)
const copiedTaskKey = ref<string | null>(null)
const taskErrors = ref<Record<string, string>>({})
const validateError = ref<string | null>(null)

const isHtml = (field: SeoSuggestionField) => htmlFields.includes(field.field)

const firstError = (errors: Record<string, string>) => Object.values(errors)[0] ?? ctrans("The suggestion could not be updated")

const copySuggestion = (task: ValidationTask) => {
    navigator.clipboard.writeText(task.value).then(() => (copiedTaskKey.value = task.key))
}

const implement = (task: ValidationTask) => {
    const suggestion = task.field.suggestion
    if (!suggestion) {
        return
    }

    busyTaskKey.value = task.key
    delete taskErrors.value[task.key]
    router.patch(route(suggestion.implement_route.name, suggestion.implement_route.parameters), task.implementData, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => (taskErrors.value[task.key] = firstError(errors)),
        onFinish: () => (busyTaskKey.value = null),
    })
}

const validate = () => router.post(route(props.review.validate_route.name, props.review.validate_route.parameters), {}, {
    preserveScroll: true,
    onStart: () => {
        isValidating.value = true
        validateError.value = null
    },
    onSuccess: () => {
        isConfirmingValidate.value = false
        emit("validated")
    },
    onError: (errors) => (validateError.value = firstError(errors)),
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
                    <div v-if="validateError" class="text-xs text-red-600">{{ validateError }}</div>
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
                    v-for="task in section.tasks"
                    :key="task.key"
                    class="rounded-md border p-3"
                    :class="task.isImplemented ? 'border-green-200' : 'border-[--app-accent-muted]'"
                >
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-gray-800">{{ task.label }}<HelpTip v-if="seoFieldHelp(task.field.field)" :text="seoFieldHelp(task.field.field)!" /></span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="task.isImplemented ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
                        >{{ task.isImplemented ? ctrans("Implemented") : ctrans("Suggestion pending") }}</span>
                    </div>
                    <p v-if="task.why" class="mb-2 text-xs text-gray-600">{{ ctrans("Why") }}: {{ task.why }}</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <div class="mb-1 text-xs font-medium text-gray-600">{{ ctrans("Current") }}</div>
                            <div
                                class="max-h-64 overflow-auto whitespace-pre-line break-words rounded bg-gray-50 px-3 py-2 text-sm text-gray-700"
                                :class="{ 'font-mono text-xs': task.field.group === 'structured_data' || task.field.field === 'url' }"
                            >
                                <template v-if="task.field.current">
                                    <div v-if="isHtml(task.field)" class="whitespace-normal [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1 [&_ul]:list-disc [&_ul]:pl-5" v-html="task.field.current" />
                                    <template v-else>{{ task.field.current }}</template>
                                </template>
                                <span v-else class="italic text-gray-400">{{ ctrans("Empty") }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 text-xs font-medium text-gray-600">{{ ctrans("SEO Suggestion") }}</div>
                            <div
                                class="max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-[--app-accent-soft] px-3 py-2 text-sm text-gray-800"
                                :class="{ 'font-mono text-xs': task.field.group === 'structured_data' || task.field.field === 'url' }"
                            >
                                <div v-if="isHtml(task.field)" class="whitespace-normal [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1 [&_ul]:list-disc [&_ul]:pl-5" v-html="task.value" />
                                <template v-else>{{ task.value }}</template>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button type="tertiary" :label="copiedTaskKey === task.key ? ctrans('Copied') : ctrans('Copy Suggestion')" @click="copySuggestion(task)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faCopy" class="text-gray-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button type="tertiary" :label="ctrans('Open Setting')" @click="emit('openSetting', task.field.field)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faSlidersH" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button
                            v-if="!task.isImplemented"
                            type="primary"
                            :label="ctrans('Mark Implemented')"
                            :disabled="!task.field.suggestion?.can_implement || busyTaskKey !== null"
                            :loading="busyTaskKey === task.key"
                            @click="implement(task)"
                        >
                            <template #icon>
                                <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                    <p v-if="taskErrors[task.key]" class="mt-1 text-xs text-red-600">{{ taskErrors[task.key] }}</p>
                </div>
            </div>
        </section>
    </div>
</template>
