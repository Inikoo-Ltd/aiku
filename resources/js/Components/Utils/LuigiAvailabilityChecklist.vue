<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { ctrans } from '@/Composables/useTrans'

defineProps<{
    checklist: {
        label: string
        passed: boolean
        detail: string | null
    }[]
}>()
</script>

<template>
    <div>
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
            {{ ctrans('Search Availability') }}
            <InformationIcon :information="ctrans('Determines whether this webpage is displayed in the website search results. It will be shown only when all rules are met.')" />
        </div>
        <div class="space-y-1.5">
            <div
                v-for="(check, index) in checklist"
                :key="index"
                class="flex items-start gap-2"
            >
                <FontAwesomeIcon
                    :icon="check.passed ? 'fas fa-check-circle' : 'fal fa-times-circle'"
                    :class="check.passed ? 'text-green-500' : 'text-red-500'"
                    class="mt-0.5 shrink-0 text-sm"
                    fixed-width
                />
                <div class="flex flex-col">
                    <span class="text-xs text-gray-700 leading-tight">{{ check.label }}</span>
                    <span v-if="!check.passed && check.detail" class="text-xs text-red-400 leading-tight">
                        {{ check.detail }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
