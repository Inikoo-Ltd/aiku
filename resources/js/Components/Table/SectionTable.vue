<script setup lang="ts">
import { ref, computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Link } from "@inertiajs/vue3"
import { useLocaleStore } from "@/Stores/locale"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import InputText from "primevue/inputtext"

const locale = useLocaleStore()

const props = defineProps<{
	data: Record<
		string,
		{
			section: string
			data: Array<{ name: string; icon: string; route: string; count: number }>
		}
	>
}>()

const searchQuery = ref("")

const sectionArray = computed(() => {
	return Object.values(props.data)
})

const collapsedSections = ref(new Set<string>())

const toggleSection = (section: string) => {
	if (collapsedSections.value.has(section)) {
		collapsedSections.value.delete(section)
	} else {
		collapsedSections.value.add(section)
	}
}

const isSectionCollapsed = (section: string) => {
	return collapsedSections.value.has(section)
}

// Filtered data
const filteredData = computed(() => {
	return sectionArray.value
		.map((section) => ({
			...section,
			data: section.data.filter((item) =>
				item.name.toLowerCase().includes(searchQuery.value.toLowerCase())
			),
		}))
		.filter((section) => section.data.length > 0)
})
</script>

<template>
	<div class="bg-white text-gray-800 rounded-lg p-6 shadow-md border border-gray-200">
		<!-- Search Header -->
		<div class="flex items-center w-full mb-4">
			<div
				class="search-container transition-all duration-300 ease-in-out flex items-center w-full">
				<IconField class="w-full">
					<InputIcon class="mr-2">
						<FontAwesomeIcon
							fixed-width
							icon="fal fa-filter"
							class="text-gray-500 cursor-pointer hover:text-gray-700" />
					</InputIcon>
					<InputText
						v-model="searchQuery"
						type="text"
						placeholder="Search ..."
						class="border border-gray-300 rounded appearance-none block w-full px-3 py-2 text-sm leading-none transition-all duration-300 placeholder:text-gray-400 placeholder:italic focus:border-gray-500 focus:ring-0 focus:outline-hidden" />
				</IconField>
			</div>
		</div>

		<div>
			<template v-if="filteredData.length">
				<div v-for="section in filteredData" :key="section.section" class="mb-6">
					<div
						class="flex justify-between items-center font-semibold text-gray-700 mb-2 border-b pb-2">
						<span>{{ section.section }}</span>
						<button
							@click="toggleSection(section.section)"
							class="text-sm text-gray-500 hover:text-gray-700">
							<FontAwesomeIcon
								class="text-xl font-semibold text-gray-700"
								:icon="
									isSectionCollapsed(section.section)
										? 'far fa-angle-up'
										: 'far fa-angle-down'
								" />
						</button>
					</div>

					<div
						v-show="!isSectionCollapsed(section.section)"
						class="grid grid-cols-12 gap-4">
						<div
							v-for="item in section.data"
							:key="item.name"
							class="col-span-12 flex items-center border-b py-2">
							<div class="mr-4">
								<FontAwesomeIcon
									:icon="item.icon"
									fixed-width
									class="text-gray-500" />
							</div>

							<div class="flex-grow text-gray-800">
								<Link :href="item.route">
									<span class="primaryLink">{{ item.name }}</span>
								</Link>
							</div>

							<div class="text-right text-gray-700">
								{{ locale.number(item.count) }}
							</div>
						</div>
					</div>
				</div>
			</template>
			<template v-else>
				<div class="text-center text-gray-500 py-4">No Product found.</div>
			</template>
		</div>
	</div>
</template>

<style scoped>
.search-container {
	transition: width 0.3s ease;
}
</style>
