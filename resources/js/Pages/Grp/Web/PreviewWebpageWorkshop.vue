<!--
  - Author: Artha <artha@aw-advantage.com>
  - Created: Thu, 26 Sep 2024 13:18:33 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { getComponent } from "@/Composables/getWorkshopComponents"
import { ref, onMounted, onBeforeUnmount } from "vue"
import WebPreview from "@/Layouts/WebPreview.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { sendMessageToParent } from "@/Composables/Workshop"
import { router, Head} from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSendBackward, faBringForward, faTrashAlt } from "@fas"
import { useConfirm } from "primevue/useconfirm";
import "@/../css/Iris/editor.css"
import { Root as RootWebpage } from "@/types/webpageTypes"
import { trans } from "laravel-vue-i18n"
const confirm = useConfirm();

defineOptions({ layout: WebPreview })
const props = defineProps<{
	webpage?: RootWebpage
	header: {
		data: {}
	}
	footer: {
		footer: {}
	}
	navigation: {
		menu: {}
	}
	layout: {}
}>()

const isPreviewLoggedIn = ref(false)
const isPreviewMode = ref(false)
const activeBlock = ref(null)
const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')

const showWebpage = (activityItem) => {
	if (activityItem?.web_block?.layout && activityItem.show) {
		if (isPreviewLoggedIn.value && activityItem.visibility.in) return true
		else if (!isPreviewLoggedIn.value && activityItem.visibility.out) return true
		else return false
	} else return false
}

const updateData = (newVal) => {
	sendMessageToParent("autosave", newVal)
}

const checkScreenType = () => {
  const width = window.innerWidth
  if (width < 640) screenType.value = 'mobile'
  else if (width >= 640 && width < 1024) screenType.value = 'tablet'
  else screenType.value = 'desktop'
}


onMounted(() => {
	window.addEventListener("message", (event) => {
		if (event.data.key === "isPreviewLoggedIn") isPreviewLoggedIn.value = event.data.value
		if (event.data.key === "isPreviewMode") isPreviewMode.value = event.data.value
		if (event.data.key === "activeBlock") {
			activeBlock.value = event.data.value
			const blockElement = document.querySelector(`[data-block-id="${event.data.value}"]`)
			if (blockElement) {
				blockElement.scrollIntoView({ behavior: "smooth", block: "center" })
			}
		}
		if (event.data.key === "reload") {
			router.reload({
				only: ["footer", "header", "webpage"],
				onSuccess: () => { },
			})
		}
	})
	checkScreenType()
	window.addEventListener('resize', checkScreenType)
})


onBeforeUnmount(() => {
  window.removeEventListener('resize', checkScreenType)
})
</script>

<template>

	<Head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0" /> 
	</Head>
	<div class="editor-class">
		<div class="shadow-xl px-1" >
			<div v-if="webpage">
				<div v-if="webpage?.layout?.web_blocks?.length">
					<TransitionGroup tag="div" name="list" class="relative">
						<template v-for="(activityItem, activityItemIdx) in webpage.layout.web_blocks"
							:key="activityItem.id">
							<section class="w-full  min-h-[50px] relative" :data-block-id="activityItemIdx"
								v-show="showWebpage(activityItem)" :class="{
									'border-4 border-[#4F46E5] active-block': activeBlock === activityItemIdx,
								}" @click="() => sendMessageToParent('activeBlock', activityItemIdx)">
								<div v-if="activeBlock === activityItemIdx" class="trapezoid-button" @click.stop="">
									<div class="flex">
										<div class="py-1 px-2 cursor-pointer hover:bg-gray-200  transition hover:text-indigo-500"
											v-tooltip="trans('Add Block Before')"
											@click="() => sendMessageToParent('addBlock', { type: 'before', parentIndex: activityItemIdx })">
											<FontAwesomeIcon :icon='faSendBackward' fixed-width aria-hidden='true' />
										</div>

										<div class="py-1 px-2 cursor-pointer hover:bg-gray-200  hover:text-indigo-500  transition md:block hidden"
											v-tooltip="trans('Add Block after')"
											@click="() => sendMessageToParent('addBlock', { type: 'after', parentIndex: activityItemIdx })">
											<FontAwesomeIcon :icon='faBringForward' fixed-width aria-hidden='true' />
										</div>

										<div class="py-1 px-2 cursor-pointer hover:bg-red-100 hover:text-red-600  transition"
											v-tooltip="trans('Delete')"
											@click="() => sendMessageToParent('deleteBlock', activityItem)">
											<FontAwesomeIcon :icon='faTrashAlt' fixed-width aria-hidden='true' />
										</div>
									</div>
								</div>

								<component class="w-full" :is="getComponent(activityItem.type)" :webpageData="webpage"
									:blockData="activityItem" @autoSave="() => updateData(activityItem)"
									v-model="activityItem.web_block.layout.data.fieldValue" :screenType="screenType"/>
							</section>
						</template>
					</TransitionGroup>
				</div>
				<EmptyState v-else :data="{
					title: trans('Pick First Block For Your Website'),
					description: trans('Pick block from list'),
				}" />
			</div>
		</div>
	</div>
</template>

<style lang="scss">
.hover-dashed {
	@apply relative;

	&::after {
		content: "";
		@apply absolute inset-0 hover:bg-gray-200/30 border border-transparent hover:border-white/80 border-dashed cursor-pointer;
	}
}

.trapezoid-button {
	position: absolute;
	top: -37px;
	/* Sesuaikan agar masuk ke dalam border */
	left: 50%;
	transform: translateX(-50%);
	padding: 5px 20px;
	background-color: #4F46E5;
	color: white;
	font-size: 12px;
	font-weight: bold;
	cursor: pointer;
	clip-path: polygon(15% 0%, 85% 0%, 100% 100%, 0% 100%);
	transition: background 0.3s;
	box-shadow: 0 4px 0px #4F46E5;
	/* Efek agar menyatu dengan border */
	border: none;
}

.trapezoid-button:hover {
	background-color: #3F3ABF;
}
</style>
