<!--
    * Author: Vika Aqordi
    * Created on: 2026-05-12 11:44
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { notify } from "@kyvg/vue3-notification"
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks } from "@fal"
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import { ref, inject } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { trans } from 'laravel-vue-i18n'
import { Dialog } from 'primevue'
import axios from 'axios'
import { debounce } from 'lodash-es'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Image from '@common/Components/Image.vue'

const screenType = inject('screenType', ref('desktop'))

library.add(faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks)

interface UnidentifiedReturnItem {
    id: number
    warehouse_slug: string
    notes: string | null
    image: {} | null
    created_at: string
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data?: {}
    shopType: string
    warehouseId?: number | null
    unidentifiedReturns?: { data: UnidentifiedReturnItem[] } | null
}>()

const layout = inject("layout", layoutStructure)
const loading = ref(false)

const isOpenModalCreateReturn = ref(false)

interface DeliveryNoteOption {
    id: number
    label: string
    reference: string
    customer_name: string
    customer_reference: string
    tracking_number: string
    date: string
}

const selectedDeliveryNote = ref<DeliveryNoteOption | null>(null)
const isSubmitting = ref(false)

const searchQuery = ref('')
const deliveryNoteOptions = ref<DeliveryNoteOption[]>([])
const nextPageUrl = ref<string | null>(null)
const isLoadingOptions = ref(false)
const isLoadingMore = ref(false)
let fetchSequence = 0

const fetchDeliveryNotes = async () => {
    const sequence = ++fetchSequence
    isLoadingOptions.value = true
    try {
        const res = await axios.get(route('grp.json.delivery_note_valid_for_return', {
            warehouse: identifyingReturn.value?.warehouse_slug ?? (route().params as Record<string, string>)['warehouse'],
            'filter[global]': searchQuery.value || undefined,
        }))
        if (sequence !== fetchSequence) return
        deliveryNoteOptions.value = res.data?.data ?? []
        nextPageUrl.value = res.data?.links?.next ?? null
    } catch (e) {
        if (sequence !== fetchSequence) return
        deliveryNoteOptions.value = []
        nextPageUrl.value = null
    } finally {
        if (sequence === fetchSequence) isLoadingOptions.value = false
    }
}

const onSearchInput = debounce(fetchDeliveryNotes, 400)

const onLoadMore = async () => {
    if (!nextPageUrl.value || isLoadingMore.value) return
    const sequence = fetchSequence
    isLoadingMore.value = true
    try {
        const res = await axios.get(nextPageUrl.value)
        if (sequence !== fetchSequence) return
        deliveryNoteOptions.value = [...deliveryNoteOptions.value, ...(res.data?.data ?? [])]
        nextPageUrl.value = res.data?.links?.next ?? null
    } finally {
        if (sequence === fetchSequence) isLoadingMore.value = false
    }
}

const modalMode = ref<'search' | 'log'>('search')
const identifyingReturn = ref<UnidentifiedReturnItem | null>(null)

const boxNotes = ref('')
const boxImage = ref<File | null>(null)
const isSavingUnidentified = ref(false)

