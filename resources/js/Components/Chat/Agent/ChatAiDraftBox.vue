<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from "vue"
import axios from "axios"
import { onClickOutside } from "@vueuse/core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faChevronUp, faStar as faStarEmpty, faExclamationTriangle, faCheck, faBowlingBall, faClipboardCheck, faLanguage, faTimes, faArrowDown, faArrowUp, faUserCheck, faTrashAlt, faCommentsAlt } from "@fal"
import { faStar } from "@fas"
import { ctrans } from "@/Composables/useTrans"
import { formatChatTime } from "@/Composables/chatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
    sessionUlid?: string | null
    whatsapp?: boolean
    readOnly?: boolean
    preview?: boolean
    assignable?: boolean
    assigning?: boolean
    inline?: boolean
}>()

const emit = defineEmits<{ (e: "use", text: string, draftId?: number): void, (e: "assign-and-use", draftId: number): void, (e: "discarded"): void, (e: "disposed"): void, (e: "loaded"): void, (e: "jump-to-message"): void }>()

interface DraftAction {
    index: number
    type: "claim" | "task" | "spam" | "close"
    label: string
    done: boolean
    available: boolean
    why: string | null
}

interface Draft {
    id: number
    text: string
    topic_label: string
    assistant: { of?: string, client?: string } | null
    staff_note?: string | null
    checked?: string[]
    premonitions?: { reference: string, subject: string, fixed: boolean, url: string }[]
    actions?: DraftAction[]
    questions?: { text: string, status: "answered" | "gap" }[]
    since_last_reply?: { count: number, first_at: string, last_at: string, after_reply: boolean } | null
    other_chats?: { ulid: string, shop: string | null, channel: string | null, url: string }[]
    rating: number | null
    reason: string | null
}

const draft = ref<Draft | null>(null)
const checkedOpen = ref(false)
const staffTranslation = ref<{ language: string, staff_note: string | null, checked: string[], labels: Record<number, string>, questions?: string[] } | null>(null)
const showTranslation = ref(false)
const translating = ref(false)
const confirming = ref<string | null>(null)
const actionBusy = ref(false)
let confirmTimer: ReturnType<typeof setTimeout> | null = null
const busy = ref(false)
const hidden = ref(false)
const draftOpen = ref(false)
let channel: any = null
let channelName: string | null = null
let latestLoad = 0

const clear = () => {
    draft.value = null
    confirming.value = null
    actionBusy.value = false
    checkedOpen.value = false
    staffTranslation.value = null
    showTranslation.value = false
    if (confirmTimer) clearTimeout(confirmTimer)
    draftOpen.value = false
}

const toggleDraft = () => {
    if (window.getSelection()?.toString()) return
    draftOpen.value = !draftOpen.value
}

const load = async () => {
    const thisLoad = ++latestLoad
    const sessionUlid = props.sessionUlid
    clear()
    if (!sessionUlid || props.readOnly) return

    try {
        const url = props.whatsapp
            ? route("grp.api.chats.meta.sessions.ai_draft.show", [sessionUlid])
            : route("grp.api.chats.sessions.ai_draft.show", [sessionUlid])
        const { data } = await axios.get(url)
        if (thisLoad !== latestLoad) return

        draft.value = data?.data ?? null
        if (draft.value) emit("loaded")
    } catch {
        if (thisLoad === latestLoad) clear()
    }
}

const usedKey = ref<string | null>(null)
const suggestionsKey = computed(() => [props.sessionUlid ?? "", draft.value?.id ?? ""].join("|"))
const used = computed(() => usedKey.value !== null && usedKey.value === suggestionsKey.value)
const shown = computed(() => !!draft.value && (props.inline || !used.value))
const useText = (text: string) => {
    usedKey.value = suggestionsKey.value
    emit("use", text)
}

const failure = ref<string | null>(null)

