<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { ref } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFilePdf, faImage, faPlus, faTags, faTrashAlt } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ArtefactLabelSheetModal from "@/Components/Production/Artefact/ArtefactLabelSheetModal.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faFilePdf, faImage, faPlus, faTags, faTrashAlt)

interface ArtefactLabel {
    id: number
    name: string
    layout: Record<string, any>
    artwork: { name: string, size: number, mime_type: string, url: string } | null
    updated_at: string | null
}

interface ArtefactShowcaseData {
    code: string
    name: string
    state_label: string
    compliance_status: string
    compliance_label: string
    recommended_batch_size: number | null
    batch_pack: { packed_in: number, batch_in_skos: number, suggested_batch_size: number | null } | null
    update_route: { name: string, parameters: any }
    label_sheet?: {
        route: { name: string, parameters: any }
        store_route: { name: string, parameters: any }
        update_route: { name: string, parameters: any }
        delete_route: { name: string, parameters: any }
        batch_code: string
        expiry_date: string
        labels: ArtefactLabel[]
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
const labelToEdit = ref<ArtefactLabel | null>(null)
const deletingLabelId = ref<number | null>(null)

const openLabel = (label: ArtefactLabel | null) => {
    labelToEdit.value = label
    isOpenLabelSheet.value = true
}

const onDeleteLabel = async (label: ArtefactLabel) => {
    if (!props.data.label_sheet) return

    deletingLabelId.value = label.id

    try {
        await axios.delete(route(props.data.label_sheet.delete_route.name, {
            ...props.data.label_sheet.delete_route.parameters,
            label: label.id,
        }))
        router.reload()
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message ?? trans("The label could not be deleted"),
            type: "error",
        })
    } finally {
        deletingLabelId.value = null
    }
}

const describeLabel = (label: ArtefactLabel) => {
    const parts = [
        `${label.layout.columns ?? '?'} × ${label.layout.rows ?? '?'}`,
        label.layout.orientation === 'landscape' ? trans('Horizontal') : trans('Vertical'),
    ]

    if (label.artwork) {
        parts.push(label.artwork.mime_type === 'application/pdf' ? trans('PDF artwork') : trans('Image artwork'))
    }

    if (label.updated_at) {
        parts.push(useFormatTime(label.updated_at, { formatTime: 'aiku' }))
    }

    return parts.join(' · ')
}

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
                <span v-if="data.batch_pack" class="text-xs text-gray-500">
                    {{ trans('is') }} {{ data.batch_pack.batch_in_skos }} {{ trans('SKOs of') }} {{ data.batch_pack.packed_in }}
                    <template v-if="data.batch_pack.suggested_batch_size">
                        &middot; {{ trans('whole SKOs at') }} {{ data.batch_pack.suggested_batch_size }}
                    </template>
                </span>
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

            <hr class="my-6 border-t border-dashed border-gray-400" />

            <div class="" v-if="data.label_sheet">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold">{{ trans('Labels') }}</h3>
                    <Button
                        type="tertiary"
                        size="xs"
                        icon="fal fa-plus"
                        :label="trans('New label')"
                        @click="openLabel(null)" />
                </div>

                <div
                    v-if="!data.label_sheet.labels.length"
                    class="rounded border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-500">
                    {{ trans('No label designed yet. A saved label keeps its artwork, so it can be printed again any time.') }}
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div
                        v-for="label in data.label_sheet.labels"
                        :key="label.id"
                        class="flex items-center gap-3 rounded border border-gray-200 px-3 py-2 cursor-pointer hover:bg-gray-50"
                        @click="openLabel(label)">
                        <FontAwesomeIcon
                            :icon="label.artwork
                                ? (label.artwork.mime_type === 'application/pdf' ? 'fal fa-file-pdf' : 'fal fa-image')
                                : 'fal fa-tags'"
                            class="text-gray-400"
                            fixed-width
                            aria-hidden="true" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm">{{ label.name }}</div>
                            <div class="truncate text-xs text-gray-500">{{ describeLabel(label) }}</div>
                        </div>
                        <div @click.stop>
                            <ModalConfirmationDelete
                                :title="trans('Delete label :name?', { name: label.name })"
                                :description="trans('The label and its layout will no longer be available to print.')"
                                @onYes="onDeleteLabel(label)">
                                <template #default="{ changeModel }">
                                    <button
                                        class="text-gray-400 hover:text-red-600 disabled:opacity-40"
                                        :disabled="deletingLabelId === label.id"
                                        @click="changeModel">
                                        <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                                    </button>
                                </template>
                            </ModalConfirmationDelete>
                        </div>
                    </div>
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
            :labelToEdit="labelToEdit"
            @onClose="isOpenLabelSheet = false"
            @onSaved="router.reload()" />
    </div>
</template>
