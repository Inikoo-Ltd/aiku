<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableStructure from "@/Components/Tables/Grp/Org/Web/TableStructure.vue"
import { capitalize } from "@/Composables/capitalize"
import {
	faShapes,
	faSortAmountDownAlt,
	faBrowser,
	faSortAmountDown,
	faHome,
	faSignInAlt,
	faBooks,
	faColumns,
	faInfoCircle,
	faNewspaper,
	faPlus,
	faChevronRight,
	faChevronDown,
	faGripLines,
} from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { ref } from "vue"
import { faCaretDown, faCaretRight } from "@fas"
import TableSnapshots from "@/Components/Tables/TableSnapshots.vue"
library.add(faChevronRight, faChevronDown, faPlus, faGripLines)

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: {}
}>()


</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<TableSnapshots :data="data" class="mt-5" />
</template>
