<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEnvelope } from "@fal"
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { faSpellCheck, faSeedling, faPaperPlane, faStop } from "@fal"

library.add(faSpellCheck, faSeedling, faPaperPlane, faStop, faEnvelope)

const props = defineProps<{
	data: object
	title: string
	pageHead: object
}>()
function emailAddressRoute(email: any) {
	return route("grp.overview.comms-marketing.email-addresses.show", [email.data.email])
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>
	<Table :resource="data" class="mt-5">
		<template #cell(email)="{ item: email }">
			<Link :href="emailAddressRoute(email)" class="primaryLink">
				{{ email["email"] }}
			</Link>
		</template>
		<template #cell(marketing)="{ item: email }">
			{{ email["marketing"] }}
		</template>
		<template #cell(transactional)="{ item: email }">
			{{ email["transactional"] }}
		</template>
	</Table>
</template>
