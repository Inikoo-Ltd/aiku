<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperPlane, faPenNib } from "@fal"
import { ctrans } from "@/Composables/useTrans"

type AuraQuestion = { id: number, question: string, answer: string | null, answered: boolean, failed: boolean, redrafted: boolean, asked_at: string }

const props = defineProps<{
    sessionUlid?: string | null
    reply: string
    draftId?: number | null
}>()

const emit = defineEmits<{ (e: "redrafted", text: string, draftId: number | null): void }>()

const visible = defineModel<boolean>("visible", { default: false })

const POLL_MS = 2000
const GIVE_UP_MS = 4 * 60 * 1000

const questions = ref<AuraQuestion[]>([])
const question = ref("")
const loading = ref(false)
const asking = ref(false)
const redraftingId = ref<number | null>(null)
const error = ref<string | null>(null)
const tookTooLong = ref(false)
const thread = ref<HTMLElement | null>(null)
let pollTimer: ReturnType<typeof setTimeout> | null = null
let pollingSince = 0

const waiting = computed(() => questions.value.some((asked) => !asked.answered))

const url = (action: "index" | "store") => route(`grp.api.chats.sessions.aura_questions.${action}`, [props.sessionUlid])

const errorOf = (exception: any, fallback: string) => {
    const errors = exception?.response?.data?.errors
    return errors ? Object.values(errors).flat()[0] as string : (exception?.response?.data?.message ?? fallback)
}

const scrollDown = async () => {
    await nextTick()
    if (thread.value) thread.value.scrollTop = thread.value.scrollHeight
}

const stopPolling = () => {
    if (pollTimer) clearTimeout(pollTimer)
    pollTimer = null
}

const show = (list: AuraQuestion[]) => {
    const grew = list.length !== questions.value.length || list.filter((asked) => asked.answered).length !== questions.value.filter((asked) => asked.answered).length
    questions.value = list
    if (grew) scrollDown()
    stopPolling()
    tookTooLong.value = waiting.value && Date.now() - pollingSince >= GIVE_UP_MS
    if (waiting.value && visible.value && !tookTooLong.value) pollTimer = setTimeout(load, POLL_MS)
}

const load = async () => {
    if (!props.sessionUlid) return
    const ulid = props.sessionUlid
    try {
        const { data } = await axios.get(url("index"))
        if (ulid === props.sessionUlid) show(data.data ?? [])
    } catch (exception) {
        error.value = errorOf(exception, ctrans("Aura's answers could not be loaded. Try again."))
    }
}

const ask = async () => {
    const text = question.value.trim()
    if (!text || asking.value || !props.sessionUlid) return
    asking.value = true
    error.value = null
    try {
        const { data } = await axios.post(url("store"), { question: text })
        question.value = ""
        pollingSince = Date.now()
        show(data.data ?? [])
    } catch (exception) {
        error.value = errorOf(exception, ctrans("Aura could not be asked. Try again."))
    } finally {
        asking.value = false
    }
}

const redraft = async (asked: AuraQuestion) => {
    if (redraftingId.value) return
    redraftingId.value = asked.id
    error.value = null
    try {
        const { data } = await axios.post(route("grp.api.chats.aura_questions.redraft", [asked.id]), { text: props.reply, draft_id: props.draftId ?? null })
        asked.redrafted = true
        emit("redrafted", data.data.text, data.data.draft_id ?? null)
        visible.value = false
    } catch (exception) {
        error.value = errorOf(exception, ctrans("Aura could not rewrite the reply. Try again."))
    } finally {
        redraftingId.value = null
    }
}

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === "Enter" && !event.shiftKey && !event.isComposing) {
        event.preventDefault()
        ask()
    }
}

watch(visible, (open) => {
    if (open) {
        error.value = null
        loading.value = !questions.value.length
        pollingSince = Date.now()
        load().finally(() => (loading.value = false))
    } else {
        stopPolling()
    }
})

watch(() => props.sessionUlid, () => {
    questions.value = []
    question.value = ""
    stopPolling()
    if (visible.value) load()
})

onBeforeUnmount(stopPolling)
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('Ask Aura about this conversation')" :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-3 text-sm">
            <div ref="thread" class="max-h-[55vh] space-y-3 overflow-y-auto">
                <div v-if="loading" class="space-y-2">
                    <div class="skeleton h-10 rounded" />
                    <div class="skeleton h-16 rounded" />
                </div>
                <p v-else-if="!questions.length" class="text-xs text-gray-500">
                    {{ ctrans("Ask about this customer, their orders, past chats and claims, stock or our policies. For example: what is in their last order, did they complain before, is a product in stock to send a replacement.") }}
                </p>
                <div v-for="asked in questions" :key="asked.id" class="space-y-1.5">
                    <p class="ml-auto w-fit max-w-[85%] whitespace-pre-line rounded-lg bg-gray-100 px-3 py-2 text-gray-800">{{ asked.question }}</p>
                    <div class="max-w-[90%] rounded-lg border border-pink-200 bg-pink-50/50 px-3 py-2">
                        <span class="aura-ask-tag mb-1 inline-block rounded-full px-1.5 text-[10px] font-semibold text-white">Aura</span>
                        <p v-if="!asked.answered" class="flex items-center gap-2 text-xs text-gray-500">
                            <LoadingIcon />
                            {{ ctrans("Aura is looking it up…") }}
                        </p>
                        <template v-else>
                            <p class="whitespace-pre-line text-gray-800" :class="{ 'text-gray-500': asked.failed }">{{ asked.answer }}</p>
                            <Button v-if="!asked.failed" type="tertiary" size="xs" class="mt-2"
                                :label="redraftingId === asked.id ? ctrans('Rewriting the reply…') : ctrans('Redraft with this')"
                                :disabled="!!redraftingId || (!reply.trim() && !draftId)"
                                v-tooltip="ctrans('Aura rewrites the reply using this answer; read it before you send')"
                                @click="redraft(asked)">
                                <template #icon>
                                    <LoadingIcon v-if="redraftingId === asked.id" />
                                    <FontAwesomeIcon v-else :icon="faPenNib" class="text-pink-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </template>
                    </div>
                </div>
            </div>

            <p v-if="tookTooLong" class="text-xs text-gray-500">{{ ctrans("Aura is taking long. Close and open this window to check again.") }}</p>

            <div class="flex items-end gap-2">
                <Textarea v-model="question" rows="2" autoResize class="min-w-0 flex-1 !text-sm" :maxlength="2000"
                    :placeholder="ctrans('Ask Aura…')" :disabled="asking" @keydown="onKeydown" />
                <Button type="primary" :label="ctrans('Ask')" :disabled="asking || !question.trim()" @click="ask">
                    <template #icon>
                        <LoadingIcon v-if="asking" />
                        <FontAwesomeIcon v-else :icon="faPaperPlane" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
            <p class="text-[11px] text-gray-400">{{ ctrans("Aura only looks things up: she changes nothing. Only you see your questions.") }}</p>
            <p v-if="error" role="alert" class="text-xs text-red-600">{{ error }}</p>
        </div>
    </Dialog>
</template>

<style scoped>
.aura-ask-tag {
    background: linear-gradient(90deg, #db2777, #a855f7);
}
</style>
