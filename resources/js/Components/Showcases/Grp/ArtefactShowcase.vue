<script setup lang="ts">
import { trans } from "laravel-vue-i18n"

interface ArtefactShowcaseData {
    code: string
    name: string
    state: string
    compliance_status: string
    compliance_label: string
    trade_unit: { id: number, code: string, name: string } | null
    org_stock: { id: number, code: string, quantity_in_locations: number | string } | null
    manufacture_tasks: {
        id: number
        code: string
        name: string
        position: number
        units_per_artefact: number | string
        task_work_cost: number | string
    }[]
}

defineProps<{
    data: ArtefactShowcaseData
}>()
</script>

<template>
    <div class="p-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900">{{ data.name }}</h2>
            <p class="text-sm text-gray-500 mb-2">{{ data.code }}</p>
            <span class="inline-block text-xs px-2 py-1 rounded border mb-6" :class="{
                'bg-gray-100 text-gray-600 border-gray-200': data.compliance_status === 'not_configured',
                'bg-green-50 text-green-700 border-green-200': data.compliance_status === 'ok',
                'bg-amber-50 text-amber-700 border-amber-200': data.compliance_status === 'expiring',
                'bg-red-50 text-red-700 border-red-200': data.compliance_status === 'problem',
            }">{{ data.compliance_label }}</span>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('State') }}</div>
                    <div class="text-sm text-gray-900">{{ data.state }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Trade unit') }}</div>
                    <div class="text-sm text-gray-900">{{ data.trade_unit ? `${data.trade_unit.code} - ${data.trade_unit.name}` : '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Stock (SKU)') }}</div>
                    <div class="text-sm text-gray-900">{{ data.org_stock ? data.org_stock.code : '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Quantity in locations') }}</div>
                    <div class="text-sm text-gray-900">{{ data.org_stock ? data.org_stock.quantity_in_locations : '-' }}</div>
                </div>
            </div>

            <div class="mt-8" v-if="data.manufacture_tasks.length">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ trans('Recipe steps') }}</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="pb-2">{{ trans('Position') }}</th>
                            <th class="pb-2">{{ trans('Task') }}</th>
                            <th class="pb-2">{{ trans('Units per artefact') }}</th>
                            <th class="pb-2">{{ trans('Work cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in data.manufacture_tasks" :key="task.id" class="border-t border-gray-100">
                            <td class="py-2 text-gray-900">{{ task.position }}</td>
                            <td class="py-2 text-gray-900">{{ task.code }} - {{ task.name }}</td>
                            <td class="py-2 text-gray-900">{{ task.units_per_artefact }}</td>
                            <td class="py-2 text-gray-900">{{ task.task_work_cost }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
