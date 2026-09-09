<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { ref } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ArtefactLabelSheetModal from "@/Components/Production/Artefact/ArtefactLabelSheetModal.vue"

interface ArtefactShowcaseData {
    code: string
    name: string
    state_label: string
    compliance_status: string
    compliance_label: string
    recommended_batch_size: number | null
    update_route: { name: string, parameters: any }
    label_sheet?: {
        route: { name: string, parameters: any }
        batch_code: string
        expiry_date: string
    }
    artefact_department: { slug: string, name: string } | null
    tags: string[]
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

const props = defineProps<{
    data: ArtefactShowcaseData
}>()

const batchSize = ref<number | string>(props.data.recommended_batch_size ?? '')
const isOpenLabelSheet = ref(false)
const isSavingBatchSize = ref(false)

const onSaveBatchSize = async () => {
    if (!batchSize.value || Number(batchSize.value) < 1) return

    try {
        isSavingBatchSize.value = true
        await axios.patch(
            route(props.data.update_route.name, props.data.update_route.parameters),
            { recommended_batch_size: Number(batchSize.value) }
        )
        notify({ title: trans("Updated"), text: trans("Recommended batch size saved"), type: "success" })
        router.reload()
    } catch (error: any) {
        notify({ title: trans("Something went wrong"), text: error.message, type: "error" })
    } finally {
        isSavingBatchSize.value = false
    }
}
</script>

<template>
    <div class="p-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ data.name }}</h2>
                    <p class="text-sm text-gray-500 mb-2">{{ data.code }}</p>
                </div>
                <Button
                    v-if="data.label_sheet"
                    type="tertiary"
                    icon="fal fa-file-pdf"
                    :label="trans('Add label')"
                    @click="isOpenLabelSheet = true" />
            </div>
            <span class="inline-block text-xs px-2 py-1 rounded border mb-6" :class="{
                'bg-gray-100 text-gray-600 border-gray-200': data.compliance_status === 'not_configured',
                'bg-green-50 text-green-700 border-green-200': data.compliance_status === 'ok',
                'bg-amber-50 text-amber-700 border-amber-200': data.compliance_status === 'expiring',
                'bg-red-50 text-red-700 border-red-200': data.compliance_status === 'problem',
            }">{{ data.compliance_label }}</span>

            <div v-if="data.recommended_batch_size" class="mb-6 flex items-baseline gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <span class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Recommended batch size') }}</span>
                <span class="text-2xl font-semibold leading-none">{{ data.recommended_batch_size }}</span>
            </div>
            <div v-else class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                <div class="text-sm font-semibold text-red-700">{{ trans('No recommended batch size') }}</div>
                <div class="text-xs text-red-600 mb-2">{{ trans('Set how many units are usually made in one go, so job orders can be prefilled.') }}</div>
                <div class="flex items-center gap-2">
                    <input
                        v-model="batchSize"
                        type="number"
                        min="1"
                        class="w-32 rounded border border-red-200 px-2 py-1 text-sm"
                        :placeholder="trans('Units')"
                        @keyup.enter="onSaveBatchSize" />
                    <Button
                        type="save"
                        :label="trans('Save')"
                        :loading="isSavingBatchSize"
                        :disabled="!batchSize"
                        @click="onSaveBatchSize" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('State') }}</div>
                    <div class="text-sm">{{ data.state_label }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Family') }}</div>
                    <div class="text-sm">{{ data.artefact_department?.name || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Tags') }}</div>
                    <div class="text-sm flex flex-wrap gap-1">
                        <span v-for="tag in data.tags" :key="tag" class="px-1.5 py-0.5 rounded bg-gray-100 text-xs">#{{ tag }}</span>
                        <span v-if="!data.tags?.length">-</span>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Trade unit') }}</div>
                    <div class="text-sm">{{ data.trade_unit ? `${data.trade_unit.code} - ${data.trade_unit.name}` : '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Stock (SKU)') }}</div>
                    <div class="text-sm">{{ data.org_stock ? data.org_stock.code : '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Quantity in locations') }}</div>
                    <div class="text-sm">{{ data.org_stock ? data.org_stock.quantity_in_locations : '-' }}</div>
                </div>
            </div>

            <div class="mt-8" v-if="data.manufacture_tasks.length">
                <h3 class="text-sm font-semibold mb-3">{{ trans('Recipe steps') }}</h3>
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
                            <td class="py-2">{{ task.position }}</td>
                            <td class="py-2">{{ task.code }} - {{ task.name }}</td>
                            <td class="py-2">{{ task.units_per_artefact }}</td>
                            <td class="py-2">{{ task.task_work_cost }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ArtefactLabelSheetModal
            v-if="data.label_sheet"
            :isOpen="isOpenLabelSheet"
            :labelSheet="data.label_sheet"
            @onClose="isOpenLabelSheet = false" />
    </div>
</template>
