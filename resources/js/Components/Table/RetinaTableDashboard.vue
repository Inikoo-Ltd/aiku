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
	data: Array<{ reference: string; icon: string; route: string; total: number }>
}>()

const searchQuery = ref("")

// Filtered data
const filteredData = computed(() => {
	return props.data.filter((item) =>
		item.reference.toLowerCase().includes(searchQuery.value.toLowerCase())
	)
})

const printLabelByType = (label?: string, data: {}) => {
    switch (data.format) {
        case 'currency':
            return locale.currencyFormat(data.currency_code || 'usd', Number(label))
        default:
            return label
    }
}
</script>

<template>
	<div class="bg-white text-gray-800 rounded-lg p-6 shadow-md border border-gray-200">
		<!-- Search Header -->
		<!-- <div class="flex items-center w-full mb-4">
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
		</div> -->

		<!-- Table Content -->
		<div>
			<template v-if="filteredData.length">
				<div class="font-bold">
					Unpaid invoices ({{ filteredData.length }})
				</div>
				<div class="grid grid-cols-12 gap-1.5">
					<div
						v-for="item in filteredData"
						:key="item.reference"
						class="col-span-12 flex items-center last:border-b py-1">
						<!-- Icon -->
						<div class="mr-4">
							<FontAwesomeIcon :icon="item.icon" fixed-width class="text-gray-500" />
						</div>

						<!-- Reference with Link -->
						<div class="flex-grow text-gray-800">
							<Link :href="item.route">
								<span class="primaryLink">{{ item.reference }}</span>
							</Link>
						</div>

						<!-- {{ item.currency_code }} == -->
						<!-- total -->
						<div class="text-right text-gray-700">
							<!-- {{ locale.number(item.total) }} -->
							{{ printLabelByType(item.total, item) }}
						</div>
					</div>
				</div>
			</template>
			<template v-else>
				<div class="text-center text-gray-500 py-4">All invoice already paid</div>
			</template>
		</div>
	</div>
</template>

<style scoped>
.search-container {
	transition: width 0.3s ease;
}
</style>
