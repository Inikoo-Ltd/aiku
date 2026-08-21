<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"
import About from "@/Iris/Components/BlocksUtils/ProductDescription/AboutProduct.vue"
import MarketingMaterials from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/MarketingMaterials.vue"
import Faq from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/Faq.vue"
import Customisation from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/Customisation.vue"
import Storage from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/Storage.vue"
import LabelingGuide from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/LabelingGuide.vue"
import {
	isProductTabVisible,
	type FamilyExtraDescriptionTabKey,
} from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/tabVisibility"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
	indexBlock: number
}>()

const layout = inject("layout", {}) as any

const tabsData = computed(() => props.fieldValue?.tabs ?? {})

const productData = computed(() => props.fieldValue?.product ?? {})

const isLoggedIn = computed(() => layout?.iris?.is_logged_in ?? true)

const tabs = computed(() =>
	[
		{ key: "about", label: ctrans("About the Product") },
		{ key: "customisation", label: ctrans("Customisation") },
		{ key: "labeling guide", label: ctrans("Labeling Guide") },
		{ key: "storage_and_shelf_life", label: ctrans("Storage & Shelf Life") },
		{ key: "marketing", label: ctrans("Marketing Materials") },
		{ key: "faq", label: ctrans("FAQ") },
	].filter(tab =>
		isProductTabVisible(
			tab.key as FamilyExtraDescriptionTabKey,
			tabsData.value,
			productData.value,
			isLoggedIn.value
		)
	)
)

const activeTab = ref(tabs.value[0]?.key ?? "")

watch(tabs, visibleTabs => {
	if (!visibleTabs.some(tab => tab.key === activeTab.value)) {
		activeTab.value = visibleTabs[0]?.key ?? ""
	}
})

const childFieldValue = computed(() => ({
	...props.fieldValue,
	family: tabsData.value,
}))

const containerStyle = computed(() => ({
	...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
	...getStyles(props.fieldValue?.description_tabs?.container?.properties),
	width: "auto",
}))

const component = (tab: string) => {
	switch (tab) {
		case "about":
			return About
		case "marketing":
			return MarketingMaterials
		case "faq":
			return Faq
		case "customisation":
			return Customisation
		case "labeling guide":
			return LabelingGuide
		case "storage_and_shelf_life":
			return Storage
		default:
			return null
	}
}

const sectionStyle = computed(() => {
	const bg =
		props.fieldValue?.description_tabs?.container?.properties?.background?.[props.screenType]

	return {
		backgroundColor: bg?.color || undefined,
		backgroundImage: bg?.image?.original ? `url(${bg.image.original})` : undefined,
		backgroundSize: "cover",
		backgroundPosition: "center",
	}
})

const isMobile = computed(() => props.screenType === "mobile")
</script>

<template>
	<section
		v-if="tabs.length"
		class="w-full"
		:id="'product-description-' + indexBlock"
		:style="sectionStyle">
		<div
			class="mw-full max-w-[1700px] px-4 py-4 sm:px-8 xl:px-14 2xl:max-w-[1800px] 2xl:px-14"
			:style="containerStyle">
			<!-- TOP NAV -->
			<div class="border-b border-[#9a9a9a] bg-[#fbfaf9]">
				<!-- Mobile -->
				<div v-if="isMobile" class="py-3">
					<select
						v-model="activeTab"
						class="w-full rounded-md border border-[#d9d9d9] bg-transparent px-4 py-3 text-[13px] focus:outline-none">
						<option v-for="tab in tabs" :key="tab.key" :value="tab.key">
							{{ tab.label }}
						</option>
					</select>
				</div>

				<!-- Tablet & Desktop -->
				<div
					v-else
					class="flex flex-wrap items-center justify-center lg:justify-end gap-3 md:gap-6 lg:gap-10 2xl:gap-14">
					<button
						v-for="tab in tabs"
						:key="tab.key"
						@click="activeTab = tab.key"
						class="relative -mb-px border-b px-1 md:px-2 py-3 md:py-4 text-[10px] sm:text-[11px] md:text-[12px] transition-all duration-200"
						:class="
							activeTab === tab.key
								? 'border-primary text-primary'
								: 'border-transparent text-[#9a9a9a]'
						">
						{{ tab.label }}
					</button>
				</div>
			</div>

			<!-- CONTENT -->
			<component
				:is="component(activeTab)"
				:field-value="childFieldValue"
				:screen-type="screenType"
				:faqs="tabsData?.faq" />
		</div>
	</section>
</template>

<style scoped>
:deep(p) {
	margin-bottom: 18px;
}

:deep(p:last-child) {
	margin-bottom: 0;
}

:deep(h2),
:deep(h3),
:deep(h4),
:deep(h5),
:deep(h6) {
	margin-top: 18px;
	margin-bottom: 12px;
	font-weight: 500;
	color: #22374a;
}

:deep(ul),
:deep(ol) {
	margin-bottom: 18px;
	padding-left: 20px;
}

:deep(li) {
	margin-bottom: 6px;
}

:deep(img) {
	max-width: 100%;
}
</style>
