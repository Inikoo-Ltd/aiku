<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, nextTick, watch, computed } from "vue"
import { useEditor, EditorContent, BubbleMenu } from '@tiptap/vue-3'
import Select from 'primevue/select'
import { useFontFamilyList } from '@/Composables/useFont'

import TiptapToolbarButton from "@/Components/Forms/Fields/BubleTextEditor/TiptapToolbarButton.vue"
import TiptapToolbarGroup from "@/Components/Forms/Fields/BubleTextEditor/TiptapToolbarGroup.vue"
import TiptapToolbarDropdown from "@/Components/Forms/Fields/BubleTextEditor/TiptapToolbarDropdown.vue"
import Paragraph from "@tiptap/extension-paragraph"
import Document from "@tiptap/extension-document"
import Text from "@tiptap/extension-text"
import History from "@tiptap/extension-history"
import Heading from "@tiptap/extension-heading"
import Bold from "@tiptap/extension-bold"
import Italic from "@tiptap/extension-italic"
import Underline from "@tiptap/extension-underline"
import Strike from "@tiptap/extension-strike"
import ListItem from "@tiptap/extension-list-item"
import BulletList from "@tiptap/extension-bullet-list"
import OrderedList from "@tiptap/extension-ordered-list"
import TextAlign from '@tiptap/extension-text-align'
import { Blockquote } from "@tiptap/extension-blockquote"
import { HardBreak } from "@tiptap/extension-hard-break"
import { CharacterCount } from "@tiptap/extension-character-count"
import { Youtube } from "@tiptap/extension-youtube"
import Dropcursor from "@tiptap/extension-dropcursor"
import { HorizontalRule } from "@tiptap/extension-horizontal-rule"
import { Table } from "@tiptap/extension-table"
import { TableHeader } from "@tiptap/extension-table-header"
import { TableRow } from "@tiptap/extension-table-row"
import { TableCell } from "@tiptap/extension-table-cell"
import Gapcursor from "@tiptap/extension-gapcursor"
import TextStyle from '@tiptap/extension-text-style'
import customLink from '@/Components/Forms/Fields/BubleTextEditor/CustomLink/CustomLinkExtension.js'
import { Color } from '@tiptap/extension-color'
import FontSize from 'tiptap-extension-font-size'
import FontFamily from '@tiptap/extension-font-family'
import Highlight from '@tiptap/extension-highlight'
import UtilsColorPicker from '@/Components/Utils/ColorPicker.vue'
import {CustomImage} from './CustomResizeImage/CustomImageSetting'
import Dialog from 'primevue/dialog';
import Placeholder from "@tiptap/extension-placeholder"
import Link from "@tiptap/extension-link"
import Iframe from "@/Components/Forms/Fields/BubleTextEditor/Iframe/IframeExtension.js"

import {
    faUndo,
    faRedo,
    faQuoteLeft,
    faBold,
    faH1,
    faH2,
    faH3,
    faItalic,
    faLink,
    faUnderline,
    faStrikethrough,
    faImage,
    faMinus,
    faList,
    faListOl,
    faAlignLeft,
    faAlignCenter,
    faAlignRight,
    faFileVideo,
    faPaintBrushAlt,
    faTextSize,
    faDraftingCompass,
    faExternalLink,
} from "@far"
import { faTable, faPalette, faUnlink, faTimes, faEllipsisH, faChevronDown, faFont, faHeading, faBracketsCurly } from "@fal"
import { faEraser, faTint, faTable as fasTable, } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

import TiptapLinkCustomDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapCustomLinkDialog.vue"
import TiptapLinkDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapLinkDialog.vue"
import TiptapVideoDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapVideoDialog.vue"
import TiptapTableDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapTableDialog.vue"
import TiptapImageDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapImageDialog.vue"
import TiptapVariableDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapVariableDialog.vue"
import { Plugin } from "prosemirror-state"
import Variabel from "./Variables/Variables"
import suggestion from './Variables/suggestion'
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { irisVariable } from "@/Composables/variableList"
import { uniqueId } from "lodash"
import { ulid } from "ulid"


const props = withDefaults(defineProps<{
    modelValue: string | null,
    toggle?: string[],
    type?: string,
    editable?: boolean
    placeholder?: any | String
    uploadImageRoute?: routeType
    routeGetInternalLink?: routeType
}>(), {
    editable: true,
    type: 'Bubble',
    toggle: () => [
        'heading1', 'heading2', 'heading3', 'fontSize', 'bold', 'italic', 'underline', 'bulletList', 'query', "fontFamily",
        'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight', "customLink",
        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear', "image", "video", "table"
    ]
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: string): void
    (e: 'onEditClick', value: any): void
    (e: 'focus'): void
    (e: 'blur'): void
}>()

