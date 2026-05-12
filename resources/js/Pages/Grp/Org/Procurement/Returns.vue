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
import { Dialog, Select } from 'primevue'
import axios from 'axios'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'

library.add(faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data?: {}
    shopType: string
}>()

const layoutStore = inject("layout", layoutStructure)
const loading = ref(false)

const isOpenModalCreateReturn = ref(false)

interface DeliveryNoteOption {
    id: number
    label: string
    reference: string
    customer_name: string
    date: string
}

// const deliveryNoteOptions = ref<DeliveryNoteOption[]>([])
const selectedDeliveryNote = ref<DeliveryNoteOption | null>(null)
// const isLoadingOptions = ref(false)
const isSubmitting = ref(false)

// const fetchDeliveryNoteOptions = async () => {
//     isLoadingOptions.value = true
//     try {
//         const res = await axios.get(route('grp.json.delivery_note_valid_for_return', {
//             warehouse: (route().params as Record<string, string>)['warehouse'],
//         }))
//         deliveryNoteOptions.value = res.data?.data ?? []
//     } catch (e) {
//         deliveryNoteOptions.value = []
//     } finally {
//         isLoadingOptions.value = false
//     }
// }

const onOpenModal = () => {
    selectedDeliveryNote.value = null
    isOpenModalCreateReturn.value = true
    // fetchDeliveryNoteOptions()
}

const onCreateReturn = () => {
    if (!selectedDeliveryNote.value) return

    isSubmitting.value = true
    router.patch(
        route('grp.models.delivery_note.return.process', {
            deliveryNote: selectedDeliveryNote.value.id
        }),
        {},
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
    <TableDeliveryNotes :data="data" />

    <Dialog v-model:visible="isOpenModalCreateReturn" modal closable dismissableMask :showHeader="false"
        :style="{ width: '36rem', 'xmin-height': '20rem' }" @hide="() => {
            isOpenModalCreateReturn = false
        }" contentClass="!overflow-visible">
        <div class="pt-4 pb-2 text-lg font-semibold">
            {{ trans("Create Return") }}
        </div>

        <div class="text-sm text-gray-600 mb-4">
            {{ trans("Select a dispatched delivery note to create a return for.") }}
        </div>

        <PureMultiselectInfiniteScroll v-model="selectedDeliveryNote" :fetch-route="{
            name: 'grp.json.delivery_note_valid_for_return',
            parameters: {
                warehouse: (route().params as Record<string, string>)['warehouse'],
            }
        }"
            required
            :object="true"
            labelProp="label"
            valueProp="id"
            :placeholder="ctrans('Select delivery note')"
        />

        <div class="flex justify-end gap-x-2 mt-8">
            <Button type="tertiary" :label="trans('Cancel')" @click="() => isOpenModalCreateReturn = false" />
            <Button :label="trans('Create Return')" icon="fal fa-plus" :disabled="!selectedDeliveryNote || isSubmitting"
                :loading="isSubmitting" full @click="onCreateReturn" />
        </div>
    </Dialog>
</template>
