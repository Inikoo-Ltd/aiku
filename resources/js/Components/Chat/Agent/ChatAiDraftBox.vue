<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { chatAutoTranslate } from "@/Composables/useChatTranslation"
import { ref, computed, watch, onBeforeUnmount } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faChevronUp, faChevronRight, faExclamationTriangle, faCheck, faClipboardCheck, faLanguage, faTimes, faArrowDown, faArrowUp, faTrashAlt, faCommentsAlt, faLink, faBoxFragile } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketStatusChip from "@/Components/Tickets/TicketStatusChip.vue"
import AuraAvatar from "@/Components/Chat/Agent/AuraAvatar.vue"
import ToggleSwitch from "primevue/toggleswitch"

const props = defineProps<{
    sessionUlid?: string | null
    whatsapp?: boolean
    readOnly?: boolean
    preview?: boolean
    inline?: boolean
    replySubject?: string
}>()

const emit = defineEmits<{ (e: "use", text: string, draftId?: number, translated?: { mine: string, theirs: string } | null): void, (e: "discarded"): void, (e: "disposed"): void, (e: "loaded"): void, (e: "jump-to-message"): void, (e: "collapse"): void }>()

interface DraftAction {
    index: number
    type: "claim" | "task" | "spam" | "close" | "link"
    label: string
    done: boolean
    available: boolean
    why: string | null
    url?: string | null
}

interface Draft {
    id: number
    text: string
    topic_label: string
    assistant: { of?: string, client?: string } | null
    staff_note?: string | null
    checked?: string[]
    premonitions?: { reference: string, subject: string, fixed: boolean, status_icon: { icon: string, class: string, tooltip: string }, url: string }[]
    actions?: DraftAction[]
    questions?: { text: string, status: "answered" | "usual_decision" | "gap" }[]
    since_last_reply?: { count: number, first_at: string, last_at: string, after_reply: boolean } | null
    other_chats?: { ulid: string, shop: string | null, channel: string | null, url: string }[]
    rating: number | null
    reason: string | null
    aura_angry?: boolean
    translate_for_me?: boolean
    notes_for_me?: boolean
    edit_in_my_language?: boolean
    my_language?: string | null
    customer_language?: string | null
}

const draft = ref<Draft | null>(null)
const noteOpen = ref(false)
const staffTranslation = ref<{ language: string, language_id?: number, editable?: boolean, text?: string | null, staff_note: string | null, checked: string[], labels: Record<number, string>, questions?: string[] } | null>(null)
const showTranslation = ref(false)
const translating = ref(false)
const confirming = ref<string | null>(null)
const actionBusy = ref(false)
let confirmTimer: ReturnType<typeof setTimeout> | null = null
const busy = ref(false)
// The off switch is the agent's own and outlives the conversation: turned off, drafts stay out of
// sight (and out of the email reply box) in every chat on this computer until turned back on.
const HIDDEN_KEY = "chat.aiSuggestionsOff"
const readHidden = () => {
    try {
        return localStorage.getItem(HIDDEN_KEY) === "1"
    } catch {
        return false
    }
}
const hidden = ref(!props.inline && readHidden())
const toggleHidden = () => {
    hidden.value = !hidden.value
    try {
        localStorage.setItem(HIDDEN_KEY, hidden.value ? "1" : "0")
    } catch {
        // ponytail: private windows forget the switch, the suggestion just shows again
    }
    if (hidden.value) return
    translateForMe()
    offerToEditor()
}
const draftOpen = ref(false)
let channel: any = null
let channelName: string | null = null
let latestLoad = 0