const onBoxImageChange = (event: Event) => {
    boxImage.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

const onOpenModal = (unidentifiedReturn: UnidentifiedReturnItem | null = null) => {
    selectedDeliveryNote.value = null
    searchQuery.value = ''
    deliveryNoteOptions.value = []
    nextPageUrl.value = null
    modalMode.value = 'search'
    identifyingReturn.value = unidentifiedReturn
    boxNotes.value = ''
    boxImage.value = null
    isOpenModalCreateReturn.value = true
    fetchDeliveryNotes()
}

const onCreateReturn = () => {
    if (!selectedDeliveryNote.value) return

    isSubmitting.value = true
    router.patch(
        route('grp.models.delivery_note.return.process', {
            deliveryNote: selectedDeliveryNote.value.id
        }),
        identifyingReturn.value ? { unidentified_return_id: identifyingReturn.value.id } : {},
        {
            onError: () => {
                isSubmitting.value = false
            },
            onFinish: () => {
                isSubmitting.value = false
            },
        }
    )
}

const onSaveUnidentifiedReturn = () => {
    if (!boxNotes.value && !boxImage.value) return

    isSavingUnidentified.value = true
    router.post(
        route('grp.models.warehouse.unidentified_return.store', { warehouse: props.warehouseId }),
        {
            notes: boxNotes.value || null,
            image: boxImage.value,
        },
        {
            forceFormData: true,
            onSuccess: () => {
                isOpenModalCreateReturn.value = false
            },
            onFinish: () => {
                isSavingUnidentified.value = false
            },
        }
    )
}

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
        </template>
        <template #button-create-return="{ action }">
            <Button :type="action.type" :style="action.style" :label="action.label" :icon="action.icon"
            @click="() => onOpenModal()" />
        </template>
    </PageHeading>
    <div v-if="unidentifiedReturns?.data?.length" class="px-4 py-3">
        <div class="mb-2 text-base font-medium">{{ trans("Returns to identify") }}</div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="ur in unidentifiedReturns.data" :key="ur.id" class="rounded-md border border-amber-300 bg-amber-50 p-3">
                <Image v-if="ur.image" :src="ur.image" class="mb-2 h-32 w-full rounded object-cover" imageCover />
                <div v-if="ur.notes" class="text-sm text-gray-700 whitespace-pre-line">{{ ur.notes }}</div>
                <div class="mt-1 text-sm text-gray-500">{{ ur.created_at }}</div>
                <Button class="mt-2" type="secondary" :label="trans('Identify')" icon="fal fa-search" full
                    @click="() => onOpenModal(ur)" />
            </div>
        </div>
    </div>

    <TableDeliveryNotes :data="data" />

    <Dialog v-model:visible="isOpenModalCreateReturn" modal closable :dismissableMask="screenType === 'desktop'" :showHeader="false"
        :style="{ width: '56rem', maxWidth: '95vw' }" @hide="() => {
            isOpenModalCreateReturn = false
        }">
        <div class="pt-4 pb-2 text-lg font-semibold">
            {{ modalMode === 'log' ? trans("Log return to identify") : identifyingReturn ? trans("Identify return") : trans("Receive Return") }}
        </div>

        <div v-if="identifyingReturn" class="mb-4 flex gap-x-3 rounded-md border border-amber-300 bg-amber-50 p-3">
            <Image v-if="identifyingReturn.image" :src="identifyingReturn.image" class="h-24 w-24 shrink-0 rounded object-cover" imageCover />
            <div class="min-w-0 text-sm">
                <div v-if="identifyingReturn.notes" class="whitespace-pre-line text-gray-700">{{ identifyingReturn.notes }}</div>
                <div class="mt-1 text-gray-500">{{ identifyingReturn.created_at }}</div>
            </div>
        </div>

        <template v-if="modalMode === 'log'">
            <div class="text-sm text-gray-600 mb-4">
                {{ trans("Take a photo of the box and copy anything written on it, so the office can identify it later.") }}
            </div>

            <input
                type="file"
                accept="image/*"
                capture="environment"
                class="w-full rounded-md border border-gray-300 px-4 py-3 text-base"
                @change="onBoxImageChange"
            />

            <textarea
                v-model="boxNotes"
                rows="4"
                :placeholder="trans('Anything written on the box: names, references, addresses, tracking numbers')"
                class="mt-3 w-full rounded-md border border-gray-300 px-4 py-3 text-base focus:border-indigo-500 focus:ring-indigo-500"
            />

            <div class="flex justify-between gap-x-2 mt-6">
                <Button type="tertiary" :label="trans('Back to search')" icon="fal fa-arrow-left" @click="() => modalMode = 'search'" />
                <Button :label="trans('Save for identification')" icon="fal fa-camera"
                    :disabled="(!boxNotes && !boxImage) || isSavingUnidentified"
                    :loading="isSavingUnidentified" @click="onSaveUnidentifiedReturn" />
            </div>
        </template>

        <template v-else>

        <div class="text-sm text-gray-600 mb-4">
            {{ trans("Select the delivery note this box came from.") }}
        </div>

        <input
            v-model="searchQuery"
            type="text"
            autofocus
            :placeholder="trans('Search by delivery note, tracking number, customer or order reference')"
            class="w-full rounded-md border border-gray-300 px-4 py-3 text-base focus:border-indigo-500 focus:ring-indigo-500"
            @input="onSearchInput"
        />

        <div class="mt-4 h-96 overflow-y-auto rounded-md border border-gray-200">
            <div v-if="isLoadingOptions" class="flex h-full items-center justify-center text-2xl text-gray-400">
                <LoadingIcon />
            </div>

            <div v-else-if="!deliveryNoteOptions.length" class="flex h-full flex-col items-center justify-center gap-y-3 text-sm text-gray-500">
                {{ trans("No delivery notes found. Only dispatched notes without a return are listed.") }}
                <Button v-if="warehouseId && !identifyingReturn" type="secondary" :label="trans('Can\'t find it? Log it to identify later')"
                    icon="fal fa-camera" @click="() => modalMode = 'log'" />
            </div>

            <div v-else class="grid grid-cols-1 gap-2 p-2 sm:grid-cols-2">
                <button
                    v-for="note in deliveryNoteOptions"
                    :key="note.id"
                    type="button"
                    class="rounded-md border p-3 text-left transition-colors"
                    :class="selectedDeliveryNote?.id === note.id
                        ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500'
                        : 'border-gray-200 hover:border-indigo-300 hover:bg-gray-50'"
                    @click="selectedDeliveryNote = note"
                >
                    <div class="flex items-baseline justify-between gap-x-2">
                        <span class="text-base font-medium">{{ note.reference }}</span>
                        <span class="text-sm text-gray-500">{{ note.date }}</span>
                    </div>
                    <div class="mt-1 truncate text-sm text-gray-700">{{ note.customer_name }} <span v-if="note.customer_reference" class="text-gray-500">({{ note.customer_reference }})</span></div>
                    <div v-if="note.tracking_number" class="mt-0.5 truncate text-sm text-gray-500">{{ note.tracking_number }}</div>
                </button>

                <div v-if="nextPageUrl" class="sm:col-span-2">
                    <Button type="tertiary" :label="trans('Load more')" :loading="isLoadingMore" full @click="onLoadMore" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-x-2 mt-6">
            <Button v-if="warehouseId && !identifyingReturn" type="tertiary" :label="trans('Can\'t find it?')" icon="fal fa-camera"
                @click="() => modalMode = 'log'" />
            <div v-else></div>
            <div class="flex gap-x-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="() => isOpenModalCreateReturn = false" />
                <Button :label="selectedDeliveryNote ? trans('Receive Return') + ' ' + selectedDeliveryNote.reference : trans('Receive Return')"
                    icon="fal fa-plus" :disabled="!selectedDeliveryNote || isSubmitting"
                    :loading="isSubmitting" @click="onCreateReturn" />
            </div>
        </div>

        </template>
    </Dialog>
</template>
