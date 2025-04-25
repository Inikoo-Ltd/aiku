<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue"
import { useEditor, EditorContent, BubbleMenu } from '@tiptap/vue-3'
/* import type DataTable from "@/models/table" */
import Select from 'primevue/select'
import { useFontFamilyList } from '@/Composables/useFont'

import TiptapToolbarButton from "@/Components/Forms/Fields/BubleTextEditor/TiptapToolbarButton.vue"
import TiptapToolbarGroup from "@/Components/Forms/Fields/BubleTextEditor/TiptapToolbarGroup.vue"
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
import Link from "@tiptap/extension-link"
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
import Image from "@tiptap/extension-image"
import TextStyle from '@tiptap/extension-text-style'
import customLink from '@/Components/Forms/Fields/BubleTextEditor/CustomLink/CustomLinkExtension.js'
import { Color } from '@tiptap/extension-color'
import FontSize from 'tiptap-extension-font-size'
import FontFamily from '@tiptap/extension-font-family'
import Highlight from '@tiptap/extension-highlight'
// import PureColorPicker from '@/Components/CMS/Fields/ColorPicker.vue'
// import ColorPicker from 'primevue/colorpicker';
import UtilsColorPicker from '@/Components/Utils/ColorPicker.vue'
import suggestion from './Variables/suggestion'
import ImageResize from 'tiptap-extension-resize-image';
import Dialog from 'primevue/dialog';
import Placeholder from "@tiptap/extension-placeholder"

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
    faVideo,
    faTable as farTable,
    faMinus,
    faList,
    faListOl,
    faAlignLeft,
    faAlignCenter,
    faAlignRight,
    faFileVideo,
    faPaintBrushAlt,
    faText,
    faTextSize,
    faDraftingCompass,
    faExternalLink,
    faTimesCircle,
} from "@far"
import { faTable, faPalette } from "@fal"
import { faEraser, faTint, faTable as fasTable, } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

import TiptapLinkCustomDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapCustomLinkDialog.vue"
import TiptapLinkDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapLinkDialog.vue"
import TiptapVideoDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapVideoDialog.vue"
import TiptapTableDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapTableDialog.vue"
import TiptapImageDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapImageDialog.vue"
import { Plugin } from "prosemirror-state"
import Variabel from "./Variables/Variables"
// import CustomLink from "./CustomLink/CustomLink.vue"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { irisVariable } from "@/Composables/variableList"
import { debounce } from "lodash"

const props = withDefaults(defineProps<{
    modelValue: string | null,
    toogle?: string[],
    type?: string,
    editable?: boolean
    placeholder?: any | String
    uploadImageRoute?: routeType
}>(), {
    editable: true,
    type: 'Bubble',
    placeholder: '',
    toogle: () => [
        'heading', 'fontSize', 'bold', 'italic', 'underline', 'bulletList', 'query', "fontFamily",
        'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight', "customLink",
        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear', "image", "video", "table"
    ]
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: string): void
    (e: 'onEditClick', value: any): void
}>()

const _bubbleMenu = ref(null)
const showDialog = ref(false)
const contentResult = ref<string>()
const currentLinkInDialog = ref<string | undefined>()
const showLinkDialogCustom = ref<boolean>()
const showAddYoutubeDialog = ref<boolean>(false)
const showAddTableDialog = ref<boolean>(false)
const showAddImageDialog = ref<boolean>(false)
const showLinkDialog = ref<boolean>()
const CustomLinkConfirm = ref(false)
const attrsCustomLink = ref<Object>(null)

