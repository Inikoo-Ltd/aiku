<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import ModelDetails from "@/Components/ModelDetails.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import TableStockDeliveryItems from "@/Components/Tables/Grp/Org/Procurement/TableStockDeliveryItems.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import UploadAttachment from '@/Components/Upload/UploadAttachment.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Timeline from "@/Components/Utils/Timeline.vue"
import { Timeline as TSTimeline } from "@/types/Timeline"

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string
        navigation: object
    },
    attachments?: {}
    attachmentRoutes?: {}
    stock_delivery: {}
    items?: {}
    history?: {}
    timelines: {
        [key: string]: TSTimeline
    }
}>()
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInventory, faWarehouse, faPersonDolly, faBoxUsd, faTruck, faTerminal, faCameraRetro, faPaperclip, faInfoCircle } from '@fal'
import ComsDashboard from "@/Components/Coms/ComsDashboard.vue";

library.add(faInventory, faWarehouse, faPersonDolly, faBoxUsd, faTruck, faTerminal, faCameraRetro, faPaperclip, faInfoCircle)

const isModalUploadOpen = ref(false)
let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        details: ModelDetails,
        history: TableHistories,
        attachments: TableAttachments,
        SHOWCASE: ComsDashboard,
        items: TableStockDeliveryItems,
    }
    return components[currentTab.value]

});
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button v-if="currentTab === 'attachments'" @click="() => isModalUploadOpen = true" label="Attach"
                icon="upload" />
        </template>
    </PageHeading>
   	<div v-if="timelines" class="mt-4 sm:mt-1 border-b border-gray-200 pb-2">
		<Timeline
			:options="timelines"
			:state="stock_delivery.state"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy, HH:mm'" />
	</div>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :detachRoute="attachmentRoutes.detachRoute">
    </component>
    <UploadAttachment v-model="isModalUploadOpen" scope="attachment" :title="{
        label: 'Upload your file',
        information: 'The list of column file: customer_reference, notes, stored_items'
    }" progressDescription="Adding Pallet Deliveries" :attachmentRoutes="attachmentRoutes" />
</template>
