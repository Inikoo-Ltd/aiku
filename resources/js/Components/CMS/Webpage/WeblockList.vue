<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import Image from '@/Components/Image.vue'
import { Root, Daum } from '@/types/webBlockTypes'
import { useLayoutStore } from '@/Stores/layout'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSearch } from '@far'
import { trans } from 'laravel-vue-i18n'
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
		<div class="h-[520px] flex flex-col bg-white  overflow-hidden">

			<TabGroup as="div" class="flex flex-col h-full">

				<!-- HEADER -->
				<div class="px-5 py-4 border-b bg-gradient-to-b from-white to-gray-50">
					<div class="flex items-center justify-between gap-3">
						<div class="text-lg font-semibold text-gray-800">
							Block Templates 
						</div>

						<!-- search -->
						<div class="relative">
							<input v-model="search" type="text" placeholder="Search block..."
								class="w-64 pl-9 pr-3 py-2 text-sm border rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-300" />
							<FontAwesomeIcon :icon="faSearch"
								class=" absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></FontAwesomeIcon>
						</div>
					</div>
				</div>

				<!-- TABS -->
				<TabList class="flex gap-2 px-4 py-3 border-b bg-gray-50 overflow-x-auto">
					<Tab v-for="tab in tabs" :key="tab" v-slot="{ selected }" as="template">
						<button class="text-xs font-medium px-4 py-2 rounded-full whitespace-nowrap transition"
							:style="selected ? { backgroundColor: layout?.app?.theme[0], color: '#fff' } : {}" :class="selected
								? 'shadow-md'
								: 'bg-white border text-gray-600 hover:bg-gray-100'">
							{{ tab === 'all' ? 'All Blocks' : tab.charAt(0).toUpperCase() + tab.slice(1) }}
						</button>
					</Tab>
				</TabList>

				<!-- CONTENT -->
				<TabPanels class="flex-1 overflow-hidden">
					<TabPanel v-for="tab in tabs" :key="tab" class="h-full overflow-y-auto px-6 py-5">
						<div class="grid gap-5 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">

							<div v-for="block in filteredBlocks(tab === 'all' ? allData : categorizedData[tab])"
								:key="block.id"
								class="group relative flex flex-col rounded-xl border bg-white overflow-hidden cursor-pointer transition-all duration-200 hover:shadow-lg hover:-translate-y-[2px]"
								@click="onPickBlock(block)">

								<!-- badge -->
								<div v-if="block.is_in_test"
									class="absolute top-2 right-2 z-10 bg-amber-500 text-white text-[10px] px-2 py-[2px] rounded-md shadow">
									Beta
								</div>

								<!-- image preview -->
								<div
									class="h-28 flex items-center justify-center bg-gradient-to-b from-gray-50 to-gray-100 border-b">
									<Image v-if="block.screenshot" :src="block.screenshot"
										class="max-h-full max-w-full object-contain transition group-hover:scale-[1.03]" />

									<FontAwesomeIcon v-else icon="fal fa-image" class="text-3xl text-gray-300" />
								</div>

								<!-- title -->
								<div class="p-3 flex flex-col justify-center text-center min-h-[68px]">
									<div class="text-sm font-semibold text-gray-800 leading-tight line-clamp-2">
										{{ block.name }}
									</div>

									<div v-if="layout?.app?.environment === 'local'"
										class="text-[10px] text-gray-400 mt-1">
										{{ block.code }}
									</div>
								</div>

								<!-- hover indicator -->
								<div
									class="absolute inset-0 rounded-xl ring-0 group-hover:ring-2 ring-indigo-200 pointer-events-none">
								</div>

							</div>

						</div>

						<!-- empty -->
						<div v-if="filteredBlocks(tab === 'all' ? allData : categorizedData[tab]).length === 0"
							class="text-center text-sm text-gray-400 py-20">
							{{ trans("No block found") }}
						</div>

					</TabPanel>
				</TabPanels>

			</TabGroup>
		</div>
</template>
