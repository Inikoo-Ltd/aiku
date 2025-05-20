<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink, faImage)

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

</script>

<template>
    <div class="flex" :style="getStyles(modelValue?.container?.properties,screenType)">
        <div typeof="button" :style="getStyles(modelValue?.button?.container?.properties,screenType)" class="flex items-center justify-center">
            {{ modelValue?.button?.text }}
        </div>
    </div>
</template>
