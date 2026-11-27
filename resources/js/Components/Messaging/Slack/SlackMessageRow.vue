<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import axios from "axios"
import { router, usePage } from "@inertiajs/vue3"
import { Dialog, Popover } from "primevue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faCommentDots, faFile, faLink, faPencil, faReply, faRobot, faSmilePlus, faThumbtack, faTimes, faTrashAlt } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import DeskMessageToolbar, { type DeskToolbarMenuItem } from "@/Components/Messaging/DeskMessageToolbar.vue"
import EmojiPicker from "@/Components/Messaging/EmojiPicker.vue"
import DeskMessageEditor, { type DeskPerson } from "@/Components/Messaging/DeskMessageEditor.vue"
import { slackTextToBox } from "@/Components/Messaging/deskMarkup"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import SlackNodes, { type SlackNode } from "@/Components/Messaging/Slack/SlackNodes.vue"
import SlackMessageVersions from "@/Components/Messaging/Slack/SlackMessageVersions.vue"
import SlackMessageFiles, { type SlackMessageFile } from "@/Components/Messaging/Slack/SlackMessageFiles.vue"

library.add(faCheck, faCommentDots, faFile, faLink, faPencil, faReply, faRobot, faSmilePlus, faThumbtack, faTimes, faTrashAlt)

export interface SlackStreamMessage {
    id: number
    ts: string
    thread_ts: string | null
    is_broadcast: boolean
    is_deleted: boolean
    is_system: boolean
    is_gap?: boolean
    text?: string
    author: { key: string | null; user_id: number | null; name: string | null; avatar: Record<string, string> | null; is_bot: boolean }
    sent_at: string
    is_edited: boolean
    versions_url: string | null
    is_pinned: boolean
    nodes: SlackNode[]
    reactions: { name: string; unicode: string | null; url: string | null; count: number; people: string[] }[]
    my_reactions: string[]
    reply_count: number
    latest_reply_at: string | null
    reply_users: { name: string | null; avatar: Record<string, string> | null }[]
    files: SlackMessageFile[]
}

const props = withDefaults(defineProps<{
    message: SlackStreamMessage
    channelLinks: Record<string, number>
    continues?: boolean
    inThread?: boolean
    bubble?: "mine" | "theirs" | null
    onWallpaper?: boolean
}>(), { continues: false, inThread: false, bubble: null, onWallpaper: false })

const emit = defineEmits<{ (e: "openThread", ts: string): void }>()

const inlineNodes = (nodes: SlackNode[]) => nodes.flatMap((node) => (node.type === "paragraph" ? node.children : [node]))
const authorName = (message: SlackStreamMessage) => message.author.name ?? ctrans("Unknown person")
const time = (iso: string) => useFormatTime(iso, { formatTime: "HH:mm" })
const reactionTooltip = (reaction: SlackStreamMessage["reactions"][number]) =>
    reaction.people.length ? reaction.people.join(", ") : ctrans(":count people", { count: String(reaction.count) })

type SlackWrite = { allowed: boolean; reason: string | null; stand_in?: boolean; connect_url: string | null; people?: DeskPerson[]; channels?: DeskPerson[] }
type Reaction = SlackStreamMessage["reactions"][number]

const page = usePage()
const pageProps = computed(() => page.props as Record<string, any>)
const write = computed<SlackWrite | null>(() => pageProps.value.slack_channel?.write ?? null)
const channelId = computed<number | null>(() => (pageProps.value.slack_channel?.is_archived ? null : pageProps.value.slack_channel?.id ?? null))
const myId = computed<number | null>(() => pageProps.value.auth?.user?.id ?? null)

const override = ref<SlackStreamMessage | null>(null)
watch(() => props.message, () => (override.value = null))
const shown = computed(() => override.value ?? props.message)
const isGone = computed(() => !!override.value?.is_deleted && !override.value.reply_count)

const canAct = computed(() => !!write.value?.allowed && channelId.value !== null && !shown.value.is_deleted && !shown.value.is_system)
const isOwn = computed(() => canAct.value && !shown.value.author.is_bot && shown.value.author.user_id !== null && shown.value.author.user_id === myId.value)

const isMine = (name: string) => shown.value.my_reactions?.includes(name)
const error = ref<string | null>(null)
const showConnect = ref(false)
const busy = ref(false)

