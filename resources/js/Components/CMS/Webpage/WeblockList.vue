<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import Image from '@/Components/Image.vue'
import { Root, Daum } from '@/types/webBlockTypes'
import { useLayoutStore } from '@/Stores/layout'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faImage)

const props = withDefaults(
	defineProps<{
		onPickBlock: Function
		webBlockTypes: Root
		scope?: string // all | block | element
	}>(),
	{
		scope: 'all',
	}
)

const tabs = ref<string[]>([])
const categorizedData = ref<Record<string, Daum[]>>({})
const allData = ref<Daum[]>([])
const layout = useLayoutStore()

const search = ref('')

const filteredBlocks = (items: Daum[]) => {
	return items.filter(b =>
		b.name.toLowerCase().includes(search.value.toLowerCase())
	)
}


onMounted(() => {
	const rawData = props.webBlockTypes.data.filter(item =>
		['block', 'element', 'column'].includes(item.category)
	)

	allData.value = [...rawData].sort((a, b) => a.name.localeCompare(b.name))

	const categoryMap: Record<string, Daum[]> = {}
	for (const item of rawData) {
		if (!categoryMap[item.category]) {
			categoryMap[item.category] = []
		}
		categoryMap[item.category].push(item)
	}

	tabs.value = ['all', ...Object.keys(categoryMap)]
	categorizedData.value = categoryMap
})

</script>

<template>
	<div class="h-[500px] flex flex-col bg-gray-50 border rounded-md shadow-sm overflow-hidden">
		<TabGroup as="div" class="flex flex-col h-full">

			<!-- Tab Headers -->
			<TabList class="flex gap-2 border-b bg-white px-4 py-2 overflow-x-auto">
				<Tab
					v-for="tab in tabs"
					:key="tab"
					v-slot="{ selected }"
					as="template"
				>
					<button
						class="text-sm font-medium px-4 py-2 rounded-md transition whitespace-nowrap"
						:style="selected ? { backgroundColor: layout?.app?.theme[0], color: '#fff' } : {}"
						:class="selected ? 'shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
					>
						{{ tab === 'all' ? 'All Blocks' : tab.charAt(0).toUpperCase() + tab.slice(1) }}
					</button>
				</Tab>
			</TabList>

			<!-- Search bar ABOVE content & sticky -->
			<div class="px-4 py-3 bg-gray-50 border-b sticky top-0 z-10">
				<input
					v-model="search"
					type="text"
					placeholder="Search blocks..."
					class="px-3 py-2 text-sm border rounded-md w-64 bg-white focus:outline-none focus:ring focus:ring-indigo-200"
				/>
			</div>

			<!-- Tab Panels -->
			<TabPanels class="flex-1 overflow-hidden">
				<TabPanel
					v-for="tab in tabs"
					:key="tab"
					class="h-full overflow-y-auto p-6"
				>
					<div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 justify-items-center">
						<div
							v-for="block in filteredBlocks(tab === 'all' ? allData : categorizedData[tab])"
							:key="block.id"
							class="relative h-36 w-52 border rounded-xl cursor-pointer bg-white shadow-sm hover:shadow-md hover:scale-[1.02] transition-transform"
							@click="onPickBlock(block)"
						>
							<!-- Beta Label -->
							<div
								v-if="block.is_in_test"
								class="absolute top-1 right-1 bg-yellow-500 text-white text-[10px] px-2 py-1 rounded-md shadow"
							>
								Beta Test
							</div>

							<div class="h-3/4 flex items-center justify-center bg-gray-100 text-sm text-gray-400">
								<Image
									v-if="block.screenshot"
									:src="block.screenshot"
									class="max-h-full max-w-full object-contain"
								/>

								<FontAwesomeIcon
									v-else
									icon="fal fa-image"
									class="text-4xl opacity-40"
								/>
							</div>

							<div class="h-1/4 flex items-center justify-center text-sm font-semibold px-2 truncate">
								{{ block.name }}
							</div>
						</div>
					</div>
				</TabPanel>
			</TabPanels>
		</TabGroup>
	</div>
</template>
