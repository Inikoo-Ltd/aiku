<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { inject } from "vue"
import Blueprint from "./Blueprint"
import { sendMessageToParent } from "@/Composables/Workshop"

library.add(faCube, faLink, faImage)

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object
    indexBlock: number
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []
</script>

<template>
    <div id="button">
        <div class="flex m-4" :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(modelValue.container?.properties, screenType)
        }" @click="() => {
            sendMessageToParent('activeBlock', indexBlock)
            sendMessageToParent('activeChildBlock', bKeys[1])
        }
        ">
            <Button :injectStyle="getStyles(modelValue?.button?.container?.properties, screenType)"
                :label="modelValue?.button?.text" @click.stop="() => {
                    sendMessageToParent('activeBlock', indexBlock)
                    sendMessageToParent('activeChildBlock', bKeys[0])
                }" />
        </div>
    </div>
</template>
