<script setup lang="ts">
import { ref, computed, watch } from "vue"
import axios from "axios"
import { ulid } from "ulid"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { EditorContent } from "@tiptap/vue-3"
import EditorV2 from "./BubleTextEditor/EditorV2.vue"
import { faLanguage, faTrashAlt } from "@far"
import { faSave as fadSave, faSpinnerThird } from "@fad"
import { faSave as falSave } from "@fal"
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"
import { faArrowToRight } from "@fal"
import { trans } from "laravel-vue-i18n"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Toggle from "@/Components/Pure/Toggle.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { get, set } from "lodash-es"
import { routeType } from "@/types/route"

interface Language {
    code: string
    [key: string]: any
}

interface FaqItem {
    question: string
    answer: string
    source_question?: string
    source_answer?: string
}

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    submit?: () => void
    fieldData: {
        main?: FaqItem[]
        disable?: boolean
        show_follow_master?: boolean
        follow_master?: boolean
        language_from?: string
        language_to?: string
        languages?: Record<string, Language>
        toogle?: string[]
        routeGetInternalLink?: routeType
    }
    updateRoute?: {
        name: string
        parameters: []
    }
}>()

const emits = defineEmits()

const normaliseFaqItems = (value: unknown): FaqItem[] => {
    if (!Array.isArray(value)) {
        return []
    }

    return value.map((item) => ({
        question: item?.question ?? "",
        answer: item?.answer ?? "",
        source_question: item?.source_question ?? "",
        source_answer: item?.source_answer ?? "",
    }))
}

const serialiseFaqItems = (value: FaqItem[]) =>
    value.map((item) => ({
        question: item.question ?? "",
        answer: item.answer ?? "",
        source_question: item.source_question ?? "",
        source_answer: item.source_answer ?? "",
    }))

if (!Array.isArray(get(props.form, props.fieldName))) {
    set(props.form, props.fieldName, [])
}

const items = ref<FaqItem[]>(normaliseFaqItems(get(props.form, props.fieldName, [])))
const isSyncingToForm = ref(false)
const isSyncingFromForm = ref(false)

const masterFaq = computed<FaqItem[]>(() =>
    Array.isArray(props.fieldData.main) ? props.fieldData.main : []
)
const hasMaster = computed(() => masterFaq.value.length > 0)

const answerToggle = computed<string[]>(() =>
    props.fieldData.toogle || [
        "heading2", "heading3", "bold", "italic", "underline", "bulletList",
        "orderedList", "blockquote", "alignLeft", "alignCenter", "alignRight",
        "undo", "redo", "clear",
    ]
)

const languagesTo = ref<Language>(
    Object.values(props.fieldData.languages || {}).find(
        (l: Language) => l.code === props.fieldData.language_to
    ) ||
        Object.values(props.fieldData.languages || {})[0] || { code: "" }
)
const langLabel = computed(() => languagesTo.value.code)

const answerKeyMap = ref<Record<number, string>>({})
const syncAnswerKeys = () => {
    items.value.forEach((_, index) => {
        if (!answerKeyMap.value[index]) {
            answerKeyMap.value[index] = ulid()
        }
    })
}

const ensureItem = (index: number) => {
    while (items.value.length <= index) {
        items.value.push({ question: "", answer: "", source_question: "", source_answer: "" })
    }
    syncAnswerKeys()
}

if (hasMaster.value) {
    ensureItem(masterFaq.value.length - 1)
}
syncAnswerKeys()

const cloneItems = (): FaqItem[] =>
    items.value.map((item) => ({
        question: item.question ?? "",
        answer: item.answer ?? "",
        source_question: item.source_question ?? "",
        source_answer: item.source_answer ?? "",
    }))

const savedSnapshot = ref<FaqItem[]>(cloneItems())

watch(
    items,
    (value) => {
        if (isSyncingFromForm.value) {
            return
        }

        const nextPersistedItems = serialiseFaqItems(value)
        const currentFormItems = normaliseFaqItems(get(props.form, props.fieldName))

        if (JSON.stringify(nextPersistedItems) === JSON.stringify(serialiseFaqItems(currentFormItems))) {
            return
        }

        isSyncingToForm.value = true
        set(props.form, props.fieldName, nextPersistedItems)
        isSyncingToForm.value = false
    },
    { deep: true }
)

