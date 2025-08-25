<script setup lang='ts'>
import { getIrisComponent } from '@/Composables/getIrisComponents'
import { Root } from '@/types/Website/Website/footer1'
import { provide } from 'vue';
import { useIrisLayoutStore } from "@/Stores/irisLayout"

const props = defineProps<{
    data: Root
    colorThemed: object
}>()
const layout = useIrisLayoutStore()
provide('layout', layout)

</script>

<template>
    <div>
        <component 
            :is="getIrisComponent(data.code)" 
            v-model="data.data.fieldValue" 
            :keyTemplate="data.code" 
            :previewMode="true"
            :colorThemed="colorThemed" 
            :language="layout?.iris?.locale"
        />
    </div>
</template>