const decide = async (action: "take" | "discard") => {
    if (!draft.value || busy.value) return
    busy.value = true

    try {
        const { data } = await axios.post(route(`grp.api.chats.ai_drafts.${action}`, [draft.value.id]))
        if (action === "take" && data?.data?.text) {
            useText(data.data.text)
            return
        }
        draft.value = null
        if (action === "discard") emit("discarded")
        if (props.inline) await load()
    } catch {
        await load()
    } finally {
        busy.value = false
    }
}

// Inline, the draft goes straight into the reply box. It counts as taken once the agent starts
// changing it, as pressing Use did, so a rewritten draft is still told apart from one ignored.
const takenId = ref<number | null>(null)
const take = () => {
    if (!draft.value || takenId.value === draft.value.id) return
    takenId.value = draft.value.id
    axios.post(route("grp.api.chats.ai_drafts.take", [draft.value.id])).catch(() => null)
}

watch(() => draft.value?.id, (id) => {
    if (props.inline && id && draft.value?.text) emit("use", draft.value.text, id)
})

defineExpose({ take })

const runAction = async (action: DraftAction) => {
    if (!draft.value || actionBusy.value) return

    const draftId = draft.value.id
    const key = `${draftId}:${action.index}`

    if (!isQuestion(action) && confirming.value !== key) {
        confirming.value = key
        if (confirmTimer) clearTimeout(confirmTimer)
        confirmTimer = setTimeout(() => confirming.value = null, 4000)
        return
    }

    confirming.value = null
    actionBusy.value = true
    failure.value = null

    try {
        const { data } = await axios.post(route("grp.api.chats.ai_drafts.action", [draftId, action.index]))
        if (draft.value?.id !== draftId) return
        draft.value.actions = draft.value.actions?.map((existing) => existing.index === action.index ? data.data : existing)
        if (isQuestion(action)) emit("disposed")
    } catch (error: any) {
        failure.value = error?.response?.data?.message ?? ctrans("That could not be done")
        await load()
    } finally {
        actionBusy.value = false
    }
}

const toggleTranslation = async () => {
    if (!draft.value || translating.value) return
    if (staffTranslation.value) {
        showTranslation.value = !showTranslation.value
        return
    }

    const draftId = draft.value.id
    translating.value = true
    try {
        const { data } = await axios.post(route("grp.api.chats.ai_drafts.translate", [draftId]))
        if (draft.value?.id !== draftId) return
        staffTranslation.value = data.data
        showTranslation.value = true
    } catch {
        failure.value = ctrans("The note could not be translated")
    } finally {
        translating.value = false
    }
}

const staffNote = computed(() => showTranslation.value && staffTranslation.value ? staffTranslation.value.staff_note : draft.value?.staff_note)
const checkedLines = computed(() => showTranslation.value && staffTranslation.value ? staffTranslation.value.checked : (draft.value?.checked ?? []))
const questionText = (position: number) => (showTranslation.value && staffTranslation.value?.questions?.[position]) || draft.value?.questions?.[position]?.text
const sinceLine = computed(() => {
    const since = draft.value?.since_last_reply
    if (!since) return null
    const replacements = { count: since.count, first: formatChatTime(Date.parse(since.first_at)), last: formatChatTime(Date.parse(since.last_at)) }
    if (since.count === 1) return since.after_reply ? ctrans("Customer wrote once since our last reply (at :first).", replacements) : ctrans("Customer wrote once (at :first).", replacements)
    return since.after_reply
        ? ctrans("Customer wrote :count times since our last reply (first at :first, last at :last).", replacements)
        : ctrans("Customer wrote :count times (first at :first, last at :last).", replacements)
})
const actionLabel = (action: DraftAction) => !action.done && showTranslation.value && staffTranslation.value?.labels?.[action.index] ? staffTranslation.value.labels[action.index] : action.label
const isQuestion = (action: DraftAction) => action.type === "spam" || action.type === "close"
const questionActions = computed(() => draft.value?.actions?.filter(isQuestion) ?? [])
const staffActions = computed(() => draft.value?.actions?.filter((action) => !isQuestion(action)) ?? [])
const hasStaffText = computed(() => !!(draft.value?.staff_note || draft.value?.checked?.length || draft.value?.questions?.length || draft.value?.actions?.some((action) => action.type === "task")))

