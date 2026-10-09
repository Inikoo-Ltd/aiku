<script setup lang="ts">
import { ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faChevronDown } from '@fal'

library.add(faChevronDown)

defineProps<{
    title: string
}>()

const isCollapsed = ref(false)
</script>

<template>
    <section class="border-b border-gray-200">
        <button type="button" class="flex w-full items-center justify-between bg-gray-50 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-gray-700 hover:bg-gray-100"
            @click="isCollapsed = !isCollapsed">
            {{ title }}
            <FontAwesomeIcon icon="fal fa-chevron-down" class="transition-transform" :class="isCollapsed ? '-rotate-90' : ''" fixed-width aria-hidden="true" />
        </button>
        <div v-show="!isCollapsed" class="px-4 py-1">
            <slot />
        </div>
    </section>
</template>
