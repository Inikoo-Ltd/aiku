<script setup lang="ts">
import { routeType } from "@/types/route"
import { computed, reactive, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import Dialog from "primevue/dialog"
import { Textarea } from "primevue"
import RichTextField from "@/Components/Catalogue/ProductSetting/RichTextField.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import { seoFieldHelp } from "@/Composables/seoFieldHelp"
import { htmlFields, type SeoReview, type SeoSuggestionField } from "@/Components/Catalogue/ProductSetting/types"

const props = defineProps<{
    review: SeoReview
    internalLinkRoute?: routeType | null
}>()

const emit = defineEmits<{
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

const allTasks = computed<ValidationTask[]>(() => props.review.fields.flatMap((field) => {
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
const tasks = computed(() => allTasks.value.filter((task) => task.field.group !== "structured_data"))
const total = computed(() => tasks.value.length)
const implementedCount = computed(() => tasks.value.filter((task) => task.isImplemented).length)
const canValidate = computed(() => props.review.can_validate && allTasks.value.length > 0)
const isValidating = ref(false)
const isConfirmingValidate = ref(false)

const sections = computed(() => [
    { key: "search", title: ctrans("Search appearance"), hint: ctrans("Suggestions from the SEO team") },
    { key: "content", title: ctrans("Product content"), hint: ctrans("Name and descriptions") },
]
    .map((section) => ({ ...section, tasks: tasks.value.filter((task) => task.field.group === section.key) }))
    .filter((section) => section.tasks.length))

const busyTaskKey = ref<string | null>(null)
const drafts = reactive<Record<string, string>>({})
watch(tasks, (tasks) => tasks.forEach((task) => (drafts[task.key] ??= task.value)), { immediate: true })
const taskErrors = ref<Record<string, string>>({})
const validateError = ref<string | null>(null)

const isHtml = (field: SeoSuggestionField) => htmlFields.includes(field.field)

const firstError = (errors: Record<string, string>) => Object.values(errors)[0] ?? ctrans("The suggestion could not be updated")

const implement = (task: ValidationTask) => {
    const suggestion = task.field.suggestion
    if (!suggestion) {
        return
    }

    busyTaskKey.value = task.key
    delete taskErrors.value[task.key]
    router.patch(route(suggestion.implement_route.name, suggestion.implement_route.parameters), { ...task.implementData, value: drafts[task.key] }, {
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
    if (total.value > implementedCount.value) {
        isConfirmingValidate.value = true
        return
    }

    validate()
}
</script>

<template>
    <div class="space-y-4">
        <Dialog v-model:visible="isConfirmingValidate" modal :header="ctrans('Finish with suggestions pending?')" class="w-full max-w-md">
            <p class="text-sm text-gray-600">
                {{ ctrans(":pending of :total suggestions are not applied. Finishing will skip them and close the SEO review.", { pending: String(total - implementedCount), total: String(total) }) }}
            </p>
            <template #footer>
                <Button type="tertiary" :label="ctrans('Cancel')" :disabled="isValidating" @click="isConfirmingValidate = false" />
                <Button type="primary" :label="ctrans('Save and finish anyway')" :loading="isValidating" @click="validate" />
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
                                    <div v-if="isHtml(task.field)" class="whitespace-normal [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1 [&_ul]:list-disc [&_ul]:pl-5" v-html="task.field.current" />
                                    <template v-else>{{ task.field.current }}</template>
                                </template>
                                <span v-else class="italic text-gray-400">{{ ctrans("Empty") }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 text-xs font-medium text-gray-600">{{ ctrans("SEO Suggestion") }}</div>
                            <template v-if="!task.isImplemented && task.field.suggestion?.can_implement">
                                <RichTextField v-if="isHtml(task.field)" v-model="drafts[task.key]" withHeadings :routeGetInternalLink="internalLinkRoute" />
                                <Textarea v-else v-model="drafts[task.key]" autoResize rows="2" fluid :class="{ 'font-mono': task.field.field === 'url' }" />
                            </template>
                            <div
                                v-else
                                class="max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-[--app-accent-soft] px-3 py-2 text-sm text-gray-800"
                                :class="{ 'font-mono text-xs': task.field.group === 'structured_data' || task.field.field === 'url' }"
                            >
                                <div v-if="isHtml(task.field)" class="whitespace-normal [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1 [&_ul]:list-disc [&_ul]:pl-5" v-html="task.value" />
                                <template v-else>{{ task.value }}</template>
                            </div>
                        </div>
                    </div>
                    <div v-if="!task.isImplemented" class="mt-3 flex flex-wrap gap-2">
                        <Button
                            type="tertiary"
                            :label="ctrans('Apply suggestion')"
                            :disabled="!task.field.suggestion?.can_implement || busyTaskKey !== null"
                            :loading="busyTaskKey === task.key"
                            @click="implement(task)"
                        >
                            <template #icon>
                                <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                    <p v-if="taskErrors[task.key]" class="mt-1 text-xs text-red-600">{{ taskErrors[task.key] }}</p>
                </div>
            </div>
        </section>

        <div v-if="allTasks.length" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[--app-accent-muted] bg-[--app-accent-soft] px-4 py-3">
            <div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ ctrans(":count SEO suggestions to implement", { count: String(total) }) }}
                </div>
                <div class="text-sm text-gray-600">
                    {{ ctrans("Read each suggestion and correct the wording if needed, then click Apply suggestion. (:done of :total done)", { done: String(implementedCount), total: String(total) }) }}
                </div>
                <div v-if="allTasks.length && !review.can_validate" class="text-sm font-semibold text-amber-800">
                    {{ ctrans("Only the webmaster can validate.") }}
                </div>
                <div v-if="validateError" class="text-xs text-red-600">{{ validateError }}</div>
            </div>
            <Button
                type="tertiary"
                :label="ctrans('Save and finish')"
                :disabled="!canValidate"
                :loading="isValidating && !isConfirmingValidate"
                @click="requestValidate"
            >
                <template #icon>
                    <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>
    </div>
</template>
