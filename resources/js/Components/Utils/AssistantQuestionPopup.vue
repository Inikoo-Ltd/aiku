<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import Textarea from "primevue/textarea"
import Checkbox from "primevue/checkbox"
import Select from "primevue/select"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faPaperPlane, faHeadSideBrain, faCheck, faShare } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { assistantQuestionColleagues, forgetAssistantQuestion } from "@/Composables/useAssistantQuestions"

library.add(faTimes, faPaperPlane, faHeadSideBrain, faCheck, faShare)

export type AssistantQuestion = {
    task: string
    question: string
    customer_words: string
    language: string
    customer: string
    shop: string
    answer_url: string
    decline_url: string
    pass_url: string
    task_url: string
    draft?: string
    draft_evidence?: string
}

const props = defineProps<{
    question: AssistantQuestion
}>()

const emit = defineEmits<{
    (e: "close"): void
}>()

const answer = ref(props.question.draft ?? "")
const keepForEveryone = ref(true)
const busy = ref<"" | "send" | "decline" | "pass">("")
const errorMessage = ref("")
const isPassing = ref(false)
const colleagues = ref<{ id: number, name: string }[]>([])
const colleagueId = ref<number | null>(null)

const done = () => {
    forgetAssistantQuestion(props.question.task)
    emit("close")
}

const post = async (kind: "send" | "decline" | "pass", url: string, payload: Record<string, unknown>) => {
    busy.value = kind
    errorMessage.value = ""
    try {
        await axios.post(url, payload)
        done()
    } catch (error: any) {
        if (error?.response?.status === 409) {
            errorMessage.value = ctrans("A colleague has already answered this question.")
            setTimeout(done, 2500)
            return
        }
        errorMessage.value = error?.response?.data?.message ?? ctrans("That did not work. Please try again.")
    } finally {
        busy.value = ""
    }
}

const send = () => {
    if (!answer.value.trim()) {
        errorMessage.value = ctrans("Write the answer first.")
        return
    }
    post("send", props.question.answer_url, { answer: answer.value, keep_for_everyone: keepForEveryone.value })
}

const startPassing = async () => {
    isPassing.value = true
    colleagues.value = await assistantQuestionColleagues()
}

const pass = () => {
    if (!colleagueId.value) {
        errorMessage.value = ctrans("Choose the colleague who knows.")
        return
    }
    post("pass", props.question.pass_url, { user_id: colleagueId.value })
}
</script>

<template>
    <div class="px-2 pt-1 pb-2">
        <div role="dialog" :aria-label="ctrans('Question from a customer')" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 shadow-[0_2px_6px_-1px_rgba(15,23,42,0.12)]">
            <div class="flex items-start gap-x-3">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm text-violet-600">
                    <FontAwesomeIcon icon="fal fa-head-side-brain" fixed-width aria-hidden="true" />
                </div>
                <div class="min-w-0 flex-1 leading-snug">
                    <div class="text-xs text-gray-500">{{ ctrans("Merlin needs a guru for :customer (:shop)", { customer: question.customer, shop: question.shop }) }}</div>
                    <div class="mt-0.5 whitespace-pre-line text-sm font-semibold text-gray-900">{{ question.question }}</div>
                    <div v-if="question.customer_words && question.customer_words !== question.question" class="mt-1 whitespace-pre-line text-xs text-gray-500">
                        {{ question.language }}: “{{ question.customer_words }}”
                    </div>
                </div>
            </div>

            <Textarea
                v-model="answer"
                rows="3"
                auto-resize
                class="mt-2 w-full text-sm"
                :placeholder="ctrans('Your answer, in any language: the customer gets it in theirs')"
                @keydown.meta.enter="send"
                @keydown.ctrl.enter="send" />
            <div v-if="question.draft_evidence" class="mt-1 whitespace-pre-line text-xs text-gray-400">
                {{ ctrans("Draft by Merlin's researcher. Evidence: :evidence", { evidence: question.draft_evidence }) }}
            </div>

            <label class="mt-1.5 flex items-center gap-2 text-xs text-gray-600">
                <Checkbox v-model="keepForEveryone" binary input-id="assistant-question-keep" :pt="{ box: { class: '!h-4 !w-4 !rounded !border !border-gray-400 !bg-white !shadow-none' } }">
                    <template #icon="{ checked }">
                        <FontAwesomeIcon v-if="checked" icon="fal fa-check" class="text-xs text-gray-800" fixed-width aria-hidden="true" />
                    </template>
                </Checkbox>
                {{ ctrans("Merlin may give this answer to every customer of the shop") }}
            </label>

            <div v-if="isPassing" class="mt-2 flex items-center gap-2">
                <Select
                    v-model="colleagueId"
                    :options="colleagues"
                    option-label="name"
                    option-value="id"
                    filter
                    :placeholder="ctrans('Who knows the answer?')"
                    class="min-w-0 flex-1 text-sm" />
                <Button type="tertiary" class="shrink-0" :label="ctrans('Pass')" :loading="busy === 'pass'" @click="pass">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-share" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>

            <p v-if="errorMessage" class="mt-1 text-xs text-red-600">{{ errorMessage }}</p>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <Button type="tertiary" size="xs" :label="ctrans('I don\'t want to answer')" :loading="busy === 'decline'" @click="post('decline', question.decline_url, {})">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button v-if="!isPassing" type="tertiary" size="xs" :label="ctrans('Pass to a colleague')" @click="startPassing">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-share" class="text-gray-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>

            <div class="mt-2 flex items-center gap-2">
                <a :href="question.task_url" class="text-xs text-gray-500 underline hover:text-gray-700">{{ ctrans("Customer & chat details") }}</a>
                <Button type="primary" class="ml-auto" :label="ctrans('Send')" :loading="busy === 'send'" @click="send">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-paper-plane" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </div>
    </div>
</template>