const ratingReasons = computed(() => [
    { key: "missing_info", label: ctrans("Missing info we know") },
    { key: "wrong_fact", label: ctrans("Wrong fact") },
    { key: "too_long", label: ctrans("Too long / wrong tone") },
    { key: "no_reply_needed", label: ctrans("No reply needed") },
])

const askingWhy = ref(false)
const whyPanel = ref<HTMLElement | null>(null)
onClickOutside(whyPanel, () => askingWhy.value = false)

const rate = (rating: number, reason: string | null = null) => {
    if (!draft.value) return
    draft.value.rating = rating
    draft.value.reason = reason
    askingWhy.value = rating <= 3 && !reason
    axios.post(route("grp.api.chats.ai_drafts.rate", [draft.value.id]), { rating, reason }).catch(() => null)
}

const listen = () => {
    if (!props.sessionUlid || !(window as any).Echo) return

    channelName = (props.whatsapp ? "meta-chat-session." : "chat-session.") + props.sessionUlid
    channel = props.whatsapp ? (window as any).Echo.private(channelName) : (window as any).Echo.channel(channelName)
    channel.listen(".ai-draft", load)
    channel.listen(".message", load)
}

const stopListening = () => {
    if (channel) {
        channel.stopListening(".ai-draft", load)
        channel.stopListening(".message", load)
    }
    channel = null
    channelName = null
}

watch(() => props.sessionUlid, () => {
    stopListening()
    usedKey.value = null
    hidden.value = false
    failure.value = null
    load()
    listen()
}, { immediate: true })

onBeforeUnmount(stopListening)
</script>

