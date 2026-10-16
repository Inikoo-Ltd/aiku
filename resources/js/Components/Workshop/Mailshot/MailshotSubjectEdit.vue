<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPencil, faEnvelope, faTimes, faCheck } from '@fal'
import { faSparkles } from '@fas'
import { Popover, InputText } from 'primevue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'

const props = defineProps<{
    mailshot: { subject: string, name: string | null, preview_text: string | null, sender_name?: string | null }
    updateMailshotRoute: routeType
    suggestCopyRoute: routeType
}>()

const emits = defineEmits<{
    (e: 'saved', subject: string, mailshot: { subject: string, name: string | null, preview_text: string | null, sender_name?: string | null }): void
}>()

const SUBJECT_RECOMMENDED_LENGTH = 60
const PREVIEW_TEXT_RECOMMENDED_LENGTH = 90

const mailshotForm = reactive({ ...props.mailshot })

watch(() => props.mailshot, (mailshot) => Object.assign(mailshotForm, mailshot), { deep: true })

const detailsPopover = ref<InstanceType<typeof Popover> | null>(null)
const isSaving = ref(false)
const isSuggesting = ref(false)

const counterClass = (value: string | null, recommendedLength: number) =>
    (value?.length ?? 0) > recommendedLength ? 'text-amber-600' : 'text-gray-400'

const close = () => detailsPopover.value?.hide()

const cancel = () => {
    Object.assign(mailshotForm, props.mailshot)
    close()
}

const save = async () => {
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
    <span class="ml-1 inline-flex">
        <Button type="tertiary" size="xs" :icon="faPencil" :label="ctrans('Edit details')"
            :tooltip="ctrans('Edit subject, preview text and name')" @click="detailsPopover?.toggle($event)" />

        <Popover ref="detailsPopover" appendTo="body">
            <div class="w-[28rem] max-w-[calc(100vw-2rem)] space-y-4 text-left text-sm font-normal">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">{{ ctrans('Email details') }}</h3>
                    <p class="text-xs text-gray-500">{{ ctrans('What recipients see in their inbox before opening the email.') }}</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-gray-400">{{ ctrans('Inbox preview') }}</div>
                    <div class="flex items-start gap-x-3 rounded-md bg-white p-2.5 shadow-sm">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[--app-accent-soft] text-[--app-accent]">
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
                    <InputText v-model="mailshotForm.subject" :placeholder="ctrans('Email subject')" class="w-full !text-sm" />
                    <span class="mt-1 block text-[11px] text-gray-400">{{ ctrans('Keep it under 60 characters so it is not cut off on mobile.') }}</span>
                </label>

                <label class="block">
                    <span class="mb-1 flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-700">{{ ctrans('Preview text') }}</span>
                        <span class="text-[11px]" :class="counterClass(mailshotForm.preview_text, PREVIEW_TEXT_RECOMMENDED_LENGTH)">
                            {{ mailshotForm.preview_text?.length ?? 0 }}/{{ PREVIEW_TEXT_RECOMMENDED_LENGTH }}
                        </span>
                    </span>
                    <InputText v-model="mailshotForm.preview_text" :placeholder="ctrans('Email preview text')" class="w-full !text-sm" />
                    <span class="mt-1 block text-[11px] text-gray-400">{{ ctrans('Shown after the subject in most inboxes.') }}</span>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans('Sender name') }}</span>
                    <InputText v-model="mailshotForm.sender_name" :placeholder="ctrans('Defaults to the shop name')" class="w-full !text-sm" />
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans('Internal name') }}</span>
                    <InputText v-model="mailshotForm.name" :placeholder="ctrans('Internal name')" class="w-full !text-sm" />
                    <span class="mt-1 block text-[11px] text-gray-400">{{ ctrans('Only visible to your team.') }}</span>
                </label>

                <div class="flex items-center justify-between gap-x-2 border-t border-gray-100 pt-3">
                    <Button type="tertiary" size="xs" :label="ctrans('Suggest with AI')" :loading="isSuggesting" @click="suggestCopy">
                        <template #icon>
                            <FontAwesomeIcon :icon="faSparkles" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <div class="flex items-center gap-x-2">
                        <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="cancel">
                            <template #icon>
                                <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button type="tertiary" size="xs" :label="ctrans('Save')" :loading="isSaving"
                            :disabled="!mailshotForm.subject" @click="save">
                            <template #icon>
                                <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                </div>
            </div>
        </Popover>
    </span>
</template>
