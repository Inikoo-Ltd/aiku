<script setup lang="ts">
import { ref, onMounted } from "vue"
import {
	faPresentation,
	faCube,
	faText,
	faImage,
	faImages,
	faPaperclip,
	faShoppingBasket,
	faStar,
	faHandHoldingBox,
	faBoxFull,
	faBars,
	faBorderAll,
	faLocationArrow,
} from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"

import { Root, Daum } from "@/types/webBlockTypes"
import Image from "@/Components/Image.vue"

library.add(
	faPresentation,
	faCube,
	faText,
	faImage,
	faImages,
	faPaperclip,
	faShoppingBasket,
	faStar,
	faHandHoldingBox,
	faBoxFull,
	faBars,
	faBorderAll,
	faLocationArrow
)
const props = withDefaults(
	defineProps<{
		onPickBlock: Function
		webBlockTypes: Root
		scope?: string /* all|block|element */
	}>(),
	{
		scope: "all",
	}
)

const data = ref<Daum[]>([])

// Define active item state
const active = ref<Daum>(props.webBlockTypes.data[0])

// Filter webBlockTypes based on scope and save in data
onMounted(() => {
	const filteredData =
		props.scope === "all"
			? props.webBlockTypes.data
			: props.webBlockTypes.data.filter((item) => item.category === props.scope )

	data.value = filteredData.sort((a, b) => a.name.localeCompare(b.name))
	active.value = data.value[0] || null;
})

</script>

<template>
	<div class="overflow-y-auto h-full select-none p-6 bg-gray-100">
		<div class="flex flex-wrap justify-center gap-6">
			<div v-for="block in data" :key="block.id"
				class="relative h-36 w-52 min-h-24 border rounded-xl cursor-pointer shadow-md transition-transform transform hover:scale-105 bg-white overflow-hidden hover:shadow-lg"
				:class="'border-gray-300'" @click="onPickBlock(block)">
				<div class="h-3/4 w-full flex items-center justify-center rounded-t-xl bg-gray-50">
					<Image :src="block.screenshot" class="max-h-full max-w-full object-contain"
						:alt="`Screenshot of ${block.name}`" />
				</div>

				<div
					class="absolute bottom-0 w-full h-1/4  rounded-b-xl flex items-center justify-center font-semibold text-sm p-2 truncate">
					{{ block.name }}
				</div>
			</div>
		</div>
	</div>



</template>