const _bubbleMenu = ref(null)
const showDialog = ref(false)
const contentResult = ref<string>()
const currentLinkInDialog = ref<string | undefined>()
const showLinkDialogCustom = ref<boolean>()
const showAddYoutubeDialog = ref<boolean>(false)
const showAddTableDialog = ref<boolean>(false)
const showAddImageDialog = ref<boolean>(false)
const showAddVariableDialog = ref<boolean>(false)
const showLinkDialog = ref<boolean>()
const CustomLinkConfirm = ref(false)
const attrsCustomLink = ref<Object>(null)
const tippyOptions = {
    placement: 'bottom',
    offset: [0, 8],
    appendTo: () => document.body,
    maxWidth: 'none',
    popperOptions: {
        strategy: 'fixed',
        modifiers: [
            { name: 'preventOverflow', options: { padding: 8 } },
            { name: 'flip', options: { padding: 8 } },
        ],
    },
}
const key = ref(ulid())
const isPickingColor = ref(false)
const colorSelection = ref<{ from: number; to: number } | null>(null)
const editorInstance = useEditor({
    content: props.modelValue,
    editable: props.editable,
    onFocus: () => {
        if (isPickingColor.value) return

        emits('focus')
    },
    onBlur: () => {
        if (isPickingColor.value) return

        emits('blur')
    },
    editorProps: {
        attributes: {
            class: "editor-class",
        },
    },
    extensions: [
        Paragraph,
        Document,
        Text,
        CustomImage,
        History,
        Placeholder.configure({
            placeholder: props.placeholder || "Start typing...",
        }),
        FontFamily.configure({
            types: ['textStyle'],
        }),
        Link.configure({
            openOnClick: false
        }),
        customLink.extend({
            addProseMirrorPlugins() {
                return [
                    new Plugin({
                        props: {
                            handleClick(view, pos, event) {
                                const linkMark = view.state.schema.marks.link
                                const { tr } = view.state
                                const attrs = tr.doc
                                    .nodeAt(pos)
                                    ?.marks.find((mark) => mark.type === linkMark)?.attrs

                                return false
                            },
                        },
                    }),
                ]
            },
        }),
        Heading.configure({
            levels: [1, 2, 3],
        }),
        Bold,
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        Italic,
        Underline,
        Strike,
        ListItem,
        BulletList,
        OrderedList,
        HardBreak.extend({
            addKeyboardShortcuts() {
                return {
                    'Mod-Enter': () => this.editor.commands.setHardBreak(),
                    'Shift-Enter': () => this.editor.commands.setHardBreak(),
                };
            },
        }),
        Blockquote,
        CharacterCount,
        Iframe,
        Youtube,
        Highlight.configure({
            multicolor: true
        }),
        Dropcursor.configure({
            width: 2,
            color: "#2563eb",
        }),
        HorizontalRule,
        Table.configure({
            resizable: false,
            allowTableNodeSelection: true,
        }).extend({
            addAttributes() {
                return {
                    // extend the existing attributes …
                    ...this.parent?.(),

                    // and add a new one …
                    backgroundColor: {
                        default: null,
                        parseHTML: element => element.getAttribute('data-background-color'),
                        renderHTML: attributes => {
                            return {
                                'data-background-color': attributes.backgroundColor,
                                style: `background-color: ${attributes.backgroundColor}`,
                            }
                        },
                    },
                    borderColor: {
                        default: undefined,
                        parseHTML: element => element.getAttribute('data-border-color'),
                        renderHTML: attributes => {
                            return {
                                'data-border-color': attributes.borderColor,
                                style: `border-color: ${attributes.borderColor}`,
                            }
                        },
                    },
                    borderWidth: {
                        default: undefined,
                        parseHTML: element => element.getAttribute('data-border-width'),
                        renderHTML: attributes => {
                            return {
                                'data-border-width': attributes.borderWidth,
                                style: `border-width: ${attributes.borderWidth}`,
                            }
                        },
                    },
                }
            },
        }),
        TableRow,
        TableHeader.extend({
            addAttributes() {
                return {
                    ...this.parent?.(),
                    backgroundColor: {
                        default: null,
                        parseHTML: element => element.getAttribute('data-background-color'),
                        renderHTML: attributes => {
                            return {
                                'data-background-color': attributes.backgroundColor,
                                style: `background-color: ${attributes.backgroundColor}`,
                            }
                        },
                    },
                    borderColor: {
                        default: undefined,
                        parseHTML: element => element.getAttribute('data-border-color'),
                        renderHTML: attributes => {
                            return {
                                'data-border-color': attributes.borderColor,
                                style: `border-color: ${attributes.borderColor}`,
                            }
                        },
                    },
                    borderWidth: {
                        default: undefined,
                        parseHTML: element => element.getAttribute('data-border-width'),
                        renderHTML: attributes => {
                            return {
                                'data-border-width': attributes.borderWidth,
                                style: `border-width: ${attributes.borderWidth}`,
                            }
                        },
                    },
                }
            },
        }),
        TableCell.extend({
            addAttributes() {
                return {
                    ...this.parent?.(),
                    backgroundColor: {
                        default: null,
                        parseHTML: element => element.getAttribute('data-background-color'),
                        renderHTML: attributes => {
                            return {
                                'data-background-color': attributes.backgroundColor,
                                style: `background-color: ${attributes.backgroundColor}`,
                            }
                        },
                    },
                    borderColor: {
                        default: undefined,
                        parseHTML: element => element.getAttribute('data-border-color'),
                        renderHTML: attributes => {
                            return {
                                'data-border-color': attributes.borderColor,
                                style: `border-color: ${attributes.borderColor}`,
                            }
                        },
                    },
                    borderWidth: {
                        default: undefined,
                        parseHTML: element => element.getAttribute('data-border-width'),
                        renderHTML: attributes => {
                            return {
                                'data-border-width': attributes.borderWidth,
                                style: `border-width: ${attributes.borderWidth}`,
                            }
                        },
                    },
                }
            },
        }),
        Gapcursor,
       /*  Image, */
        TextStyle,
        FontSize.configure({
            types: ['textStyle'],
        }),
        Color.configure({
            types: ['textStyle'],
        }),
        Variabel.configure({
            HTMLAttributes: {
                class: 'mention',
            },
            suggestion,
        }),
    ],
    onUpdate: ({ editor }) => {
        contentResult.value = editor.getHTML()
        emits('update:modelValue', editor.getHTML())
    },
})

const keyLinkCustomDialog = ref(uniqueId('link-custom-dialog-'))
function openLinkDialogCustom() {
    const attrs = editorInstance.value?.getAttributes("link")
    currentLinkInDialog.value = attrs;
    showLinkDialogCustom.value = true;
    showDialog.value = true;
    keyLinkCustomDialog.value=uniqueId()
}

function updateLinkCustom(value) {
    if (value.href) {
        const attrs = {
            type: value.type,
            workshop: value.workshop,
            id: value.type === 'internal' ? value.id : null,
            href: value.href,
            target: value.target ? value.target : '_self',
            rel: value.rel
        };
        editorInstance.value?.chain().focus().extendMarkRange("link").setCustomLink(attrs).run();
    }
}


function openLinkDialog() {
    currentLinkInDialog.value = editorInstance.value?.getAttributes("link").href
    showLinkDialog.value = true
    showDialog.value = true;
}

function updateLink(value?: string) {
    if (!value) {
        editorInstance.value
            ?.chain()
            .focus()
            .extendMarkRange("link")
            .unsetLink()
            .run()
        return
    }

    editorInstance.value
        ?.chain()
        .focus()
        .extendMarkRange("link")
        .setLink({ href: value })
        .run()
}


function insertImage(url: string, alt?: string) {
    editorInstance.value?.chain().focus().setImage({ src: url, alt: alt || "image" }).run()
}

function insertYoutubeVideo(url: string) {
    editorInstance.value?.commands.setYoutubeVideo({
        src: url,
        width: 400,
        height: 300,
    })
}

