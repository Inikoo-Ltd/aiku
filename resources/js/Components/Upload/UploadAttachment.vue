<script setup lang='ts'>


import { Upload } from '@/types/Upload'
import { defineAsyncComponent } from 'vue'

const ModalUploadAttachment = defineAsyncComponent(() => import('../Utils/ModalUploadAttachment.vue'))

const props = defineProps<{
    title: {
        label: string
        information: string
    }
    scope?: string
    additionalDataToSend?: string[]
    attachmentRoutes?: object
    options?: {
		name: string
		code: string
	}[]
    withCaption?: boolean
    multiple?: boolean
}>()


const model = defineModel()

const emits = defineEmits<{
    (e: 'onCloseModal', value: boolean): void
}>()

</script>

<template>
    <KeepAlive>
        <ModalUploadAttachment v-model="model" :scope :title :additionalDataToSend :attachmentRoutes :options :withCaption :multiple />
    </KeepAlive>

</template>
