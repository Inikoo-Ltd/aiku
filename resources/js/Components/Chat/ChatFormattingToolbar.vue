<script setup lang="ts">
import { computed, ref } from "vue"
import type { Editor } from "@tiptap/vue-3"
import Popover from "primevue/popover"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBold, faItalic, faUnderline, faStrikethrough, faListUl, faListOl, faLink } from "@fortawesome/free-solid-svg-icons"
import { faCheck, faUnlink } from "@fal"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    editor?: Editor | null
    allowUnderline?: boolean
    allowLink?: boolean
}>()

const buttons = computed(() => [
    { name: "bold", icon: faBold, label: ctrans("Bold") + " (Ctrl+B)", run: (e: Editor) => e.chain().focus().toggleBold().run() },
    { name: "italic", icon: faItalic, label: ctrans("Italic") + " (Ctrl+I)", run: (e: Editor) => e.chain().focus().toggleItalic().run() },
    ...(props.allowUnderline
        ? [{ name: "underline", icon: faUnderline, label: ctrans("Underline") + " (Ctrl+U)", run: (e: Editor) => e.chain().focus().toggleUnderline().run() }]
        : []),
    { name: "strike", icon: faStrikethrough, label: ctrans("Strikethrough"), run: (e: Editor) => e.chain().focus().toggleStrike().run() },
    { name: "bulletList", icon: faListUl, label: ctrans("Bullet list"), run: (e: Editor) => e.chain().focus().toggleBulletList().run() },
    { name: "orderedList", icon: faListOl, label: ctrans("Numbered list"), run: (e: Editor) => e.chain().focus().toggleOrderedList().run() },
])

const linkPopover = ref<InstanceType<typeof Popover> | null>(null)
const linkUrl = ref("")
const linkError = ref<string | null>(null)
const isEditingLink = ref(false)

const openLink = (event: MouseEvent) => {
    if (!props.editor) {
        return
    }
    if (!props.editor.isActive("link")) {
        props.editor.commands.extendMarkRange("link")
    }
    const href = props.editor.getAttributes("link").href
    isEditingLink.value = !!href
    linkUrl.value = href ?? ""
    linkError.value = null
    linkPopover.value?.toggle(event)
}

const normalisedUrl = (value: string): string | null => {
    const url = value.trim().replace(/\(/g, "%28").replace(/\)/g, "%29")
    const withScheme = /^https?:\/\//i.test(url) ? url : `https://${url}`

    return /^https?:\/\/[^\s<>"]+\.[^\s<>"]+$/i.test(withScheme) ? withScheme : null
}

const applyLink = () => {
    const editor = props.editor
    const href = normalisedUrl(linkUrl.value)
    if (!editor || !href) {
        linkError.value = ctrans("Enter a web address, such as https://www.example.com/product")
        return
    }
    if (editor.state.selection.empty && !editor.isActive("link")) {
        editor.chain().focus().insertContent({ type: "text", text: href, marks: [{ type: "link", attrs: { href } }] }).run()
    } else {
        editor.chain().focus().extendMarkRange("link").setLink({ href }).run()
    }
    linkPopover.value?.hide()
}

const removeLink = () => {
    props.editor?.chain().focus().extendMarkRange("link").unsetLink().run()
    linkPopover.value?.hide()
}
</script>

<template>
    <div class="flex items-center gap-0.5">
        <button v-for="button in buttons" :key="button.name" type="button" @mousedown.prevent
            @click="editor && button.run(editor)"
            class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100"
            :class="editor?.isActive(button.name) ? 'bg-gray-200 text-gray-900' : 'text-gray-500'"
            v-tooltip="button.label" :aria-label="button.label">
            <FontAwesomeIcon :icon="button.icon" class="text-xs" fixed-width />
        </button>
        <template v-if="allowLink">
            <button type="button" @mousedown.prevent @click="openLink"
                class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100"
                :class="editor?.isActive('link') ? 'bg-gray-200 text-gray-900' : 'text-gray-500'"
                v-tooltip="ctrans('Link: select words, such as a product code, then add its web address')"
                :aria-label="ctrans('Link')">
                <FontAwesomeIcon :icon="faLink" class="text-xs" fixed-width />
            </button>
            <Popover ref="linkPopover">
                <form class="flex w-80 flex-col gap-2" @submit.prevent="applyLink">
                    <label for="chat-link-url" class="text-xs font-medium text-gray-600">{{ ctrans("Web address") }}</label>
                    <InputText id="chat-link-url" v-model="linkUrl" size="small" fluid autofocus
                        :placeholder="ctrans('https://www.example.com/product')" />
                    <p v-if="linkError" class="text-xs text-red-500 leading-snug">{{ linkError }}</p>
                    <div class="flex w-full gap-2">
                        <Button v-if="isEditingLink" type="tertiary" class="shrink-0" :label="ctrans('Remove link')" @click="removeLink">
                            <template #icon>
                                <FontAwesomeIcon :icon="faUnlink" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button type="primary" full :label="ctrans('Add link')" @click="applyLink">
                            <template #icon>
                                <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                </form>
            </Popover>
        </template>
    </div>
</template>