const ready = (standInMay = false) => {
    error.value = null
    if (write.value?.reason && !(standInMay && write.value?.stand_in)) {
        showConnect.value = true
        return false
    }
    return true
}

const errorOf = (exception: any): string =>
    exception?.response?.data?.errors?.slack?.[0]
    ?? exception?.response?.data?.errors?.text?.[0]
    ?? (exception?.response?.status === 429 ? ctrans("Too many changes at once, wait a minute") : ctrans("Slack did not accept this, try again later"))

const send = async (method: "post" | "patch" | "delete", routeName: string, body: Record<string, unknown>) => {
    const { data } = await axios.request({ method, url: route(routeName, { slackChannel: channelId.value }), data: { ts: shown.value.ts, ...body } })
    if (data?.message) override.value = data.message
}

const withReaction = (message: SlackStreamMessage, key: string, unicode: string | null, add: boolean): SlackStreamMessage => {
    const reactions: Reaction[] = message.reactions
        .map((reaction) => (reaction.name === key ? { ...reaction, count: reaction.count + (add ? 1 : -1) } : reaction))
        .filter((reaction) => reaction.count > 0)
    if (add && !reactions.some((reaction) => reaction.name === key)) reactions.push({ name: key, unicode, url: null, count: 1, people: [] })
    const mine = message.my_reactions.filter((name) => name !== key)
    return { ...message, reactions, my_reactions: add ? [...mine, key] : mine }
}

const react = async (key: string, body: { name: string } | { emoji: string }, add: boolean) => {
    if (busy.value || !ready()) return
    const before = override.value
    override.value = withReaction(shown.value, key, "emoji" in body ? body.emoji : null, add)
    busy.value = true
    try {
        await send("post", "grp.chat.staff.slack.reactions.toggle", { ...body, add })
    } catch (exception) {
        override.value = before
        error.value = errorOf(exception)
    } finally {
        busy.value = false
    }
}

const onChip = (reaction: Reaction) => {
    if (canAct.value) react(reaction.name, { name: reaction.name }, !isMine(reaction.name))
}

const picker = ref()
const openPicker = (event: Event) => {
    if (ready()) picker.value?.toggle(event)
}
const quickReact = (emoji: string) => {
    const held = shown.value.reactions.find((reaction) => reaction.unicode === emoji)
    if (held) {
        react(held.name, { name: held.name }, !isMine(held.name))
        return
    }
    react(emoji, { emoji }, true)
}
const mineUnicode = computed(() => shown.value.reactions.filter((reaction) => reaction.unicode && isMine(reaction.name)).map((reaction) => reaction.unicode as string))

const hasToolbar = computed(() => !shown.value.is_deleted && !shown.value.is_system && !editing.value)
const copied = ref(false)
const messageLink = () => {
    const slackChannel = pageProps.value.slack_channel?.id
    const params = props.inThread && shown.value.thread_ts
        ? { slackChannel, thread: shown.value.thread_ts, reply: shown.value.ts }
        : { slackChannel, around: shown.value.ts }
    return route("grp.chat.staff.slack.show", params)
}
const copyLink = async () => {
    error.value = null
    try {
        await navigator.clipboard.writeText(messageLink())
        copied.value = true
        setTimeout(() => (copied.value = false), 2000)
    } catch {
        error.value = ctrans("Could not copy the link")
    }
}
const togglePin = async () => {
    if (busy.value || !ready()) return
    busy.value = true
    try {
        await send("post", "grp.chat.staff.slack.pin.toggle", { pinned: !shown.value.is_pinned })
        router.reload({ only: ["slack_channel"] })
    } catch (exception) {
        error.value = errorOf(exception)
    } finally {
        busy.value = false
    }
}
const toolbarMenu = computed<DeskToolbarMenuItem[]>(() => [
    { label: ctrans("Copy link"), icon: "fal fa-link", command: copyLink },
    ...(canAct.value ? [{ label: shown.value.is_pinned ? ctrans("Unpin from channel") : ctrans("Pin to channel"), icon: "fal fa-thumbtack", command: togglePin }] : []),
    ...(isOwn.value
        ? [
            { label: ctrans("Edit message"), icon: "fal fa-pencil", command: startEdit },
            { label: ctrans("Delete message"), icon: "fal fa-trash-alt", danger: true, command: askDelete },
        ]
        : []),
])

