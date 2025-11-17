<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { inject, ref, computed } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { faTrash as falTrash, faEdit, faExternalLink, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo } from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { Accordion, AccordionPanel, AccordionHeader, AccordionContent } from "primevue"
import { faTag } from "@far"
import { ProductShowcase } from "@/types/product-showcase"
import FractionDisplay from "../DataDisplay/FractionDisplay.vue"

interface Stats {
	amount: number | null
	amount_ly: number | null
	name: string
	percentage: number | null
}

interface TradeUnit {
	brand: Record<string, unknown>
	brand_routes: {
		index_brand: routeType
		store_brand: routeType
		update_brand: routeType
		delete_brand: routeType
		attach_brand: routeType
		detach_brand: routeType
	}
	tag_routes: {
		index_tag: routeType
		store_tag: routeType
		update_tag: routeType
		delete_tag: routeType
		attach_tag: routeType
		detach_tag: routeType
	}
	tags: Record<string, unknown>[]
	tags_selected_id: number[]
}

interface Gpsr {
	acute_toxicity: boolean
	corrosive: boolean
	eu_responsible: string | null
	explosive: boolean
	flammable: boolean
	gas_under_pressure: boolean
	gpsr_class_category_danger: string | null
	hazard_environment: boolean
	health_hazard: boolean | null
	how_to_use: string
	manufacturer: string | null
	oxidising: boolean
	product_languages: string | null
	warnings: string | null
}

interface PropsData {
	stockImagesRoute: routeType
	uploadImageRoute: routeType
	attachImageRoute: routeType
	deleteImageRoute: routeType
	imagesUploadedRoutes: routeType
	acute_toxicity: boolean
	corrosive: boolean
	eu_responsible: string | null
	explosive: boolean
	flammable: boolean
	gas_under_pressure: boolean
	gpsr_class_category_danger: string | null
	hazard_environment: boolean
	health_hazard: boolean | null
	how_to_use: string
	manufacturer: string | null
	oxidising: boolean
	product_languages: string | null
	warnings: string | null
	stats: Stats[] | null
	trade_units: TradeUnit[]
}

const props = withDefaults(
	defineProps<{
		data: ProductShowcase
        // gpsr?: Gpsr
		parts?: { id: number; name: string }[]
		type?: string
		video?: string
		hide?: string[]
		properties?: {
			country_of_origin?: { code: string; name: string }
			tariff_code?: string
			duty_rate?: string
		}
	}>(),
	{
		type: "product", // default type is 'product' (alternative: 'trade_unit')
	}
)

library.add(
	faCircle,
	faTrash,
	falTrash,
	faEdit,
	faExternalLink,
	faPlay,
	faPlus,
	faBarcode,
	faPuzzlePiece,
	faShieldAlt,
	faInfoCircle,
	faChevronDown,
	faChevronUp,
	faBox,
	faVideo
)

// ---------------- State ----------------
const locale = inject("locale", aikuLocaleStructure)
const showFullWarnings = ref(false)
const showFullInstructions = ref(false)
const showFullDescription = ref(false)



// ---------------- Hazards ----------------
const hazardDefinitions = ref([
	{ key: "acuteToxicity", name: "Acute Toxicity", icon: "toxic-icon.png" },
	{ key: "corrosive", name: "Corrosive", icon: "corrosive-icon.png" },
	{ key: "explosive", name: "Explosive", icon: "explosive.jpg" },
	{ key: "flammable", name: "Flammable", icon: "flammable.png" },
	{ key: "gasUnderPressure", name: "Gas under pressure", icon: "gas.png" },
	{ key: "environmentHazard", name: "Hazards to the environment", icon: "hazard-env.png" },
	{ key: "healthHazard", name: "Health hazard", icon: "health-hazard.png" },
	{ key: "oxidising", name: "Oxidising", icon: "oxidising.png" },
	{ key: "seriousHealthHazard", name: "Serious Health hazard", icon: "serious-health-hazard.png" }
])

