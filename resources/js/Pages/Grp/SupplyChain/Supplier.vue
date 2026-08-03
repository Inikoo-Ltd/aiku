<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Fri, 24 Feb 2023 10:21:46 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBoxUsd,
    faClock,
    faMoneyBill,
    faPaperclip,
    faPersonDolly
} from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import UploadAttachment from '@/Components/Upload/UploadAttachment.vue'
import SupplierShowcase from '@/Components/Showcases/Grp/SupplierShowcase.vue'
import TableSupplierProducts from '@/Components/Tables/Grp/SupplyChain/TableSupplierProducts.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import TableAttachments from '@/Components/Tables/Grp/Helpers/TableAttachments.vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from '@/Composables/capitalize'

library.add(faBoxUsd, faClock, faMoneyBill, faPaperclip, faPersonDolly)

const props = defineProps<{
    title: string
    pageHead: object
    tabs: {
        current: string
        navigation: object
    }
    showcase?: object
    purchase_sales?: object
    history?: object
    attachments?: {}
    attachmentRoutes?: {}
    errors?: object
}>()

const currentTab = ref(props.tabs.current)
const isModalUploadOpen = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components = {
        showcase: SupplierShowcase,
        purchase_sales: TableSupplierProducts,
        history: TableHistories,
        attachments: TableAttachments
    }

    return components[currentTab.value]
})

const getErrors = () => {
    if (props.errors.purchase_order) {
        if (confirm(props.errors.purchase_order)) {
            const form = useForm({ force: true })

            form.post(route(
                props.pageHead.create_direct.route.name,
                props.pageHead.create_direct.route.parameters
            ))
        }
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                v-if="currentTab === 'attachments'"
                label="Attach"
                icon="upload"
                @click="() => (isModalUploadOpen = true)" />
        </template>
    </PageHeading>
    <div v-if="props.errors.purchase_order">{{ getErrors() }}</div>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :data="props[currentTab]"
        :tab="currentTab"
        :detachRoute="attachmentRoutes?.detachRoute" />

    <UploadAttachment
        v-model="isModalUploadOpen"
        scope="attachment"
        :title="{
            label: 'Upload your file',
            information: 'The list of column file: customer_reference, notes, stored_items'
        }"
        progressDescription="Adding Pallet Deliveries"
        :attachmentRoutes="attachmentRoutes" />
</template>
