<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import Image from '@/Components/Image.vue'
import { Root, Daum } from '@/types/webBlockTypes'
import { useLayoutStore } from '@/Stores/layout'

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

onMounted(() => {
	const rawData = props.webBlockTypes.data.filter(item =>
		['block', 'element'].includes(item.category)
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

			<!-- Tab Panels -->
			<TabPanels class="flex-1 overflow-hidden">
				<TabPanel
					v-for="tab in tabs"
					:key="tab"
					as="div"
					class="h-full overflow-y-auto p-6"
				>
					<div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 justify-items-center">
						<div
							v-for="block in tab === 'all' ? allData : categorizedData[tab]"
							:key="block.id"
							class="relative h-36 w-52 border rounded-xl cursor-pointer bg-white shadow-sm hover:shadow-md hover:scale-[1.02] transition-transform"
							@click="onPickBlock(block)"
						>
							<div class="h-3/4 flex items-center justify-center bg-gray-100">
								<Image
									:src="block.screenshot"
									class="max-h-full max-w-full object-contain"
									:alt="`Screenshot of ${block.name}`"
								/>
							</div>
							<div
								class="h-1/4 flex items-center justify-center text-sm font-semibold px-2 truncate"
							>
								{{ block.name }}
							</div>
						</div>
					</div>
				</TabPanel>
			</TabPanels>
		</TabGroup>
	</div>
</template>