watch(
    () => get(props.form, props.fieldName),
    (value) => {
        if (isSyncingToForm.value) {
            return
        }

        const nextItems = normaliseFaqItems(value)
        const currentPersistedItems = serialiseFaqItems(items.value)

        if (JSON.stringify(nextItems) !== JSON.stringify(currentPersistedItems)) {
            isSyncingFromForm.value = true
            items.value = nextItems.map((item, index) => ({
                ...item,
                source_question: items.value[index]?.source_question ?? "",
                source_answer: items.value[index]?.source_answer ?? "",
            }))
            answerKeyMap.value = {}
            syncAnswerKeys()
            isSyncingFromForm.value = false
        }
    },
    { deep: true }
)

const dirtyMap = computed<Record<string, boolean>>(() => {
    const map: Record<string, boolean> = {}
    items.value.forEach((item, index) => {
        const saved = savedSnapshot.value[index]
        map[`${index}-question`] =
            (item.question ?? "") !== (saved?.question ?? "") ||
            (item.source_question ?? "") !== (saved?.source_question ?? "")
        map[`${index}-answer`] =
            (item.answer ?? "") !== (saved?.answer ?? "") ||
            (item.source_answer ?? "") !== (saved?.source_answer ?? "")
    })
    return map
})

const isCellDirty = (index: number, key: "question" | "answer"): boolean =>
    dirtyMap.value[`${index}-${key}`] === true

watch(
    () => props.form.recentlySuccessful,
    (successful) => {
        if (successful) {
            savedSnapshot.value = cloneItems()
        }
    }
)

const addFaq = () => {
    ensureItem(items.value.length)
}

const submitFaq = () => {
    isSyncingToForm.value = true
    set(props.form, props.fieldName, serialiseFaqItems(items.value))
    isSyncingToForm.value = false

    if (props.updateRoute?.name && typeof props.form.post === "function") {
        props.form.post(route(props.updateRoute.name, props.updateRoute.parameters), {
            preserveScroll: true,
            onSuccess: () => {
                savedSnapshot.value = cloneItems()
            },
        })
        return
    }

    props.submit?.()
}

const removeFaq = (index: number) => {
    items.value.splice(index, 1)
    savedSnapshot.value.splice(index, 1)
    answerKeyMap.value = {}
    syncAnswerKeys()
    submitFaq()
}

const translatingCell = ref<string | null>(null)
const isDisabled = computed(() => props.fieldData.disable || translatingCell.value !== null)

const sourceText = (index: number, key: "question" | "answer"): string =>
    masterFaq.value[index]?.[key] ?? items.value[index]?.[`source_${key}`] ?? ""

const canTranslate = (index: number, key: "question" | "answer"): boolean =>
    sourceText(index, key).trim().length > 0

const translateField = async (index: number, key: "question" | "answer") => {
    const text = sourceText(index, key)
    if (!text || isDisabled.value) {
        return
    }

    translatingCell.value = `${index}-${key}`
    try {
        const { data } = await axios.post(
            route("grp.models.translate", {
                languageFrom: props.fieldData.language_from || "en",
                languageTo: languagesTo.value.code || "en",
            }),
            { text },
            { timeout: 10000 }
        )

        if (data) {
            ensureItem(index)
            items.value[index][key] = data
            if (key === "answer") {
                answerKeyMap.value[index] = ulid()
            }
            emits("update:form", { ...props.form })

            notify({
                title: trans("Translation Completed"),
                text: trans("Translation generated successfully."),
                type: "success",
            })
        }
    } catch (error: any) {
        const isTimeout = error.code === "ECONNABORTED"
        notify({
            title: trans("Translation Error"),
            text: isTimeout
                ? trans("Translation request timed out. Please try again.")
                : error.response?.data?.message || trans("Failed to generate translation."),
            type: "error",
        })
    } finally {
        translatingCell.value = null
    }
}

const onSave = () => {
    submitFaq()
}

const isLoadingFollowMaster = ref(false)

const capitalizeFirstLetter = (text: string) => {
    if (text.length === 0) {
        return ""
    }
    return text.charAt(0).toUpperCase() + text.slice(1)
}

