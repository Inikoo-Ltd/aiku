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
library.add(
	faChevronRight,
	faChevronDown,
 	faPlus,
	faGripLines
)

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: {}
}>()

interface TreeNode {
  id: number
  name: string
  children?: TreeNode[]
}

const treeData = ref<TreeNode[]>([
	{
		id: 1,
		name: "Root A",
		children: [],
	},
	{
		id: 2,
		name: "Root B",
		children: [],
	},
])
// ----- Fetch Data from Backend -----
// When you’re ready to switch to dynamic data, use the following function.
// Make sure to adjust the API endpoint and parameters as needed.
/* const fetchData = async () => {
  const dataId = route.query.dataId || '';
  try {
    const response = await axios.get('/api/tree', {
      params: { dataId },
    });
    // Assume the API returns an array of TreeNode items
    treeData.value = response.data;
  } catch (error) {
    console.error('Error fetching tree data:', error);
  }
}; */

// Uncomment the following lines to fetch dynamic data instead of dummy data.
// onMounted(() => {
//   fetchData();
// });
// watch(
//   () => route.fullPath,
//   () => {
//     fetchData();
//   }
// );

if (props.data) {
    treeData.value = props.data
}
</script>

<template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead" />
      <TableStructure :nodes="treeData" />
</template>
