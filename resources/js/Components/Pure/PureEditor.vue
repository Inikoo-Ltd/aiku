<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import { useEditor, EditorContent, BubbleMenu } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextStyle from '@tiptap/extension-text-style'
import Underline from '@tiptap/extension-underline'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'
import BulletList from '@tiptap/extension-bullet-list'
import ListItem from '@tiptap/extension-list-item'
import Heading from '@tiptap/extension-heading'
import TextAlign from '@tiptap/extension-text-align'
import Highlight from '@tiptap/extension-highlight'
import { Color } from '@tiptap/extension-color'
import FontSize from 'tiptap-extension-font-size'
import Link from '@tiptap/extension-link'
import EditorMenu from '@/Components/Pure/Supports/EditorMenu.vue'
import Placeholder from '@tiptap/extension-placeholder'
import FontFamily from '@tiptap/extension-font-family'
import "@/../css/Iris/blockTextContent.css"


import ColorPicker from '@/Components/CMS/Fields/ColorPicker.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faText, faUndoAlt, faRedoAlt } from '@far'
import { faHorizontalRule, faQuoteRight, faMarker } from '@fas'
import { faBold, faItalic, faUnderline, faStrikethrough, faAlignLeft, faAlignCenter, faAlignRight, faAlignJustify, faSubscript, faSuperscript, faEraser, faListUl, faListOl, faPaintBrushAlt, faTextHeight, faLink } from '@fal'
library.add(faBold, faQuoteRight, faMarker, faHorizontalRule, faItalic, faUnderline, faStrikethrough, faAlignLeft, faAlignCenter, faAlignRight, faAlignJustify, faSubscript, faSuperscript, faEraser, faListUl, faListOl, faUndoAlt, faRedoAlt, faPaintBrushAlt, faTextHeight, faLink, faText)


const props = withDefaults(defineProps<{
    modelValue: string,
    toogle?: string[],
    type?: string,
    editable?: boolean
    placeholder?: any | String
}>(), {
    editable: true,
    type: 'Bubble',
    placeholder: '',
    toogle: () => [
        'heading', 'fontSize', 'bold', 'italic', 'underline', 'bulletList',
        'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight',
        'alignCenter', 'link', 'undo', 'redo', 'highlight', 'color', 'clear',
        /* TODO 'fontFamily' */
    ]
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: string): void
    (e: 'onEditClick', value: any): void
}>()

const toggleList = ref([
    { key: 'heading' },
    { key: 'fontSize', active: 'fontsize' },
    { key: 'bold', icon: 'fal fa-bold', action: () => onActionClick('bold'), active: 'bold' },
    { key: 'italic', icon: 'fal fa-italic', action: () => onActionClick('italic'), active: 'italic' },
    { key: 'underline', icon: 'fal fa-underline', action: () => onActionClick('underline'), active: 'underline' },
    { key: 'bulletList', icon: 'fal fa-list-ul', action: () => onActionClick('bulletList'), active: 'bulletList' },
    { key: 'orderedList', icon: 'fal fa-list-ol', action: () => onActionClick('orderedList'), active: 'orderedList' },
    { key: 'blockquote', icon: 'fas fa-quote-right', action: () => onActionClick('blockquote'), active: 'blockquote' }, // Added missing comma here
    { key: 'divider', icon: 'fas fa-horizontal-rule', action: () => onActionClick('divider'), active: "divider" },
    { key: 'alignLeft', icon: 'fal fa-align-left', action: () => onActionClick('alignLeft'), active: { textAlign: 'left' } },
    { key: 'alignRight', icon: 'fal fa-align-right', action: () => onActionClick('alignRight'), active: { textAlign: 'right' } },
    { key: 'alignCenter', icon: 'fal fa-align-center', action: () => onActionClick('alignCenter'), active: { textAlign: 'center' } },
    { key: 'link', icon: 'fal fa-link', action: () => onActionClick('link'), active: 'link' },
    { key: 'undo', icon: 'far fa-undo-alt', action: () => onActionClick('undo'), active: 'undo' },
    { key: 'redo', icon: 'far fa-redo-alt', action: () => onActionClick('redo'), active: 'redo' },
    { key: 'highlight', icon: 'fal fa-paint-brush-alt', action: () => onActionClick('highlight'), active: 'highlightcolor' },
    { key: 'color', icon: 'far fa-text', action: () => onActionClick('color'), active: 'textcolor' },
    { key: 'clear', icon: 'fal fa-eraser', action: () => onActionClick('clear'), active: 'clear' },
    /* TODO { key: 'fontFamily', icon: 'fal fa-text', action: () => onActionClick('fontFamily'), active: 'fontFamily' }, */
])