const editorInstance = useEditor({
    content: props.modelValue,
    editable: props.editable,
    editorProps: {
        attributes: {
            class: "editor-class",
        },
    },
    extensions: [
        Paragraph,
        Document,
        Text,
        ImageResize,
        History,
        Placeholder.configure({
            placeholder: props.placeholder || "Start typing...", // Fallback to default placeholder
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

                                if (attrs) {

                                    // Prevent default link click behavior
                                    event.preventDefault()

                                    // Check if workshop URL exists
                                    if (attrs.workshop) {
                                        CustomLinkConfirm.value = true
                                        attrsCustomLink.value = attrs
                                    } else {
                                      /*   window.open(attrs.href, "_blank") */
                                      console.log(attrs.href)
                                    }
                                    return true
                                }
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
        TableCell.extend({
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
        Gapcursor,
        Image,
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

function openLinkDialogCustom() {
    const attrs = editorInstance.value?.getAttributes("link")
    currentLinkInDialog.value = attrs
    showLinkDialogCustom.value = true;
    showDialog.value = true;
}

function updateLinkCustom(value) {
    if (value.href) {
        const attrs = {
            type: value.type,
            workshop: value.workshop,
            id: value.type === 'internal' ? value.id?.id : null,
            href: value.href,
            target: value.target ? value.target : '_self'
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


function insertImage(url: string) {
    //function alt by ai
    editorInstance.value?.chain().focus().setImage({ src: url, alt :"image" }).run()
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

onMounted(() => {
    setTimeout(() => (contentResult.value = editorInstance.value?.getHTML()), 250)
})

onBeforeUnmount(() => {
    editorInstance.value?.destroy()
})

const onEditorClick = () => {
    emits('onEditClick', editorInstance.value)
}

const setVariabel = (value) => {
    const content = `<span class="mention" data-type="mention" data-id="username" contenteditable="false">{{ ${value} }}</span>`;
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

</script>

<template>
    <div id="tiptap" class="divide-y divide-gray-400">
        <BubbleMenu ref="_bubbleMenu" class="w-[900px]" :editor="editorInstance" :tippy-options="{ duration: 100 }"
            v-if="editorInstance && !showDialog">
            <div class="bg-gray-100 rounded-xl border border-gray-300 divide-y divide-gray-400 isolate">
                <!-- 1st row -->
                <section id="tiptap-toolbar" class="flex items-center divide-x divide-gray-400">
                    <TiptapToolbarGroup>
                        <TiptapToolbarButton v-if="toogle.includes('undo')" label="Undo"
                            @click="editorInstance?.chain().focus().undo().run()"
                            :disabled="!editorInstance?.can().chain().focus().undo().run()">
                            <FontAwesomeIcon :icon="faRedo" class="h-5 w-5" />
                        </TiptapToolbarButton>
                        <TiptapToolbarButton v-if="toogle.includes('redo')" label="Redo"
                            @click="editorInstance?.chain().focus().redo().run()"
                            :disabled="!editorInstance?.can().chain().focus().redo().run()">
                            <FontAwesomeIcon :icon="faUndo" class="h-5 w-5" />
                        </TiptapToolbarButton>
                    </TiptapToolbarGroup>

                    <!-- Section: Heading 1,2,3 -->
                    <TiptapToolbarGroup>
                        <TiptapToolbarButton v-if="toogle.includes('heading')" label="Heading 1"
                            :is-active="editorInstance?.isActive('heading', { level: 1 })"
                            @click="editorInstance?.chain().focus().toggleHeading({ level: 1 }).run()"
                            class="toolbar-button">
                            <FontAwesomeIcon :icon="faH1" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('heading')" label="Heading 2"
                            :is-active="editorInstance?.isActive('heading', { level: 2 })"
                            @click="editorInstance?.chain().focus().toggleHeading({ level: 2 }).run()"
                            class="toolbar-button">
                            <FontAwesomeIcon :icon="faH2" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('heading')" label="Heading 3"
                            :is-active="editorInstance?.isActive('heading', { level: 3 })"
                            @click="editorInstance?.chain().focus().toggleHeading({ level: 3 }).run()"
                            class="toolbar-button">
                            <FontAwesomeIcon :icon="faH3" class="h-5 w-5" />
                        </TiptapToolbarButton>
                    </TiptapToolbarGroup>

                    <!-- Section: Font size -->
                    <div class="my-1.5 inline-flex flex-row flex-wrap items-center space-x-1 px-2">
                        <div :class="[
                            'inline-flex h-8 shrink-0 flex-row items-center justify-center rounded-md disabled:bg-transparent disabled:text-gray-300',
                            'text-gray-600 hover:bg-blue-50',
                        ]" type="button" v-tooltip="'font size'" :aria-label="'font size'">
                            <div class="group relative">
                                <div
                                    class="text-sm py-1 px-2 cursor-pointer hover:border-gray-400 flex items-center justify-between transition h-8">
                                    <FontAwesomeIcon v-if="!editorInstance?.getAttributes('textStyle').fontSize"
                                        :icon="faTextSize" class="h-5 w-5" />
                                    <div v-else id="tiptapfontsize" class="text-gray-600 text-sm font-semibold h-5">
                                        {{ editorInstance?.getAttributes('textStyle').fontSize }}
                                    </div>
                                    <FontAwesomeIcon v-if="editorInstance?.getAttributes('textStyle').fontSize"
                                        @click="editorInstance?.chain().focus().unsetFontSize().run()"
                                        icon="fal fa-times" class="text-red-500 ml-2 cursor-pointer"
                                        aria-hidden="true" />
                                </div>
                                <div
                                    class="w-min h-32 overflow-y-auto text-black cursor-pointer overflow-hidden hidden group-hover:block absolute left-0 right-0 border border-gray-500 rounded bg-white z-[1]">
                                    <div v-for="fontsize in ['8', '9', '12', '14', '16', '18', '20', '24', '28', '36', '44', '52', '64']"
                                        :key="fontsize"
                                        class="px-4 py-2 text-left text-sm cursor-pointer hover:bg-gray-100"
                                        :class="{ 'bg-indigo-600 text-white': parseInt(editorInstance?.getAttributes('textStyle').fontSize, 10) === parseInt(fontsize) }"
                                        @click="editorInstance?.chain().focus().setFontSize(fontsize + 'px').run()">
                                        {{ fontsize }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <TiptapToolbarGroup>
                        <TiptapToolbarButton v-if="toogle.includes('bold')" label="Bold"
                            :is-active="editorInstance?.isActive('bold')"
                            @click="editorInstance?.chain().focus().toggleBold().run()">
                            <FontAwesomeIcon :icon="faBold" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('italic')" label="Italic"
                            :is-active="editorInstance?.isActive('italic')"
                            @click="editorInstance?.chain().focus().toggleItalic().run()">
                            <FontAwesomeIcon :icon="faItalic" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('underline')" label="Underline"
                            :is-active="editorInstance?.isActive('underline')"
                            @click="editorInstance?.chain().focus().toggleUnderline().run()">
                            <FontAwesomeIcon :icon="faUnderline" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('strikethrough')" label="Strikethrough"
                            :is-active="editorInstance?.isActive('strike')"
                            @click="editorInstance?.chain().focus().toggleStrike().run()">
                            <FontAwesomeIcon :icon="faStrikethrough" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <!--  <TiptapToolbarButton v-if="toogle.includes('color')" label="Text Color">
                            <ColorPicker
                                v-model="editorInstance.getAttributes('textStyle').color"
                                :baseZIndex="9999"
                                @update:model-value="color => editorInstance?.chain().focus().setColor(`#${color}`).run()"
                            />
                        </TiptapToolbarButton> -->

                        <TiptapToolbarButton v-if="toogle.includes('color')" label="Text Color">
                            <div class="relative w-7 h-7">
                                <!-- Color Input -->
                                <input type="color" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                    @input="editorInstance?.chain().focus().setColor($event.target.value).run()"
                                    :value="editorInstance.getAttributes('textStyle').color" />
                                <!-- Icon -->
                                <div class="flex items-center justify-center w-full h-full rounded"
                                    :style="{ color: editorInstance.getAttributes('textStyle').color || 'gray' }">
                                    <FontAwesomeIcon :icon="faTint" />
                                </div>
                            </div>
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('highlight')" label="Text highlight">
                            <div class="relative w-7 h-7">
                                <!-- Color Input -->
                                <input type="color" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                    @input="editorInstance.chain().focus().setHighlight({ color: $event.target.value }).run()"
                                    :value="editorInstance.getAttributes('highlight').color" />
                                <!-- Icon -->
                                <div class="flex items-center justify-center w-full h-full  rounded"
                                    :style="{ backgroundColor: editorInstance?.getAttributes('highlight').color }">
                                    <FontAwesomeIcon :icon="faPaintBrushAlt" />
                                </div>
                            </div>
                        </TiptapToolbarButton>
                    </TiptapToolbarGroup>

                    <TiptapToolbarGroup>
                        <TiptapToolbarButton v-if="toogle.includes('bulletList')" label="Bullet List"
                            :is-active="editorInstance?.isActive('bulletList')"
                            @click="editorInstance?.chain().focus().toggleBulletList().run()">
                            <FontAwesomeIcon :icon="faList" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('orderedList')" label="Ordered List"
                            :is-active="editorInstance?.isActive('orderedList')"
                            @click="editorInstance?.chain().focus().toggleOrderedList().run()">
                            <FontAwesomeIcon :icon="faListOl" class="h-5 w-5" />
                        </TiptapToolbarButton>
                    </TiptapToolbarGroup>

                    <TiptapToolbarGroup>
                        <TiptapToolbarButton v-if="toogle.includes('alignLeft')" label="Align Left"
                            :is-active="editorInstance?.isActive('textAlign', 'left')"
                            @click="editorInstance?.chain().focus().setTextAlign('left').run()">
                            <FontAwesomeIcon :icon="faAlignLeft" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('alignCenter')" label="Align Center"
                            :is-active="editorInstance?.isActive('textAlign', 'center')"
                            @click="editorInstance?.chain().focus().setTextAlign('center').run()">
                            <FontAwesomeIcon :icon="faAlignCenter" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('alignRight')" label="Align Right"
                            :is-active="editorInstance?.isActive('textAlign', 'right')"
                            @click="editorInstance?.chain().focus().setTextAlign('right').run()">
                            <FontAwesomeIcon :icon="faAlignRight" class="h-5 w-5" />
                        </TiptapToolbarButton>
                    </TiptapToolbarGroup>

                    <TiptapToolbarGroup>
                        <TiptapToolbarButton v-if="toogle.includes('link')" label="Link" @click="openLinkDialog"
                            :is-active="editorInstance?.isActive('link')">
                            <FontAwesomeIcon :icon="faLink" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('customLink')" label="Link Internal & External"
                            @click="openLinkDialogCustom" :is-active="editorInstance?.isActive('link')">
                            <FontAwesomeIcon :icon="faLink" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('image')" label="Image"
                            @click="() => { showAddImageDialog = true, showDialog = true }">
                            <FontAwesomeIcon :icon="faImage" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('video')" label="Youtube Video"
                            @click="() => { showAddYoutubeDialog = true, showDialog = true }">
                            <FontAwesomeIcon :icon="faFileVideo" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('blockquote')" label="Blockquote"
                            :is-active="editorInstance?.isActive('blockquote')"
                            @click="editorInstance?.chain().focus().toggleBlockquote().run()">
                            <FontAwesomeIcon :icon="faQuoteLeft" class="h-5 w-5" />
                        </TiptapToolbarButton>

                        <TiptapToolbarButton v-if="toogle.includes('divider')"
                            @click="editorInstance?.chain().focus().setHorizontalRule().run()" label="Horizontal Line">
                            <FontAwesomeIcon :icon="faMinus" class="h-5 w-5" />
                        </TiptapToolbarButton>
                    </TiptapToolbarGroup>
                </section>

                <!-- 2nd row -->
                <section id="tiptap-toolbar" class="py-1 px-2 flex items-center divide-x divide-gray-400 gap-y-2">
                    <Select v-if="toogle.includes('query')" @change="(e) => setVariabel(e.value.value)"
                        :options="irisVariable" optionLabel="label" size="small"
                        :placeholder="trans('Select a variable to put')" class="w-full md:w-56 max-w-56 mr-2" />

                    <div class="my-1.5 inline-flex flex-row flex-wrap items-center space-x-1 px-2">
                        <div :class="[
                            'inline-flex h-8 shrink-0 flex-row items-center justify-center rounded-md disabled:bg-transparent disabled:text-gray-300',
                            'text-gray-600 hover:bg-blue-50',
                        ]" type="button" v-tooltip="'font Family'" :aria-label="'font family'">
                            <div class="group relative">
                                <div
                                    class="text-sm py-1 px-2 cursor-pointer hover:border-gray-400 flex items-center justify-between transition h-8 bg-white border rounded">
                                    <div v-if="!editorInstance?.getAttributes('textStyle').fontFamily"
                                        id="tiptapfontsize" class="text-gray-600 text-sm font-semibold h-5">
                                        Font Family
                                    </div>
                                    <div v-else id="tiptapfontsize" class="text-gray-600 text-sm font-semibold h-5">
                                        {{ editorInstance?.getAttributes('textStyle').fontFamily }}
                                    </div>
                                    <FontAwesomeIcon v-if="editorInstance?.getAttributes('textStyle').fontFamily"
                                        @click="editorInstance?.chain().focus().unsetFontFamily().run()"
                                        icon="fal fa-times" class="text-red-500 ml-2 cursor-pointer"
                                        aria-hidden="true" />
                                </div>
                                <div
                                    class="w-min h-32 overflow-y-auto text-black cursor-pointer overflow-hidden hidden group-hover:block absolute left-0 right-0 border border-gray-500 rounded bg-white z-[1]">
                                    <div v-for="font in useFontFamilyList" :key="font.value"
                                        class="px-4 py-2 text-left text-sm cursor-pointer hover:bg-gray-100"
                                        @click="editorInstance?.chain().focus().setFontFamily(font.value).run()">
                                        {{ font.label }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <TiptapToolbarGroup v-if="toogle.includes('table')">
                        <TiptapToolbarButton label="Table"
                            @click="() => { showAddTableDialog = true, showDialog = true }">
                            <FontAwesomeIcon :icon="faTable" class="h-5 w-5" />
                        </TiptapToolbarButton>
                    </TiptapToolbarGroup>

                    <TiptapToolbarGroup v-if="editorInstance?.isActive('table')">
                        <!-- Table: background color -->
                        <TiptapToolbarButton v-if="toogle.includes('color')" :label="trans('Color background')">
                            <!-- {{ editorInstance.getAttributes('table')?.backgroundColor || editorInstance.getAttributes('tableCell')?.backgroundColor }} -->
                            <div class="relative w-7 h-7">
                                <!-- {{ editorInstance.getAttributes('table') }} -->
                                <UtilsColorPicker
                                    key="picker_table_background_color"
                                    :color="editorInstance.getAttributes('table')?.backgroundColor || editorInstance.getAttributes('tableCell')?.backgroundColor"
                                    class=""
                                    @changeColor="(newColor)=> {
                                        editorInstance?.chain().focus().setCellAttribute('backgroundColor', newColor.hex).run()
                                    }"
                                    closeButton
                                >
                                    <template #button>
                                        <div class="group relative h-7 w-7 overflow-hidden rounded flex justify-center items-center" :style="{
                                            backgroundColor: editorInstance.getAttributes('table')?.backgroundColor || editorInstance.getAttributes('tableCell')?.backgroundColor
                                        }">
                                            <FontAwesomeIcon :icon='faPalette' class='text-gray-500' fixed-width aria-hidden='true' />
                                        </div>
                                    </template>
                                </UtilsColorPicker>
                            </div>
                        </TiptapToolbarButton>

                        <!-- Table: border color -->
                        <TiptapToolbarButton v-if="toogle.includes('color')" :label="trans('Color border')">
                            <div class="relative w-7 h-7">
                                <UtilsColorPicker
                                    key="picker_table_border_color"
                                    :color="editorInstance.getAttributes('tableCell')?.borderColor"
                                    class=""
                                    @changeColor="(newColor)=> {
                                        editorInstance?.chain().focus().setCellAttribute('borderColor', newColor.hex).run()
                                    }"
                                    closeButton
                                    :isEditable="editorInstance.getAttributes('tableCell')?.borderWidth && editorInstance.getAttributes('tableCell')?.borderWidth != '0px'"
                                >
                                    <template #before-main-picker>
                                        <div class="mb-2">
                                            <Select
                                                size="small"
                                                :modelValue="editorInstance.getAttributes('tableCell')?.borderWidth"
                                                @update:modelValue="(e) => (console.log('qqq'), editorInstance?.chain().focus().setCellAttribute('borderWidth', e).run())"
                                                :options="tableBorderWidthOptions"
                                                optionLabel="label"
                                                optionValue="value"
                                                :placeholder="trans('Select border width')"
                                                fluid
                                            />
                                        </div>
                                    </template>

                                    <template #button>
                                        <div class="group relative h-7 w-7 overflow-hidden rounded flex justify-center items-center" :style="{
                                            color: editorInstance.getAttributes('tableCell')?.borderColor,
                                            background: editorInstance.getAttributes('tableCell')?.borderWidth && editorInstance.getAttributes('tableCell')?.borderWidth != '0px' ? '#d1d5db' : 'none'
                                        }">
                                            <FontAwesomeIcon :icon='fasTable' class='' fixed-width aria-hidden='true' />
                                        </div>
                                    </template>
                                </UtilsColorPicker>

                                
                            </div>
                        </TiptapToolbarButton>
                        
                        <TiptapToolbarButton @click="editorInstance?.commands.deleteTable()" label="Remove table">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5"
                                fill="currentColor">
                                <path
                                    d="M15.46,15.88L16.88,14.46L19,16.59L21.12,14.46L22.54,15.88L20.41,18L22.54,20.12L21.12,21.54L19,19.41L16.88,21.54L15.46,20.12L17.59,18L15.46,15.88M4,3H18A2,2 0 0,1 20,5V12.08C18.45,11.82 16.92,12.18 15.68,13H12V17H13.08C12.97,17.68 12.97,18.35 13.08,19H4A2,2 0 0,1 2,17V5A2,2 0 0,1 4,3M4,7V11H10V7H4M12,7V11H18V7H12M4,13V17H10V13H4Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton label="Add column before"
                            @click="editorInstance?.commands.addColumnBefore()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5"
                                fill="currentColor">
                                <path
                                    d="M13,2A2,2 0 0,0 11,4V20A2,2 0 0,0 13,22H22V2H13M20,10V14H13V10H20M20,16V20H13V16H20M20,4V8H13V4H20M9,11H6V8H4V11H1V13H4V16H6V13H9V11Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton label="Add column after"
                            @click="editorInstance?.commands.addColumnAfter()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5"
                                fill="currentColor">
                                <path
                                    d="M11,2A2,2 0 0,1 13,4V20A2,2 0 0,1 11,22H2V2H11M4,10V14H11V10H4M4,16V20H11V16H4M4,4V8H11V4H4M15,11H18V8H20V11H23V13H20V16H18V13H15V11Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton label="Remove column" @click="editorInstance?.commands.deleteColumn()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5"
                                fill="currentColor">
                                <path
                                    d="M4,2H11A2,2 0 0,1 13,4V20A2,2 0 0,1 11,22H4A2,2 0 0,1 2,20V4A2,2 0 0,1 4,2M4,10V14H11V10H4M4,16V20H11V16H4M4,4V8H11V4H4M17.59,12L15,9.41L16.41,8L19,10.59L21.59,8L23,9.41L20.41,12L23,14.59L21.59,16L19,13.41L16.41,16L15,14.59L17.59,12Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton label="Add row before" @click="editorInstance?.commands.addRowBefore()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5"
                                fill="currentColor">
                                <path
                                    d="M22,14A2,2 0 0,0 20,12H4A2,2 0 0,0 2,14V21H4V19H8V21H10V19H14V21H16V19H20V21H22V14M4,14H8V17H4V14M10,14H14V17H10V14M20,14V17H16V14H20M11,10H13V7H16V5H13V2H11V5H8V7H11V10Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton @click="editorInstance?.commands.addRowAfter()" label="Add row after">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5"
                                fill="currentColor">
                                <path
                                    d="M22,10A2,2 0 0,1 20,12H4A2,2 0 0,1 2,10V3H4V5H8V3H10V5H14V3H16V5H20V3H22V10M4,10H8V7H4V10M10,10H14V7H10V10M20,10V7H16V10H20M11,14H13V17H16V19H13V22H11V19H8V17H11V14Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton label="Remove row" @click="editorInstance?.commands.deleteRow()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5"
                                fill="currentColor">
                                <path
                                    d="M9.41,13L12,15.59L14.59,13L16,14.41L13.41,17L16,19.59L14.59,21L12,18.41L9.41,21L8,19.59L10.59,17L8,14.41L9.41,13M22,9A2,2 0 0,1 20,11H4A2,2 0 0,1 2,9V6A2,2 0 0,1 4,4H20A2,2 0 0,1 22,6V9M4,9H8V6H4V9M10,9H14V6H10V9M16,9H20V6H16V9Z" />
                            </svg>
                        </TiptapToolbarButton>
                        <TiptapToolbarButton label="Merge or split cell"
                            @click="editorInstance?.commands.mergeOrSplit()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5"
                                fill="currentColor">
                                <path
                                    d="M5,10H3V4H11V6H5V10M19,18H13V20H21V14H19V18M5,18V14H3V20H11V18H5M21,4H13V6H19V10H21V4M8,13V15L11,12L8,9V11H3V13H8M16,11V9L13,12L16,15V13H21V11H16Z" />
                            </svg>
                        </TiptapToolbarButton>
                    </TiptapToolbarGroup>

                    <TiptapToolbarButton
                            @click="editorInstance?.chain().focus().unsetAllMarks().run()" label="Unset Style">
                            <FontAwesomeIcon :icon="faEraser" class="h-5 w-5" />
                    </TiptapToolbarButton>

                </section>
            </div>
        </BubbleMenu>

        <div class="flex flex-col">
            <slot name="editor-content" :editor="editorInstance">
                <EditorContent  @click.stop="onEditorClick" :editor="editorInstance" />
            </slot>
        </div>

        <TiptapTableDialog v-if="showAddTableDialog" :show="showAddTableDialog"
            @close="() => { showAddTableDialog = false, showDialog = false; }" @insert="insertTable" />

        <TiptapLinkCustomDialog v-if="showLinkDialogCustom" :show="showLinkDialogCustom" :attribut="currentLinkInDialog"
            @close="() => { showLinkDialogCustom = false; showDialog = false; }" @update="updateLinkCustom" />
        <TiptapImageDialog v-if="showAddImageDialog" :show="showAddImageDialog"
            @close="() => { showAddImageDialog = false, showDialog = false }" @insert="insertImage"
            :uploadImageRoute="uploadImageRoute" />
        <TiptapLinkDialog v-if="showLinkDialog" :show="showLinkDialog" :current-url="currentLinkInDialog"
            @close="() => { showLinkDialog = false; showDialog = false; }" @update="updateLink" />
        <TiptapVideoDialog v-if="showAddYoutubeDialog" :show="showAddYoutubeDialog" @insert="insertYoutubeVideo"
            @close="() => { showAddYoutubeDialog = false; showDialog = false; }" />


        <Dialog v-model:visible="CustomLinkConfirm" :style="{ width: '25rem' }" modal :closable="false"
            :dismissableMask="true" :showHeader="false">
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
:deep(.tippy-box) {
    min-width: 10px !important;
    max-width: max-content !important;
    background-color: transparent
}

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
    @apply mt-2 first:mt-0;
}

:deep(.editor-class blockquote) {
    @apply italic border-l-4 border-gray-300 p-4 py-2 ml-6 mt-6 mb-2 bg-gray-50;
}

/* :deep(.editor-class a) {
    @apply hover:underline text-blue-600 cursor-pointer;
} */

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
</style>
