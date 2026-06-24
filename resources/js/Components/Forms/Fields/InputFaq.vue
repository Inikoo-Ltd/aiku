<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue';
import { faTrashAlt } from '@far';
import { get, set } from 'lodash-es'

const props = defineProps<{
    form: any
    fieldName: string
}>()

const addFaq = () => {
    if (!get(props.form, props.fieldName)) {
        set(props.form, props.fieldName, [])
    }

    get(props.form, props.fieldName).push({
        question: '',
        answer: ''
    })
}

const removeFaq = (index: number) => {
    get(props.form, props.fieldName).splice(index, 1)
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="(faq, index) in get(form, fieldName, [])"
            :key="index"
            class="rounded-lg border border-gray-200 p-4"
        >
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Question
                    </label>

                    <input
                        v-model="faq.question"
                        type="text"
                        class="w-full rounded-md border border-gray-300 px-3 py-2"
                        placeholder="Enter question"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Answer
                    </label>

                    <textarea
                        v-model="faq.answer"
                        rows="4"
                        class="w-full rounded-md border border-gray-300 px-3 py-2"
                        placeholder="Enter answer"
                    />
                </div>

                <div class="flex justify-end">
                   <FontawesomeIcon
                        :icon="faTrashAlt"
                        class="cursor-pointer text-red-500"
                        @click="removeFaq(index)"
                    />
                </div>
            </div>
        </div>

        <Button
            class="rounded bg-blue-600 px-4 py-2 text-white"
            @click="addFaq"
            label="Add FAQ"
        >
        </Button>

        <p
            v-if="get(form, ['errors', fieldName])"
            class="mt-2 text-sm text-red-600"
        >
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>