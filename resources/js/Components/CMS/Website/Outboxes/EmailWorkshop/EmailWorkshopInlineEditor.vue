<script setup lang="ts">
import { computed } from 'vue'
import EditorV2 from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import type { ReadyMadeLink } from '@/Components/Forms/Fields/BubleTextEditor/linkHref'
import { EmailModule, MODULE_TYPES, getModuleText, moduleTextToggles, setModuleText } from './emailWorkshopBlocks'
import { buttonLinkStyle, isLinkUnderlined, modulePaddingStyle, responsiveContentStyle, styleToString } from './renderEmailHtml'

const props = defineProps<{
    module: EmailModule
    linkColor: string
    isLinkUnderlined?: boolean
    isMobile?: boolean
    mergeTags?: Array<{ name: string, value: string }>
    readyMadeLinks?: ReadyMadeLink[]
}>()

const emits = defineEmits<{
    (e: 'edited'): void
}>()

const descriptor = computed(() => props.module.descriptor)
const paddingStyle = computed(() => modulePaddingStyle(props.module, props.isMobile))
const textDescriptor = computed(() => descriptor.value.heading ?? descriptor.value.paragraph ?? descriptor.value.text ?? descriptor.value.list ?? {})
const linkDecoration = computed(() => isLinkUnderlined(textDescriptor.value.computedStyle?.linkUnderline, props.isLinkUnderlined) ? 'underline' : 'none')
const headingStyle = computed(() => `margin:0;${styleToString(responsiveContentStyle(props.module, descriptor.value.heading?.style, !!props.isMobile))}`)
const toggles = computed(() => moduleTextToggles(props.module))

const text = computed({
    get: () => getModuleText(props.module),
    set: (html: string) => {
        setModuleText(props.module, html)
        emits('edited')
    },
})

const textBlockStyle = computed(() => {
    const textDescriptor = descriptor.value.paragraph ?? descriptor.value.text ?? descriptor.value.list ?? {}
    const hasMobileStyle = props.module.type === MODULE_TYPES.paragraph && !!props.isMobile

    return [
        styleToString(responsiveContentStyle(props.module, textDescriptor.style, hasMobileStyle)),
        {
            '--email-link-decoration': linkDecoration.value,
            '--email-link-color': textDescriptor.computedStyle?.linkColor ?? props.linkColor,
            '--email-paragraph-spacing': textDescriptor.computedStyle?.paragraphSpacing ?? '0px',
            '--email-list-indent': textDescriptor.computedStyle?.liIndent ?? '24px',
            '--email-list-spacing': textDescriptor.computedStyle?.liSpacing ?? '0px',
            '--email-list-position': textDescriptor.computedStyle?.listStylePosition === 'inside' ? 'inside' : 'outside',
            '--email-list-marker': textDescriptor.computedStyle?.listStyleType && textDescriptor.computedStyle.listStyleType !== 'revert' ? textDescriptor.computedStyle.listStyleType : null,
        },
    ]
})

const buttonAlign = computed(() => descriptor.value.style?.['text-align'] ?? 'center')
const buttonStyle = computed(() => buttonLinkStyle(props.module, props.isMobile))
</script>

<template>
    <div class="email-inline-editor" :style="paddingStyle" @click.stop>
        <div v-if="module.type === MODULE_TYPES.heading" :style="[headingStyle, { '--email-link-decoration': linkDecoration }]" class="email-inline-text">
            <EditorV2 v-model="text" :toggle="toggles" :mergeTags="mergeTags" :readyMadeLinks="readyMadeLinks" :linkUnderline="linkDecoration === 'underline'" />
        </div>

        <div v-else-if="module.type === MODULE_TYPES.button" :style="{ textAlign: buttonAlign }">
            <div :style="buttonStyle" class="email-inline-text">
                <EditorV2 v-model="text" :toggle="toggles" :mergeTags="mergeTags" :readyMadeLinks="readyMadeLinks" />
            </div>
        </div>

        <div v-else :style="textBlockStyle" class="email-inline-text">
            <EditorV2 v-model="text" :toggle="toggles" :mergeTags="mergeTags" :readyMadeLinks="readyMadeLinks" :linkUnderline="linkDecoration === 'underline'" />
        </div>
    </div>
</template>

<style scoped>
.email-inline-editor {
    cursor: text;
}

.email-inline-text :deep(.ProseMirror) {
    min-height: 1em;
}

.email-inline-text :deep(.editor-class p) {
    text-shadow: none;
}

.email-inline-text :deep(p:not(:last-child)) {
    margin-bottom: var(--email-paragraph-spacing, 0px);
}

.email-inline-text :deep(a) {
    color: var(--email-link-color, #0068A5);
    text-decoration: var(--email-link-decoration, underline);
}

.email-inline-text :deep(ul),
.email-inline-text :deep(ol) {
    margin: 0;
    padding: 0 0 0 var(--email-list-indent, 24px);
    list-style-position: var(--email-list-position, outside);
}

.email-inline-text :deep(ul) {
    list-style-type: var(--email-list-marker, disc);
}

.email-inline-text :deep(ol) {
    list-style-type: var(--email-list-marker, decimal);
}

.email-inline-text :deep(li) {
    margin: 0 0 var(--email-list-spacing, 0px) 0;
}

.email-inline-text :deep(li > p) {
    display: inline;
    margin: 0;
}
</style>
