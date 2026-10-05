<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationTriangle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ctrans } from '@/Composables/useTrans'
library.add(faExclamationTriangle)

defineProps<{
    large?: boolean
}>()

const host = typeof window !== 'undefined' ? window.location.hostname : ''
</script>

<template>
    <div v-if="large" class="z-30 top-0 left-0 w-full bg-red-600 text-white border-b-8 border-red-900 flex items-center justify-center gap-x-6 px-4 py-5 text-center">
        <FontAwesomeIcon icon='fas fa-exclamation-triangle' class='text-4xl hidden sm:block' fixed-width aria-hidden='true' />
        <div>
            <div class="text-3xl sm:text-5xl font-black tracking-wider uppercase">{{ ctrans('Staging environment') }}</div>
            <div class="mt-1 font-mono text-xl sm:text-2xl font-bold">{{ host }}</div>
            <div class="mt-1 text-sm sm:text-base">{{ ctrans('This is a test copy, reset every Sunday. Nothing you do here changes the live site.') }}</div>
        </div>
        <FontAwesomeIcon icon='fas fa-exclamation-triangle' class='text-4xl hidden sm:block' fixed-width aria-hidden='true' />
    </div>
    <div v-else class="z-30 top-0 left-0 w-full bg-red-500 text-white flex items-center justify-center gap-x-2 py-1">
        <FontAwesomeIcon icon='fas fa-exclamation-triangle' class='text-xs' fixed-width aria-hidden='true' />
        <slot>
            <span class="text-sm">
                Warning: You are currently in the staging environment.  Data can be delayed and overwritten at any time.
            </span>
        </slot>
        <FontAwesomeIcon icon='fas fa-exclamation-triangle' class='text-xs' fixed-width aria-hidden='true' />
    </div>
</template>
