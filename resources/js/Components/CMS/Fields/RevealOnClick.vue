<script setup lang="ts">
import { computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import PureInput from '@/Components/Pure/PureInput.vue'
import { useCopyText } from '@/Composables/useCopyText'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCopy, faExclamationTriangle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faCopy, faExclamationTriangle)

export interface RevealSetting {
    enabled: boolean
    key: string
    close_label: string | null
    scroll_to: boolean
}

const props = defineProps<{
    modelValue?: RevealSetting | null
    blockId: number | string
    usedKeys?: string[]
    disabled?: boolean
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: RevealSetting): void
}>()

const slugify = (value: string) =>
    (value ?? '')
        .toString()
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9-_]+/g, '-')
        .replace(/-{2,}/g, '-')
        .replace(/^-+/, '')

const isEnabled = computed(() => !!props.modelValue?.enabled)
const revealKey = computed(() => props.modelValue?.key ?? '')
const revealLink = computed(() => (revealKey.value ? `#${revealKey.value}` : ''))
const fallbackKey = computed(() => slugify(`reveal-${props.blockId}`))

const isKeyDuplicated = computed(
    () => !!revealKey.value && (props.usedKeys ?? []).includes(revealKey.value)
)

const update = (patch: Partial<RevealSetting>) => {
    if (props.disabled) return

    emit('update:modelValue', {
        enabled: isEnabled.value,
        key: revealKey.value,
        close_label: props.modelValue?.close_label ?? null,
        scroll_to: props.modelValue?.scroll_to ?? true,
        ...patch,
    })
}

const toggleEnabled = () => {
    update({
        enabled: !isEnabled.value,
        key: revealKey.value || fallbackKey.value,
    })
}
</script>

<template>
    <div class="pb-3 border-gray-300 mb-5 px-2 grid">
        <div class="w-full my-2 text-start py-1 font-semibold select-none text-sm border-b border-gray-300 pb-1 mb-3">
            {{ trans('Reveal on click') }}
        </div>

        <div class="flex items-center">
            <input
                type="checkbox"
                id="revealOnClick"
                :checked="isEnabled"
                :disabled="disabled"
                @change="toggleEnabled"
                class="form-checkbox h-5 w-5 text-indigo-500 border-gray-300 rounded focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" />
            <label
                for="revealOnClick"
                class="ml-2 cursor-pointer text-xs"
                :class="disabled ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:text-indigo-600'">
                {{ trans('Hidden, shown when its link is clicked') }}
            </label>
        </div>

        <template v-if="isEnabled">
            <div class="mt-3">
                <div class="text-gray-500 text-xs tracking-wide mb-1">{{ trans('Link id') }}</div>
                <PureInput
                    :modelValue="revealKey"
                    :disabled="disabled"
                    placeholder="view-more-information"
                    :isError="isKeyDuplicated"
                    @update:modelValue="(value: string) => update({ key: slugify(value) })" />

                <div v-if="isKeyDuplicated" class="mt-1 text-xs text-red-500 flex items-center gap-1">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                    {{ trans('Another block on this page already uses this id') }}
                </div>

                <div v-if="revealLink" class="mt-2 flex items-center justify-between gap-2 rounded border border-gray-200 bg-gray-50 px-2 py-1">
                    <span class="text-xs text-gray-600 truncate">{{ revealLink }}</span>
                    <button
                        type="button"
                        class="text-xs text-gray-500 hover:text-indigo-600"
                        v-tooltip="trans('Copy this link, then paste it in any button or text link on this page')"
                        @click="useCopyText(revealLink)">
                        <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div class="mt-3">
                <div class="text-gray-500 text-xs tracking-wide mb-1">{{ trans('Link label while open') }}</div>
                <PureInput
                    :modelValue="modelValue?.close_label ?? ''"
                    :disabled="disabled"
                    :placeholder="trans('Show Less')"
                    @update:modelValue="(value: string) => update({ close_label: value || null })" />
                <div class="mt-1 text-[11px] text-gray-400">
                    {{ trans('The same button closes the block again. Leave empty to keep its original text.') }}
                </div>
            </div>

            <div class="mt-3 flex items-center">
                <input
                    type="checkbox"
                    id="revealScrollTo"
                    :checked="modelValue?.scroll_to ?? true"
                    :disabled="disabled"
                    @change="update({ scroll_to: !(modelValue?.scroll_to ?? true) })"
                    class="form-checkbox h-5 w-5 text-indigo-500 border-gray-300 rounded focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed" />
                <label
                    for="revealScrollTo"
                    class="ml-2 cursor-pointer text-xs"
                    :class="disabled ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:text-indigo-600'">
                    {{ trans('Scroll to the block when it opens') }}
                </label>
            </div>
        </template>
    </div>
</template>

<style lang="scss" scoped></style>
