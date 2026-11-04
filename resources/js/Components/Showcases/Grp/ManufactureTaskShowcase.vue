<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import ArtisanAssignments from "@/Components/Production/ArtisanAssignments.vue"

interface ManufactureTaskShowcaseData {
    slug: string
    code: string
    name: string
    description: string | null
    status: boolean
    is_piece_rate: boolean
    pays_by_speed: boolean
    is_recorded_in_kg: boolean
    is_quantity_only: boolean
    artisans: {
        current: { id: number, name: string }[]
        options: { id: number, name: string }[]
        attach_route: { name: string, parameters: (number | string)[] } | null
        detach_route: { name: string, parameters: (number | string)[] } | null
    }
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
                <div v-if="data.pays_by_speed">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans('Paid by piece rate') }}</div>
                    <div class="text-sm text-gray-900">{{ data.is_piece_rate ? ctrans('Yes') : ctrans('No, paid by the hour') }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans('Quantity recorded in') }}</div>
                    <div class="text-sm text-gray-900">{{ data.is_recorded_in_kg ? ctrans('kg') : ctrans('Units') }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ ctrans('On the floor') }}</div>
                    <div class="text-sm text-gray-900">{{ data.is_quantity_only ? ctrans('Quantity only, no timer') : ctrans('Start and stop timer') }}</div>
                </div>
            </div>

            <ArtisanAssignments
                :data="data.artisans"
                :label="ctrans('Only these artisans can do it')"
                :empty="ctrans('anyone')"
                plain
                class="!mx-0 mt-6" />

            <p class="text-xs text-gray-500 mt-6">{{ ctrans('Targets and rates are set on each product\'s recipe step.') }}</p>
        </div>
    </div>
</template>
