<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import type { Editor } from '@tiptap/vue-3'
import Popover from 'primevue/popover'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSpellCheck, faSpinnerThird } from '@fal'
import { ctrans } from '@/Composables/useTrans'
import WritingQualityPanel from './WritingQualityPanel.vue'
import {
    WritingQualityContext, WritingQualityIssue, checkWritingQuality, findQuoteInDoc, showWritingQualityMarks, writingQualityPlugin, writingQualityPluginKey,
} from './writingQuality'

const props = defineProps<{
    editor: Editor | null | undefined
    context: WritingQualityContext
    textOf?: (editor: Editor) => string
    compact?: boolean
}>()

const trigger = ref<HTMLElement | null>(null)
const popover = ref<InstanceType<typeof Popover> | null>(null)

const issues = ref<WritingQualityIssue[]>([])
const isChecking = ref(false)
const hasChecked = ref(false)
const error = ref<string | null>(null)

const dropIssuesNoLongerInText = ({ editor }: { editor: Editor }) => {
    if (issues.value.length) {
        issues.value = issues.value.filter((issue) => !!findQuoteInDoc(editor.state.doc, issue.quote))
    }
}

const detach = (editor: Editor | null | undefined) => {
    if (editor && !editor.isDestroyed) {
        editor.off('update', dropIssuesNoLongerInText)
        editor.unregisterPlugin(writingQualityPluginKey)
    }
}

watch(() => props.editor, (editor, previous) => {
    detach(previous)
    if (editor && !editor.isDestroyed && !writingQualityPluginKey.getState(editor.state)) {
        editor.registerPlugin(writingQualityPlugin())
        editor.on('update', dropIssuesNoLongerInText)
    }
}, { immediate: true })

onBeforeUnmount(() => detach(props.editor))

const refreshMarks = () => showWritingQualityMarks(props.editor, issues.value.map((issue) => issue.quote))

const check = async () => {
    if (!props.editor) {
        return
    }

    isChecking.value = true
    error.value = null
    try {
        const text = props.textOf ? props.textOf(props.editor) : props.editor.getHTML()
        issues.value = (await checkWritingQuality([{ id: 'text', text }], props.context))
            .filter((issue) => !!findQuoteInDoc(props.editor!.state.doc, issue.quote))
        hasChecked.value = true
    } catch (exception: any) {
        issues.value = []
        error.value = exception.message
    } finally {
        isChecking.value = false
        refreshMarks()
    }
}

const openFromIcon = async (event: MouseEvent) => {
    if (hasChecked.value || error.value) {
        popover.value?.toggle(event, trigger.value)
        return
    }

    await check()
    popover.value?.show({ currentTarget: trigger.value } as unknown as Event, trigger.value)
}

const ignore = (issue: WritingQualityIssue) => {
    issues.value = issues.value.filter((open) => open.key !== issue.key)
    refreshMarks()
}

const accept = (issue: WritingQualityIssue) => {
    const editor = props.editor
    const range = editor ? findQuoteInDoc(editor.state.doc, issue.quote) : null
    if (!editor || !range) {
        error.value = ctrans('That text has changed since the check. Edit it by hand or check again.')
        ignore(issue)
        return
    }

    error.value = null
    editor.view.dispatch(issue.suggestion ? editor.state.tr.insertText(issue.suggestion, range.from, range.to) : editor.state.tr.delete(range.from, range.to))
    ignore(issue)
}
</script>

<template>
    <div v-if="compact" class="flex items-center">
        <button ref="trigger" type="button" @mousedown.prevent @click="openFromIcon" :disabled="isChecking"
            class="relative flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 disabled:cursor-wait"
            v-tooltip="ctrans('Check writing: points at generic, padded or unnatural wording, with a suggestion for each')" :aria-label="ctrans('Check writing')">
            <FontAwesomeIcon :icon="isChecking ? faSpinnerThird : faSpellCheck" :spin="isChecking" class="text-sm" fixed-width aria-hidden="true" />
            <span v-if="issues.length" class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-semibold leading-none text-white tabular-nums">
                {{ issues.length }}
            </span>
        </button>
        <Popover ref="popover">
            <div class="w-[26rem] max-w-[90vw]">
                <WritingQualityPanel :issues="issues" :isChecking="isChecking" :hasChecked="hasChecked" :error="error"
                    @check="check" @accept="accept" @ignore="ignore" />
            </div>
        </Popover>
    </div>
    <WritingQualityPanel v-else :issues="issues" :isChecking="isChecking" :hasChecked="hasChecked" :error="error"
        @check="check" @accept="accept" @ignore="ignore" />
</template>
