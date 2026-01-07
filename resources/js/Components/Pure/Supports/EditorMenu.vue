<script setup lang="ts">
import ColorPicker from '@/Components/CMS/Fields/ColorPicker.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faText, faUndoAlt, faRedoAlt } from '@far'
import { faHorizontalRule, faQuoteRight, faMarker } from '@fas'
import { faBold, faItalic, faUnderline, faStrikethrough, faAlignLeft, faAlignCenter, faAlignRight, faAlignJustify, faSubscript, faSuperscript, faEraser, faListUl, faListOl, faPaintBrushAlt, faTextHeight, faLink } from '@fal'
library.add(faBold, faQuoteRight, faMarker, faHorizontalRule, faItalic, faUnderline, faStrikethrough, faAlignLeft, faAlignCenter, faAlignRight, faAlignJustify, faSubscript, faSuperscript, faEraser, faListUl, faListOl, faUndoAlt, faRedoAlt, faPaintBrushAlt, faTextHeight, faLink, faText)


const props = withDefaults(defineProps<{
    editor: any,
    action: {
        key: string
        icon?: {
            icon: string | string[]
        }
        active: boolean
    },
}>(), {

});


const onHeadingClick = (index: number) => {
    props.editor.chain().focus().toggleHeading({ level: index }).run()
}


</script>

<template>

    <div v-if="action.key == 'heading'" class="group relative inline-block">
        <div class="text-xs min-w-16 p-1 appearance-none rounded-sm cursor-pointer border border-gray-200"
            :class="{ 'bg-slate-700 text-white font-bold': editor?.isActive('heading') }">
            Heading <span id="headingIndex"></span>
        </div>
        <div
            class="cursor-pointer overflow-hidden hidden group-hover:block absolute left-0 right-0 border border-gray-500 rounded-sm bg-white z-[1]">
            <div v-for="index in 6" class="block py-1.5 px-3 text-center cursor-pointer hover:bg-gray-300"
                :class="{ 'bg-slate-700 text-white hover:bg-slate-700': editor?.isActive('heading', { level: index }) }"
                :style="{ fontSize: (20 - index) + 'px' }" role="button" @click="onHeadingClick(index)">
                H{{ index }}
            </div>
        </div>
    </div>



    <div v-else-if="action.key == 'fontSize'" class="group relative inline-block">
        <div class="flex items-center text-xs min-w-10 py-1 pl-1.5 pr-0 appearance-none rounded-sm cursor-pointer border border-gray-500"
            :class="{ 'bg-slate-700 text-white font-bold': editor?.getAttributes('textStyle').fontSize }">
            <div id="tiptapfontsize" class="pr-1.5">
                <span class="hidden last:inline">Text size</span>
            </div>
            <div v-if="editor?.getAttributes('textStyle').fontSize"
                @click="editor?.chain().focus().unsetFontSize().run()" class="px-1">
                <FontAwesomeIcon icon='fal fa-times' class='' fixed-width aria-hidden='true' />
            </div>
        </div>
        <div
            class="w-min h-48 overflow-y-auto cursor-pointer overflow-hidden hidden group-hover:block absolute left-0 right-0 border border-gray-500 rounded-sm bg-white z-[1]">
            <div v-for="fontsize in ['8', '9', '12', '14', '16', '20', '24', '28', '36', '44', '52', '64']"
                class="w-full block py-1.5 px-3 leading-none text-left cursor-pointer hover:bg-gray-300"
                :class="{ 'bg-slate-700 text-white hover:bg-slate-700': parseInt(editor?.getAttributes('textStyle').fontSize, 10) == fontsize }"
                @click="editor?.chain().focus().setFontSize(fontsize + 'px').run()" role="button">
                <div v-if="parseInt(editor?.getAttributes('textStyle').fontSize, 10) == fontsize" to="#tiptapfontsize">
                </div>
                {{ fontsize }}
            </div>
        </div>
    </div>


    <ColorPicker v-else-if="action.key == 'highlight'" :color="editor?.getAttributes('highlight').color"
        @changeColor="(color) => editor?.chain().setHighlight({ color: color.hex }).run()"
        class="flex items-center justify-center w-6 aspect-square rounded-sm cursor-pointer border border-gray-700"
        :style="{ backgroundColor: editor?.getAttributes('highlight').color }">
        <FontAwesomeIcon icon='fal fa-paint-brush-alt' class='text-gray-500' fixed-width aria-hidden='true' />
    </ColorPicker>


    <ColorPicker v-else-if="action.key == 'color'" :color="editor?.getAttributes('textStyle').color"
        @changeColor="(color) => editor?.chain().setColor(color.hex).run()"
        class="flex items-center justify-center w-6 aspect-square rounded-sm cursor-pointer border border-gray-700">
        <FontAwesomeIcon icon='far fa-text' fixed-width aria-hidden='true'
            :style="{ color: editor?.getAttributes('textStyle').color || '#010101' }" />
    </ColorPicker>



    <button v-else type="button" @click="action?.action" :class="{
        'bg-gray-200 rounded': editor?.isActive(action.active),
        '': !editor?.isActive(action.active)
    }" class="p-1">
        <span v-if="action.icon">
            <FontAwesomeIcon :icon='action.icon' />
        </span>
    </button>


</template>
