<script setup lang='ts'>
import { getIrisComponent } from '@/Composables/getIrisComponents'

import { Root } from '@/types/Website/Website/footer1'
import { checkScreenType } from '@/Composables/useWindowSize'
import { onMounted, ref } from 'vue'


const props = defineProps<{
    data: Root
    colorThemed: object
}>()
const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')

onMounted(() => {
    screenType.value = checkScreenType()
})
</script>

<template>
    <div>
        <component 
            :is="getIrisComponent(data.code)" 
            v-model="data.data.fieldValue" 
            :keyTemplate="data.code"
            :screenType 
            :previewMode="true"
            :colorThemed="colorThemed" 
        />
    </div>
</template>