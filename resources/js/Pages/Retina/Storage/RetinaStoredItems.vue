<!--
-  Author: Raul Perusquia <raul@inikoo.com>
-  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
-  Copyright (c) 2022, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"

import { faNarwhal } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { PageHeadingTypes } from '@/types/PageHeading'
import TableStoredItemsInWarehouse from '@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsInWarehouse.vue'
import { UploadPallet } from "@/types/Pallet"
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"
library.add(faNarwhal)

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
	bulk_edit_upload: UploadSection
}>()

const isModalUploadSpreadsheet = ref(false)

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
      <TableStoredItemsInWarehouse :data="data" :name="undefined" />
  </template>
