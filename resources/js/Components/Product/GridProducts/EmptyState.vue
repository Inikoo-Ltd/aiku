<template>
    <div 
        class="col-span-full flex flex-col items-center justify-center py-12 px-4"
        role="status"
        :aria-label="message"
    >
        <!-- Icon -->
        <div class="mb-4 animate-pulse">
            <FontAwesomeIcon 
                :icon="currentIcon" 
                class="text-gray-300 text-6xl"
                :aria-hidden="true"
            />
        </div>
        
        <!-- Main Message -->
        <h3 class="text-gray-500 text-lg font-medium mb-2 text-center">
            {{ message }}
        </h3>
        
        <!-- Description -->
        <p 
            v-if="description" 
            class="text-gray-400 text-sm text-center max-w-md"
        >
            {{ description }}
        </p>
        
        <!-- Action Slot -->
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * EmptyState Component
 * 
 * Displays a centered empty state message with icon and optional description.
 * Features:
 * - Customizable icon
 * - Main message and optional description
 * - Action slot for CTAs
 * - Accessibility support
 */

import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import type { IconProp } from '@fortawesome/fontawesome-svg-core'

// Props definition
interface Props {
    message: string
    description?: string
    icon?: string | IconProp
}

const props = withDefaults(defineProps<Props>(), {
    description: '',
    icon: 'fal fa-box-open'
})

// Computed properties
const currentIcon = computed<IconProp>(() => {
    return (props.icon as IconProp) || 'fal fa-box-open'
})
</script>