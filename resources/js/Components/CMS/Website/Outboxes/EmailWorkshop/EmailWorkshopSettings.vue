<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import TiptapImageDialog from '@/Components/Forms/Fields/BubleTextEditor/TiptapImageDialog.vue'
import EmailWorkshopField from './EmailWorkshopField.vue'
import EmailWorkshopSection from './EmailWorkshopSection.vue'
import EmailWorkshopProperties from './EmailWorkshopProperties.vue'
import { ctrans } from '@/Composables/useTrans'
import { routeType } from '@/types/route'
import { EmailJson, MailshotMetadata } from './emailWorkshopBlocks'

const props = defineProps<{
    email: EmailJson
    mailshot?: MailshotMetadata | null
    updateMailshotRoute?: routeType
    imagesUploadRoute?: routeType
}>()

const emits = defineEmits<{
    (e: 'mailshotSaved', value: MailshotMetadata): void
}>()

const metadataForm = reactive<MailshotMetadata>({ subject: '', name: null, preview_text: null, ...props.mailshot })
const isSavingMetadata = ref(false)

watch(() => props.mailshot, (mailshot) => Object.assign(metadataForm, mailshot), { deep: true })

const saveMetadata = async () => {
    if (!props.updateMailshotRoute || !metadataForm.subject) {
        return
    }
    isSavingMetadata.value = true
    try {
        await axios.patch(route(props.updateMailshotRoute.name, props.updateMailshotRoute.parameters), metadataForm)
        emits('mailshotSaved', { ...metadataForm })
        notify({ title: ctrans('Success'), text: ctrans('Email metadata saved'), type: 'success' })
    } catch {
        notify({ title: ctrans('Something went wrong'), text: ctrans('Failed to save the email metadata'), type: 'error' })
    } finally {
        isSavingMetadata.value = false
    }
}

const isFaviconPickerOpen = ref(false)

const onFaviconPicked = (url: string) => {
    props.email.page.favicon = url
    isFaviconPickerOpen.value = false
}

const removeFavicon = () => {
    props.email.page.favicon = null
}
</script>

<template>
    <div class="text-sm">
        <EmailWorkshopSection v-if="mailshot && updateMailshotRoute" :title="ctrans('Email metadata')">
            <EmailWorkshopField v-model="metadataForm.subject" :label="ctrans('Subject')" />
            <EmailWorkshopField v-model="metadataForm.preview_text" :label="ctrans('Preview text')" />
            <EmailWorkshopField v-model="metadataForm.name" :label="ctrans('Internal name')" />
            <button type="button"
                class="my-2 w-full rounded bg-[var(--theme-color-4)] py-2 text-[13px] font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)] disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!metadataForm.subject || isSavingMetadata" @click="saveMetadata">
                {{ isSavingMetadata ? ctrans('Saving') + '…' : ctrans('Save metadata') }}
            </button>
        </EmailWorkshopSection>

        <EmailWorkshopSection :title="ctrans('Favicon')">
            <p class="pt-2 text-[12px] text-gray-500">{{ ctrans('Shown in the browser tab when the email is opened in a web browser.') }}</p>
            <div class="flex items-center gap-x-3 py-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded border border-dashed border-gray-300 bg-gray-50">
                    <img v-if="email.page.favicon" :src="email.page.favicon" :alt="ctrans('Favicon')" class="h-8 w-8 object-contain" />
                    <span v-else class="text-[10px] text-gray-400">{{ ctrans('None') }}</span>
                </div>
                <div class="flex flex-1 flex-col gap-y-1.5">
                    <button type="button"
                        class="rounded bg-[var(--theme-color-4)] py-1.5 text-[13px] font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]"
                        @click="isFaviconPickerOpen = true">
                        {{ email.page.favicon ? ctrans('Change favicon') : ctrans('Upload or browse favicon') }}
                    </button>
                    <button v-if="email.page.favicon" type="button" class="text-[12px] text-red-500 hover:text-red-700" @click="removeFavicon">
                        {{ ctrans('Remove favicon') }}
                    </button>
                </div>
            </div>
        </EmailWorkshopSection>

        <EmailWorkshopProperties :body="email.page.body" />

        <TiptapImageDialog v-if="isFaviconPickerOpen" :show="isFaviconPickerOpen" :uploadImageRoute="imagesUploadRoute"
            :imagesUploadedRoute="{ name: 'grp.gallery.uploaded-images.email.index' }"
            @insert="onFaviconPicked" @close="isFaviconPickerOpen = false" />
    </div>
</template>
