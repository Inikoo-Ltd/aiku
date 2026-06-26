<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 24 Jul 2023 12:11:04 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { useRemoveHttps } from '@/Composables/useRemoveHttps'
import { ref, onBeforeUnmount } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSpinnerThird } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faSpinnerThird)

defineProps<{
    data: {
        text?: string
        target: string
    }
    swiperRef?: Element
}>()

const isLoading = ref(false)
let loadingTimeout: ReturnType<typeof setTimeout> | null = null

const resetLoading = () => {
    isLoading.value = false
    if (loadingTimeout) {
        clearTimeout(loadingTimeout)
        loadingTimeout = null
    }
}

const handleClick = () => {
    isLoading.value = true
    if (loadingTimeout) clearTimeout(loadingTimeout)
    loadingTimeout = setTimeout(() => {
        isLoading.value = false
        loadingTimeout = null
    }, 1800)
}

onBeforeUnmount(() => {
    if (loadingTimeout) clearTimeout(loadingTimeout)
})

</script>

<template>
    <a
        :href="`https://${useRemoveHttps(data?.target)}`"
        target="_top"
        :style="`background : ${data?.button_color}; color: ${data?.text_color};`"
        class="relative inline-flex items-center justify-center gap-x-2 border border-gray-50/50 rounded-md px-3 py-1 hover:bg-gray-900/60 whitespace-nowrap"
        :class="{ 'pointer-events-none opacity-80': isLoading }"
        @click="handleClick"
        @dragstart="resetLoading"
    >
        <FontAwesomeIcon
            v-if="isLoading"
            icon="fas fa-spinner-third"
            spin
            fixed-width
            aria-hidden="true"
        />
        {{ data?.text?.length == 0 ? trans('Open') : data?.text }}
    </a>
</template>