const editor = useEditor({
    content: props.modelValue,
    editable: props.editable,
    onUpdate: ({ editor }) => {
        emits('update:modelValue', editor.getHTML())
    },
    extensions: [
        StarterKit,
        Underline,
        Subscript,
        Superscript,
        TextStyle,
        BulletList,
        ListItem,
        FontFamily.configure({
            types: ['textStyle']
        }),
        Placeholder.configure({
            // Use a placeholder:
            placeholder: props.placeholder,
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        Heading.configure({
            levels: [1, 2, 3],
        }),
        Highlight.configure({
            multicolor: true
        }),
        Color.configure({
            types: ['textStyle'],
        }),
        FontSize.configure({
            types: ['textStyle'],
        }),
        Link,
    ],
})

const onActionClick = (key: string, option: string = '') => {
    if (!editor.value) return

    const chain = editor.value.chain().focus()
    switch (key) {
        case 'bold':
            chain.toggleBold().run()
            break
        case 'italic':
            chain.toggleItalic().run()
            break
        case 'underline':
            chain.toggleUnderline().run()
            break
        case 'bulletList':
            chain.toggleBulletList().run()
            break
        case 'orderedList':
            chain.toggleOrderedList().run()
            break
        case 'blockquote':
            chain.toggleBlockquote().run()
            break
        case 'divider':
            chain.setHorizontalRule().run()
            break
        case 'alignLeft':
            chain.setTextAlign('left').run()
            break
        case 'alignRight':
            chain.setTextAlign('right').run()
            break
        case 'alignCenter':
            chain.setTextAlign('center').run()
            break
        case 'link':
            setLink()
            break
        case 'undo':
            chain.undo().run()
            break
        case 'redo':
            chain.redo().run()
            break
        case 'clear':
            chain?.clearNodes().run()
            chain?.unsetAllMarks().run()
            break
        /* TODO  case 'fontFamily':
             chain.setFontFamily('cursive').run()
             break */
        default:
            console.warn(`Action not found for key: ${key}`)
            break
    }
}



const setLink = () => {
    const previousUrl = editor.value?.getAttributes('link').href
    const url = window.prompt('URL', previousUrl) || ''

    if (url) {
        editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
    } else {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    }
}

const onEditorClick = () => {
    emits('onEditClick', editor.value)
}

onMounted(() => {
    nextTick(() => {
        toggleList.value = toggleList.value.filter(item => props.toogle?.includes(item.key))
        if (editor.value) editor.value.commands.selectAll()
    })
})

defineExpose({
    editor
})


</script>

<template>
    <div class="pt-1">
        <BubbleMenu v-if="type == 'Bubble' && editor" :editor="editor" :tippy-options="{ duration: 100 }">
            <section
                class="buttons text-gray-700 flex text-xs items-center flex-wrap gap-x-4 border-t border-l border-r border-gray-400 p-1 bg-gray-200">
                <EditorMenu v-for="action in toggleList" :key="action.key" :editor="editor" :action="action" />
            </section>
        </BubbleMenu>

        <section v-else-if="type == 'basic' && editor"
            class="buttons text-xs text-gray-700 flex items-center flex-wrap gap-x-4 border border-gray-400 p-2 rounded-t-lg bg-white">
            <EditorMenu v-for="action in toggleList" :key="action.key" :editor="editor" :action="action" />
        </section>

        <EditorContent @click="onEditorClick" :editor="editor" class="basic-content" />
    </div>
</template>



<style lang="scss">
/* Basic editor styles */
.tiptap {
    /*   >*+* {
        margin-top: 0.75em;
    } */

    blockquote {
        padding-left: 1rem;
        border-left: 3px solid rgba(#0D0D0D, 0.1);
    }


    ul,
    ol {
        padding: 0 1rem;
    }

    ul {
        list-style: disc
    }

    ol {
        list-style: decimal
    }

    h1 {
        display: block;
        font-size: 2em;
        margin-block-start: 0.67em;
        margin-block-end: 0.67em;
        margin-inline-start: 0;
        margin-inline-end: 0;
        font-weight: bold;
        unicode-bidi: isolate;
    }

    h2 {
        display: block;
        font-size: 1.5em;
        margin-block-start: 0.83em;
        margin-block-end: 0.83em;
        margin-inline-start: 0;
        margin-inline-end: 0;
        font-weight: bold;
        unicode-bidi: isolate;
    }

    h3 {
        display: block;
        font-size: 1.17em;
        margin-block-start: 1em;
        margin-block-end: 1em;
        margin-inline-start: 0;
        margin-inline-end: 0;
        font-weight: bold;
        unicode-bidi: isolate;
    }

}

.fixed-width-bubble-menu {
    width: 300px;
    /* Set your desired fixed width */
}

.basic-content {
    .ProseMirror {
        @apply py-1 px-2 h-32 w-full;
        overflow-y: auto;
        padding-left: 0.5em;
        padding-right: 0.5em;
        outline: none;
        background-color: white;
        border-radius: 0.5rem;
        // border-bottom-right-radius: 0.5rem;
        border: solid 1px #D1D5DB;
    }
}

.tiptap p.is-editor-empty:first-child::before {
    color: #adb5bd;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}


.ProseMirror:focus {
    outline: none;
}
</style>