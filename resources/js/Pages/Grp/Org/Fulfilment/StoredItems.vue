<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"

import { faNarwhal, faBallotCheck } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { PageHeadingTypes } from '@/types/PageHeading'
import { computed, ref } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import Tabs from '@/Components/Navigation/Tabs.vue'
import TableStoredItemsAudits from '@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsAudits.vue'
import TableStoredItemsInWarehouse from "@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsInWarehouse.vue";
import TablePalletStoredItems from '@/Components/Tables/Grp/Org/Fulfilment/TablePalletStoredItems.vue'
import Button from "@/Components/Elements/Buttons/Button.vue"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"
import { routeType } from "@/types/route"
import { UploadPallet } from "@/types/Pallet"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
library.add(faNarwhal, faBallotCheck)

interface UploadSection {
	title: {
		label: string
		information: string
	}
	progressDescription: string
	upload_spreadsheet: UploadPallet
	preview_template: {
		header: string[]
		rows: {}[]
	}
}

const props = defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string;
        navigation: object;
    }
	bulk_edit_upload: UploadSection
    stored_items : {}
    pallet_stored_items : {}
    stored_item_audits : {}
}>()
let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const isModalUploadSpreadsheet = ref(false)

const component = computed(() => {

    const components = {
        stored_items:TableStoredItemsInWarehouse,
        pallet_stored_items: TablePalletStoredItems,
        stored_item_audits: TableStoredItemsAudits
    };
    return components[currentTab.value];

});


const onNoStructureUpload = () => {
	notify({
		title: trans("Something went wrong"),
		text: trans("Upload structure is not provided. Please contact support."),
		type: "error",
	})
}

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
		<template #button-edit-sku="{action}">
			<Button
				@click="() => bulk_edit_upload ? isModalUploadSpreadsheet = true : onNoStructureUpload()"
				type="tertiary"
				:style="'edit'"
				:label="'Bulk Edit SKU'"
			/>
		</template>
	</PageHeading>

	<UploadExcel
		v-if="bulk_edit_upload"
		v-model="isModalUploadSpreadsheet"
		:title="bulk_edit_upload.title"
		:progressDescription="bulk_edit_upload.progressDescription"
		:preview_template="bulk_edit_upload.preview_template"
		:upload_spreadsheet="bulk_edit_upload.upload_spreadsheet"
		xxxadditionalDataToSend="interest.pallets_storage ? ['stored_items'] : undefined"
	/>

	<Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :key="currentTab" :tab="currentTab" :data="props[currentTab as keyof typeof props]"></component>
</template>