const onPick = (emoji: string) => {
    picker.value?.hide()
    const held = shown.value.reactions.find((reaction) => reaction.unicode === emoji)
    if (held) {
        if (!isMine(held.name)) react(held.name, { name: held.name }, true)
        return
    }
    react(emoji, { emoji }, true)
}

const editing = ref(false)
const draft = ref("")
const original = ref("")
const editBox = ref()
const startEdit = async () => {
    if (busy.value || !ready(true)) return
    let text = shown.value.text
    if (text === undefined) {
        busy.value = true
        try {
            const { data } = await axios.get(route("grp.chat.staff.slack.messages.own-text", { slackChannel: channelId.value }), { params: { ts: shown.value.ts } })
            text = data.text as string
        } catch (exception) {
            error.value = errorOf(exception)
            return
        } finally {
            busy.value = false
        }
    }
    original.value = slackTextToBox(text ?? "", write.value?.people, write.value?.channels)
    draft.value = original.value
    editing.value = true
    nextTick(() => editBox.value?.focus())
}
const cancelEdit = () => {
    editing.value = false
    error.value = null
}
const saveEdit = async () => {
    if (busy.value) return
    if (!draft.value.trim()) {
        error.value = ctrans("Write something before saving")
        return
    }
    if (draft.value === original.value) {
        cancelEdit()
        return
    }
    busy.value = true
    error.value = null
    try {
        await send("patch", "grp.chat.staff.slack.messages.update", { text: draft.value, format: "readable" })
        editing.value = false
    } catch (exception) {
        error.value = errorOf(exception)
    } finally {
        busy.value = false
    }
}

