<script setup lang="ts">
import { getStyles } from '@/Composables/styles'
import { inject } from 'vue';

const props = defineProps<{
    fieldValue: {
        value: string
        container: {
            properties: {}
        }
    }
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()
const layout: any = inject("layout", {})
</script>

<template>
    <div id="text">
        <div :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(fieldValue.container?.properties, screenType)
        }">
            <div class="editor-class" v-html="fieldValue?.value"></div>
        </div>

    </div>
</template>