<template>
    <div v-if="shown || failure" class="text-xs" :class="inline ? '' : 'mb-1.5'">
        <p v-if="failure" class="mb-1 px-1 text-[11px] text-red-600">{{ failure }}</p>
        <div v-if="shown && !inline" class="flex items-center justify-end gap-3 px-1 text-[11px] text-gray-400">
            <Button v-if="preview && !hidden" type="tertiary" size="xs" class="bg-white" :label="ctrans('Jump to the message')" @click="emit('jump-to-message')">
                <template #icon>
                    <FontAwesomeIcon :icon="faArrowUp" class="text-gray-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <button type="button" :aria-expanded="!hidden" class="flex items-center gap-1 hover:text-gray-600 focus:outline-none focus-visible:underline" @click="hidden = !hidden">
                <FontAwesomeIcon :icon="hidden ? faChevronUp : faChevronDown" fixed-width />
                {{ hidden ? ctrans("Show suggestion") : ctrans("Hide suggestion") }}
            </button>
        </div>
        <template v-if="!hidden || inline">
            <div v-if="shown && draft" class="relative rounded-xl border border-[--app-accent-muted] bg-[--app-accent-soft] px-3 py-2">
                <button v-if="hasStaffText" type="button" :disabled="translating" @click="toggleTranslation"
                    class="absolute right-7 top-1.5 rounded px-1 text-[11px] text-gray-400 hover:text-gray-700 disabled:opacity-50"
                    :aria-pressed="showTranslation"
                    :title="showTranslation ? ctrans('Show the original') : ctrans('Translate the notes for staff into my language')">
                    <FontAwesomeIcon :icon="faLanguage" fixed-width aria-hidden="true" />
                    <span class="sr-only">{{ showTranslation ? ctrans("Show the original") : ctrans("Translate") }}</span>
                </button>
                <img src="/art/invader.svg" class="absolute right-2 top-2 h-3.5 select-none"
                    :alt="draft.assistant?.of ? ctrans('Draft by Aiku, asked by :name', { name: draft.assistant.of }) : ctrans('Draft by Aiku')"
                    :title="draft.assistant?.of ? ctrans('Draft by Aiku, asked by :name', { name: draft.assistant.of }) + (draft.assistant.client ? ` · ${draft.assistant.client}` : '') : ctrans('Draft by Aiku')" />
                <p v-if="inline" class="mb-1 mr-12 font-semibold text-gray-800">{{ ctrans("Draft by Aiku") }}</p>
                <div v-for="action in questionActions" :key="action.index" class="mr-12 flex flex-wrap items-center gap-2 rounded-md border px-2 py-1.5 text-sm"
                    :class="action.type === 'spam' ? 'border-red-200 bg-red-50 text-red-900' : 'border-green-200 bg-green-50 text-green-900'">
                    <FontAwesomeIcon :icon="action.type === 'spam' ? faTrashAlt : faCheck" :class="action.type === 'spam' ? 'text-red-500' : 'text-green-600'" fixed-width aria-hidden="true" />
                    <span class="min-w-0 flex-1 font-medium">{{ action.label }}</span>
                    <Button v-if="!action.done" type="tertiary" size="m" class="bg-white" :disabled="actionBusy || !action.available || (preview && !assignable)"
                        :tooltip="action.why ?? undefined" :label="ctrans('Yes')" @click="runAction(action)">
                        <template #icon>
                            <FontAwesomeIcon :icon="action.type === 'spam' ? faTrashAlt : faCheck" :class="action.type === 'spam' ? 'text-red-500' : 'text-green-600'" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
                <div v-if="staffNote || staffActions.length" :class="inline ? 'flex-col items-start' : 'mr-12 flex-wrap items-center'" class="flex gap-x-2 gap-y-1 rounded-md border border-violet-200 bg-violet-50 px-2 py-1 text-[11px] text-violet-900">
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="text-violet-600" fixed-width aria-hidden="true" />
                    <span v-if="staffNote" class="min-w-0 flex-1 whitespace-pre-line">{{ staffNote }}</span>
                    <template v-for="action in staffActions" :key="action.index">
                        <span v-if="action.done" class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700 ring-1 ring-inset ring-emerald-200">
                            <FontAwesomeIcon :icon="faCheck" fixed-width />{{ action.label }}
                        </span>
                        <Button v-else type="tertiary" size="xs" class="bg-white" :disabled="preview || actionBusy || !action.available" @click="runAction(action)"
                            :tooltip="action.why ?? (preview ? ctrans('Assign the chat to yourself first') : undefined)"
                            :label="confirming === `${draft.id}:${action.index}` ? ctrans('Confirm: :label', { label: actionLabel(action) }) : actionLabel(action)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faClipboardCheck" class="text-violet-600" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </template>
                </div>
                <div v-if="draft.premonitions?.length" class="mt-1 flex flex-wrap gap-1.5 text-[11px]">
                    <a v-for="premonition in draft.premonitions" :key="premonition.reference" :href="premonition.url" target="_blank" rel="noopener" :title="premonition.subject"
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 ring-1 ring-inset hover:underline"
                        :class="premonition.fixed ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-white text-gray-700 ring-gray-200'">
                        <FontAwesomeIcon :icon="faBowlingBall" fixed-width />
                        {{ premonition.reference }} · {{ premonition.fixed ? ctrans("fixed") : ctrans("engineers are on it") }}
                    </a>
                </div>
                <div v-if="draft.questions?.length" class="mt-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] text-gray-700">
                    <p class="font-semibold text-gray-800">{{ ctrans("Since our last reply") }}</p>
                    <p v-if="sinceLine" class="text-gray-500">{{ sinceLine }}</p>
                    <ol class="mt-0.5 space-y-0.5">
                        <li v-for="(question, position) in draft.questions" :key="position" class="flex items-start gap-1.5">
                            <span class="w-4 shrink-0 text-right tabular-nums text-gray-400">{{ position + 1 }}.</span>
                            <FontAwesomeIcon :icon="question.status === 'answered' ? faCheck : faExclamationTriangle" :class="question.status === 'answered' ? 'text-green-600' : 'text-amber-500'" class="mt-0.5" fixed-width aria-hidden="true" />
                            <span class="min-w-0 flex-1">{{ questionText(position) }}</span>
                            <span class="whitespace-nowrap" :class="question.status === 'answered' ? 'text-green-700' : 'font-medium text-amber-700'">
                                {{ question.status === "answered" ? ctrans("answered") : ctrans("gap for staff") }}
                            </span>
                        </li>
                    </ol>
                    <div v-if="draft.other_chats?.length" class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 border-t border-gray-100 pt-1 text-gray-500">
                        <FontAwesomeIcon :icon="faCommentsAlt" fixed-width aria-hidden="true" />
                        <span>{{ ctrans("Also open for this customer:") }}</span>
                        <a v-for="other in draft.other_chats" :key="other.ulid" :href="other.url" target="_blank" rel="noopener" class="whitespace-nowrap text-gray-700 underline hover:text-gray-900">
                            {{ [other.shop, other.channel].filter(Boolean).join(" · ") }}
                        </a>
                    </div>
                </div>
                <p v-if="!inline && draft.text" role="button" tabindex="0" :aria-expanded="draftOpen" :title="draftOpen ? undefined : draft.text" class="mt-1 cursor-pointer whitespace-pre-line text-gray-800" :class="draftOpen ? 'max-h-48 overflow-y-auto' : 'line-clamp-3'" @click="toggleDraft" @keydown.enter.prevent="toggleDraft">{{ draft.text }}</p>
                <p v-if="draft.text.includes('[[')" class="mt-1 text-[11px] text-amber-700">{{ ctrans("Fill in or delete the parts marked [[ ]] before sending.") }}</p>
                <div v-if="draft.checked?.length" class="mt-1 text-[11px] text-gray-500">
                    <button type="button" class="inline-flex items-center gap-1 hover:text-gray-700" :aria-expanded="checkedOpen" @click="checkedOpen = !checkedOpen">
                        <FontAwesomeIcon :icon="faClipboardCheck" fixed-width />
                        {{ ctrans("Checked in aiku (:count)", { count: draft.checked.length }) }}
                    </button>
                    <ul v-if="checkedOpen" class="mt-0.5 list-disc pl-5 text-gray-600">
                        <li v-for="(line, position) in checkedLines" :key="position">{{ line }}</li>
                    </ul>
                </div>
                <div v-if="preview && assignable && draft.text" class="mt-2">
                    <Button type="tertiary" size="xs" class="bg-white" :disabled="assigning" :label="ctrans('Assign to me and use')" @click="emit('assign-and-use', draft.id)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faUserCheck" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
                <div v-if="!preview" class="mt-2 flex flex-wrap items-center gap-2">
                    <Button v-if="!inline && draft.text" type="tertiary" size="xs" class="bg-white" :disabled="busy" :label="ctrans('Use')" @click="decide('take')">
                        <template #icon>
                            <FontAwesomeIcon :icon="faArrowDown" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" size="xs" class="bg-white" :disabled="busy" :label="inline ? ctrans('Discard draft') : ctrans('Discard')"
                        :tooltip="inline ? ctrans('Clears the reply box; a newer suggestion takes its place if there is one') : undefined" @click="decide('discard')">
                        <template #icon>
                            <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <span class="relative ml-auto flex items-center" :title="ctrans('How good is this draft?')">
                        <button v-for="star in 5" :key="star" type="button" class="px-0.5 text-amber-500 hover:scale-110" :aria-label="ctrans(':count stars', { count: star })" @click="rate(star)">
                            <FontAwesomeIcon :icon="(draft.rating ?? 0) >= star ? faStar : faStarEmpty" fixed-width />
                        </button>
                        <div v-if="askingWhy && draft.rating" ref="whyPanel"
                            class="absolute bottom-full right-0 z-20 mb-1 w-48 rounded-lg border border-gray-200 bg-white p-1 text-[11px] shadow-lg">
                            <p class="px-2 py-1 text-gray-500">{{ ctrans("What was wrong?") }}</p>
                            <button v-for="reason in ratingReasons" :key="reason.key" type="button" @click="rate(draft.rating, reason.key)"
                                class="block w-full rounded px-2 py-1 text-left text-gray-800 hover:bg-[--app-accent-soft]">
                                {{ reason.label }}
                            </button>
                        </div>
                    </span>
                </div>
            </div>

        </template>
    </div>
</template>
