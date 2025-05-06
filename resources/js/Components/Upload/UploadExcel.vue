<script setup lang='ts'>

import ModalUpload from '@/Components/Utils/ModalUpload.vue'
import ProgressBar from '@/Components/Utils/ProgressBar.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useEchoGrpPersonal } from '@/Stores/echo-grp-personal'
import { useEchoRetinaPersonal } from '@/Stores/echo-retina-personal'

import { Upload } from '@/types/Upload'
import { inject, provide } from 'vue'

const props = defineProps<{
    title: {
        label: string
        information: string
    }
    progressDescription: string
    upload_spreadsheet?: Upload
    scope?: string
    additionalDataToSend?: string[]
    preview_template?: {
        header: string[]
        rows: {}[]
    }
    propsRefreshAfterFinish?: string[]
}>()

const layout = inject('layout', layoutStructure)

const model = defineModel()

const selectedEchopersonal = () => {
    switch (layout.app.name){
        case 'retina':
            return useEchoRetinaPersonal()
        default:
            return useEchoGrpPersonal()
    }
}

provide('selectedEchopersonal', selectedEchopersonal())

</script>

<template>
    <!-- Modal: Upload -->
    <KeepAlive>
        <ModalUpload
            v-model="model"
            :scope
            :title        
            :upload_spreadsheet
            :additionalDataToSend
            :propsRefreshAfterFinish
            :preview_template
        />
    </KeepAlive>

    <ProgressBar
        :description="progressDescription"
    />

</template>
