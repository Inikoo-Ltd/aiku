<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, reactive, ref, watch } from "vue"
import { EditorContent, useEditor } from "@tiptap/vue-3"
import { Extension } from "@tiptap/core"
import StarterKit from "@tiptap/starter-kit"
import Link from "@tiptap/extension-link"
import Mention from "@tiptap/extension-mention"
import { Placeholder } from "@tiptap/extensions"
import { PluginKey } from "@tiptap/pm/state"
import Popover from "primevue/popover"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faAt, faBracketsCurly, faCode, faPaperPlane, faPlus, faQuoteRight, faSmile } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import ChatFormattingToolbar from "@/Components/Chat/ChatFormattingToolbar.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import { DESK_EMOJI_NAMES } from "@/Components/Messaging/deskEmojiNames"
import { deskDocToSlackText } from "@/Components/Messaging/deskMarkup"

const EmojiPicker = defineAsyncComponent(() => import("@/Components/Messaging/EmojiPicker.vue"))

library.add(faAt, faBracketsCurly, faCode, faPaperPlane, faPlus, faQuoteRight, faSmile)

export interface DeskPerson {
    id: string
    label: string
    avatar?: { original?: string | null } | null
}

type Suggestion = { kind: "@"; person: DeskPerson } | { kind: ":"; name: string; unicode: string }

const props = withDefaults(defineProps<{
    modelValue?: string
    placeholder?: string
    disabled?: boolean
    sending?: boolean
    people?: DeskPerson[]
    canSend?: boolean
    canEditLast?: boolean
    attachLabel?: string
    attachDisabled?: boolean
}>(), { modelValue: "", people: () => [] })

const emit = defineEmits<{
    (e: "update:modelValue", value: string): void
    (e: "submit", text: string): void
    (e: "editLast"): void
    (e: "attach"): void
    (e: "paste", event: ClipboardEvent): void
}>()

const suggest = reactive<{ open: boolean; kind: "@" | ":"; query: string; items: Suggestion[]; index: number; pick: ((item: Suggestion) => void) | null }>({
    open: false,
    kind: "@",
    query: "",
    items: [],
    index: 0,
    pick: null,
})
const listOpen = computed(() => suggest.open && suggest.items.length > 0)

const peopleFor = (query: string): Suggestion[] => {
    const wanted = query.toLowerCase()
    return props.people
        .filter((person) => !wanted || person.label.toLowerCase().split(/\s+/).some((word) => word.startsWith(wanted)) || person.label.toLowerCase().startsWith(wanted))
        .slice(0, 8)
        .map((person) => ({ kind: "@", person }))
}

const emojiFor = (query: string): Suggestion[] => {
    const wanted = query.toLowerCase()
    if (wanted.length < 2) return []
    const seen = new Set<string>()
    return [...DESK_EMOJI_NAMES.filter(([name]) => name.startsWith(wanted)), ...DESK_EMOJI_NAMES.filter(([name]) => !name.startsWith(wanted) && name.includes(wanted))]
        .filter(([, unicode]) => !seen.has(unicode) && !!seen.add(unicode))
        .slice(0, 8)
        .map(([name, unicode]) => ({ kind: ":", name, unicode }))
}

const renderSuggestions = (kind: "@" | ":") => () => ({
    onStart: (state: any) => Object.assign(suggest, { open: true, kind, query: state.query, items: state.items, index: 0, pick: state.command }),
    onUpdate: (state: any) => Object.assign(suggest, { query: state.query, items: state.items, index: 0, pick: state.command }),
    onKeyDown: ({ event }: { event: KeyboardEvent }) => {
        if (!listOpen.value) return false
        if (event.key === "ArrowDown" || event.key === "ArrowUp") {
            const step = event.key === "ArrowDown" ? 1 : -1
            suggest.index = (suggest.index + step + suggest.items.length) % suggest.items.length
            return true
        }
        if (event.key === "Enter" || event.key === "Tab") {
            choose(suggest.index)
            return true
        }
        if (event.key === "Escape") {
            suggest.open = false
            return true
        }
        return false
    },
    onExit: () => {
        suggest.open = false
    },
})

const choose = (index: number) => {
    const item = suggest.items[index]
    if (item) suggest.pick?.(item)
}

const DeskKeys = Extension.create({
    name: "deskKeys",
    priority: 1000,
    addKeyboardShortcuts() {
        const submit = () => {
            if (listOpen.value) return false
            send()
            return true
        }
        return {
            Enter: () => (this.editor.isActive("codeBlock") || this.editor.isActive("listItem") ? false : submit()),
            "Mod-Enter": submit,
            "Shift-Enter": () => (this.editor.isActive("codeBlock") || this.editor.isActive("listItem") ? false : this.editor.commands.splitBlock()),
            ArrowUp: () => {
                if (!props.canEditLast || listOpen.value || !this.editor.isEmpty) return false
                emit("editLast")
                return true
            },
        }
    },
})

