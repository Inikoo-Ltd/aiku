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
import { ctrans } from "@/Composables/useTrans"

library.add(faFilePdf, faImage, faPlus, faTags, faTrashAlt)

interface ArtefactLabel {
    id: number
    name: string
    layout: Record<string, any>
    state: "raw" | "processed" | "published"
    state_label: string
    published_at: string | null
    pdf_url: string | null
    artwork: { name: string, size: number, mime_type: string, url: string } | null
    updated_at: string | null
}

const LABEL_STATE_CLASSES: Record<ArtefactLabel["state"], string> = {
    raw: "bg-gray-100 text-gray-600",
    processed: "bg-amber-50 text-amber-700",
    published: "bg-emerald-50 text-emerald-700",
}

interface ArtefactLabelSheet {
    route: { name: string, parameters: any }
    store_route: { name: string, parameters: any }
    update_route: { name: string, parameters: any }
    delete_route: { name: string, parameters: any }
    publish_route: { name: string, parameters: any }
    unpublish_route: { name: string, parameters: any }
    batch_code: string
    expiry_date: string
    barcode: string
    labels: ArtefactLabel[]
}

const props = defineProps<{
    data: ArtefactLabelSheet
}>()

const isOpenLabelSheet = ref(false)
const labelToEdit = ref<ArtefactLabel | null>(null)
const deletingLabelId = ref<number | null>(null)

const openLabel = (label: ArtefactLabel | null) => {
    labelToEdit.value = label
    isOpenLabelSheet.value = true
}

const onDeleteLabel = async (label: ArtefactLabel) => {
    deletingLabelId.value = label.id

    try {
        await axios.delete(route(props.data.delete_route.name, {
            ...props.data.delete_route.parameters,
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
</script>

<template>
    <div class="p-4">
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" aria-labelledby="artefact-labels-title">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 id="artefact-labels-title" class="text-sm font-semibold">{{ trans('Labels') }}</h2>
                <Button
                    type="tertiary"
                    size="xs"
                    icon="fal fa-plus"
                    :label="trans('New label')"
                    :aria-label="ctrans('Design a new label')"
                    aria-haspopup="dialog"
                    :aria-expanded="isOpenLabelSheet && !labelToEdit"
                    @click="openLabel(null)" />
            </div>

            <div
                v-if="!data.labels.length"
                class="rounded border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-500"
                role="status">
                {{ trans('No label designed yet. A saved label keeps its artwork, so it can be printed again any time.') }}
            </div>

            <ul v-else class="grid grid-cols-1 md:grid-cols-2 gap-2" :aria-label="ctrans('Saved labels')">
                <li
                    v-for="label in data.labels"
                    :key="label.id"
                    class="flex items-center gap-3 rounded border border-gray-200 px-3 py-2 cursor-pointer hover:bg-gray-50"
                    role="button"
                    tabindex="0"
                    aria-haspopup="dialog"
                    :aria-label="ctrans('Edit label :name, :state, :details', { name: label.name, state: label.state_label, details: describeLabel(label) })"
                    :aria-busy="deletingLabelId === label.id"
                    :data-label-id="label.id"
                    :data-label-state="label.state"
                    @click="openLabel(label)"
                    @keydown.enter.self.prevent="openLabel(label)"
                    @keydown.space.self.prevent="openLabel(label)">
                    <FontAwesomeIcon
                        :icon="label.artwork
                            ? (label.artwork.mime_type === 'application/pdf' ? 'fal fa-file-pdf' : 'fal fa-image')
                            : 'fal fa-tags'"
                        class="text-gray-400"
                        fixed-width
                        aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-sm">{{ label.name }}</span>
                            <span
                                class="shrink-0 rounded-full px-2 py-px text-[10px] uppercase tracking-wide"
                                :class="LABEL_STATE_CLASSES[label.state]"
                                :aria-label="ctrans('State: :state', { state: label.state_label })">{{ label.state_label }}</span>
                        </div>
                        <div class="truncate text-xs text-gray-500">{{ describeLabel(label) }}</div>
                    </div>
                    <div @click.stop @keydown.stop>
                        <ModalConfirmationDelete
                            :title="trans('Delete label :name?', { name: label.name })"
                            :description="trans('The label and its layout will no longer be available to print.')"
                            @onYes="onDeleteLabel(label)">
                            <template #default="{ changeModel }">
                                <button
                                    type="button"
                                    class="text-gray-400 hover:text-red-600 disabled:opacity-40"
                                    :aria-label="ctrans('Delete label :name', { name: label.name })"
                                    :title="ctrans('Delete label :name', { name: label.name })"
                                    aria-haspopup="dialog"
                                    :aria-busy="deletingLabelId === label.id"
                                    :disabled="deletingLabelId === label.id"
                                    @click="changeModel">
                                    <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                                </button>
                            </template>
                        </ModalConfirmationDelete>
                    </div>
                </li>
            </ul>
        </section>

        <ArtefactLabelSheetModal
            :isOpen="isOpenLabelSheet"
            :labelSheet="data"
            :labelToEdit="labelToEdit"
            @onClose="isOpenLabelSheet = false"
            @onSaved="router.reload()" />
    </div>
</template>