const confirmingDelete = ref(false)
const deleteError = ref<string | null>(null)
const askDelete = () => {
    if (!ready(true)) return
    deleteError.value = null
    confirmingDelete.value = true
}
const confirmDelete = async () => {
    if (busy.value) return
    busy.value = true
    deleteError.value = null
    try {
        await send("delete", "grp.chat.staff.slack.messages.delete", {})
        confirmingDelete.value = false
    } catch (exception) {
        deleteError.value = errorOf(exception)
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <div v-if="isGone" class="hidden" />

    <div v-else-if="shown.is_system" class="flex items-baseline gap-x-2 py-0.5 pl-11 text-xs text-[#616061]">
        <span class="min-w-0 break-words"><SlackNodes :nodes="inlineNodes(shown.nodes)" :channel-links="channelLinks" /></span>
        <span class="shrink-0 text-xs text-[#616061]">{{ time(shown.sent_at) }}</span>
    </div>

    <div v-else class="group relative flex gap-x-2 px-1 text-[#1d1c1d]" :class="[continues ? 'py-0.5' : 'mt-2 pt-1 pb-0.5', shown.is_pinned ? 'bg-[#fef9ed]' : bubble ? '' : 'hover:bg-black/[0.03]']">
        <div class="w-9 shrink-0">
            <span v-if="shown.is_deleted && !continues" class="flex h-9 w-9 items-center justify-center rounded-[22%] bg-[#f8f8f8] text-[#616061]">
                <FontAwesomeIcon icon="fal fa-comment-dots" fixed-width aria-hidden="true" />
            </span>
            <TicketUserAvatar v-else-if="!continues" :name="shown.author.name" :avatar="shown.author.avatar" size="lg" square />
            <span v-else class="block pt-0.5 text-right text-xs leading-5 text-[#616061] opacity-0 group-hover:opacity-100">{{ time(shown.sent_at) }}</span>
        </div>
        <div class="min-w-0 text-[15px] leading-[1.46668]" :class="bubble ? ['max-w-[85%] rounded-lg px-3 py-1.5 shadow-sm', bubble === 'mine' ? 'bg-[var(--app-accent-soft)]' : onWallpaper ? 'bg-white' : 'bg-gray-100'] : 'flex-1'">
            <template v-if="shown.is_deleted">
                <div class="flex flex-wrap items-baseline gap-x-1.5">
                    <span class="italic text-[#616061]">{{ ctrans("This message was deleted.") }}</span>
                    <span class="text-xs text-[#616061]">{{ time(shown.sent_at) }}</span>
                </div>
            </template>
            <template v-else>
                <button
                    v-if="shown.is_broadcast && shown.thread_ts && !inThread"
                    type="button"
                    class="flex items-center gap-1 text-xs text-[#616061] hover:underline"
                    @click="emit('openThread', shown.thread_ts)">
                    <FontAwesomeIcon icon="fal fa-reply" fixed-width aria-hidden="true" />{{ ctrans("Replied to a thread") }}
                </button>
                <div v-if="!continues" class="flex flex-wrap items-baseline gap-x-1.5">
                    <span class="font-black" :class="shown.author.name ? 'text-[#1d1c1d]' : 'italic text-[#616061]'">{{ authorName(shown) }}</span>
                    <span v-if="shown.author.is_bot" class="inline-flex items-center gap-0.5 rounded bg-gray-200 px-1 text-xxs font-medium uppercase text-gray-600">
                        <FontAwesomeIcon icon="fal fa-robot" fixed-width aria-hidden="true" />{{ ctrans("Bot") }}
                    </span>
                    <span class="text-xs text-[#616061] hover:underline" v-tooltip="useFormatTime(shown.sent_at, { formatTime: 'hm' })">{{ time(shown.sent_at) }}</span>
                </div>
                <div v-if="shown.is_pinned" class="flex items-center gap-1 text-xxs font-medium text-amber-700">
                    <FontAwesomeIcon icon="fal fa-thumbtack" fixed-width aria-hidden="true" />{{ ctrans("Pinned") }}
                </div>
                <div v-if="shown.is_broadcast && inThread" class="flex items-center gap-1 text-xs text-[#616061]">
                    <FontAwesomeIcon icon="fal fa-reply" fixed-width aria-hidden="true" />{{ ctrans("Also sent to the channel") }}
                </div>

                <div v-if="editing" class="mt-0.5">
                    <DeskMessageEditor
                        ref="editBox"
                        v-model="draft"
                        :sending="busy"
                        :disabled="busy"
                        :people="write?.people ?? []"
                        :channels="write?.channels ?? []"
                        mention-tokens
                        attach-disabled
                        @submit="saveEdit"
                        @keydown.esc.prevent="cancelEdit" />
                    <div class="mt-1 flex items-center gap-x-2">
                        <Button type="tertiary" size="xxs" :label="ctrans('Cancel')" :disabled="busy" @click="cancelEdit">
                            <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <Button type="primary" size="xxs" :label="ctrans('Save')" :loading="busy" @click="saveEdit">
                            <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <span class="text-xxs text-gray-400">{{ ctrans("Enter to save, Esc to cancel") }}</span>
                    </div>
                </div>

                <p v-else-if="shown.is_gap" class="italic text-[#616061]">
                    {{ shown.text !== undefined ? ctrans("Your message is not in Verba yet. Bring your messages to see it here") : ctrans("Not brought to Verba yet") }}
                </p>

                <div v-else class="text-[#1d1c1d]" :class="shown.is_edited && '[&>p:last-of-type]:inline'">
                    <SlackNodes :nodes="shown.nodes" :channel-links="channelLinks" />
                    <SlackMessageVersions v-if="shown.is_edited && shown.versions_url" :url="shown.versions_url" :channel-links="channelLinks" />
                    <span v-else-if="shown.is_edited" class="ml-1 text-xs text-[#616061]" v-tooltip="ctrans('Edited in Slack')">({{ ctrans("edited") }})</span>
                </div>

                <SlackMessageFiles v-if="shown.files.length" :files="shown.files" />

                <div v-if="shown.reactions.length" class="mt-1 flex flex-wrap gap-1">
                    <component
                        :is="canAct ? 'button' : 'span'"
                        v-for="reaction in shown.reactions"
                        :key="reaction.name"
                        v-tooltip="reactionTooltip(reaction)"
                        :type="canAct ? 'button' : undefined"
                        :aria-pressed="canAct ? isMine(reaction.name) : undefined"
                        class="inline-flex h-6 items-center gap-x-1 rounded-xl border px-1.5 text-xs"
                        :class="[
                            isMine(reaction.name) ? 'border-[#1d9bd1] bg-[#e8f5fa] text-[#1264a3]' : 'border-transparent bg-[#1d1c1d0f] text-[#1d1c1d]',
                            canAct ? 'cursor-pointer hover:border-[#1d1c1d80] hover:bg-white' : 'cursor-default',
                        ]"
                        @click="onChip(reaction)">
                        <span v-if="reaction.unicode">{{ reaction.unicode }}</span>
                        <img v-else-if="reaction.url" :src="reaction.url" :alt="':' + reaction.name + ':'" class="h-4 w-4 object-contain" loading="lazy" />
                        <span v-else class="text-xxs">:{{ reaction.name }}:</span>
                        <span class="tabular-nums">{{ reaction.count }}</span>
                    </component>
                    <button
                        v-if="canAct"
                        type="button"
                        class="inline-flex h-6 items-center rounded-xl border border-transparent bg-[#1d1c1d0f] px-1.5 text-xs text-[#616061] opacity-0 hover:border-[#1d1c1d80] hover:bg-white group-hover:opacity-100 focus:opacity-100"
                        :aria-label="ctrans('Add reaction')"
                        v-tooltip="ctrans('Add reaction')"
                        @click="openPicker">
                        <FontAwesomeIcon icon="fal fa-smile-plus" fixed-width aria-hidden="true" />
                    </button>
                </div>

                <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
                <p v-if="showConnect && write?.reason" class="mt-1 text-xs text-gray-600">
                    {{ write.reason }}
                    <a v-if="write.connect_url" :href="write.connect_url" class="ml-1 font-medium text-[#1264a3] hover:underline">{{ ctrans("Connect Slack") }}</a>
                </p>
            </template>

            <button
                v-if="shown.reply_count && !inThread"
                type="button"
                class="mt-1 flex max-w-full items-center gap-x-1.5 rounded-lg border border-transparent p-1 -ml-1 text-[13px] hover:border-[#1d1c1d21] hover:bg-white"
                @click="emit('openThread', shown.ts)">
                <span v-if="shown.reply_users.length" class="flex shrink-0 -space-x-1">
                    <TicketUserAvatar v-for="(person, personIndex) in shown.reply_users" :key="personIndex" :name="person.name" :avatar="person.avatar" size="sm" square class="ring-1 ring-white" />
                </span>
                <span class="whitespace-nowrap font-bold text-[#1264a3] hover:underline">{{ shown.reply_count === 1 ? ctrans("1 reply") : ctrans(":count replies", { count: String(shown.reply_count) }) }}</span>
                <span v-if="shown.latest_reply_at" class="truncate text-[#616061]">{{ ctrans("Last reply :time", { time: useFormatTime(shown.latest_reply_at, { formatTime: "short-datetime" }) }) }}</span>
            </button>
        </div>

        <div v-if="hasToolbar" class="absolute -top-4 right-4 z-10 invisible transition-[visibility] delay-0 group-hover:visible group-hover:delay-100 group-focus-within:visible">
            <DeskMessageToolbar
                :can-react="canAct"
                :mine="mineUnicode"
                :reply-label="inThread ? null : ctrans('Reply in thread')"
                :menu="toolbarMenu"
                @react="quickReact"
                @add-reaction="openPicker"
                @reply="emit('openThread', shown.thread_ts ?? shown.ts)" />
        </div>
        <button v-if="isOwn && hasToolbar" type="button" class="sr-only" tabindex="-1" data-desk-own-edit :aria-label="ctrans('Edit message')" @click="startEdit">
            <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
        </button>
        <span v-if="copied" class="absolute -top-4 right-4 z-20 rounded bg-[#1d1c1d] px-2 py-1 text-xs text-white" role="status">{{ ctrans("Link copied") }}</span>

        <Popover v-if="canAct" ref="picker">
            <EmojiPicker @pick="onPick" />
        </Popover>

        <Dialog v-if="isOwn" v-model:visible="confirmingDelete" modal :header="ctrans('Delete message')" :style="{ width: '28rem' }" :breakpoints="{ '640px': '92vw' }">
            <p class="text-sm text-gray-700">{{ ctrans("Delete this message in Slack for everyone? This cannot be undone.") }}</p>
            <p v-if="deleteError" class="mt-2 text-xs text-red-600">{{ deleteError }}</p>
            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" :disabled="busy" @click="confirmingDelete = false">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" full :label="ctrans('Delete')" :loading="busy" @click="confirmDelete">
                        <template #icon><FontAwesomeIcon icon="fal fa-trash-alt" class="text-amber-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
