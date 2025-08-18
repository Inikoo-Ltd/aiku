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
    <div class="inline-flex xleading-none group">
        <Transition name="spin-to-right">
            <FontAwesomeIcon v-if="isRecentlyCopied" icon='fal fa-check' class='text-green-500 h-full text-xxs xleading-none ' fixed-width aria-hidden='true' />
            <FontAwesomeIcon
                v-else
                @click="() => onClickCopyButton(text)"
                icon="fal fa-copy"
                class="h-full text-xxs xleading-none opacity-50 group-hover:opacity-100 group-active:opacity-100 cursor-pointer"
                fixed-width
                aria-hidden="true"
            />
        </Transition>
    </div>
</template>