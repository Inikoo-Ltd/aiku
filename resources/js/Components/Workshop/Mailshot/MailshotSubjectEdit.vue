<script setup lang="ts">
import { reactive, ref } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPencil } from '@fal'
import { faSparkles } from '@fas'
import Popover from '@/Components/Popover.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'

const props = defineProps<{
    mailshot: { subject: string, name: string | null, preview_text: string | null }
    updateMailshotRoute: routeType
    suggestCopyRoute: routeType
}>()

const emits = defineEmits<{
    (e: 'saved', subject: string): void
}>()

const mailshotForm = reactive({ ...props.mailshot })
const isSaving = ref(false)
const isSuggesting = ref(false)

const save = async (close: () => void) => {
    isSaving.value = true
    try {
        await axios.patch(route(props.updateMailshotRoute.name, props.updateMailshotRoute.parameters), mailshotForm)
        emits('saved', mailshotForm.subject)
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
        if (data.preview_text) mailshotForm.preview_text = data.preview_text
        if (data.name) mailshotForm.name = data.name
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
    <Popover position="left-0" width="w-96">
        <template #button>
            <FontAwesomeIcon :icon="faPencil" v-tooltip="ctrans('Edit subject')"
                class="text-gray-400 hover:text-gray-600 text-sm cursor-pointer" fixed-width />
        </template>
        <template #content="{ close }">
            <div class="space-y-2 text-left font-normal text-base">
                <div>
                    <label class="text-xs text-gray-500">{{ ctrans('Subject') }}</label>
                    <PureInput v-model="mailshotForm.subject" :placeholder="ctrans('Email subject')" />
                </div>
                <div>
                    <label class="text-xs text-gray-500">{{ ctrans('Preview text') }}</label>
                    <PureInput v-model="mailshotForm.preview_text" :placeholder="ctrans('Email preview text')" />
                </div>
                <div>
                    <label class="text-xs text-gray-500">{{ ctrans('Name') }}</label>
                    <PureInput v-model="mailshotForm.name" :placeholder="ctrans('Internal name')" />
                </div>
                <div class="flex justify-between gap-x-2 pt-1">
                    <Button type="tertiary" size="xs" :icon="faSparkles" :label="ctrans('Suggest with AI')"
                        :loading="isSuggesting" @click="suggestCopy" />
                    <Button size="xs" :label="ctrans('Save')" :loading="isSaving"
                        :disabled="!mailshotForm.subject" @click="() => save(close)" />
                </div>
            </div>
        </template>
    </Popover>
</template>