function insertTable(table: DataTable) {
    editorInstance.value
        ?.chain()
        .focus()
        .insertTable({
            rows: table.rows,
            cols: table.columns,
            withHeaderRow: table.withHeader,
        })
        .run()
}


onBeforeUnmount(() => {
    editorInstance.value?.destroy()
})

const onEditorClick = () => {
    emits('onEditClick', editorInstance.value)
}

const setVariabel = (value) => {
    const content = `<span class="mention" data-type="mention" data-id="${value}" contenteditable="false">${value}</span>`;
    editorInstance.value?.chain().focus().insertContent(content).run();
};

defineExpose({
    editor: editorInstance
})

const tableBorderWidthOptions = [
    {
        label: trans('No border'),
        value: '0px'
    },
    {
        label: '1px',
        value: '1px'
    },
    {
        label: '2px',
        value: '2px'
    },
    {
        label: '4px',
        value: '4px'
    },
    {
        label: '6px',
        value: '6px'
    },
    {
        label: '8px',
        value: '8px'
    }
]

const convertRemToPx = (remString) => {
    if (!remString || typeof remString !== 'string') return ''
    const remValue = parseFloat(remString)
    return isNaN(remValue) ? '' : Math.round(remValue * 16).toString()
}

const fontSizeOptions = ['8', '9', '12', '14', '16', '18', '20', '24', '28', '36', '44', '52', '64']

const toRemFontSize = (fontSize: string) => (parseInt(fontSize) / 16).toFixed(4) + 'rem'

const alignOptions = [
    { value: 'left', label: 'Align Left', icon: faAlignLeft, toggleKey: 'alignLeft' },
    { value: 'center', label: 'Align Center', icon: faAlignCenter, toggleKey: 'alignCenter' },
    { value: 'right', label: 'Align Right', icon: faAlignRight, toggleKey: 'alignRight' },
]

const availableAlignOptions = computed(() =>
    alignOptions.filter(option => props.toggle.includes(option.toggleKey))
)

const activeAlignOption = computed(() =>
    availableAlignOptions.value.find(option =>
        editorInstance.value?.isActive({ textAlign: option.value })
    ) ?? null
)

const headingOptions = [
    { level: 1, label: 'Heading 1', icon: faH1, toggleKey: 'heading1' },
    { level: 2, label: 'Heading 2', icon: faH2, toggleKey: 'heading2' },
    { level: 3, label: 'Heading 3', icon: faH3, toggleKey: 'heading3' },
]

const availableHeadingOptions = computed(() =>
    headingOptions.filter(option => props.toggle.includes(option.toggleKey))
)

const activeHeadingOption = computed(() =>
    availableHeadingOptions.value.find(option =>
        editorInstance.value?.isActive('heading', { level: option.level })
    ) ?? null
)

const listOptions = [
    { value: 'bulletList', label: 'Bullet list', icon: faList, toggleKey: 'bulletList' },
    { value: 'orderedList', label: 'Numbered list', icon: faListOl, toggleKey: 'orderedList' },
]

const availableListOptions = computed(() =>
    listOptions.filter(option => props.toggle.includes(option.toggleKey))
)

const activeListOption = computed(() =>
    availableListOptions.value.find(option => editorInstance.value?.isActive(option.value)) ?? null
)

const toggleList = (value: string) => {
    const chain = editorInstance.value?.chain().focus()
    if (!chain) return

    if (value === 'bulletList') {
        chain.toggleBulletList().run()
        return
    }

    chain.toggleOrderedList().run()
}

const hasMoreMenuItems = computed(() =>
    ['blockquote', 'divider', 'image', 'video', 'table', 'query']
        .some(key => props.toggle.includes(key))
)

const showBubble = ref(true)
const userCloseBubble = ref(false)
const lastSelection = ref<{ from: number; to: number } | null>(null)

const shouldShowBubble = ({ editor }: any) => {
  if (!editor) return false
  if (isPickingColor.value) return true
  if (!showBubble.value) return false
  return editor.isFocused && !showDialog.value
}

const startPickingColor = () => {
  const selection = editorInstance.value?.state.selection
  colorSelection.value = selection ? { from: selection.from, to: selection.to } : null
  isPickingColor.value = true
}

const applyTextColor = (color: string) => {
  const chain = editorInstance.value?.chain()
  if (!chain) return

  if (colorSelection.value) {
    chain.setTextSelection(colorSelection.value)
  }

  chain.setColor(color).run()
}

const finishPickingColor = () => {
  if (!isPickingColor.value) return

  editorInstance.value?.commands.focus()
  isPickingColor.value = false
  colorSelection.value = null
}

const cancelPickingColor = () => {
  if (!isPickingColor.value) return

  isPickingColor.value = false
  colorSelection.value = null

  if (!editorInstance.value?.isFocused) {
    showBubble.value = false
    lastSelection.value = null
    key.value = ulid()
    emits('blur')
  }
}


const closeBubble = () => {
  showBubble.value = false
  userCloseBubble.value = true
  key.value = ulid()
}

watch(editorInstance, (editor) => {
  if (!editor) return

  // BLUR: always hide, except while the color picker holds the focus
  editor.on('blur', () => {
    if (isPickingColor.value) return

    showBubble.value = false
    lastSelection.value = null
  })

  // FOCUS: auto mode shows bubble immediately
  editor.on('focus', () => {
    if (!userCloseBubble.value) {
      showBubble.value = true
    }
  })

  // SELECTION HANDLING
  editor.on('selectionUpdate', ({ editor }) => {
    const { from, to } = editor.state.selection
    const hasSelection = from !== to

    // MANUAL MODE
    if (userCloseBubble.value) {
      showBubble.value = hasSelection
      lastSelection.value = hasSelection ? { from, to } : null
      return
    }

    // AUTO MODE
    showBubble.value = true
    lastSelection.value = hasSelection ? { from, to } : null
  })
})


onMounted(async () => {
  await nextTick()

  setTimeout(() => {
    contentResult.value = editorInstance.value?.getHTML()
  }, 250)
})

</script>

