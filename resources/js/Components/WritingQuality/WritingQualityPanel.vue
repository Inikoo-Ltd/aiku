<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSpellCheck, faCheck, faTimes, faExclamationTriangle } from '@fal'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { ctrans } from '@/Composables/useTrans'
import { WritingQualityIssue, writingQualityCategoryLabel } from './writingQuality'

library.add(faSpellCheck, faCheck, faTimes, faExclamationTriangle)

defineProps<{
    issues: WritingQualityIssue[]
    isChecking: boolean
    hasChecked: boolean
    error: string | null
    segmentLabel?: (segment: string) => string | null
}>()

const emits = defineEmits<{
    (e: 'check'): void
    (e: 'accept', issue: WritingQualityIssue): void
    (e: 'ignore', issue: WritingQualityIssue): void
}>()
</script>

<template>
    <div class="text-xs">
        <div class="flex items-center gap-x-3">
            <Button type="tertiary" size="xs" :label="ctrans('Check writing')" :loading="isChecking" @click="emits('check')">
                <template #icon>
                    <FontAwesomeIcon :icon="faSpellCheck" class="text-gray-500" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <span v-if="hasChecked && !isChecking && !error" class="text-gray-500">
                {{ issues.length ? ctrans(':count suggestions', { count: issues.length }) : ctrans('Nothing to improve') }}
            </span>
        </div>

        <p v-if="error" class="mt-1.5 text-red-600">{{ error }}</p>

        <ul v-if="issues.length" class="mt-2 max-h-80 divide-y divide-gray-100 overflow-y-auto rounded border border-gray-200 bg-white">
            <li v-for="issue in issues" :key="issue.key" class="space-y-1 px-3 py-2">
                <div class="flex items-center gap-x-2 text-[11px] text-gray-500">
                    <FontAwesomeIcon v-if="issue.category === 'unsupported_claim' || issue.category === 'placeholder'" :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
                    <span class="font-semibold uppercase tracking-wide">{{ writingQualityCategoryLabel(issue.category) }}</span>
                    <span v-if="segmentLabel?.(issue.segment)" class="truncate">· {{ segmentLabel(issue.segment) }}</span>
                </div>
                <p class="text-gray-500 line-through decoration-gray-400">{{ issue.quote }}</p>
                <p class="text-gray-900">{{ issue.suggestion || ctrans('(remove these words)') }}</p>
                <p v-if="issue.explanation" class="italic text-gray-500">{{ issue.explanation }}</p>
                <div class="flex gap-x-2 pt-0.5">
                    <Button type="tertiary" size="xxs" :label="ctrans('Accept')" @click="emits('accept', issue)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" size="xxs" :label="ctrans('Ignore')" @click="emits('ignore', issue)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>

<style>
.writing-quality-mark {
    text-decoration: underline wavy #f59e0b;
    text-decoration-skip-ink: none;
    text-underline-offset: 3px;
}
</style>
