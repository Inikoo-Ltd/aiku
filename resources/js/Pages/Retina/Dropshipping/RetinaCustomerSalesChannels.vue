<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import { Table } from '@/types/Table'
import Toggle from "@/Components/Pure/Toggle.vue"
import RetinaTableCustomerSalesChannels from "@/Components/Tables/Retina/RetinaTableCustomerSalesChannels.vue"


defineProps<{
	data: Table
	title: string
	pageHead: PageHeadingTypes
	tabs: {
		current: string
		navigation: {}
	}

}>()


const urlParams = new URLSearchParams(window.location.search);
const showClosed = ref(urlParams.get('closed') === 'true');

watch(showClosed, (newValue) => {
    router.get(route('retina.dropshipping.customer_sales_channels.index'), {
        closed: newValue ? 'true' : undefined,
    });
});

</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #otherBefore>
			<div class="flex items-center gap-x-2 text-sm text-gray-500">
				<span>Show Closed Channels</span>
			   	<Toggle v-model="showClosed" />
			</div>
		</template>
	</PageHeading>
	<div class="overflow-x-auto">
		<RetinaTableCustomerSalesChannels :data="data" />
	</div>
</template>