const changeValue = async () => {
    if (!props.updateRoute) {
        return
    }
    props.fieldData.follow_master = !props.fieldData.follow_master
    isLoadingFollowMaster.value = true
    await axios
        .patch(route(props.updateRoute.name, props.updateRoute.parameters), {
            ["follow_master_" + props.fieldName]: props.fieldData.follow_master,
        })
        .finally(() => {
            isLoadingFollowMaster.value = false
            const textDisplay = props.fieldData.follow_master
                ? trans(":_fieldname will follow master", { _fieldname: capitalizeFirstLetter(props.fieldName) })
                : trans(":_fieldname stops following master", { _fieldname: capitalizeFirstLetter(props.fieldName) })
            notify({
                title: trans("Success"),
                text: textDisplay,
                type: "success",
            })
        })
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="hasMaster && fieldData.show_follow_master"
            class="px-3 py-1 flex items-center justify-end w-full"
        >
            <span class="mr-3 font-semibold">{{ trans('Follow Master') }}</span>
            <Toggle
                v-tooltip="trans('Turning this option on would make it so that this item will follow its master counterpart')"
                :modelValue="fieldData.follow_master"
                @update:modelValue="changeValue()"
                :loading="isLoadingFollowMaster"
            />
        </div>

        <div
            v-for="(faq, index) in items"
            :key="index"
            class="rounded-lg border border-gray-200 p-4 space-y-4"
        >
            <div class="flex items-center justify-end">
                <FontAwesomeIcon
                    :icon="faTrashAlt"
                    class="cursor-pointer text-red-400 hover:text-red-600"
                    v-tooltip="trans('Remove')"
                    @click="removeFaq(index)"
                />
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                    {{ trans('Question') }}
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div class="p-3 rounded-md border bg-white flex gap-3 items-start">
                        <template v-if="masterFaq[index]">
                            <div class="h-6 w-6 flex items-center justify-center rounded-md bg-indigo-100 text-[#4B0082] shrink-0">
                                <FontAwesomeIcon
                                    :icon="faOctopusDeploy"
                                    v-tooltip="trans('Question of the Master')"
                                    class="h-3.5 w-3.5"
                                />
                            </div>
                            <div class="text-sm text-gray-700 whitespace-pre-wrap leading-6 flex-1 bg-gray-50 px-4 py-1 rounded-md border">
                                {{ masterFaq[index]?.question }}
                            </div>
                        </template>
                        <template v-else>
                            <div class="h-6 w-6 flex items-center justify-center rounded-md bg-gray-100 text-gray-500 shrink-0">
                                <FontAwesomeIcon :icon="faLanguage" v-tooltip="trans('Source text')" class="h-3.5 w-3.5" />
                            </div>
                            <div class="relative flex-1">
                                <input
                                    v-model="faq.source_question"
                                    type="text"
                                    :placeholder="trans('Enter question')"
                                    class="h-8 w-full text-sm bg-gray-50 border border-gray-200 rounded-md focus:outline-none focus:bg-white focus:border-primary-500"
                                />
                            </div>
                        </template>
                        <div class="h-6 w-6 flex items-center justify-center rounded-md bg-indigo-100 text-[#4B0082] shrink-0">
                            <FontAwesomeIcon :icon="faArrowToRight" class="h-3.5 w-3.5" />
                        </div>
                    </div>

                    <div class="rounded-md border p-2 bg-white flex items-center gap-2">
                        <p
                            v-if="langLabel"
                            class="w-fit text-[11px] font-medium text-gray-500 uppercase tracking-wide"
                            v-tooltip="languagesTo?.name"
                        >
                            {{ langLabel }}
                        </p>
                        <div class="relative flex-1">
                            <input
                                v-model="faq.question"
                                type="text"
                                :placeholder="trans('Translation...')"
                                class="h-8 w-full pr-10 text-sm bg-gray-50 border border-gray-200 rounded-md focus:outline-none focus:bg-white focus:border-primary-500 disabled:opacity-60"
                            />
                            <button
                                v-if="canTranslate(index, 'question')"
                                type="button"
                                :disabled="translatingCell !== null"
                                @click="translateField(index, 'question')"
                                class="absolute right-1 top-1/2 -translate-y-1/2 h-6 w-6 flex items-center justify-center rounded-md border bg-white text-gray-600 hover:bg-gray-100 disabled:opacity-50"
                                v-tooltip="hasMaster ? trans('get translation from AI') : trans('translate this text with AI')"
                            >
                                <LoadingIcon v-if="translatingCell === `${index}-question`" />
                                <FontAwesomeIcon v-else :icon="faLanguage" class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <button
                            type="button"
                            :disabled="form.processing || !isCellDirty(index, 'question')"
                            @click="onSave"
                            class="h-8 w-8 flex items-center justify-center shrink-0"
                            v-tooltip="trans('Save')"
                        >
                            <FontAwesomeIcon v-if="form.processing" :icon="faSpinnerThird" class="text-xl animate-spin" />
                            <FontAwesomeIcon
                                v-else-if="isCellDirty(index, 'question')"
                                :icon="fadSave"
                                class="h-6"
                                :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }"
                            />
                            <FontAwesomeIcon v-else :icon="falSave" class="h-6 text-gray-300" />
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                    {{ trans('Answer') }}
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div class="p-3 rounded-md border bg-white flex gap-3 items-start">
                        <template v-if="masterFaq[index]">
                            <div class="h-6 w-6 flex items-center justify-center rounded-md bg-indigo-100 text-[#4B0082] shrink-0 mt-1">
                                <FontAwesomeIcon
                                    :icon="faOctopusDeploy"
                                    v-tooltip="trans('Answer of the Master')"
                                    class="h-3.5 w-3.5"
                                />
                            </div>
                            <div
                                class="text-sm text-gray-700 whitespace-pre-wrap leading-6 flex-1 bg-gray-50 p-4 rounded-md border"
                                v-html="masterFaq[index]?.answer"
                            />
                        </template>
                        <template v-else>
                            <div class="h-6 w-6 flex items-center justify-center rounded-md bg-gray-100 text-gray-500 shrink-0 mt-1">
                                <FontAwesomeIcon :icon="faLanguage" v-tooltip="trans('Source text')" class="h-3.5 w-3.5" />
                            </div>
                            <div class="flex-1 bg-gray-50 border rounded-md p-2">                                
                                <EditorV2
                                    v-model="faq.source_answer"
                                    :key="`${answerKeyMap[index]}-source`"
                                    :toogle="answerToggle"
                                    :routeGetInternalLink="fieldData.routeGetInternalLink"
                                >
                                    <template #editor-content="{ editor }">
                                        <EditorContent
                                            :editor="editor"
                                            class="focus:outline-none text-sm text-gray-700 whitespace-pre-wrap leading-6 min-h-[5rem]"
                                        />
                                    </template>
                                </EditorV2>
                            </div>
                        </template>
                        <div class="h-6 w-6 flex items-center justify-center rounded-md bg-indigo-100 text-[#4B0082] shrink-0 mt-1">
                            <FontAwesomeIcon :icon="faArrowToRight" class="h-3.5 w-3.5" />
                        </div>
                    </div>

                    <div class="p-2 rounded-md border bg-white flex gap-2 items-start">
                        <p
                            v-if="langLabel"
                            class="text-[11px] font-medium text-gray-500 uppercase tracking-wide shrink-0 pt-2"
                            v-tooltip="languagesTo?.name"
                        >
                            {{ langLabel }}
                        </p>
                        <div class="flex-1 bg-gray-50 border rounded-md p-2">
                            <EditorV2
                                v-model="faq.answer"
                                :key="answerKeyMap[index]"
                                :toogle="answerToggle"
                                :routeGetInternalLink="fieldData.routeGetInternalLink"
                            >
                                <template #editor-content="{ editor }">
                                    <EditorContent
                                        :editor="editor"
                                        class="focus:outline-none text-sm text-gray-700 whitespace-pre-wrap leading-6 min-h-[5rem]"
                                    />
                                </template>
                            </EditorV2>
                        </div>

                        <div class="flex flex-col gap-1 shrink-0">
                            <button
                                v-if="canTranslate(index, 'answer')"
                                type="button"
                                :disabled="translatingCell !== null"
                                @click="translateField(index, 'answer')"
                                class="h-6 w-6 flex items-center justify-center rounded-md border bg-white text-gray-600 hover:bg-gray-100 disabled:opacity-50 mt-1"
                                v-tooltip="hasMaster ? trans('get translation from AI') : trans('translate this text with AI')"
                            >
                                <LoadingIcon v-if="translatingCell === `${index}-answer`" />
                                <FontAwesomeIcon v-else :icon="faLanguage" class="h-3.5 w-3.5" />
                            </button>

                            <button
                                type="button"
                                :disabled="form.processing || !isCellDirty(index, 'answer')"
                                @click="onSave"
                                class="h-8 w-8 flex items-center justify-center"
                                v-tooltip="trans('Save')"
                            >
                                <FontAwesomeIcon v-if="form.processing" :icon="faSpinnerThird" class="text-xl animate-spin" />
                                <FontAwesomeIcon
                                    v-else-if="isCellDirty(index, 'answer')"
                                    :icon="fadSave"
                                    class="h-6"
                                    :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }"
                                />
                                <FontAwesomeIcon v-else :icon="falSave" class="h-6 text-gray-300" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Button
            class="rounded bg-blue-600 px-4 py-2 text-white"
            @click="addFaq"
            :label="trans('Add FAQ')"
        />

        <p v-if="get(form, ['errors', fieldName])" class="mt-2 text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
