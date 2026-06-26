<script setup lang="ts">
import { ref, watch } from "vue"
import { ulid } from "ulid"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { EditorContent } from "@tiptap/vue-3"
import EditorV2 from "./BubleTextEditor/EditorV2.vue"
import { faTrashAlt } from "@far"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { get, set } from "lodash-es"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"

interface FaqItem {
    question: string
    answer: string
}

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    fieldData: {
        toogle?: string[]
        routeGetInternalLink?: routeType
    }
}>()

const normaliseFaqItems = (value: unknown): FaqItem[] => {
    if (!Array.isArray(value)) {
        return []
    }

    return value.map((item) => ({
        question: item?.question ?? "",
        answer: item?.answer ?? "",
    }))
}

if (!Array.isArray(get(props.form, props.fieldName))) {
    set(props.form, props.fieldName, [])
}

const items = ref<FaqItem[]>(normaliseFaqItems(get(props.form, props.fieldName, [])))

watch(
    items,
    (value) => {
        set(props.form, props.fieldName, normaliseFaqItems(value))
    },
    { deep: true }
)

watch(
    () => get(props.form, props.fieldName),
    (value) => {
        const nextItems = normaliseFaqItems(value)
        if (JSON.stringify(nextItems) !== JSON.stringify(items.value)) {
            items.value = nextItems
            syncAnswerKeys()
        }
    },
    { deep: true }
)

const answerToggle = props.fieldData.toogle || [
    "heading2", "heading3", "bold", "italic", "underline", "bulletList",
    "orderedList", "blockquote", "alignLeft", "alignCenter", "alignRight",
    "undo", "redo", "clear",
]

const answerKeyMap = ref<Record<number, string>>({})
const syncAnswerKeys = () => {
    items.value.forEach((_, index) => {
        if (!answerKeyMap.value[index]) {
            answerKeyMap.value[index] = ulid()
        }
    })
}

syncAnswerKeys()

const addFaq = () => {
    items.value.push({ question: "", answer: "" })
    syncAnswerKeys()
}

const removeFaq = (index: number) => {
    items.value.splice(index, 1)
    answerKeyMap.value = {}
    syncAnswerKeys()
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="(faq, index) in items"
            :key="index"
            class="rounded-lg border border-gray-200 p-4 space-y-3"
        >
            <div>
                <label class="mb-1 block text-sm font-medium">{{ trans('Question') }}</label>
                <input
                    v-model="faq.question"
                    type="text"
                    class="w-full rounded-md border border-gray-300 px-3 py-2"
                    :placeholder="trans('Enter question')"
                />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ trans('Answer') }}</label>
                <div class="bg-gray-50 border border-gray-300 rounded-md p-2">
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
            </div>

            <div class="flex justify-end">
                <FontAwesomeIcon
                    :icon="faTrashAlt"
                    class="cursor-pointer text-red-500"
                    @click="removeFaq(index)"
                />
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
