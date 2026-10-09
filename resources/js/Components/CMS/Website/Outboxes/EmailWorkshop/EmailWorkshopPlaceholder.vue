<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faImage, faVideo, faText, faCode, faShareAlt, faIcons, faPuzzlePiece, faCubes } from '@fal'
import { ctrans } from '@/Composables/useTrans'
import { ModulePlaceholder } from './emailWorkshopBlocks'

library.add(faImage, faVideo, faText, faCode, faShareAlt, faIcons, faPuzzlePiece, faCubes)

defineProps<{
    placeholder: ModulePlaceholder
}>()
</script>

<template>
    <div class="p-2.5">
        <div class="flex items-center justify-center gap-3 rounded-md border-2 border-dashed border-gray-300 bg-gray-50 text-center transition group-hover/module:border-[var(--theme-color-4)] group-hover/module:bg-[color-mix(in_srgb,var(--theme-color-4)_5%,white)]"
            :class="placeholder.size === 'large' ? 'h-40 flex-col' : 'h-16 flex-row'">
            <FontAwesomeIcon :icon="placeholder.icon" class="text-gray-400 group-hover/module:text-[var(--theme-color-4)]"
                :class="placeholder.size === 'large' ? 'text-4xl' : 'text-xl'" fixed-width aria-hidden="true" />
            <div :class="placeholder.size === 'large' ? '' : 'text-left'">
                <div class="text-sm font-medium text-gray-600">{{ ctrans(placeholder.title) }}</div>
                <div class="text-xs text-gray-400">{{ ctrans(placeholder.hint) }}</div>
            </div>
        </div>
    </div>
</template>
