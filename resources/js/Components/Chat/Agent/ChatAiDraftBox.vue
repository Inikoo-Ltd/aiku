<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faRobot, faBookOpen, faBug, faDatabase, faCommentCheck } from "@fal"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    sessionUlid?: string | null
    whatsapp?: boolean
    readOnly?: boolean
    preview?: boolean
}>()

const emit = defineEmits<{ (e: "use", text: string): void, (e: "action", action: "close" | "wait"): void }>()

interface Draft {
    id: number
    text: string
    topic_label: string
}

interface Guide {
    title: string
    summary: string
    url: string
    probability: number
    message?: string
}

interface Engineer {
    probability: number
    platform: string | null
    platform_label: string | null
    symptom_label: string | null
    ticket: { reference: string, subject: string } | null
    raised?: string | null
}

const draft = ref<Draft | null>(null)
const guides = ref<Guide[]>([])
const engineer = ref<Engineer | null>(null)
const facts = ref<string[]>([])
const nextStep = ref<{ kind: "close" | "wait" | "owed", probability: number } | null>(null)
const readingId = ref<number | null>(null)
const raising = ref(false)
const raised = ref<{ reference: string, url: string, added: boolean } | null>(null)
const busy = ref(false)
let channel: any = null
let channelName: string | null = null
let latestLoad = 0

const clear = () => {
    draft.value = null
    guides.value = []
    engineer.value = null
    facts.value = []
    nextStep.value = null
    readingId.value = null
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
        guides.value = data?.suggestions?.guides ?? []
        engineer.value = data?.suggestions?.engineer ?? null
        facts.value = data?.suggestions?.facts ?? []
        nextStep.value = data?.suggestions?.next_step ?? null
        readingId.value = data?.suggestions?.reading_id ?? null
    } catch {
        if (thisLoad === latestLoad) clear()
    }
}

const usedKey = ref<string | null>(null)
const suggestionsKey = computed(() => [props.sessionUlid ?? "", readingId.value ?? "", draft.value?.id ?? "", ...guides.value.map((guide) => guide.url)].join("|"))
const used = computed(() => usedKey.value !== null && usedKey.value === suggestionsKey.value)
const shownGuides = computed(() => used.value ? [] : guides.value.filter((guide) => !draft.value?.text.includes(guide.url)))
const main = computed<"draft" | "guide" | "engineer" | "next" | null>(() => {
    if (used.value) return null

    return draft.value ? "draft" : shownGuides.value.length ? "guide" : engineer.value ? "engineer" : nextStep.value ? "next" : null
})
const useText = (text: string) => {
    usedKey.value = suggestionsKey.value
    emit("use", text)
}

const recordUse = (kind: "guide" | "close" | "wait") => {
    if (!props.sessionUlid) return
    const url = props.whatsapp
        ? route("grp.api.chats.meta.sessions.suggestion_used", [props.sessionUlid])
        : route("grp.api.chats.sessions.suggestion_used", [props.sessionUlid])
    axios.post(url, { kind, reading_id: readingId.value }).catch(() => null)
}

const suggestGuide = (guide: Guide) => {
    useText(guide.message ?? `${guide.title}\n${guide.url}`)
    recordUse("guide")
}

const takeNextStep = (action: "close" | "wait") => {
    recordUse(action)
    usedKey.value = suggestionsKey.value
    emit("action", action)
}

const writingGoodbye = ref(false)
const failure = ref<string | null>(null)

const writeGoodbye = async () => {
    if (!props.sessionUlid || writingGoodbye.value) return
    writingGoodbye.value = true
    failure.value = null
    const sessionUlid = props.sessionUlid

    try {
        const url = props.whatsapp
            ? route("grp.api.chats.meta.sessions.goodbye", [sessionUlid])
            : route("grp.api.chats.sessions.goodbye", [sessionUlid])
        const { data } = await axios.post(url)
        if (sessionUlid === props.sessionUlid && data?.data?.message) useText(data.data.message)
    } catch (error: any) {
        if (sessionUlid === props.sessionUlid) failure.value = error?.response?.data?.message ?? ctrans("No goodbye could be written, please write your own")
    } finally {
        writingGoodbye.value = false
    }
}
const alsoGuides = computed(() => main.value === "guide" ? shownGuides.value.slice(1) : shownGuides.value)
const raisedReference = computed(() => raised.value?.reference ?? engineer.value?.raised ?? null)

