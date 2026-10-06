<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"

interface ManufactureTaskShowcaseData {
    slug: string
    code: string
    name: string
    description: string | null
    status: boolean
    is_piece_rate: boolean
}

defineProps<{
    data: ManufactureTaskShowcaseData
}>()
</script>

<template>
    <div class="p-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900">{{ data.name }}</h2>
            <p class="text-sm text-gray-500 mb-6">{{ data.code }}</p>

            <p v-if="data.description" class="text-sm text-gray-700 whitespace-pre-line mb-6">{{ data.description }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans('Status') }}</div>
                    <div class="text-sm text-gray-900">{{ data.status ? ctrans('Active') : ctrans('Inactive') }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans('Paid by piece rate') }}</div>
                    <div class="text-sm text-gray-900">{{ data.is_piece_rate ? ctrans('Yes') : ctrans('No') }}</div>
                </div>
            </div>

            <p class="text-xs text-gray-500 mt-6">{{ ctrans('Targets and rates are set on each product\'s recipe step.') }}</p>
        </div>
    </div>
</template>
