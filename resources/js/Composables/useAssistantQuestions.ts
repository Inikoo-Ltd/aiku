import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import type { AssistantQuestion } from "@/Components/Utils/AssistantQuestionPopup.vue"

const shown = new Map<string, string>()
let colleagues: Promise<{ id: number, name: string }[]> | null = null

export const showAssistantQuestion = (question: AssistantQuestion) => {
    if (shown.get(question.task) === question.question) {
        return
    }
    if (shown.has(question.task)) {
        notify.close(question.task)
    }
    shown.set(question.task, question.question)
    notify({
        id: question.task,
        group: "assistant-questions",
        title: question.customer,
        text: question.question,
        duration: -1,
        data: { question },
    })
}

export const forgetAssistantQuestion = (task: string) => {
    shown.delete(task)
}

export const loadMyAssistantQuestions = async () => {
    try {
        const { data } = await axios.get(route("grp.tasks.assistant_questions"))
        ;(data.questions ?? []).forEach((question: AssistantQuestion) => showAssistantQuestion(question))
    } catch {
        // ponytail: the bell and the task list still have the question if this read fails
    }
}

export const assistantQuestionColleagues = () => {
    colleagues ??= axios.get(route("grp.tasks.assistant_questions"), { params: { colleagues: 1 } })
        .then(({ data }) => data.colleagues ?? [])
        .catch(() => {
            colleagues = null
            return []
        })

    return colleagues
}
