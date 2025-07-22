<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject } from "vue"

library.add(faCube, faLink, faImage)

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object
    indexBlock: number
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

</script>

<template>
    <div id="button">
        sfdgosfdjkpsjkf
    </div>
</template>
