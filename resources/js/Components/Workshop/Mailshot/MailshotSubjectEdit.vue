<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPencil, faEnvelope } from '@fal'
import { faSparkles } from '@fas'
import Popover from '@/Components/Popover.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { routeType } from '@/types/route'

const props = defineProps<{
    mailshot: { subject: string, name: string | null, preview_text: string | null }
    updateMailshotRoute: routeType
    suggestCopyRoute: routeType
}>()

const emits = defineEmits<{
    (e: 'saved', subject: string, mailshot: { subject: string, name: string | null, preview_text: string | null }): void
}>()

const SUBJECT_RECOMMENDED_LENGTH = 60
const PREVIEW_TEXT_RECOMMENDED_LENGTH = 90

const mailshotForm = reactive({ ...props.mailshot })

watch(() => props.mailshot, (mailshot) => Object.assign(mailshotForm, mailshot), { deep: true })

const isSaving = ref(false)
const isSuggesting = ref(false)

const counterClass = (value: string | null, recommendedLength: number) =>
    (value?.length ?? 0) > recommendedLength ? 'text-amber-600' : 'text-gray-400'

const cancel = (close: () => void) => {
    Object.assign(mailshotForm, props.mailshot)
    close()
}

const save = async (close: () => void) => {
    isSaving.value = true
    try {
        await axios.patch(route(props.updateMailshotRoute.name, props.updateMailshotRoute.parameters), mailshotForm)
        emits('saved', mailshotForm.subject, { ...mailshotForm })
        close()
    } catch (error) {
        notify({
            title: ctrans('Something went wrong'),
            text: ctrans('Failed to save the subject'),
            type: 'error'
        })
    } finally {
        isSaving.value = false
    }
}

const suggestCopy = async () => {
    isSuggesting.value = true
    try {
        const { data } = await axios.post(route(props.suggestCopyRoute.name, props.suggestCopyRoute.parameters))
        mailshotForm.subject = data.subject
        if (data.preview_text) {
            mailshotForm.preview_text = data.preview_text
        }
        if (data.name) {
            mailshotForm.name = data.name
        }
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message
                || ctrans('Could not generate a suggestion, add some content first'),
            type: 'error'
        })
    } finally {
        isSuggesting.value = false
    }
}
</script>

<template>
    <Popover position="left-0" width="w-[28rem]">
        <template #button>
            <span class="ml-1 inline-flex cursor-pointer items-center gap-x-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs font-normal text-gray-600 transition hover:border-[var(--theme-color-4)] hover:text-[var(--theme-color-4)]"
                v-tooltip="ctrans('Edit subject, preview text and name')">
                <FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
                {{ ctrans('Edit details') }}
            </span>
        </template>

        <template #content="{ close }">
            <div class="space-y-4 text-left text-sm font-normal">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">{{ ctrans('Email details') }}</h3>
                    <p class="text-xs text-gray-500">{{ ctrans('What recipients see in their inbox before opening the email.') }}</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-gray-400">{{ ctrans('Inbox preview') }}</div>
                    <div class="flex items-start gap-x-3 rounded-md bg-white p-2.5 shadow-sm">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--theme-color-4)_12%,white)] text-[var(--theme-color-4)]">
                            <FontAwesomeIcon :icon="faEnvelope" fixed-width aria-hidden="true" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold" :class="mailshotForm.subject ? 'text-gray-900' : 'italic text-gray-400'">
                                {{ mailshotForm.subject || ctrans('Your subject line') }}
                            </div>
                            <div class="truncate text-xs" :class="mailshotForm.preview_text ? 'text-gray-500' : 'italic text-gray-400'">
                                {{ mailshotForm.preview_text || ctrans('Preview text appears here…') }}
                            </div>
                        </div>
                    </div>
                </div>

                <label class="block">
                    <span class="mb-1 flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-700">{{ ctrans('Subject') }} <span class="text-red-500">*</span></span>
                        <span class="text-[11px]" :class="counterClass(mailshotForm.subject, SUBJECT_RECOMMENDED_LENGTH)">
                            {{ mailshotForm.subject?.length ?? 0 }}/{{ SUBJECT_RECOMMENDED_LENGTH }}
                        </span>
                    </span>
                    <input v-model="mailshotForm.subject" type="text" :placeholder="ctrans('Email subject')"
                        class="w-full rounded-md border-gray-300 px-3 py-2 text-sm focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]" />
                    <span class="mt-1 block text-[11px] text-gray-400">{{ ctrans('Keep it under 60 characters so it is not cut off on mobile.') }}</span>
                </label>

                <label class="block">
                    <span class="mb-1 flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-700">{{ ctrans('Preview text') }}</span>
                        <span class="text-[11px]" :class="counterClass(mailshotForm.preview_text, PREVIEW_TEXT_RECOMMENDED_LENGTH)">
                            {{ mailshotForm.preview_text?.length ?? 0 }}/{{ PREVIEW_TEXT_RECOMMENDED_LENGTH }}
                        </span>
                    </span>
                    <input v-model="mailshotForm.preview_text" type="text" :placeholder="ctrans('Email preview text')"
                        class="w-full rounded-md border-gray-300 px-3 py-2 text-sm focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]" />
                    <span class="mt-1 block text-[11px] text-gray-400">{{ ctrans('Shown after the subject in most inboxes.') }}</span>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans('Internal name') }}</span>
                    <input v-model="mailshotForm.name" type="text" :placeholder="ctrans('Internal name')"
                        class="w-full rounded-md border-gray-300 px-3 py-2 text-sm focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]" />
                    <span class="mt-1 block text-[11px] text-gray-400">{{ ctrans('Only visible to your team.') }}</span>
                </label>

                <div class="flex items-center justify-between gap-x-2 border-t border-gray-100 pt-3">
                    <button type="button"
                        class="flex h-8 items-center gap-x-1.5 rounded-md px-2.5 text-xs font-medium text-[var(--theme-color-4)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_8%,white)] disabled:opacity-50"
                        :disabled="isSuggesting" @click="suggestCopy">
                        <LoadingIcon v-if="isSuggesting" />
                        <FontAwesomeIcon v-else :icon="faSparkles" fixed-width aria-hidden="true" />
                        {{ ctrans('Suggest with AI') }}
                    </button>
                    <div class="flex items-center gap-x-2">
                        <button type="button" class="h-8 rounded-md border border-gray-300 px-3 text-xs text-gray-700 hover:bg-gray-50" @click="cancel(close)">
                            {{ ctrans('Cancel') }}
                        </button>
                        <button type="button"
                            class="flex h-8 items-center gap-x-1.5 rounded-md bg-[var(--theme-color-4)] px-4 text-xs font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)] disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!mailshotForm.subject || isSaving" @click="save(close)">
                            <LoadingIcon v-if="isSaving" />
                            {{ ctrans('Save') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </Popover>
</template>