const clear = () => {
    draft.value = null
    confirming.value = null
    actionBusy.value = false
    noteOpen.value = false
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
    const kept = { id: draft.value?.id, translation: staffTranslation.value, shown: showTranslation.value }
    clear()
    if (!sessionUlid || props.readOnly) return

    try {
        const url = props.whatsapp
            ? route("grp.api.chats.meta.sessions.ai_draft.show", [sessionUlid])
            : route("grp.api.chats.sessions.ai_draft.show", [sessionUlid])
        const { data } = await axios.get(url)
        if (thisLoad !== latestLoad) return

        draft.value = data?.data ?? null
        // A new customer message reloads the same draft: its translation is kept, not asked for again.
        if (draft.value && draft.value.id === kept.id && kept.translation) {
            staffTranslation.value = kept.translation
            showTranslation.value = kept.shown
        }
        if (draft.value) emit("loaded")
        translateForMe()
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

// Copying the text instead of pressing Use is recorded once per draft, quietly.
const copiedId = ref<number | null>(null)
const copied = () => {
    if (!draft.value || copiedId.value === draft.value.id) return
    copiedId.value = draft.value.id
    axios.post(route("grp.api.chats.ai_drafts.copied", [draft.value.id])).catch(() => null)
}

watch(() => draft.value?.id, () => offerToEditor())


const runAction = async (action: DraftAction) => {
    if (!draft.value || actionBusy.value) return
    if (action.url) {
        window.open(action.url, "_blank", "noopener")
        return
    }

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

let translationRequest: { draftId: number, promise: Promise<void> } | null = null
const fetchTranslation = (): Promise<void> => {
    const draftId = draft.value?.id
    if (!draftId || staffTranslation.value) return Promise.resolve()
    if (translationRequest?.draftId === draftId) return translationRequest.promise

    translating.value = true
    const promise = axios.post(route("grp.api.chats.ai_drafts.translate", [draftId]))
        .then(({ data }) => {
            if (draft.value?.id === draftId) staffTranslation.value = data.data
        })
        .catch(() => {
            if (draft.value?.id === draftId) failure.value = ctrans("The draft could not be translated")
        })
        .finally(() => {
            if (translationRequest?.draftId === draftId) translationRequest = null
            translating.value = !!translationRequest
        })
    translationRequest = { draftId, promise }

    return promise
}

const toggleTranslation = async () => {
    if (!draft.value || translating.value) return
    if (staffTranslation.value) {
        showTranslation.value = !showTranslation.value
        return
    }

    const draftId = draft.value.id
    await fetchTranslation()
    if (draft.value?.id === draftId && staffTranslation.value) showTranslation.value = true
}

// HELP-4534: an agent who does not write the customer's language edits the draft in their own;
// the reply is translated back before it is emailed, and sent unchanged it is Aiku's original.
const editsInMyLanguage = computed(() => !!draft.value?.translate_for_me && !!draft.value?.edit_in_my_language)

const offerToEditor = async () => {
    if (!props.inline || !draft.value?.text || hidden.value) return
    const draftId = draft.value.id

    if (editsInMyLanguage.value) {
        await fetchTranslation()
        if (draft.value?.id !== draftId || hidden.value) return
        if (staffTranslation.value?.editable && staffTranslation.value.text) {
            emit("use", staffTranslation.value.text, draftId, {
                mine: staffTranslation.value.language,
                theirs: draft.value.customer_language ?? ctrans("the customer's language"),
            })
            return
        }
        failure.value = ctrans("The draft could not be translated for you to edit, so it is in the customer's language.")
    }

    emit("use", draft.value.text, draftId, null)
}

const editSwitchPt = {
    slider: ({ props }: { props: { modelValue: boolean } }) => ({
        style: props.modelValue ? { background: "#db2777" } : undefined,
    }),
}

const savingEditLanguage = ref(false)
const setEditInMyLanguage = async (on: boolean) => {
    if (!draft.value || savingEditLanguage.value) return
    const draftId = draft.value.id
    draft.value.edit_in_my_language = on
    failure.value = null
    savingEditLanguage.value = true
    try {
        await axios.post(route("grp.api.chats.ai_drafts.edit_in_my_language", [draftId]), { on })
    } catch (error: any) {
        if (draft.value?.id === draftId) draft.value.edit_in_my_language = !on
        failure.value = error?.response?.data?.message ?? ctrans("That could not be saved")
        return
    } finally {
        savingEditLanguage.value = false
    }
    await offerToEditor()
}

// A draft in a language the agent does not read is shown translated from the start; the customer
// still gets the original.
const translateForMe = () => {
    if (chatAutoTranslate.value && (draft.value?.translate_for_me || draft.value?.notes_for_me) && !hidden.value && !staffTranslation.value) toggleTranslation()
}

const draftText = computed(() => showTranslation.value && staffTranslation.value?.text ? staffTranslation.value.text : draft.value?.text)
const staffNote = computed(() => showTranslation.value && staffTranslation.value ? staffTranslation.value.staff_note : draft.value?.staff_note)
const sinceLine = computed(() => {
    const since = draft.value?.since_last_reply
    if (!since || since.count < 2) return null
    return since.after_reply
        ? ctrans("Customer wrote :count times since our reply", { count: since.count })
        : ctrans("Customer wrote :count times", { count: since.count })
})
const actionLabel = (action: DraftAction) => !action.done && showTranslation.value && staffTranslation.value?.labels?.[action.index] ? staffTranslation.value.labels[action.index] : action.label
const isQuestion = (action: DraftAction) => action.type === "spam" || action.type === "close"
const actionIcon = (action: DraftAction) => ({ link: faLink, claim: faBoxFragile }[action.type as string] ?? faClipboardCheck)
const questionActions = computed(() => draft.value?.actions?.filter(isQuestion) ?? [])
const staffActions = computed(() => draft.value?.actions?.filter((action) => !isQuestion(action)) ?? [])
const hasStaffText = computed(() => !!(draft.value?.staff_note || draft.value?.actions?.some((action) => action.type === "task")))

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
    failure.value = null
    load()
    listen()
}, { immediate: true })

onBeforeUnmount(stopListening)

const replaceText = (draftId: number, text: string) => {
    if (draft.value?.id !== draftId) return
    draft.value.text = text
    staffTranslation.value = null
    showTranslation.value = false
}

defineExpose({ take, draft, busy, offerToEditor, replaceText, discard: () => decide("discard"), translating, showTranslation, toggleTranslation, savingEditLanguage, setEditInMyLanguage, editSwitchPt })
</script>

<template>
    <div v-if="shown || failure" class="text-xs" :class="inline ? '' : 'mb-1.5'">
        <p v-if="failure" class="mb-1 px-1 text-[11px] text-red-600">{{ failure }}</p>
        <template v-if="!hidden">
            <div v-if="shown && draft" class="relative rounded-xl border border-pink-300 bg-pink-50" :class="inline ? 'py-3.5 pl-6 pr-4' : 'py-2 pl-3 pr-3'">
                <button v-if="!inline && (hasStaffText || draft.text)" type="button" :disabled="translating" @click="toggleTranslation"
                    class="absolute right-7 top-1.5 rounded px-1 text-[11px] text-gray-400 hover:text-gray-700 disabled:opacity-50"
                    :aria-pressed="showTranslation"
                    :title="showTranslation ? ctrans('Show the original') : ctrans('Translate the draft and the notes into my language')">
                    <FontAwesomeIcon :icon="faLanguage" fixed-width aria-hidden="true" />
                    <span class="sr-only">{{ showTranslation ? ctrans("Show the original") : ctrans("Translate") }}</span>
                </button>
                <span v-if="!inline" class="absolute right-1.5 top-1.5 select-none"
                    :title="draft.aura_angry ? ctrans('Aura is cross: she had suggestions right that you wrote again by hand') : draft.assistant?.of ? ctrans('Draft by Aura, asked by :name', { name: draft.assistant.of }) + (draft.assistant.client ? ` · ${draft.assistant.client}` : '') : ctrans('Draft by Aura')">
                    <AuraAvatar class="h-5 w-5" :angry="draft.aura_angry" :label="draft.assistant?.of ? ctrans('Draft by Aura, asked by :name', { name: draft.assistant.of }) : ctrans('Draft by Aura')" />
                </span>
                <button v-if="inline" type="button" class="absolute left-0 top-1/2 z-10 flex w-8 lg:w-5 aspect-square -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-pink-300 bg-pink-50 text-pink-500 hover:bg-pink-100 hover:text-pink-700" v-tooltip="ctrans('Make it a thin bar')" :aria-label="ctrans('Make it a thin bar')" @click="emit('collapse')">
                    <FontAwesomeIcon :icon="faChevronRight" class="h-[10px] leading-none" fixed-width aria-hidden="true" />
                </button>
                <div v-for="action in questionActions" :key="action.index" class="mr-12 flex flex-wrap items-center gap-2 rounded-md border px-2 py-1.5 text-sm"
                    :class="action.type === 'spam' ? 'border-red-200 bg-red-50 text-red-900' : 'border-green-200 bg-green-50 text-green-900'">
                    <FontAwesomeIcon :icon="action.type === 'spam' ? faTrashAlt : faCheck" :class="action.type === 'spam' ? 'text-red-500' : 'text-green-600'" fixed-width aria-hidden="true" />
                    <span class="min-w-0 flex-1 font-medium">{{ action.label }}</span>
                    <Button v-if="!action.done" type="tertiary" size="m" class="bg-white" :disabled="actionBusy || !action.available || preview"
                        :tooltip="action.why ?? undefined" :label="ctrans('Yes')" @click="runAction(action)">
                        <template #icon>
                            <FontAwesomeIcon :icon="action.type === 'spam' ? faTrashAlt : faCheck" :class="action.type === 'spam' ? 'text-red-500' : 'text-green-600'" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
                <div v-if="staffNote || staffActions.length || draft.premonitions?.length" :class="inline ? 'flex-col items-start gap-y-2.5 px-3.5 py-3 text-xs' : 'mr-12 flex-wrap items-center gap-y-1 px-2 py-1 text-[11px]'" class="flex gap-x-2 rounded-md border border-pink-200 bg-pink-100 text-pink-950">
                    <div v-if="staffNote || draft.premonitions?.length" class="flex min-w-0 flex-1 items-start gap-1.5 self-stretch">
                        <FontAwesomeIcon v-if="!draft.premonitions?.length" :icon="faExclamationTriangle" class="mt-px shrink-0 text-pink-600" fixed-width aria-hidden="true" />
                        <div class="min-w-0 flex-1">
                            <span v-if="staffNote" role="button" tabindex="0" class="block cursor-pointer whitespace-pre-line" :class="noteOpen ? '' : 'line-clamp-3'" @click="noteOpen = !noteOpen" @keydown.enter.prevent="noteOpen = !noteOpen">{{ staffNote }}</span>
                            <span v-if="draft.premonitions?.length" class="mt-1.5 flex flex-wrap gap-2 text-xs"><TicketStatusChip v-for="premonition in draft.premonitions" :key="premonition.reference" :ticket="premonition" /></span>
                        </div>
                    </div>
                    <template v-for="action in staffActions" :key="action.index">
                        <span v-if="action.done" class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700 ring-1 ring-inset ring-emerald-200">
                            <FontAwesomeIcon :icon="faCheck" fixed-width />{{ action.label }}
                        </span>
                        <Button v-else type="tertiary" :size="inline ? 'm' : 'xs'" class="!border-pink-600 !bg-pink-600 !text-white hover:!bg-pink-700 disabled:!opacity-50" :disabled="preview || actionBusy || !action.available" @click="runAction(action)"
                            :tooltip="action.why ?? undefined"
                            :label="confirming === `${draft.id}:${action.index}` ? ctrans('Confirm: :label', { label: actionLabel(action) }) : actionLabel(action)">
                            <template #icon>
                                <FontAwesomeIcon :icon="actionIcon(action)" class="text-white" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <span v-if="!action.done && !action.available && action.why" class="text-gray-500">{{ action.why }}</span>
                    </template>
                </div>
                <p v-if="sinceLine" class="mt-1 text-[11px] text-gray-500">{{ sinceLine }}</p>
                <div v-if="draft.other_chats?.length" class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-gray-500">
                    <FontAwesomeIcon :icon="faCommentsAlt" fixed-width aria-hidden="true" />
                    <span>{{ ctrans("Also open for this customer:") }}</span>
                    <a v-for="other in draft.other_chats" :key="other.ulid" :href="other.url" target="_blank" rel="noopener" class="whitespace-nowrap text-gray-700 underline hover:text-gray-900">
                        {{ [other.shop, other.channel].filter(Boolean).join(" · ") }}
                    </a>
                </div>
                <template v-if="inline && editsInMyLanguage && staffTranslation?.editable">
                    <p class="mt-1 text-[11px] text-gray-500">{{ ctrans("You edit it in :language below. Sent unchanged, the customer gets Aura's original; changed, your reply is translated into their language before it is sent.", { language: staffTranslation.language }) }}</p>
                    <p class="mt-1.5 text-[11px] font-medium text-gray-500">{{ ctrans("What the customer gets if you send it unchanged") }}</p>
                    <div class="mt-0.5 overflow-hidden rounded-md border border-gray-200 bg-white">
                        <p v-if="replySubject" class="truncate border-b border-gray-100 bg-gray-50 px-2 py-1 text-[11px] font-semibold text-gray-800">{{ replySubject }}</p>
                        <div class="px-2 py-1.5">
                            <p role="button" tabindex="0" :aria-expanded="draftOpen" class="cursor-pointer whitespace-pre-line text-[11px] text-gray-700" :class="draftOpen ? 'max-h-48 overflow-y-auto' : 'line-clamp-3'"
                                @click="toggleDraft" @keydown.enter.prevent="toggleDraft" @copy="copied">{{ draft.text }}</p>
                        </div>
                    </div>
                </template>
                <p v-else-if="draft.text && showTranslation && staffTranslation?.text" class="mt-1 text-[11px] text-gray-500">{{ ctrans("Translated into :language for you to read: the customer gets the original.", { language: staffTranslation.language }) }}</p>
                <p v-if="!inline && draft.text" role="button" tabindex="0" :aria-expanded="draftOpen" :title="draftOpen ? undefined : draftText" class="mt-1 cursor-pointer whitespace-pre-line text-gray-800" :class="draftOpen ? 'max-h-48 overflow-y-auto' : 'line-clamp-3'" @click="toggleDraft" @keydown.enter.prevent="toggleDraft" @copy="copied">{{ draftText }}</p>
                <p v-else-if="inline && draft.text && showTranslation && staffTranslation?.text && !(editsInMyLanguage && staffTranslation.editable)" class="mt-1 max-h-48 overflow-y-auto whitespace-pre-line text-gray-800" @copy="copied">{{ staffTranslation.text }}</p>
                <p v-if="draft.text.includes('[[')" class="mt-1 text-[11px] text-amber-700">{{ ctrans("Fill in or delete the parts marked [[ ]] before sending.") }}</p>
                <div v-if="!preview && !inline" class="mt-2 flex flex-wrap items-center gap-2">
                    <Button v-if="!inline && draft.text" type="tertiary" size="xs" class="bg-white" :disabled="busy" :label="ctrans('Use')" @click="decide('take')">
                        <template #icon>
                            <FontAwesomeIcon :icon="faArrowDown" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button v-if="!inline" type="tertiary" size="xs" class="bg-white" :disabled="busy" :label="inline ? ctrans('Discard draft') : ctrans('Discard')"
                        :tooltip="inline ? ctrans('Clears the reply box; a newer suggestion takes its place if there is one') : undefined" @click="decide('discard')">
                        <template #icon>
                            <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </div>

        </template>
        <div v-if="shown && !inline" class="mt-1 flex items-center justify-end gap-3 px-1 text-[11px] text-gray-400">
            <Button v-if="!hidden && !inline" type="tertiary" size="xs" class="bg-white" :label="ctrans('Jump to the message')" @click="emit('jump-to-message')">
                <template #icon>
                    <FontAwesomeIcon :icon="faArrowUp" class="text-gray-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <button type="button" :aria-expanded="!hidden" class="flex items-center gap-1 hover:text-gray-600 focus:outline-none focus-visible:underline"
                :title="ctrans('Remembered for every chat until you turn them back on')" @click="toggleHidden">
                <FontAwesomeIcon :icon="hidden ? faChevronUp : faChevronDown" fixed-width />
                {{ hidden ? ctrans("Turn AI suggestions on") : ctrans("Turn AI suggestions off") }}
            </button>
        </div>
    </div>
</template>
