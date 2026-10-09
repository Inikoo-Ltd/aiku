<script setup lang="ts">
import { computed } from 'vue'
import EditorV2 from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { EmailModule, MODULE_TYPES, getModuleText, moduleTextToggles, setModuleText } from './emailWorkshopBlocks'
import { modulePaddingStyle, styleToString } from './renderEmailHtml'

const props = defineProps<{
    module: EmailModule
    linkColor: string
    mergeTags?: Array<{ name: string, value: string }>
}>()

const emits = defineEmits<{
    (e: 'edited'): void
}>()

const descriptor = computed(() => props.module.descriptor)
const paddingStyle = computed(() => modulePaddingStyle(props.module))
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

    return [
        styleToString(textDescriptor.style),
        {
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
const buttonStyle = computed(() => `display:inline-block;text-align:center;${styleToString(descriptor.value.button?.style, ['width', 'max-width'])}`)
</script>

<template>
    <div class="email-inline-editor" :style="paddingStyle" @click.stop>
        <div v-if="module.type === MODULE_TYPES.heading" :style="`margin:0;${styleToString(descriptor.heading.style)}`" class="email-inline-text">
            <EditorV2 v-model="text" :toggle="toggles" :mergeTags="mergeTags" />
        </div>

        <div v-else-if="module.type === MODULE_TYPES.button" :style="{ textAlign: buttonAlign }">
            <div :style="buttonStyle" class="email-inline-text">
                <EditorV2 v-model="text" :toggle="toggles" :mergeTags="mergeTags" />
            </div>
        </div>

        <div v-else :style="textBlockStyle" class="email-inline-text">
            <EditorV2 v-model="text" :toggle="toggles" :mergeTags="mergeTags" />
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

.email-inline-text :deep(p:not(:last-child)) {
    margin-bottom: var(--email-paragraph-spacing, 0px);
}

.email-inline-text :deep(a) {
    color: var(--email-link-color, #0068A5);
    text-decoration: underline;
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
