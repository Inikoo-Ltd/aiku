<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { Message, Textarea } from "primevue"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faPlus } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import WorkflowSteps from "@/Components/DataDisplay/WorkflowSteps.vue"
import { type FamilyEditCardData } from "@/Components/Showcases/Grp/FamilyEditCard.vue"
import RichTextField from "@/Components/Catalogue/ProductSetting/RichTextField.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import { seoFieldHelp } from "@/Composables/seoFieldHelp"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

interface Task {
    key: string
    card: string
    title: string | null
    is_new: boolean
    rows: { label: string | null, current: string | null, suggestion: string | null, is_html: boolean, is_code: boolean, input: string | null }[]
    note: string | null
    copy_text: string
    is_done: boolean
    can_implement: boolean
    implement_route: routeType
    implement_data: Record<string, number>
}

const props = defineProps<{
    data: {
        validate_route: routeType
        can_validate: boolean
        is_validated: boolean
        tasks: Task[]
        activity: { who: string, at: string, text: string }[]
    }
    sections: { title: string, subtitle: string, cards: FamilyEditCardData[] }[]
    pageScore: {
        steps: { key: string, label: string, owner: string, is_done: boolean }[]
        passed: number
        total: number
        areas: { key: string, label: string, checks: { key: string, label: string, is_passed: boolean | null }[] }[]
        findings: { type: string, label: string, description: string, is_error: boolean }[] | null
    } | null
}>()

const hiddenCards = ["seo_image", "structured_data"]
const tasks = computed(() => props.data.tasks.filter((task) => !hiddenCards.includes(task.card)))

const tasksByCard = computed(() => tasks.value.reduce<Record<string, Task[]>>((groups, task) => {
    (groups[task.card] ??= []).push(task)
    return groups
}, {}))
const visibleSections = computed(() => props.sections
    .map((section) => ({ ...section, cards: section.cards.filter((card) => tasksByCard.value[card.key ?? ""]?.length) }))
    .filter((section) => section.cards.length))
const doneCount = computed(() => tasks.value.filter((task) => task.is_done).length)
const pendingCount = computed(() => tasks.value.length - doneCount.value)
const isConfirmingValidate = ref(false)
const pendingIn = (section: { cards: FamilyEditCardData[] }) =>
    section.cards.flatMap((card) => tasksByCard.value[card.key ?? ""] ?? []).filter((task) => !task.is_done).length

const steps = computed(() => (props.pageScore?.steps ?? []).map((step) => ({ key: step.key, label: step.label, owner: step.owner, done: step.is_done })))
const currentStep = computed(() => steps.value.findIndex((step) => !step.done) + 1)

const isFilled = (value?: string | null) => !!value?.replace(/<[^>]*>/g, "").replace(/&nbsp;/g, " ").trim()

const busyKey = ref<string | null>(null)
const errorMessages = ref<string[]>([])

const visitOptions = (key: string) => ({
    preserveScroll: true,
    preserveState: true,
    onStart: () => busyKey.value = key,
    onFinish: () => busyKey.value = null,
    onError: (errors: Record<string, string>) => errorMessages.value = Object.values(errors),
    onSuccess: () => errorMessages.value = [],
})

const drafts = reactive<Record<string, Record<string, string>>>({})
watch(() => props.data.tasks, (tasks) => tasks.forEach((task) => {
    drafts[task.key] ??= Object.fromEntries(task.rows.filter((row) => row.input).map((row) => [row.input, row.suggestion ?? ""]))
}), { immediate: true })

const implement = (task: Task) => router.patch(
    route(task.implement_route.name, task.implement_route.parameters),
    { ...task.implement_data, ...drafts[task.key] },
    visitOptions(task.key),
)

const validate = () => {
    isConfirmingValidate.value = false
    router.post(
        route(props.data.validate_route.name, props.data.validate_route.parameters),
        {},
        visitOptions("validate"),
    )
}

