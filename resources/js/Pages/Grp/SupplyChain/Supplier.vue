<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Fri, 24 Feb 2023 10:21:46 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBoxUsd,
    faClipboardList,
    faClock,
    faInbox,
    faPaperclip,
    faPersonDolly,
    faTruckContainer
} from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import SupplierShowcase from '@/Components/Showcases/Grp/SupplierShowcase.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import TableSupplierMessages from '@/Components/Tables/Grp/Org/Procurement/TableSupplierMessages.vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from '@/Composables/capitalize'

library.add(faBoxUsd, faClipboardList, faClock, faInbox, faPaperclip, faPersonDolly, faTruckContainer)

const props = defineProps<{
    title: string
    pageHead: object
    tabs: {
        current: string
        navigation: object
    }
    showcase?: object
    inbox?: object
    history?: object
    attachments?: object
    attachmentRoutes: { attachRoute: routeType; detachRoute: routeType }
    attachmentScopes: { name: string; code: string }[]
}>()

const currentTab = ref(props.tabs.current)
const isModalUploadAttachmentOpen = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components = {
        showcase: SupplierShowcase,
        inbox: TableSupplierMessages,
        attachments: TableAttachments,
        history: TableHistories
    }

    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button v-if="currentTab === 'attachments'" :label="ctrans('Attach')" icon="upload" @click="() => (isModalUploadAttachmentOpen = true)" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :detachRoute="attachmentRoutes.detachRoute" />

    <UploadAttachment
        v-model="isModalUploadAttachmentOpen"
        scope="attachment"
        :title="{ label: ctrans('Upload your file'), information: '' }"
        :progressDescription="ctrans('Adding supplier attachments')"
        :attachmentRoutes="attachmentRoutes"
        :options="attachmentScopes"
        withCaption
    />
</template>