const getHazardIconPath = (iconName: string) => `/hazardIcon/${iconName}`

const getActiveHazards = () => {
	return hazardDefinitions.value.filter((hazard) => {
		switch (hazard.key) {
			case "acuteToxicity":
				return props.data?.gpsr?.acute_toxicity
			case "corrosive":
				return props.data?.gpsr?.corrosive
			case "explosive":
				return props.data?.gpsr?.explosive
			case "flammable":
				return props.data?.gpsr?.flammable
			case "gasUnderPressure":
				return props.data?.gpsr?.gas_under_pressure
			case "environmentHazard":
				return props.data?.gpsr?.hazard_environment
			case "healthHazard":
				return props.data?.gpsr?.health_hazard
			case "oxidising":
				return props.data?.gpsr?.oxidising
			default:
				return false
		}
	})
}


// --- Normalize video URL to embed form ---
function normalizeVideoUrl(url: string): string {
	if (!url) return ""

	// YouTube
	const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/)
	if (ytMatch) {
		return `https://www.youtube.com/embed/${ytMatch[1]}`
	}

	// Vimeo
	const vimeoMatch = url.match(/vimeo\.com\/(\d+)/)
	if (vimeoMatch) {
		return `https://player.vimeo.com/video/${vimeoMatch[1]}`
	}

	return url // fallback
}

const embedUrl = computed(() => normalizeVideoUrl(props.video || ""))

console.log('product summary : ', props)
</script>