<template>
    <div id="tiptap" class="divide-y divide-gray-400">
        <Teleport to="body">
            <BubbleMenu  
                :key="key"
                :shouldShow="shouldShowBubble"
                :tippy-options="tippyOptions"
                ref="_bubbleMenu"
                :editor="editorInstance"
                v-if="editorInstance && !showDialog"
                class="w-max max-w-[92vw]"
            >

                <div class="rounded-lg border border-gray-200 bg-white p-1 shadow-lg isolate">

                    <!-- Primary row: everyday formatting, always one line -->
                    <section
                        class="flex flex-nowrap items-center overflow-visible divide-x divide-gray-200">

                        <!-- History -->
                        <TiptapToolbarGroup v-if="toggle.includes('undo') || toggle.includes('redo')"
                            class="px-1 first:pl-0">
                            <TiptapToolbarButton v-if="toggle.includes('undo')" label="Undo"
                                @click="editorInstance?.chain().focus().undo().run()"
                                :disabled="!editorInstance?.can().chain().focus().undo().run()">
                                <FontAwesomeIcon :icon="faUndo" class="h-4 w-4" />
                            </TiptapToolbarButton>
                            <TiptapToolbarButton v-if="toggle.includes('redo')" label="Redo"
                                @click="editorInstance?.chain().focus().redo().run()"
                                :disabled="!editorInstance?.can().chain().focus().redo().run()">
                                <FontAwesomeIcon :icon="faRedo" class="h-4 w-4" />
                            </TiptapToolbarButton>
                        </TiptapToolbarGroup>

                        <!-- Typeface -->
                        <TiptapToolbarGroup v-if="toggle.includes('fontFamily') || toggle.includes('fontSize')"
                            class="px-1 first:pl-0">
                            <TiptapToolbarDropdown v-if="toggle.includes('fontFamily')" label="Font family"
                                :menu-height="240"
                                :is-active="!!editorInstance?.getAttributes('textStyle').fontFamily">
                                <template #trigger>
                                    <FontAwesomeIcon :icon="faFont" class="h-4 w-4" />
                                    <span class="max-w-[5.5rem] truncate text-xs">
                                        {{ editorInstance?.getAttributes('textStyle').fontFamily || trans('Font') }}
                                    </span>
                                    <FontAwesomeIcon :icon="faChevronDown" class="h-2.5 w-2.5 opacity-60" />
                                </template>
                                <template #menu="{ close }">
                                    <div class="max-h-56 w-48 overflow-y-auto">
                                        <button type="button"
                                            class="flex w-full items-center px-3 py-1.5 text-left text-sm text-red-600 hover:bg-gray-100"
                                            @click="editorInstance?.chain().focus().unsetFontFamily().run(); close()">
                                            {{ trans('Clear font') }}
                                        </button>
                                        <button v-for="font in useFontFamilyList" :key="font.value" type="button"
                                            class="flex w-full items-center px-3 py-1.5 text-left text-sm transition-colors"
                                            :class="editorInstance?.getAttributes('textStyle').fontFamily === font.value
                                                ? 'bg-blue-50 text-blue-800'
                                                : 'text-gray-700 hover:bg-gray-100'"
                                            :style="{ fontFamily: font.value }"
                                            @click="editorInstance?.chain().focus().setFontFamily(font.value).run(); close()">
                                            {{ font.label }}
                                        </button>
                                    </div>
                                </template>
                            </TiptapToolbarDropdown>

                            <TiptapToolbarDropdown v-if="toggle.includes('fontSize')" label="Font size"
                                :menu-height="240"
                                :is-active="!!editorInstance?.getAttributes('textStyle').fontSize">
                                <template #trigger>
                                    <FontAwesomeIcon :icon="faTextSize" class="h-4 w-4" />
                                    <span v-if="editorInstance?.getAttributes('textStyle').fontSize"
                                        class="text-xs font-semibold">
                                        {{ convertRemToPx(editorInstance?.getAttributes('textStyle').fontSize) }}
                                    </span>
                                    <FontAwesomeIcon :icon="faChevronDown" class="h-2.5 w-2.5 opacity-60" />
                                </template>
                                <template #menu="{ close }">
                                    <div class="max-h-56 w-28 overflow-y-auto">
                                        <button type="button"
                                            class="flex w-full items-center px-3 py-1.5 text-left text-sm text-red-600 hover:bg-gray-100"
                                            @click="editorInstance?.chain().focus().unsetFontSize().run(); close()">
                                            {{ trans('Clear') }}
                                        </button>
                                        <button v-for="fontsize in fontSizeOptions" :key="fontsize" type="button"
                                            class="flex w-full items-center justify-between px-3 py-1.5 text-left text-sm transition-colors"
                                            :class="editorInstance?.getAttributes('textStyle').fontSize === toRemFontSize(fontsize)
                                                ? 'bg-blue-50 text-blue-800'
                                                : 'text-gray-700 hover:bg-gray-100'"
                                            @click="editorInstance?.chain().focus().setFontSize(toRemFontSize(fontsize)).run(); close()">
                                            <span>{{ fontsize }}px</span>
                                        </button>
                                    </div>
                                </template>
                            </TiptapToolbarDropdown>
                        </TiptapToolbarGroup>

                        <!-- Heading: one button, full choice on open -->
                        <TiptapToolbarGroup v-if="availableHeadingOptions.length" class="px-1 first:pl-0">
                            <TiptapToolbarDropdown :label="trans('Heading')" :menu-height="60"
                                :is-active="!!activeHeadingOption">
                                <template #trigger>
                                    <FontAwesomeIcon :icon="activeHeadingOption?.icon ?? faHeading"
                                        class="h-4 w-4" />
                                    <FontAwesomeIcon :icon="faChevronDown" class="h-2.5 w-2.5 opacity-60" />
                                </template>
                                <template #menu="{ close }">
                                    <div class="flex items-center gap-0.5 px-1">
                                        <button v-for="option in availableHeadingOptions" :key="option.level"
                                            type="button" v-tooltip="trans(option.label)"
                                            :aria-label="trans(option.label)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded transition-colors"
                                            :class="activeHeadingOption?.level === option.level
                                                ? 'bg-blue-100 text-blue-800'
                                                : 'text-gray-600 hover:bg-blue-50'"
                                            @click="editorInstance?.chain().focus().toggleHeading({ level: option.level }).run(); close()">
                                            <FontAwesomeIcon :icon="option.icon" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </template>
                            </TiptapToolbarDropdown>
                        </TiptapToolbarGroup>

                        <!-- Lists: one button, full choice on open -->
                        <TiptapToolbarGroup v-if="availableListOptions.length" class="px-1 first:pl-0">
                            <TiptapToolbarDropdown :label="trans('Lists')" :menu-height="60"
                                :is-active="!!activeListOption">
                                <template #trigger>
                                    <FontAwesomeIcon :icon="activeListOption?.icon ?? faList" class="h-4 w-4" />
                                    <FontAwesomeIcon :icon="faChevronDown" class="h-2.5 w-2.5 opacity-60" />
                                </template>
                                <template #menu="{ close }">
                                    <div class="flex items-center gap-0.5 px-1">
                                        <button v-for="option in availableListOptions" :key="option.value"
                                            type="button" v-tooltip="trans(option.label)"
                                            :aria-label="trans(option.label)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded transition-colors"
                                            :class="activeListOption?.value === option.value
                                                ? 'bg-blue-100 text-blue-800'
                                                : 'text-gray-600 hover:bg-blue-50'"
                                            @click="toggleList(option.value); close()">
                                            <FontAwesomeIcon :icon="option.icon" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </template>
                            </TiptapToolbarDropdown>
                        </TiptapToolbarGroup>

                        <!-- Character formatting -->
                        <TiptapToolbarGroup
                            v-if="toggle.includes('bold') || toggle.includes('italic') || toggle.includes('underline') || toggle.includes('strikethrough') || toggle.includes('color') || toggle.includes('highlight')"
                            class="px-1 first:pl-0">
                            <TiptapToolbarButton v-if="toggle.includes('bold')" label="Bold"
                                :is-active="editorInstance?.isActive('bold')"
                                @click="editorInstance?.chain().focus().toggleBold().run()">
                                <FontAwesomeIcon :icon="faBold" class="h-4 w-4" />
                            </TiptapToolbarButton>

                            <TiptapToolbarButton v-if="toggle.includes('italic')" label="Italic"
                                :is-active="editorInstance?.isActive('italic')"
                                @click="editorInstance?.chain().focus().toggleItalic().run()">
                                <FontAwesomeIcon :icon="faItalic" class="h-4 w-4" />
                            </TiptapToolbarButton>

                            <TiptapToolbarButton v-if="toggle.includes('underline')" label="Underline"
                                :is-active="editorInstance?.isActive('underline')"
                                @click="editorInstance?.chain().focus().toggleUnderline().run()">
                                <FontAwesomeIcon :icon="faUnderline" class="h-4 w-4" />
                            </TiptapToolbarButton>

                            <TiptapToolbarButton v-if="toggle.includes('strikethrough')" label="Strikethrough"
                                :is-active="editorInstance?.isActive('strike')"
                                @click="editorInstance?.chain().focus().toggleStrike().run()">
                                <FontAwesomeIcon :icon="faStrikethrough" class="h-4 w-4" />
                            </TiptapToolbarButton>

                            <TiptapToolbarButton v-if="toggle.includes('color')" label="Text Color" :preserve-selection="false">
                                <div class="relative h-5 w-5">
                                    <input type="color" :aria-label="trans('Text Color')"
                                        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                        @mousedown="startPickingColor"
                                        @input="applyTextColor($event.target.value)"
                                        @change="finishPickingColor"
                                        @blur="cancelPickingColor"
                                        :value="editorInstance.getAttributes('textStyle').color || '#000000'" />
                                    <div class="flex h-full w-full items-center justify-center rounded"
                                        :style="{ color: editorInstance.getAttributes('textStyle').color || 'gray' }">
                                        <FontAwesomeIcon :icon="faTint" class="text-sm" />
                                    </div>
                                </div>
                            </TiptapToolbarButton>

                            <TiptapToolbarButton v-if="toggle.includes('highlight')" label="Text highlight" :preserve-selection="false">
                                <div class="relative h-5 w-5">
                                    <input type="color" :aria-label="trans('Text highlight')"
                                        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                        @input="editorInstance.chain().focus().setHighlight({ color: $event.target.value }).run()"
                                        :value="editorInstance.getAttributes('highlight').color" />
                                    <div class="flex h-full w-full items-center justify-center rounded"
                                        :style="{ backgroundColor: editorInstance?.getAttributes('highlight').color }">
                                        <FontAwesomeIcon :icon="faPaintBrushAlt" class="text-sm" />
                                    </div>
                                </div>
                            </TiptapToolbarButton>
                        </TiptapToolbarGroup>

                        <!-- Alignment: one button, full choice on open -->
                        <TiptapToolbarGroup v-if="availableAlignOptions.length" class="px-1 first:pl-0">
                            <TiptapToolbarDropdown :label="trans('Text alignment')" :menu-height="60"
                                :is-active="!!activeAlignOption">
                                <template #trigger>
                                    <FontAwesomeIcon :icon="activeAlignOption?.icon ?? faAlignLeft" class="h-4 w-4" />
                                    <FontAwesomeIcon :icon="faChevronDown" class="h-2.5 w-2.5 opacity-60" />
                                </template>
                                <template #menu="{ close }">
                                    <div class="flex items-center gap-0.5 px-1">
                                        <button v-for="option in availableAlignOptions" :key="option.value"
                                            type="button" v-tooltip="trans(option.label)"
                                            :aria-label="trans(option.label)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded transition-colors"
                                            :class="activeAlignOption?.value === option.value
                                                ? 'bg-blue-100 text-blue-800'
                                                : 'text-gray-600 hover:bg-blue-50'"
                                            @click="editorInstance?.chain().focus().setTextAlign(option.value).run(); close()">
                                            <FontAwesomeIcon :icon="option.icon" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </template>
                            </TiptapToolbarDropdown>
                        </TiptapToolbarGroup>

                        <!-- Links -->
                        <TiptapToolbarGroup v-if="toggle.includes('link') || toggle.includes('customLink')"
                            class="px-1 first:pl-0">
                            <TiptapToolbarButton v-if="toggle.includes('link')" label="Link" @click="openLinkDialog"
                                :is-active="editorInstance?.isActive('link')">
                                <FontAwesomeIcon :icon="faLink" class="h-4 w-4" />
                            </TiptapToolbarButton>

                            <TiptapToolbarButton v-if="toggle.includes('customLink')"
                                label="Link Internal & External" @click="openLinkDialogCustom"
                                :is-active="editorInstance?.isActive('link')">
                                <FontAwesomeIcon :icon="faLink" class="h-4 w-4" />
                            </TiptapToolbarButton>

                            <TiptapToolbarButton label="Unlink"
                                @click="editorInstance.chain().focus().unsetLink().run()"
                                :disabled="!editorInstance?.isActive('link')">
                                <FontAwesomeIcon :icon="faUnlink" class="h-4 w-4" />
                            </TiptapToolbarButton>
                        </TiptapToolbarGroup>

                        <!-- Clear formatting -->
                        <TiptapToolbarGroup v-if="toggle.includes('clear')" class="px-1 first:pl-0">
                            <TiptapToolbarButton :label="trans('Clear formatting')"
                                @click="editorInstance?.chain().focus().unsetAllMarks().run()">
                                <FontAwesomeIcon :icon="faEraser" class="h-4 w-4" />
                            </TiptapToolbarButton>
                        </TiptapToolbarGroup>

                        <!-- Everything else, one click away -->
                        <TiptapToolbarGroup v-if="hasMoreMenuItems" class="px-1 first:pl-0">
                            <TiptapToolbarDropdown :label="trans('More options')" align-menu="right"
                                :menu-height="320">
                                <template #trigger>
                                    <FontAwesomeIcon :icon="faEllipsisH" class="h-4 w-4" />
                                </template>
                                <template #menu="{ close }">
                                    <div class="max-h-80 w-52 overflow-y-auto">
                                        <template
                                            v-if="toggle.includes('image') || toggle.includes('video') || toggle.includes('table') || toggle.includes('blockquote') || toggle.includes('divider')">
                                            <div class="my-1 h-px bg-gray-200" aria-hidden="true" />
                                            <div class="px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                                {{ trans('Insert') }}
                                            </div>
                                            <button v-if="toggle.includes('image')" type="button"
                                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100"
                                                @click="showAddImageDialog = true; showDialog = true; close()">
                                                <FontAwesomeIcon :icon="faImage" class="h-4 w-4" fixed-width />
                                                {{ trans('Image') }}
                                            </button>
                                            <button v-if="toggle.includes('video')" type="button"
                                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100"
                                                @click="showAddYoutubeDialog = true; showDialog = true; close()">
                                                <FontAwesomeIcon :icon="faFileVideo" class="h-4 w-4" fixed-width />
                                                {{ trans('YouTube video') }}
                                            </button>
                                            <button v-if="toggle.includes('table')" type="button"
                                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100"
                                                @click="showAddTableDialog = true; showDialog = true; close()">
                                                <FontAwesomeIcon :icon="faTable" class="h-4 w-4" fixed-width />
                                                {{ trans('Table') }}
                                            </button>
                                            <button v-if="toggle.includes('blockquote')" type="button"
                                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm transition-colors"
                                                :class="editorInstance?.isActive('blockquote')
                                                    ? 'bg-blue-50 text-blue-800'
                                                    : 'text-gray-700 hover:bg-gray-100'"
                                                @click="editorInstance?.chain().focus().toggleBlockquote().run(); close()">
                                                <FontAwesomeIcon :icon="faQuoteLeft" class="h-4 w-4" fixed-width />
                                                {{ trans('Blockquote') }}
                                            </button>
                                            <button v-if="toggle.includes('divider')" type="button"
                                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100"
                                                @click="editorInstance?.chain().focus().setHorizontalRule().run(); close()">
                                                <FontAwesomeIcon :icon="faMinus" class="h-4 w-4" fixed-width />
                                                {{ trans('Horizontal line') }}
                                            </button>
                                        </template>

                                        <template v-if="toggle.includes('query')">
                                            <div class="my-1 h-px bg-gray-200" aria-hidden="true" />
                                            <button type="button"
                                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100"
                                                @click="showAddVariableDialog = true; showDialog = true; close()">
                                                <FontAwesomeIcon :icon="faBracketsCurly" class="h-4 w-4"
                                                    fixed-width />
                                                {{ trans('Insert variable') }}…
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </TiptapToolbarDropdown>
                        </TiptapToolbarGroup>

                        <!-- Close -->
                        <div v-if="props.toggle.length" class="px-1 first:pl-0">
                            <button type="button" v-tooltip="trans('Close toolbar')"
                                :aria-label="trans('Close toolbar')" @mousedown.prevent @click="closeBubble"
                                class="inline-flex h-7 w-7 items-center justify-center rounded text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600">
                                <FontAwesomeIcon :icon="faTimes" class="h-4 w-4" />
                            </button>
                        </div>
                    </section>

                    <!-- Table tools: only while the caret sits inside a table -->
                    <section v-if="editorInstance?.isActive('table')"
                        class="mt-1 flex flex-nowrap items-center gap-0.5 overflow-x-auto border-t border-gray-200 pt-1">
                        <span class="shrink-0 pr-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                            {{ trans('Table') }}
                        </span>

                        <TiptapToolbarButton v-if="toggle.includes('color')" :label="trans('Color background')" :preserve-selection="false">
                            <div class="relative h-5 w-5">
                                <UtilsColorPicker key="picker_table_background_color"
                                    :color="editorInstance.getAttributes('table')?.backgroundColor || editorInstance.getAttributes('tableCell')?.backgroundColor"
                                    @changeColor="(newColor) => {
                                        editorInstance?.chain().focus().setCellAttribute('backgroundColor', newColor.hex).run()
                                    }" closeButton>
                                    <template #button>
                                        <div class="group relative flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                            :style="{
                                                backgroundColor: editorInstance.getAttributes('table')?.backgroundColor || editorInstance.getAttributes('tableCell')?.backgroundColor
                                            }">
                                            <FontAwesomeIcon :icon='faPalette' class='text-gray-500' fixed-width
                                                aria-hidden='true' />
                                        </div>
                                    </template>
                                </UtilsColorPicker>
                            </div>
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toggle.includes('color')" :label="trans('Color border')" :preserve-selection="false">
                            <div class="relative h-5 w-5">
                                <UtilsColorPicker key="picker_table_border_color"
                                    :color="editorInstance.getAttributes('tableCell')?.borderColor"
                                    @changeColor="(newColor) => {
                                        editorInstance?.chain().focus().setCellAttribute('borderColor', newColor.hex).run()
                                    }" closeButton
                                    :isEditable="editorInstance.getAttributes('tableCell')?.borderWidth && editorInstance.getAttributes('tableCell')?.borderWidth != '0px'">
                                    <template #before-main-picker>
                                        <div class="mb-2">
                                            <Select size="small"
                                                :modelValue="editorInstance.getAttributes('tableCell')?.borderWidth"
                                                @update:modelValue="(e) => (editorInstance?.chain().focus().setCellAttribute('borderWidth', e).run())"
                                                :options="tableBorderWidthOptions" optionLabel="label"
                                                optionValue="value" :placeholder="trans('Select border width')"
                                                fluid />
                                        </div>
                                    </template>

                                    <template #button>
                                        <div class="group relative flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                            :style="{
                                                color: editorInstance.getAttributes('tableCell')?.borderColor,
                                                background: editorInstance.getAttributes('tableCell')?.borderWidth && editorInstance.getAttributes('tableCell')?.borderWidth != '0px' ? '#d1d5db' : 'none'
                                            }">
                                            <FontAwesomeIcon :icon='fasTable' fixed-width aria-hidden='true' />
                                        </div>
                                    </template>
                                </UtilsColorPicker>
                            </div>
                        </TiptapToolbarButton>

                        <span class="mx-0.5 h-5 w-px shrink-0 bg-gray-200" aria-hidden="true" />

                        <TiptapToolbarButton label="Add column before"
                            @click="editorInstance?.commands.addColumnBefore()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"
                                fill="currentColor">
                                <path
                                    d="M13,2A2,2 0 0,0 11,4V20A2,2 0 0,0 13,22H22V2H13M20,10V14H13V10H20M20,16V20H13V16H20M20,4V8H13V4H20M9,11H6V8H4V11H1V13H4V16H6V13H9V11Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton label="Add column after"
                            @click="editorInstance?.commands.addColumnAfter()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"
                                fill="currentColor">
                                <path
                                    d="M11,2A2,2 0 0,1 13,4V20A2,2 0 0,1 11,22H2V2H11M4,10V14H11V10H4M4,16V20H11V16H4M4,4V8H11V4H4M15,11H18V8H20V11H23V13H20V16H18V13H15V11Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton label="Remove column" @click="editorInstance?.commands.deleteColumn()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"
                                fill="currentColor">
                                <path
                                    d="M4,2H11A2,2 0 0,1 13,4V20A2,2 0 0,1 11,22H4A2,2 0 0,1 2,20V4A2,2 0 0,1 4,2M4,10V14H11V10H4M4,16V20H11V16H4M4,4V8H11V4H4M17.59,12L15,9.41L16.41,8L19,10.59L21.59,8L23,9.41L20.41,12L23,14.59L21.59,16L19,13.41L16.41,16L15,14.59L17.59,12Z" />
                            </svg>
                        </TiptapToolbarButton>

                        <span class="mx-0.5 h-5 w-px shrink-0 bg-gray-200" aria-hidden="true" />

                        <TiptapToolbarButton label="Add row before" @click="editorInstance?.commands.addRowBefore()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"
                                fill="currentColor">
                                <path
                                    d="M22,14A2,2 0 0,0 20,12H4A2,2 0 0,0 2,14V21H4V19H8V21H10V19H14V21H16V19H20V21H22V14M4,14H8V17H4V14M10,14H14V17H10V14M20,14V17H16V14H20M11,10H13V7H16V5H13V2H11V5H8V7H11V10Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton @click="editorInstance?.commands.addRowAfter()" label="Add row after">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"
                                fill="currentColor">
                                <path
                                    d="M22,10A2,2 0 0,1 20,12H4A2,2 0 0,1 2,10V3H4V5H8V3H10V5H14V3H16V5H20V3H22V10M4,10H8V7H4V10M10,10H14V7H10V10M20,10V7H16V10H20M11,14H13V17H16V19H13V22H11V19H8V17H11V14Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton label="Remove row" @click="editorInstance?.commands.deleteRow()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"
                                fill="currentColor">
                                <path
                                    d="M9.41,13L12,15.59L14.59,13L16,14.41L13.41,17L16,19.59L14.59,21L12,18.41L9.41,21L8,19.59L10.59,17L8,14.41L9.41,13M22,9A2,2 0 0,1 20,11H4A2,2 0 0,1 2,9V6A2,2 0 0,1 4,4H20A2,2 0 0,1 22,6V9M4,9H8V6H4V9M10,9H14V6H10V9M16,9H20V6H16V9Z" />
                            </svg>
                        </TiptapToolbarButton>

                        <span class="mx-0.5 h-5 w-px shrink-0 bg-gray-200" aria-hidden="true" />

                        <TiptapToolbarButton label="Merge or split cell"
                            @click="editorInstance?.commands.mergeOrSplit()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"
                                fill="currentColor">
                                <path
                                    d="M5,10H3V4H11V6H5V10M19,18H13V20H21V14H19V18M5,18V14H3V20H11V18H5M21,4H13V6H19V10H21V4M8,13V15L11,12L8,9V11H3V13H8M16,11V9L13,12L16,15V13H21V11H16Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton @click="editorInstance?.commands.deleteTable()" label="Remove table">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"
                                fill="currentColor">
                                <path
                                    d="M15.46,15.88L16.88,14.46L19,16.59L21.12,14.46L22.54,15.88L20.41,18L22.54,20.12L21.12,21.54L19,19.41L16.88,21.54L15.46,20.12L17.59,18L15.46,15.88M4,3H18A2,2 0 0,1 20,5V12.08C18.45,11.82 16.92,12.18 15.68,13H12V17H13.08C12.97,17.68 12.97,18.35 13.08,19H4A2,2 0 0,1 2,17V5A2,2 0 0,1 4,3M4,7V11H10V7H4M12,7V11H18V7H12M4,13V17H10V13H4Z" />
                            </svg>
                        </TiptapToolbarButton>
                    </section>
                </div>
            </BubbleMenu>
        </Teleport>

        <div class="flex flex-col" id="content-editor-container">
            <slot name="editor-content" :editor="editorInstance" :onEditorClick="onEditorClick">
                <EditorContent @click.stop="onEditorClick" :editor="editorInstance" />
            </slot>
        </div>

        <TiptapTableDialog
            v-if="showAddTableDialog"
            :show="showAddTableDialog"
            @close="() => { showAddTableDialog = false, showDialog = false; }"
            @insert="insertTable"
        />

        <TiptapLinkCustomDialog
            v-if="showLinkDialogCustom"
            :show="showLinkDialogCustom"
            :attribut="currentLinkInDialog"
            :route-get-internal-link="routeGetInternalLink"
            :key="keyLinkCustomDialog"
            @close="() => { showLinkDialogCustom = false; showDialog = false; }"
            @update="updateLinkCustom"
        />
        <TiptapImageDialog
            v-if="showAddImageDialog"
            :show="showAddImageDialog"
            @close="() => { showAddImageDialog = false, showDialog = false }"
            @insert="insertImage"
            :uploadImageRoute="uploadImageRoute"
        />
        <TiptapLinkDialog
            v-if="showLinkDialog"
            :show="showLinkDialog"
            :current-url="currentLinkInDialog"
            @close="() => { showLinkDialog = false; showDialog = false; }"
            @update="updateLink"
        />

        <TiptapVariableDialog
            v-if="showAddVariableDialog"
            :show="showAddVariableDialog"
            :variables="irisVariable"
            @insert="setVariabel"
            @close="() => { showAddVariableDialog = false; showDialog = false; }"
        />

        <TiptapVideoDialog
            v-if="showAddYoutubeDialog"
            :show="showAddYoutubeDialog"
            @insert="insertYoutubeVideo"
            @close="() => { showAddYoutubeDialog = false; showDialog = false; }"
        />


        <Dialog
            v-model:visible="CustomLinkConfirm"
            :style="{ width: '25rem' }"
            modal
            :closable="false"
            :dismissableMask="true"
            :showHeader="false"
        >
            <div class="pt-5">
                <ul class="list-none p-0">
                    <li class="mb-2">
                        <a :href="attrsCustomLink?.workshop" target="_blank"
                            class="block px-4 py-2 bg-blue-500 text-white rounded-lg text-center hover:bg-blue-600 transition">
                            <FontAwesomeIcon :icon="faDraftingCompass" /> Go to Workshop
                        </a>
                    </li>
                    <li>
                        <a :href="attrsCustomLink?.href" target="_blank"
                            class="block px-4 py-2 bg-blue-500 text-white rounded-lg text-center hover:bg-blue-600 transition">
                            <FontAwesomeIcon :icon="faExternalLink" /> Go to Page
                        </a>
                    </li>
                </ul>
            </div>
        </Dialog>
    </div>