const raise = async () => {
    if (!props.sessionUlid || raising.value) return
    raising.value = true

    try {
        const url = props.whatsapp
            ? route("grp.api.chats.meta.sessions.engineer_ticket", [props.sessionUlid])
            : route("grp.api.chats.sessions.engineer_ticket", [props.sessionUlid])
        const sessionUlid = props.sessionUlid
        const { data } = await axios.post(url)
        if (sessionUlid === props.sessionUlid) raised.value = data?.data ?? null
    } catch (error: any) {
        await load()
        failure.value = error?.response?.data?.message ?? ctrans("The ticket could not be raised")
    } finally {
        raising.value = false
    }
}

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
    } catch {
        await load()
    } finally {
        busy.value = false
    }
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
    raised.value = null
    failure.value = null
    load()
    listen()
}, { immediate: true })

onBeforeUnmount(stopListening)
</script>

<template>
    <div v-if="main || (used && engineer) || facts.length || failure" class="mb-1.5 text-xs">
        <p v-if="failure" class="mb-1 px-1 text-[11px] text-red-600">{{ failure }}</p>
        <div v-if="facts.length" class="mb-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 px-1 text-[11px] text-gray-500">
            <span class="flex items-center gap-1"><FontAwesomeIcon :icon="faDatabase" fixed-width class="text-sky-600" />{{ ctrans("In aiku:") }}</span>
            <span v-for="fact in facts" :key="fact" class="text-gray-700">{{ fact }}</span>
        </div>
        <div v-if="main === 'draft' && draft" class="rounded-xl border border-indigo-200 bg-indigo-50/60 px-3 py-2">
            <div class="flex items-center gap-1.5 text-[11px] text-indigo-700">
                <FontAwesomeIcon :icon="faRobot" fixed-width />
                <span>{{ ctrans("Draft written by AI from aiku data") }} · {{ draft.topic_label }}</span>
            </div>
            <p class="mt-1 line-clamp-6 whitespace-pre-line text-gray-800" :title="draft.text">{{ draft.text }}</p>
            <div v-if="!preview" class="mt-2 flex gap-2">
                <button type="button" :disabled="busy" @click="decide('take')"
                    class="rounded-md bg-indigo-600 px-2.5 py-0.5 text-[11px] font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                    {{ ctrans("Use") }}
                </button>
                <button type="button" :disabled="busy" @click="decide('discard')"
                    class="rounded-md px-2.5 py-0.5 text-[11px] text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-white disabled:opacity-50">
                    {{ ctrans("Discard") }}
                </button>
            </div>
        </div>

        <div v-else-if="main === 'guide'" class="rounded-xl border border-emerald-200 bg-emerald-50/60 px-3 py-2">
            <div class="flex items-center gap-1.5 text-[11px] text-emerald-700">
                <FontAwesomeIcon :icon="faBookOpen" fixed-width />
                <span>{{ ctrans("The answer is probably in this guide") }} · {{ ctrans(":percent% sure", { percent: Math.round(shownGuides[0].probability * 100) }) }}</span>
            </div>
            <p class="mt-1 font-medium text-gray-800">{{ shownGuides[0].title }}</p>
            <p class="mt-0.5 line-clamp-2 text-gray-600" :title="shownGuides[0].summary">{{ shownGuides[0].summary }}</p>
            <div class="mt-2 flex gap-2">
                <a :href="shownGuides[0].url" target="_blank" rel="noopener"
                    class="rounded-md px-2.5 py-0.5 text-[11px] text-emerald-800 ring-1 ring-inset ring-emerald-300 hover:bg-white">
                    {{ ctrans("Read guide") }}
                </a>
                <button v-if="!preview" type="button" @click="suggestGuide(shownGuides[0])"
                    class="rounded-md bg-emerald-600 px-2.5 py-0.5 text-[11px] font-medium text-white hover:bg-emerald-500">
                    {{ ctrans("Suggest it to the customer") }}
                </button>
            </div>
        </div>

        <div v-else-if="main === 'engineer' && engineer" class="rounded-xl border border-amber-200 bg-amber-50/70 px-3 py-2">
            <div class="flex items-center gap-1.5 text-[11px] text-amber-800">
                <FontAwesomeIcon :icon="faBug" fixed-width />
                <span>{{ ctrans("This probably needs the programmers") }} · {{ ctrans(":percent% sure", { percent: Math.round(engineer.probability * 100) }) }}</span>
            </div>
            <p v-if="engineer.platform_label || engineer.symptom_label" class="mt-1 font-medium text-gray-800">
                {{ [engineer.platform_label, engineer.symptom_label].filter(Boolean).join(" · ") }}
            </p>
            <p v-if="engineer.ticket && !raisedReference" class="mt-0.5 text-gray-600">
                {{ ctrans("Looks like a problem we already know about:") }}
                <a :href="route('grp.tickets.show', engineer.ticket.reference)" target="_blank" rel="noopener" class="font-medium underline">{{ engineer.ticket.reference }}</a>
                {{ engineer.ticket.subject }}
            </p>
            <p v-if="raisedReference" class="mt-1 text-gray-700">
                {{ raised?.added ? ctrans("Customer added to") : ctrans("Ticket raised:") }}
                <a :href="route('grp.tickets.show', raisedReference)" target="_blank" rel="noopener" class="font-medium underline">{{ raisedReference }}</a>
            </p>
            <div v-else-if="!preview" class="mt-2 flex gap-2">
                <button type="button" :disabled="raising" @click="raise"
                    class="rounded-md bg-amber-600 px-2.5 py-0.5 text-[11px] font-medium text-white hover:bg-amber-500 disabled:opacity-50">
                    {{ engineer.ticket ? ctrans("Add this customer to :reference", { reference: engineer.ticket.reference }) : ctrans("Raise CUS ticket") }}
                </button>
            </div>
        </div>

        <div v-else-if="main === 'next' && nextStep" class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
            <div class="flex items-center gap-1.5 text-[11px] text-gray-600">
                <FontAwesomeIcon :icon="faCommentCheck" fixed-width />
                <span>{{ nextStep.kind === "close" ? ctrans("The customer seems to be done") : nextStep.kind === "wait" ? ctrans("The customer is sending us something") : ctrans("We still owe this customer something") }} · {{ ctrans(":percent% sure", { percent: Math.round(nextStep.probability * 100) }) }}</span>
            </div>
            <p v-if="nextStep.kind === 'owed'" class="mt-1 text-gray-600">{{ ctrans("Check what we promised before closing.") }}</p>
            <div v-if="!preview && nextStep.kind !== 'owed'" class="mt-2 flex gap-2">
                <button v-if="nextStep.kind === 'close'" type="button" :disabled="writingGoodbye" @click="writeGoodbye"
                    class="rounded-md bg-gray-700 px-2.5 py-0.5 text-[11px] font-medium text-white hover:bg-gray-600 disabled:opacity-50">
                    {{ writingGoodbye ? ctrans("Writing…") : ctrans("Write a goodbye") }}
                </button>
                <button v-if="nextStep.kind === 'close'" type="button" @click="takeNextStep('close')"
                    class="rounded-md px-2.5 py-0.5 text-[11px] text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-white">
                    {{ ctrans("End chat") }}
                </button>
                <button v-if="nextStep.kind === 'wait'" type="button" @click="takeNextStep('wait')"
                    class="rounded-md bg-gray-700 px-2.5 py-0.5 text-[11px] font-medium text-white hover:bg-gray-600">
                    {{ ctrans("Wait 3 days for them") }}
                </button>
            </div>
        </div>

        <div v-if="alsoGuides.length || (engineer && main !== 'engineer')" class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 px-1 text-[11px] text-gray-500">
            <span v-if="main">{{ ctrans("Also:") }}</span>
            <span v-for="guide in alsoGuides" :key="guide.url" class="flex items-center gap-1">
                <FontAwesomeIcon :icon="faBookOpen" fixed-width class="text-emerald-600" />
                <a :href="guide.url" target="_blank" rel="noopener" class="text-gray-700 hover:underline" :title="guide.summary">{{ guide.title }}</a>
                <button v-if="!preview" type="button" class="text-emerald-700 underline" @click="suggestGuide(guide)">{{ ctrans("suggest") }}</button>
            </span>
            <span v-if="engineer && main !== 'engineer'" class="flex items-center gap-1">
                <FontAwesomeIcon :icon="faBug" fixed-width class="text-amber-600" />
                <span class="text-gray-700">{{ ctrans("Probably needs the programmers") }} ({{ Math.round(engineer.probability * 100) }}%)</span>
                <a v-if="raisedReference" :href="route('grp.tickets.show', raisedReference)" target="_blank" rel="noopener" class="font-medium text-amber-800 underline">{{ raisedReference }}</a>
                <button v-else-if="!preview" type="button" :disabled="raising" class="text-amber-800 underline disabled:opacity-50" @click="raise">
                    {{ engineer.ticket ? ctrans("add to :reference", { reference: engineer.ticket.reference }) : ctrans("raise CUS ticket") }}
                </button>
            </span>
        </div>
    </div>
</template>
