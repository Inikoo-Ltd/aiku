<script setup lang='ts'>
import { MenuButton } from "@headlessui/vue"
import { useTruncate } from '@/Composables/useTruncate'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPalletAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faPalletAlt)

const props = defineProps<{
    activeButton?: boolean
    icon: string | string[]
    label?: string
}>()

</script>

<template>
    <MenuButton v-slot="{ open }"
        class="inline-flex sm:min-w-32 max-w-full whitespace-nowrap justify-between items-center gap-x-2 px-2.5 py-1.5 text-xs font-medium focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/75"
        :class="[activeButton ? 'bg-slate-200 text-slate-600 hover:bg-slate-300' : 'hover:bg-slate-200 text-slate-600']">
        <div class="flex items-center gap-x-1">
            <FontAwesomeIcon v-if="icon" :icon='icon' class='opacity-60 text-xs' fixed-width aria-hidden='true' />
            <span class="hidden sm:inline">{{ useTruncate(label || '', 20) }}</span>
        </div>
        <FontAwesomeIcon icon='far fa-chevron-down' class='text-xs transition-all duration-200 ease-in-out' :class="[open ? 'rotate-180' : '']" aria-hidden='true' />
    </MenuButton>
</template>