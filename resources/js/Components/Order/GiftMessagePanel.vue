<script setup lang="ts">
import { ref, computed } from 'vue'
import axios from 'axios'
import { debounce } from 'lodash-es'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { routeType } from '@/types/route'

const props = defineProps<{
    giftMessage: string | null
    hasGiftMessagePdf: boolean
    textRoute: routeType
    pdfRoute: routeType
}>()

const emit = defineEmits<{
    (e: 'uploaded'): void
}>()

const giftMessageMode = ref<'text' | 'pdf'>(props.hasGiftMessagePdf ? 'pdf' : 'text')
const giftMessageText = ref(props.giftMessage || '')
const isLoadingText = ref(false)
const isLoadingPdf = ref(false)

const isMissing = computed(() => !giftMessageText.value && !props.hasGiftMessagePdf)
defineExpose({ isMissing })

const onSubmitText = async () => {
    try {
        isLoadingText.value = true
        await axios.patch(route(props.textRoute.name, props.textRoute.parameters), {
            gift_message: giftMessageText.value
        })
    } catch {
        notify({
            title: ctrans("Something went wrong"),
            text: ctrans("Failed to save the gift message, try again."),
            type: "error",
        })
    } finally {
        isLoadingText.value = false
    }
}
const debounceSubmitText = debounce(() => onSubmitText(), 800)

const onUploadPdf = async (event: Event) => {
    const file = (event.target as HTMLInputElement)?.files?.[0]
    if (!file) {
        return
    }

    const formData = new FormData()
    formData.append('gift_message_pdf', file)

    try {
        isLoadingPdf.value = true
        await axios.post(route(props.pdfRoute.name, props.pdfRoute.parameters), formData)
        emit('uploaded')
    } catch {
        notify({
            title: ctrans("Something went wrong"),
            text: ctrans("Failed to upload the PDF, try again."),
            type: "error",
        })
    } finally {
        isLoadingPdf.value = false
    }
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex justify-end gap-x-4 text-sm">
            <label class="flex items-center gap-x-1 cursor-pointer">
                <input type="radio" value="text" v-model="giftMessageMode" />
                {{ ctrans('Write message') }}
            </label>
            <label class="flex items-center gap-x-1 cursor-pointer">
                <input type="radio" value="pdf" v-model="giftMessageMode" />
                {{ ctrans('Upload PDF') }}
            </label>
        </div>

        <div v-if="giftMessageMode === 'text'">
            <PureTextarea
                v-model="giftMessageText"
                @update:modelValue="() => debounceSubmitText()"
                @blur="() => onSubmitText()"
                :placeholder="ctrans('Write the gift message to print')"
                maxlength="500"
                rows="3"
                :loading="isLoadingText"
            />
            <div class="text-right text-xs text-gray-400">{{ giftMessageText?.length ?? 0 }} / 500</div>
        </div>

        <div v-else>
            <input type="file" accept="application/pdf" @change="onUploadPdf" />
            <LoadingIcon v-if="isLoadingPdf" xclass="text-sm text-gray-500" />
            <div v-if="hasGiftMessagePdf" class="text-xs text-green-600">{{ ctrans('PDF uploaded') }}</div>
        </div>

        <div v-if="isMissing" class="text-right text-xs text-red-500">
            {{ ctrans('Write a gift message or upload a PDF before checking out.') }}
        </div>
    </div>
</template>
