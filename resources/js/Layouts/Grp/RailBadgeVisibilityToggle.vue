<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faEye, faEyeSlash } from "@fal"

defineProps<{
    hidden: boolean
}>()

const emit = defineEmits<{
    toggle: []
}>()
</script>

<template>
    <button
        type="button"
        v-tooltip="hidden ? ctrans('Hidden on the narrow bar, click to show it there') : ctrans('Hide on the narrow bar')"
        :aria-label="hidden ? ctrans('Show on the narrow bar') : ctrans('Hide on the narrow bar')"
        :aria-pressed="hidden"
        class="rail-eye absolute -bottom-1 -right-1 z-10 flex h-4 w-4 items-center justify-center rounded-full bg-white text-[8px] shadow ring-1 transition duration-200 hover:scale-110"
        :class="hidden ? 'text-gray-400 ring-gray-300' : 'text-gray-700 ring-gray-200'"
        @click.stop="emit('toggle')">
        <FontAwesomeIcon :icon="hidden ? faEyeSlash : faEye" fixed-width aria-hidden="true" />
    </button>
</template>