let lastEmitted = props.modelValue

const editor = useEditor({
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({ heading: false, horizontalRule: false, link: false, underline: false, trailingNode: false }),
        Link.configure({ autolink: true, openOnClick: false, HTMLAttributes: { class: "text-[#1264a3] underline", target: null, rel: null } }),
        Placeholder.configure({ placeholder: () => props.placeholder ?? "" }),
        Mention.configure({
            HTMLAttributes: { class: "desk-mention" },
            renderText: ({ node }) => `@${node.attrs.label ?? node.attrs.id}`,
            renderHTML: ({ options, node }) => ["span", { ...options.HTMLAttributes, "data-type": "mention" }, `@${node.attrs.label ?? node.attrs.id}`],
            suggestions: [
                {
                    char: "@",
                    pluginKey: new PluginKey("deskPeople"),
                    items: ({ query }: { query: string }) => peopleFor(query),
                    command: ({ editor: current, range, props: item }: any) => {
                        current.chain().focus().insertContentAt(range, [
                            { type: "mention", attrs: { id: item.person.id, label: item.person.label, mentionSuggestionChar: "@" } },
                            { type: "text", text: " " },
                        ]).run()
                    },
                    render: renderSuggestions("@"),
                },
                {
                    char: ":",
                    pluginKey: new PluginKey("deskEmoji"),
                    items: ({ query }: { query: string }) => emojiFor(query),
                    command: ({ editor: current, range, props: item }: any) => {
                        current.chain().focus().insertContentAt(range, item.unicode).run()
                    },
                    render: renderSuggestions(":"),
                },
            ],
        }),
        DeskKeys,
    ],
    editorProps: {
        handlePaste: (_view, event) => {
            emit("paste", event)
            return event.defaultPrevented
        },
    },
    onUpdate: ({ editor: current }) => {
        lastEmitted = deskDocToSlackText(current.getJSON() as any)
        emit("update:modelValue", lastEmitted)
    },
})

watch(() => props.modelValue, (value) => {
    if (!editor.value || value === lastEmitted) return
    lastEmitted = value
    editor.value.commands.setContent(value ? value.split("\n").map((line) => ({ type: "paragraph", content: line ? [{ type: "text", text: line }] : [] })) : "", { emitUpdate: false })
})

watch(() => props.disabled, (disabled) => editor.value?.setEditable(!disabled))

onBeforeUnmount(() => editor.value?.destroy())

const hasText = computed(() => !!props.modelValue.trim())

const send = () => {
    if (props.sending || props.disabled || (!hasText.value && !props.canSend)) return
    emit("submit", lastEmitted)
}

const toggle = (name: "code" | "codeBlock" | "blockquote") => {
    const chain = editor.value?.chain().focus()
    if (!chain) return
    ;({ code: () => chain.toggleCode(), codeBlock: () => chain.toggleCodeBlock(), blockquote: () => chain.toggleBlockquote() })[name]().run()
}

const extraButtons = [
    { name: "blockquote" as const, icon: "fal fa-quote-right", label: ctrans("Quote") },
    { name: "code" as const, icon: "fal fa-code", label: ctrans("Code") },
    { name: "codeBlock" as const, icon: "fal fa-brackets-curly", label: ctrans("Code block") },
]

const emojiPopover = ref()
const insertEmoji = (emoji: string) => {
    emojiPopover.value?.hide()
    editor.value?.chain().focus().insertContent(emoji).run()
}

const startMention = () => {
    const current = editor.value
    if (!current) return
    const before = current.state.doc.textBetween(Math.max(0, current.state.selection.from - 1), current.state.selection.from)
    current.chain().focus().insertContent(before && !/\s/.test(before) ? " @" : "@").run()
}

defineExpose({
    focus: () => nextTick(() => editor.value?.commands.focus("end")),
    insertText: (text: string) => editor.value?.chain().focus().insertContent(text).run(),
})
</script>