</template>


<style scoped>

/* .BubbleMenu {
  position: fixed !important;
  top: 10px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 50;
} */

:deep(.font-inter) {
    font-family: "Inter", sans-serif;
}

.editor-class p {
    display: block;
    margin-block-start: 0em;
    margin-block-end: 0em;
    margin-inline-start: 0px;
    margin-inline-end: 0px;
    unicode-bidi: isolate;
}

:deep(.ProseMirror) {
    @apply focus:outline-none px-0 py-0 min-h-[10px] relative;
}

:deep(.editor-class) {
    @apply flex flex-col;
}

/* :deep(.editor-class p) {
    @apply leading-4 mb-0 mt-0 mr-0 ml-0
} */

:deep(.editor-class p) {
    display: block;
    margin-block-start: 0em;
    margin-block-end: 0em;
    margin-inline-start: 0px;
    margin-inline-end: 0px;
    unicode-bidi: isolate;
    text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.1);
}

:deep(.editor-class h1) {
    @apply text-4xl font-semibold;
}

:deep(.editor-class h2) {
    @apply text-3xl font-semibold mt-0 mb-0 mr-0 ml-0;
}

:deep(.editor-class h3) {
    @apply text-2xl font-semibold !important;
}

:deep(.editor-class ol),
:deep(.editor-class ul) {
    @apply ml-8 list-outside mt-2;
}