const requestValidate = () => {
    if (pendingCount.value > 0) {
        isConfirmingValidate.value = true
        return
    }

    validate()
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <WorkflowSteps v-if="steps.length" :steps="steps" :current="currentStep" />

        <section class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[--app-accent-muted] bg-[--app-accent-soft] px-4 py-3">
            <div class="flex flex-[1_1_380px] flex-col gap-0.5">
                <template v-if="tasks.length">
                    <h2 class="text-sm font-semibold text-gray-900">
                        {{ ctrans(":count SEO suggestions to implement", { count: tasks.length }) }} · {{ ctrans("Webmaster") }}
                    </h2>
                    <p class="text-sm text-gray-600">
                        {{ ctrans("For each one: edit the suggestion if needed, then apply it with Mark implemented.") }}
                        {{ ctrans("Implemented: :done of :total.", { done: doneCount, total: tasks.length }) }}
                    </p>
                    <p v-if="!data.can_validate" class="text-sm font-semibold text-amber-800">{{ ctrans("Only the webmaster can validate.") }}</p>
                </template>
                <template v-else-if="data.is_validated">
                    <h2 class="text-sm font-semibold text-gray-900">{{ ctrans("SEO suggestions validated") }}</h2>
                    <p class="text-sm text-gray-600">{{ ctrans("The last SEO review is validated.") }}</p>
                </template>
                <template v-else>
                    <h2 class="text-sm font-semibold text-gray-900">{{ ctrans("No SEO suggestions to implement") }}</h2>
                    <p class="text-sm text-gray-600">{{ ctrans("Suggestions appear here once the SEO team sends its review.") }}</p>
                </template>
            </div>
            <Button v-if="data.tasks.length" type="tertiary" :label="ctrans('Validate')" :disabled="!data.can_validate"
                :loading="busyKey === 'validate'" @click="requestValidate">
                <template #icon>
                    <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </section>

        <Dialog v-model:visible="isConfirmingValidate" modal :header="ctrans('Validate with suggestions pending?')" class="w-full max-w-md">
            <p class="text-sm text-gray-600">
                {{ ctrans(":pending of :total suggestions are not marked as implemented. They will not be updated: validating skips them and closes the SEO review.", { pending: pendingCount, total: tasks.length }) }}
            </p>
            <template #footer>
                <Button type="tertiary" :label="ctrans('Cancel')" @click="isConfirmingValidate = false" />
                <Button type="primary" :label="ctrans('Validate anyway')" @click="validate" />
            </template>
        </Dialog>

        <Message v-if="errorMessages.length" severity="error">
            <ul>
                <li v-for="message in errorMessages" :key="message">{{ message }}</li>
            </ul>
        </Message>

        <div class="flex flex-col gap-4">
            <div class="flex min-w-0 flex-col gap-4">
                <section v-for="(section, sectionIndex) in visibleSections" :key="sectionIndex"
                    class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-semibold text-gray-900">{{ section.title }}</h2>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs text-gray-500">{{ section.subtitle }}</span>
                            <span v-if="pendingIn(section)" class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                {{ ctrans(":count suggestions pending", { count: pendingIn(section) }) }}
                            </span>
                        </div>
                    </div>

                    <template v-for="(card, cardIndex) in section.cards" :key="cardIndex">
                        <template v-if="card.key && tasksByCard[card.key]?.length">
                            <article v-for="task in tasksByCard[card.key]" :key="task.key" class="flex flex-col gap-2 rounded-md border p-3"
                                :class="task.is_done ? 'border-green-200' : 'border-[--app-accent-muted]'">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-col gap-0.5">
                                        <h3 class="text-sm font-semibold text-gray-800">
                                            {{ task.title ?? card.label }}
                                            <HelpTip v-if="seoFieldHelp(card.key)" :text="seoFieldHelp(card.key)!" />
                                        </h3>
                                        <span v-if="!task.title && card.owner" class="text-xs text-gray-500">{{ card.owner }}</span>
                                    </div>
                                    <span v-if="task.is_done" class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">
                                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                                        {{ ctrans("Implemented") }}
                                    </span>
                                    <span v-else-if="task.is_new" class="rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">{{ ctrans("New question") }}</span>
                                    <span v-else-if="!task.title && card.check" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="card.check.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                        <FontAwesomeIcon :icon="card.check.ok ? faCheck : faTimes" fixed-width aria-hidden="true" />
                                        {{ card.check.note }}
                                    </span>
                                </div>

                                <div v-for="(row, rowIndex) in task.rows" :key="rowIndex" class="flex flex-col gap-1.5">
                                    <span v-if="row.label" class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ row.label }}</span>
                                    <div class="grid grid-cols-1 gap-3" :class="{ 'md:grid-cols-2': !row.is_code }">
                                        <div class="flex min-w-0 flex-col gap-1">
                                            <span class="text-xs font-medium text-gray-600">{{ row.is_code ? ctrans("Current custom JSON-LD") : ctrans("Current") }}</span>
                                            <pre v-if="row.is_code && isFilled(row.current)" class="m-0 max-h-64 overflow-auto rounded bg-gray-50 px-3 py-2 font-mono text-xs leading-5 text-gray-700">{{ row.current }}</pre>
                                            <div v-else-if="isFilled(row.current) && row.is_html" v-html="row.current"
                                                class="max-h-64 overflow-auto break-words rounded bg-gray-50 px-3 py-2 text-sm text-gray-700 [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_h3]:font-semibold [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1" />
                                            <div v-else-if="isFilled(row.current)" class="whitespace-pre-line break-words rounded bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ row.current }}</div>
                                            <div v-else class="rounded bg-gray-50 px-3 py-2 text-sm italic text-gray-400">
                                                {{ task.is_new ? ctrans("Not on the page yet") : row.is_code ? ctrans("No custom structured data") : ctrans("Empty") }}
                                            </div>
                                        </div>
                                        <div class="flex min-w-0 flex-col gap-1">
                                            <span class="text-xs font-medium text-gray-600">{{ ctrans("SEO Suggestion") }}</span>
                                            <template v-if="row.input && !task.is_done && task.can_implement">
                                                <RichTextField v-if="row.is_html" v-model="drafts[task.key][row.input]" withHeadings />
                                                <Textarea v-else v-model="drafts[task.key][row.input]" autoResize rows="2" fluid />
                                            </template>
                                            <pre v-else-if="row.is_code" class="m-0 max-h-64 overflow-auto rounded bg-[--app-accent-soft] px-3 py-2 font-mono text-xs leading-5 text-gray-800">{{ row.suggestion }}</pre>
                                            <div v-else-if="isFilled(row.suggestion) && row.is_html" v-html="row.suggestion"
                                                class="max-h-64 overflow-auto break-words rounded bg-[--app-accent-soft] px-3 py-2 text-sm text-gray-800 [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_h3]:font-semibold [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1" />
                                            <div v-else-if="isFilled(row.suggestion)" class="max-h-64 overflow-auto whitespace-pre-line break-words rounded bg-[--app-accent-soft] px-3 py-2 text-sm text-gray-800">{{ row.suggestion }}</div>
                                            <div v-else class="rounded border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-500">{{ ctrans("No change suggested") }}</div>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="task.note" class="text-sm text-gray-700"><span class="font-semibold">{{ ctrans("Why") }}:</span> {{ task.note }}</p>

                                <div v-if="!task.is_done" class="flex flex-wrap gap-2">
                                    <Button type="tertiary" :label="task.is_new ? ctrans('Add to FAQ') : ctrans('Mark implemented')" :disabled="!task.can_implement || busyKey !== null"
                                        :loading="busyKey === task.key" @click="implement(task)">
                                        <template #icon>
                                            <FontAwesomeIcon :icon="task.is_new ? faPlus : faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                                        </template>
                                    </Button>
                                </div>
                            </article>
                        </template>
                    </template>
                </section>

            </div>
        </div>
    </div>
</template>