<template>
    <div class="relative">
        <div v-if="listOpen" class="absolute bottom-full left-0 right-0 z-30 mb-1 overflow-hidden rounded-lg border border-gray-200 bg-white py-1.5 shadow-lg" role="listbox" :aria-label="suggest.kind === '@' ? ctrans('People') : ctrans('Emoji')">
            <p v-if="suggest.kind === ':'" class="px-4 pb-1 text-xs text-gray-500">{{ ctrans("Emoji matching :query", { query: ":" + suggest.query }) }}</p>
            <button
                v-for="(item, index) in suggest.items"
                :key="item.kind === '@' ? item.person.id : item.name"
                type="button"
                role="option"
                :aria-selected="index === suggest.index"
                class="flex w-full items-center gap-x-2 px-4 py-1 text-left text-[15px]"
                :class="index === suggest.index ? 'bg-[#1264a3] text-white' : 'text-gray-900'"
                @mouseenter="suggest.index = index"
                @mousedown.prevent="choose(index)">
                <template v-if="item.kind === '@'">
                    <TicketUserAvatar :name="item.person.label" :avatar="item.person.avatar ?? null" size="xs" square />
                    <span class="truncate font-bold">{{ item.person.label }}</span>
                </template>
                <template v-else>
                    <span class="w-5 text-center text-lg leading-none">{{ item.unicode }}</span>
                    <span class="truncate">:{{ item.name }}:</span>
                </template>
            </button>
        </div>

        <div class="rounded-lg border border-gray-300 bg-white transition-shadow focus-within:border-gray-400 focus-within:shadow-[0_1px_4px_rgba(0,0,0,0.12)]" :class="disabled ? 'opacity-60' : ''">
            <div class="flex items-center gap-x-0.5 rounded-t-lg bg-gray-50/80 px-1.5 py-1">
                <ChatFormattingToolbar :editor="editor" allow-link />
                <span class="mx-1 h-5 w-px bg-gray-200" aria-hidden="true" />
                <button
                    v-for="button in extraButtons"
                    :key="button.name"
                    type="button"
                    class="flex h-7 w-7 items-center justify-center rounded-lg hover:bg-gray-100"
                    :class="editor?.isActive(button.name) ? 'bg-gray-200 text-gray-900' : 'text-gray-500'"
                    v-tooltip="button.label"
                    :aria-label="button.label"
                    @mousedown.prevent
                    @click="toggle(button.name)">
                    <FontAwesomeIcon :icon="button.icon" class="text-xs" fixed-width aria-hidden="true" />
                </button>
            </div>

            <EditorContent
                :editor="editor"
                class="desk-editor max-h-60 overflow-y-auto px-3 py-2 text-[15px] leading-[22px] text-gray-900 [&_.ProseMirror]:outline-none [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_blockquote]:border-l-4 [&_blockquote]:border-gray-300 [&_blockquote]:pl-3 [&_code]:rounded [&_code]:border [&_code]:border-gray-200 [&_code]:bg-gray-100 [&_code]:px-1 [&_code]:font-mono [&_code]:text-[13px] [&_code]:text-[#c01343] [&_pre]:my-1 [&_pre]:rounded [&_pre]:border [&_pre]:border-gray-200 [&_pre]:bg-gray-50 [&_pre]:p-2 [&_pre_code]:border-0 [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_pre_code]:text-gray-800" />

            <div class="flex items-center gap-x-1 px-1.5 pb-1.5">
                <button
                    type="button"
                    class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="attachDisabled"
                    v-tooltip="attachLabel ?? ctrans('Attach')"
                    :aria-label="attachLabel ?? ctrans('Attach')"
                    @click="emit('attach')">
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                </button>
                <slot name="actions" />
                <button type="button" class="flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100" v-tooltip="ctrans('Emoji')" :aria-label="ctrans('Emoji')" @mousedown.prevent @click="emojiPopover?.toggle($event)">
                    <FontAwesomeIcon icon="fal fa-smile" fixed-width aria-hidden="true" />
                </button>
                <button v-if="people.length" type="button" class="flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100" v-tooltip="ctrans('Mention someone')" :aria-label="ctrans('Mention someone')" @mousedown.prevent @click="startMention">
                    <FontAwesomeIcon icon="fal fa-at" fixed-width aria-hidden="true" />
                </button>
                <span class="flex-1" />
                <button
                    type="button"
                    class="flex h-7 items-center justify-center rounded px-2.5 transition-colors"
                    :class="(hasText || canSend) && !sending ? 'bg-[#007a5a] text-white hover:bg-[#148567]' : 'text-gray-400'"
                    :disabled="sending || disabled || (!hasText && !canSend)"
                    v-tooltip="ctrans('Send now')"
                    :aria-label="ctrans('Send now')"
                    @click="send">
                    <FontAwesomeIcon icon="fal fa-paper-plane" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>

        <Popover ref="emojiPopover">
            <EmojiPicker @pick="insertEmoji" />
        </Popover>
    </div>
</template>

<style scoped>
.desk-editor :deep(p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
    color: #868686;
}
.desk-editor :deep(.desk-mention) {
    border-radius: 3px;
    background: rgba(29, 155, 209, 0.1);
    color: #1264a3;
    padding: 0 2px;
}
</style>
