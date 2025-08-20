<script setup lang='ts'>
import { useCopyText } from '@/Composables/useCopyText'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCopy } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref } from 'vue'
library.add(faCopy)

const props = defineProps<{
    text: string | number
}>()

const isRecentlyCopied = ref(false)
const onClickCopyButton = async (text: string | number) => {
    useCopyText(text)
    isRecentlyCopied.value = true
    setTimeout(() => {
        isRecentlyCopied.value = false
    }, 3000)
}
</script>

<template>
    <div
        @click="() => isRecentlyCopied ? '' : onClickCopyButton(text)"
        class="text-xxs inline-flex xleading-none group"
        :class="isRecentlyCopied ? '' : 'cursor-pointer'"
    >
        <Transition name="spin-to-right">
            <FontAwesomeIcon v-if="isRecentlyCopied" icon='fal fa-check' class='text-green-500 h-full xleading-none ' fixed-width aria-hidden='true' />
            <FontAwesomeIcon
                v-else
                icon="fal fa-copy"
                class="h-full xleading-none opacity-50 group-hover:opacity-100 group-active:opacity-100 cursor-pointer"
                fixed-width
                aria-hidden="true"
            />
        </Transition>
    </div>
</template>