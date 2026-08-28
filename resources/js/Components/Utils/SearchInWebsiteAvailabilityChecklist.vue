<script setup lang="ts">
import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { ctrans } from '@/Composables/useTrans'

const props = defineProps<{
    availability: {
        is_in_website: boolean
        checklist: {
            label: string
            passed: boolean
            detail: string | null
        }[]
    }
}>()

const allRulesPassed = computed(() => props.availability.checklist.every(check => check.passed))

// The search filters on the stored is_in_website flag, which is hydrated asynchronously,
// so it can briefly disagree with the live rules shown here
const isIndexOutdated = computed(() => allRulesPassed.value !== props.availability.is_in_website)
</script>

<template>
    <div>
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
            {{ ctrans('Search Availability') }}
            <InformationIcon :information="ctrans('Determines whether this product is shown in the website search results. It is shown only when all rules are met.')" />
        </div>

        <div
            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 mb-2 text-xs font-medium"
            :class="availability.is_in_website ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'"
        >
            <FontAwesomeIcon
                :icon="availability.is_in_website ? 'fas fa-check-circle' : 'fal fa-times-circle'"
                class="text-sm"
                fixed-width
            />
            {{ availability.is_in_website ? ctrans('Shown in website search') : ctrans('Not shown in website search') }}
        </div>

        <div class="space-y-1.5">
            <div
                v-for="(check, index) in availability.checklist"
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

        <div v-if="isIndexOutdated" class="mt-2 text-xs text-amber-600 leading-tight">
            {{ ctrans('The search index has not caught up with these rules yet') }}
        </div>
    </div>
</template>