:deep(.editor-class ol) {
    @apply list-decimal;
}

:deep(.editor-class ul) {
    @apply list-disc;
}

:deep(.editor-class ol li),
:deep(.editor-class ul li) {
    @apply mt-[0.3rem] first:mt-0;
}

:deep(.editor-class blockquote) {
    @apply italic border-l-4 border-gray-300 p-4 py-2 ml-6 mt-6 mb-2 bg-gray-50;
}

:deep(.editor-class a) {
    @apply hover:cursor-pointer;
}

:deep(.editor-class hr) {
    @apply border-gray-400 my-4;
}

:deep(.editor-class table) {
    @apply border-none table-fixed border-collapse w-full my-4;
}

:deep(.editor-class table th),
:deep(.editor-class table td) {
    @apply border border-gray-400 py-2 px-4 text-left relative;
}

:deep(.editor-class table th) {
    @apply bg-blue-100 font-semibold;
}

:deep(.editor-class .tableWrapper) {
    @apply overflow-auto;
}

:deep(.ProseMirror iframe) {
    @apply w-full h-auto max-w-[480px] min-h-[320px] aspect-video mr-6;
}

:deep(.ProseMirror h3) {
    margin-block-end: 0em;
    @apply m-0;
}

/* :deep(.ProseMirror img) {
    @apply mr-6 w-full max-w-[480px] max-h-[320px] object-contain object-center;
} */

:deep(.ProseMirror img.ProseMirror-selectednode),
:deep(.ProseMirror div[data-youtube-video]) {
    @apply cursor-move;
}

:deep(.ProseMirror .selectedCell:after) {
    @apply z-[2] absolute inset-0 bg-gray-400/30 pointer-events-none content-[''];
}

:deep(.ProseMirror-gapcursor) {
    @apply hidden pointer-events-none relative;
    @apply after:content-[''] after:block after:relative after:h-5 after:border-l after:border-t-0 after:border-black after:mt-1;
}

:deep(.ProseMirror-gapcursor:after) {
    animation: ProseMirror-cursor-blink 1.1s steps(2, start) infinite;
}

:deep(.mention) {
    background-color: #F6F2FF;
    border-radius: 0.4rem;
    box-decoration-break: clone;
    color: #6A00F5;
    padding: 0.1rem 0.3rem;
}

/*
:deep(.ProseMirror > p > br:first-child:last-child) {
  display: none;
} */


@keyframes ProseMirror-cursor-blink {
    to {
        visibility: hidden;
    }
}

:deep(.ProseMirror-focused .ProseMirror-gapcursor) {
    @apply block;
}

:deep(p.is-editor-empty:first-child::before){
    color: gray;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
