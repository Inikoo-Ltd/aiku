<script setup lang="ts">
import { trans } from "laravel-vue-i18n"

interface ManufactureTaskShowcaseData {
    slug: string
    code: string
    name: string
    task_work_cost: number | string
    task_materials_cost: number | string
    task_energy_cost: number | string
    task_other_cost: number | string
    task_lower_target: number | string
    task_upper_target: number | string
    operative_reward_terms: string
    operative_reward_allowance_type: string
    operative_reward_amount: number | string
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Task work cost') }}</div>
                    <div class="text-sm text-gray-900">{{ data.task_work_cost }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Task materials cost') }}</div>
                    <div class="text-sm text-gray-900">{{ data.task_materials_cost }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Task energy cost') }}</div>
                    <div class="text-sm text-gray-900">{{ data.task_energy_cost }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Task other cost') }}</div>
                    <div class="text-sm text-gray-900">{{ data.task_other_cost }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Task lower target') }}</div>
                    <div class="text-sm text-gray-900">{{ data.task_lower_target }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Task upper target') }}</div>
                    <div class="text-sm text-gray-900">{{ data.task_upper_target }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Operative reward terms') }}</div>
                    <div class="text-sm text-gray-900">{{ data.operative_reward_terms }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Operative reward allowance type') }}</div>
                    <div class="text-sm text-gray-900">{{ data.operative_reward_allowance_type }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Operative reward amount') }}</div>
                    <div class="text-sm text-gray-900">{{ data.operative_reward_amount }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
