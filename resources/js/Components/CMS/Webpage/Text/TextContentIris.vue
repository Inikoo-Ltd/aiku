<script setup lang="ts">
import { getStyles } from '@/Composables/styles'

const props = defineProps<{
	fieldValue: {
        value: string
        container: {
            properties: {}
        }
    }
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

</script>

<template>
    <div id="blockTextContent" :style="getStyles(fieldValue?.container?.properties,screenType)">
        <div class="editor-class" v-html="fieldValue?.value"></div>
    </div>
</template>
