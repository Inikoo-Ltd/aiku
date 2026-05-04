<script setup lang="ts">
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";

library.add(faAlbumCollection);
const props = withDefaults(defineProps<{
    data: {};
    isMaster?: boolean
}>(), {
    // Default values
    isMaster: false,
});

console.log(props)
</script>

<template>
    <div class="w-full px-4 py-3 shadow-sm">
		<span class="text-xl font-semibold text-gray-800 whitespace-pre-wrap">
			<span class="flex align-middle">
				{{ data.tradeUnitFamily?.name }} 
                <span v-if="data.brand?.name" v-tooltip="'Click to go to Brand'"
                    class="border border-gray-400 bg-gray-200 rounded-md px-2 py-1 font-light text-sm cursor-pointer hover:opacity-[80%] transition ease-in-out whitespace-nowrap">
                    {{ data.brand?.name }}
                </span>
			</span>
		</span>
	</div>
	<div class="grid gap-4 mx-3 lg:mx-0">
		<!-- Sidebar -->
		<div class="space-y-3 lg:space-y-6">
			<!-- Image Preview -->
			<div class="bg-white rounded-xl px-4 lg:px-5">
				<div v-if="data.tradeUnitFamily?.description" class="bg-white mb-4 lg:mb-5 py-2 px-3 font-light rounded-md border w-full flex">
                    {{ data.tradeUnitFamily?.description }}
                </div>
				<dd class="font-medium flex flex-wrap gap-1 mb-2">
					<span v-for="tag in data.tags ?? []" :key="tag.id" v-tooltip="'tag'"
						class="px-2 py-0.5 rounded-full text-xs bg-green-50 border border-blue-100">
						{{ tag.name }}
					</span>
				</dd>
			</div>
		</div>
    </div>
</template>
