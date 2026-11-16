import axios from "axios"
import { ref } from "vue"
import { notify } from "@kyvg/vue3-notification"
import type { AssistantQuestion } from "@/Components/Utils/AssistantQuestionPopup.vue"

const shown = new Map<string, string>()
let colleagues: Promise<{ id: number, name: string }[]> | null = null
let heldQuestions: AssistantQuestion[] = []

export const guruWelcomeOpen = ref(false)

const OFFSET_KEY = "assistant-questions-offset"

const onScreen = (x: number, y: number) => ({
    x: Math.min(Math.max(x, 160 - window.innerWidth), 120),
    y: Math.min(Math.max(y, -40), window.innerHeight - 160),
})

const readOffset = (): { x: number, y: number } => {
    try {
        const saved = JSON.parse(localStorage.getItem(OFFSET_KEY) ?? "null")
        return typeof saved?.x === "number" && typeof saved?.y === "number" ? onScreen(saved.x, saved.y) : { x: 0, y: 0 }
    } catch {
        return { x: 0, y: 0 }
    }
}

export const assistantQuestionsOffset = ref(readOffset())

export const moveAssistantQuestions = (x: number, y: number) => {
    assistantQuestionsOffset.value = onScreen(x, y)
}

export const keepAssistantQuestionsPlace = () => {
    try {
        localStorage.setItem(OFFSET_KEY, JSON.stringify(assistantQuestionsOffset.value))
    } catch {
        // ponytail: the place is only remembered where the browser allows it
    }
}

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
        if (data.welcome) {
            heldQuestions = data.questions ?? []
            guruWelcomeOpen.value = true
            return
        }
        ;(data.questions ?? []).forEach((question: AssistantQuestion) => showAssistantQuestion(question))
    } catch {
        // ponytail: the bell and the task list still have the question if this read fails
    }
}

export const closeGuruWelcome = (refused = false) => {
    guruWelcomeOpen.value = false
    axios.post(route("grp.tasks.assistant_questions.welcome_seen"), { refused }).catch(() => {})
    heldQuestions.forEach((question) => showAssistantQuestion(question))
    heldQuestions = []
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