<template>
	<!-- Product Summary -->


	<div>
		<div class="bg-white rounded-xl px-4 lg:px-5">
			<!-- <div class="flex justify-between items-center border-b pb-3">
				<h2 class="text-base lg:text-lg font-semibold "></h2>

				
			</div> -->
			
			<dl class="mt-4 space-y-6 text-sm">
				<div class="space-y-3">
					<!-- Section: Since -->
                    <div  class="flex justify-between flex-wrap gap-1">
                        <dt class="text-gray-500">{{ trans("Since") }}</dt>
                        <dd class="font-medium">{{ useFormatTime(data?.created_at) }}</dd>
                    </div>
					
					<!-- Section: Units -->
					<div  class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Units") }}</dt>
						<dd class="font-medium max-w-[236px] text-right">{{ data?.units }}  ({{data.unit}}) </dd>
					</div>

					<!-- Section: Weight marketing -->
                    <div  class="flex justify-between flex-wrap gap-1">
                        <dt class="text-gray-500">{{ trans("Weight") }} <span class="text-xs font-light text-gray-500">({{trans('Marketing')}})</span></dt>
                        <dd class="font-medium">
                            {{ data?.marketing_weight }}
                        </dd>
                    </div>

					<!-- Section: Weight shipping -->
					<div class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Weight") }} <span class="text-xs font-light text-gray-500">({{trans('Shipping')}})</span></dt>
						<dd class="font-medium">
							{{ data?.gross_weight }}
						</dd>
					</div>

					<!-- Section: Marketing Dimensions -->
					<div  class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Dimensions") }}</dt>
						<dd class="font-medium">
							{{ data?.marketing_dimensions}}
						</dd>
					</div>

					<!-- Section: Barcode -->
                    <div  class="flex justify-between flex-wrap gap-1">
                        <dt class="text-gray-500">{{ trans("Barcode") }} <FontAwesomeIcon :icon="faBarcode" /></dt>
                        <dd class="font-medium">
                            {{ data?.barcode}}
                        </dd>
                    </div>

					<!-- Section: Picking -->
                    <div  class="flex justify-between flex-wrap gap-1">
                        <dt class="text-gray-500">{{ trans("Picking") }}</dt>
						<dd class="w-full border border-gray-300 px-2.5 py-1.5 rounded">
							<div v-for="pick in data.picking_factor" class="grid grid-cols-4">
								<div class="col-span-3">
									<div class="w-fit">
										<span class="xprimaryLink ">{{ pick.org_stock_code }}</span>
										<span class="italic opacity-60">({{ pick.org_stock_id }})</span>
									</div>

									<div v-tooltip="trans('Note')" class="text-gray-400 text-xs w-fit">
										{{ pick.note || '-' }}
									</div>
								</div>
								
								<div class=" text-right">
									<FractionDisplay
										:fractionData="pick.picking_factor"
									/>
								</div>
							</div>
						</dd>
                    </div>
				</div>


				<div class="space-y-3">
					<Accordion multiple>


						<AccordionPanel v-if="!hide?.includes('brands_tags') && type == 'trade_unit'" value="2">
							<AccordionHeader>
								<div class="flex items-center gap-2">
									<span class="font-medium text-base">{{ trans("Brands & Tags") }}</span>
									<FontAwesomeIcon :icon="faTag" class="text-orange-500" />
								</div>
							</AccordionHeader>
							<AccordionContent>
								<div class="space-y-3 py-2">
									<!-- Brands -->
									<div class="flex justify-between items-start gap-3">
										<dt class="text-gray-500 whitespace-nowrap">{{ trans("Brands") }}</dt>
										<dd class="font-medium flex flex-wrap gap-1">
											<span
												v-for="brand in data.brands"
												:key="brand.id"
												v-tooltip="'brand'"
												class="px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-600 border border-blue-100"
											>
												{{ brand.name }}
											</span>
										</dd>
									</div>

									<!-- Tags -->
									<div class="flex justify-between items-start gap-3">
										<dt class="text-gray-500 whitespace-nowrap">{{ trans("Tags") }}</dt>
										<dd class="font-medium flex flex-wrap gap-1">
											<span
												v-for="tag in data.tags"
												:key="tag.id"
												v-tooltip="'tag'"
												class="px-2 py-0.5 rounded-full text-xs bg-green-100 bg-green-50 border border-blue-100"
											>
												{{ tag.name }}
											</span>
										</dd>
									</div>
								</div>
							</AccordionContent>
						</AccordionPanel>

						<AccordionPanel v-if="!hide?.includes('properties')" value="4">
							<AccordionHeader>
								<div class="flex items-center gap-2">
									<span class="font-medium text-base">{{ trans("Properties") }}</span>
									<FontAwesomeIcon icon="fal fa-puzzle-piece" class="text-indigo-500" />
								</div>
							</AccordionHeader>
							<AccordionContent>
								<div class="space-y-3 py-2">
                                    <div  class="flex justify-between flex-wrap gap-1">
                                        <dt class="text-gray-500">{{ trans("Materials/Ingredients") }}</dt>
                                        <dd class="font-medium max-w-[236px] text-right">{{ data?.marketing_ingredients }}</dd>
                                    </div>

									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Country of origin") }}</dt>
										<dd class="font-medium">
											<div v-if="data?.country_of_origin?.code">
												<img class="inline-block h-[14px] w-[20px] object-cover rounded-sm"
													:src="'/flags/' + data?.country_of_origin.code.toLowerCase() + '.png'"
													loading="lazy" />
												<span class="ml-2">{{ data.country_of_origin.name
												}}</span>
											</div>

										</dd>
									</div>
                                    <div v-if="!hide?.includes('cpnp')" class="flex justify-between flex-wrap gap-1">
                                        <dt class="text-gray-500">{{ trans("CPNP Number") }}</dt>
                                        <dd class="font-medium">{{data?.cpnp_number}}</dd>
                                    </div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Tariff code") }}</dt>
										<dd class="font-medium">
											{{ properties?.tariff_code || '-' }}
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Duty rate") }}</dt>
										<dd class="font-medium">
											{{ data?.duty_rate }}
										</dd>
									</div>
									<div class="flex justify-between">
										<dt v-tooltip="'Harmonized Tariff Schedule of the United States Code'"
											class="text-gray-500">{{ trans("HTS US") }}
											<img class="inline-block h-[14px] w-[20px] object-cover rounded-sm"
												:src="'/flags/' + 'us' + '.png'" :alt="`Flag ${'us'}`"
												loading="lazy" />
										</dt>
										<dd class="font-medium">
                                            {{ data?.duty_rate }}
										</dd>
									</div>


                                    <div v-if="!hide?.includes('ufi')" class="flex justify-between flex-wrap gap-1">
                                        <dt class="text-gray-500">{{ trans("UFI Number") }}</dt>
                                        <dd class="font-medium">{{data?.ufi_number}}</dd>
                                    </div>
                                    <div v-if="!hide?.includes('ufi')" class="flex justify-between flex-wrap gap-1">
                                        <dt class="text-gray-500">{{ trans("SCPN Number") }}</dt>
                                        <dd class="font-medium">{{data?.scpn_number}}</dd>
                                    </div>

								</div>
							</AccordionContent>
						</AccordionPanel>
						<AccordionPanel v-if="!hide?.includes('health')" value="5">
							<AccordionHeader>
								<div class="flex items-center gap-2">
									<span class="font-medium text-base">{{ trans("Health & Safety") }}</span>
									<FontAwesomeIcon icon="fal fa-shield-alt" class="text-red-500" />
								</div>
							</AccordionHeader>
							<AccordionContent>
								<div class="space-y-3 py-2">
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("UN number") }}</dt>
										<dd class="font-medium">
                                            {{data?.un_number}}
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("UN class") }}</dt>
										<dd class="font-medium">
                                            {{data?.un_class}}
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Packing group") }}</dt>
										<dd class="font-medium">
                                            {{data?.packing_group}}
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Proper shipping name") }}</dt>
										<dd class="font-medium">
                                            {{data?.proper_shipping_name}}
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Hazard identification number") }}</dt>
										<dd class="font-medium">
                                            {{data?.hazard_identification_number}}
										</dd>
									</div>
								</div>
							</AccordionContent>
						</AccordionPanel>
						<AccordionPanel value="6" >
							<AccordionHeader>
								<div class="flex items-center gap-2">
									<span class="font-medium text-base">GPSR</span>
									<FontAwesomeIcon icon="fal fa-shield-alt" class="text-blue-500" />
								</div>
							</AccordionHeader>
							<AccordionContent>
								<div class="space-y-4 pt-2">
									<!-- Basic Information -->
									<div class="grid grid-cols-1 gap-4">
										<div class="space-y-3">
											<div class="flex justify-between items-start">
												<dt class="text-gray-500 text-sm">{{ trans("Manufacturer") }}</dt>
												<dd class="font-medium text-sm text-right flex-1 ml-2">
													<span >{{data?.gpsr_manufacturer }}</span>
												</dd>
											</div>

											<div class="flex justify-between items-start">
												<dt class="text-gray-500 text-sm">{{ trans("EU responsible") }}</dt>
												<dd class="font-medium text-sm text-right flex-1 ml-2">
													<span >{{data?.gpsr_eu_responsible }}</span>
                                                </dd>
											</div>

											<div class="flex justify-between items-start">
												<dt class="text-gray-500 text-sm">{{ trans("Class & category of danger")
												}}</dt>
												<dd class="font-medium text-sm text-right flex-1 ml-2">
													<span>{{data?.gpsr_class_category_danger }}</span>
													
												</dd>
											</div>

											<div class="flex justify-between items-start">
												<dt class="text-gray-500 text-sm">{{ trans("Product GPSR Languages")
												}}</dt>
												<dd class="font-medium text-sm text-right flex-1 ml-2">
													<span>{{data?.gpsr_product_languages }}</span>
													
												</dd>
											</div>
										</div>
									</div>

									<!-- Hazard Icons Section -->
									<div class="border-t pt-4">
										<h5 class="text-sm font-medium text-gray-700 mb-3">{{ trans("Hazard Symbols") }}
										</h5>
										<div class="flex gap-2 overflow-x-auto pb-2">
											<div v-for="hazard in getActiveHazards()" :key="hazard.key"
												class="flex-shrink-0 w-10 h-10 bg-white rounded border-2 border-red-200 p-1.5 shadow-sm"
												v-tooltip="hazard.name">
												<img :src="getHazardIconPath(hazard.icon)" :alt="hazard.name"
													class="w-full h-full object-contain">
											</div>
											<div v-if="getActiveHazards().length === 0"
												class="flex items-center text-gray-400 text-sm">
												<FontAwesomeIcon icon="fal fa-info-circle" class="mr-2" />
												{{ trans("No hazards identified") }}
											</div>
										</div>
									</div>

									<!-- Warnings Section -->
									<div class="border-t pt-4">
										<h5 class="text-sm font-medium text-gray-700 mb-2">{{ trans("Warnings") }}
										</h5>
										<div v-if="data?.gpsr_warnings">
											<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
												<div v-if="!showFullWarnings && data?.gpsr_warnings.length > 200"
													class="space-y-2">
													<p class="text-sm text-gray-700 leading-relaxed">
														{{ data?.gpsr_warnings.substring(0, 200) }}...
													</p>
													<button @click="showFullWarnings = true"
														class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1">
														<FontAwesomeIcon icon="fal fa-chevron-down" />
														{{ trans("Show more") }}
													</button>
												</div>
												<div v-else class="space-y-2">
													<p
														class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">
														{{ data?.gpsr_warnings }}</p>
													<button v-if="data?.gpsr_warnings.length > 200"
														@click="showFullWarnings = false"
														class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1">
														<FontAwesomeIcon icon="fal fa-chevron-up" />
														{{ trans("Show less") }}
													</button>
												</div>
											</div>
										</div>
										<div v-else class="flex items-center text-gray-400 text-sm">
											<FontAwesomeIcon icon="fal fa-info-circle" class="mr-2" />
											{{ trans("No warnings specified") }}
										</div>
									</div>

									<!-- How to Use Section -->
									<div class="border-t pt-4">
										<h5 class="text-sm font-medium text-gray-700 mb-2">{{ trans("How to use") }}
										</h5>
										<div v-if="data?.gpsr_manual">
											<div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
												<div v-if="!showFullInstructions && data?.gpsr_manual.length > 200"
													class="space-y-2">
													<p class="text-sm text-gray-700 leading-relaxed">
														{{ data?.gpsr_manual.substring(0, 200) }}...
													</p>
													<button @click="showFullInstructions = true"
														class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1">
														<FontAwesomeIcon icon="fal fa-chevron-down" />
														{{ trans("Show more") }}
													</button>
												</div>
												<div v-else class="space-y-2">
													<p
														class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">
														{{ data?.gpsr_manual }}</p>
													<button v-if="data?.gpsr_manual.length > 200"
														@click="showFullInstructions = false"
														class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1">
														<FontAwesomeIcon icon="fal fa-chevron-up" />
														{{ trans("Show less") }}
													</button>
												</div>
											</div>
										</div>
										<div v-else class="flex items-center text-gray-400 text-sm">
											<FontAwesomeIcon icon="fal fa-info-circle" class="mr-2" />
											{{ trans("No instructions specified") }}
										</div>
									</div>
								</div>
							</AccordionContent>
						</AccordionPanel>
					</Accordion>
				</div>
			</dl>
		</div>
	</div>
</template>

<style scoped>
/* Add custom styles if needed for better text readability */
.whitespace-pre-wrap {
	white-space: pre-wrap;
	word-wrap: break-word;
}

/* Remove all padding from accordion */
:deep(.p-accordion) {
	padding: 0;
}

:deep(.p-accordion-panel) {
	border: none;
}

:deep(.p-accordionheader) {
	padding: 10px 0;
	background: #f8fafc;
	border-radius: 0.5rem;
	border: none;
	background-color: #ffffff;
}

:deep(.p-accordionheader:hover) {
	background: #e2e8f0;
}

:deep(.p-accordioncontent-content) {
	padding: 0 !important;
	border: none;
}

:deep(.p-accordionheader-text) {
	padding: 0.75rem 1rem;
	width: 100%;
}
</style>