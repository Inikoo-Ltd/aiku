<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue"
import { ulid } from "ulid"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { EditorContent } from "@tiptap/vue-3"
import EditorV2 from "./BubleTextEditor/EditorV2.vue"
import { faTrashAlt } from "@far"
import { faCheck } from "@fal"
import { faSpinnerThird } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { get, set } from "lodash-es"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"

library.add(faTrashAlt, faCheck, faSpinnerThird)

interface FaqItem {
    question: string
    answer: string
}

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    fieldData: {
        toggle?: string[]
        routeGetInternalLink?: routeType
        master_product_category_id?: number
    }
}>()

// Cascade progress broadcast by CascadeMasterProductCategoryFaqToChildren after a save:
// "n/total shops updated" while the FAQ is translated and copied down, then "Shops updated".
const cascadeProgress = ref<{ state: string, done: number, total: number } | null>(null)

const onCascadeProgress = (event: { state: string, done: number, total: number }) => {
    cascadeProgress.value = event
}

onMounted(() => {
    if (window.Echo && props.fieldData.master_product_category_id) {
        window.Echo.private(`grp.master-product-category.${props.fieldData.master_product_category_id}`)
            .listen('.faq-cascade-progress', onCascadeProgress)
    }
})

onUnmounted(() => {
    if (window.Echo && props.fieldData.master_product_category_id) {
        window.Echo.private(`grp.master-product-category.${props.fieldData.master_product_category_id}`)
            .stopListening('.faq-cascade-progress', onCascadeProgress)
    }
})

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

const answerToggle = props.fieldData.toggle || [
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
            v-if="cascadeProgress"
            class="flex h-5 items-center justify-end gap-x-1.5 text-xs"
            :class="cascadeProgress.state === 'done' ? 'text-green-600' : 'text-gray-500'"
        >
            <FontAwesomeIcon
                v-if="cascadeProgress.state !== 'done'"
                icon="fad fa-spinner-third"
                class="animate-spin"
                fixed-width
                aria-hidden="true"
            />
            <FontAwesomeIcon v-else :icon="faCheck" fixed-width aria-hidden="true" />
            <span v-if="cascadeProgress.state !== 'done'">
                {{ cascadeProgress.done }}/{{ cascadeProgress.total }} {{ trans('shops updated') }}
            </span>
            <span v-else>
                {{ trans('Shops updated') }} ({{ cascadeProgress.total }})
            </span>
        </div>

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
                        :toggle="answerToggle"
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
