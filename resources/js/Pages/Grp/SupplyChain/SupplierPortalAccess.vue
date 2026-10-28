<script setup lang="ts">
import { ref } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import SupplierDirectLinkDialog, { Share } from "@/Components/Procurement/SupplierDirectLinkDialog.vue"
import TableSupplierPortalContacts from "@/Components/Tables/Grp/SupplyChain/TableSupplierPortalContacts.vue"
import TableSupplierDirectLinks from "@/Components/Tables/Grp/SupplyChain/TableSupplierDirectLinks.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { faShareAlt, faEnvelope, faLink, faExternalLink } from "@fal"

library.add(faShareAlt, faEnvelope, faLink, faExternalLink)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	tabs: {
		current: string
		navigation: object
	}
	share: Share
	self_log_in?: object
	direct_links?: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<a :href="share.portal_url" target="_blank" rel="noopener">
				<Button type="tertiary" size="xs" :label="ctrans('Supplier portal URL')">
					<template #icon><FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" /></template>
				</Button>
			</a>
			<SupplierDirectLinkDialog :share="share" />
		</template>
	</PageHeading>
	<Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
	<TableSupplierPortalContacts v-if="currentTab === 'self_log_in' && self_log_in" :data="self_log_in" tab="self_log_in" :can-add="share.can_create" :email-route="share.email_route" />
	<TableSupplierDirectLinks v-else-if="currentTab === 'direct_links' && direct_links" :data="direct_links" tab="direct_links" :can-stop="share.can_create" />
</template>
