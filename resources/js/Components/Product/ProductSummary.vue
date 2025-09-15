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
    data: PropsData
    gpsr?: Gpsr
    parts?: { id: number; name: string }[]
    type: string
	video?: string
	hide?:string[]
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
		<div class="bg-white rounded-xl p-4 lg:p-5">
			<div class="flex justify-between items-center border-b pb-3">
				<h2 class="text-base lg:text-lg font-semibold ">{{ type == 'product' ? trans("Product summary") :
					trans("Trade unit summary") }}</h2>
				<!-- the barcode label need provide from BE -->
				<span v-tooltip="'barcode label'" class="text-xs cursor-pointer">{{ data?.specifications?.barcode }}
					<FontAwesomeIcon :icon="faBarcode" />
				</span>
			</div>
			<dl class="mt-4 space-y-6 text-sm">
				<div class="space-y-3">
					<div v-if="!hide?.includes('code')" class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Code") }}</dt>
						<dd class="font-medium">{{ data?.code }}</dd>
					</div>
					<div v-if="!hide?.includes('code')"  class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Product Name") }}</dt>
						<dd class="font-medium max-w-[236px] text-right">{{ data?.name }}</dd>
					</div>
					<div  v-if="!hide?.includes('cpnp')" class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("CPNP Number") }}</dt>
						<dd class="font-medium">-</dd>
					</div>
					<div  v-if="!hide?.includes('ufi')" class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("UFI (Poison Centres)") }}</dt>
						<dd class="font-medium">-</dd>
					</div>
					<div  v-if="!hide?.includes('created_at')" class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Added date") }}</dt>
						<dd class="font-medium">{{ useFormatTime(data?.created_at) }}</dd>
					</div>
					<div  v-if="!hide?.includes('stock')" class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Stock") }}</dt>
						<dd class="font-medium">
							{{ data?.stock }} {{ data?.unit }}
						</dd>
					</div>
					<div v-if="type == 'product' && !hide?.includes('price')" class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Price") }}</dt>
						<dd class="font-semibold text-green-600">
							{{ locale.currencyFormat(data?.currency_code, data?.price) }}
						</dd>
					</div>
					<div v-if="type == 'product' && !hide?.includes('rrp')" class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">RRP</dt>
						<dd class="font-semibold">
							{{ locale.currencyFormat(data?.currency_code, data?.rrp) }}
							<span class="ml-1 text-xs text-gray-500">
								({{
								((data?.rrp - data?.price) /
								data?.price * 100).toFixed(2)
								}}%)
							</span>
						</dd>
					</div>
					<div v-if="!hide?.includes('Weight')" class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Weight") }}</dt>
						<dd class="font-medium">
							{{ locale.number(data?.specifications?.gross_weight) }} gr
						</dd>
					</div>
					<div class="flex justify-between flex-wrap gap-1">
						<dt class="text-gray-500">{{ trans("Dimension") }}</dt>
						<dd class="font-medium">
							{{ data?.product?.data?.spesifications?.dimenison[0] ?? '-' }}
						</dd>
					</div>

					<!-- Combined Description -->
					<div v-if="data?.description_title || data?.description || data?.description_extra" class="space-y-2">
						<dt class="text-gray-500">{{ trans("Description") }}</dt>
						<dd class="font-medium">
							<div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
								<div v-if="!showFullDescription && data?.description_extra">
									<!-- Show title and description, hide extra -->
									<div v-if="data?.description_title" class="text-base font-semibold text-gray-700 leading-relaxed mb-3" 
										 v-html="data?.description_title">
									</div>
									<div v-if="data?.description" class="text-sm text-gray-700 leading-relaxed" 
										 v-html="data?.description">
									</div>
									<button @click="showFullDescription = true"
										class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1 mt-2">
										<FontAwesomeIcon icon="fal fa-chevron-down" />
										{{ trans("Read more") }}
									</button>
								</div>
								<div v-else>
									<!-- Show all content -->
									<div v-if="data?.description_title" class="text-base font-semibold text-gray-700 leading-relaxed mb-3" 
										 v-html="data?.description_title">
									</div>
									<div v-if="data?.description" class="text-sm text-gray-700 leading-relaxed mb-3" 
										 v-html="data?.description">
									</div>
									<div v-if="data?.description_extra" class="text-sm text-gray-700 leading-relaxed" 
										 v-html="data?.description_extra">
									</div>
									<button v-if="data?.description_extra"
										@click="showFullDescription = false"
										class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1 mt-2">
										<FontAwesomeIcon icon="fal fa-chevron-up" />
										{{ trans("Read less") }}
									</button>
								</div>
							</div>
						</dd>
					</div>
				</div>

				<!-- Video Section - Accordion -->
				<div class="space-y-3">
					<Accordion multiple>
						<AccordionPanel value="0">
							<AccordionHeader>
								<div class="flex items-center gap-2">
									<span class="font-medium text-base">{{ trans("Video (vimeo)") }}</span>
									<FontAwesomeIcon icon="fal fa-video" class="text-purple-500" />
								</div>
							</AccordionHeader>
							<AccordionContent>
								<div class="py-2">
									<div v-if="embedUrl" class="w-full h-auto aspect-video rounded-lg">
										<iframe :src="embedUrl" class="w-full h-full rounded-lg" frameborder="0"
											allow="autoplay; fullscreen" allowfullscreen></iframe>
									</div>
									<div v-else
										class="w-full h-auto aspect-video rounded-lg bg-gray-200 flex items-center justify-center">
										<span>No Video to Show</span>
									</div>
								</div>
							</AccordionContent>
						</AccordionPanel>
						<AccordionPanel value="1" v-if="parts">
							<AccordionHeader>
								<div class="flex items-center gap-2">
									<span class="font-medium text-base">{{ trans("Parts") }}</span>
									<FontAwesomeIcon icon="fal fa-puzzle-piece" class="text-green-500" />
								</div>
							</AccordionHeader>
							<AccordionContent>
								<div class="py-2">
									<dt class="text-gray-500">{{ trans("Parts") }}</dt>
									<ul class="list-disc list-inside text-gray-700 mt-1 space-y-1">
										<li v-for="part in parts" :key="part.id">
											{{ part.name }}
										</li>
									</ul>
								</div>
							</AccordionContent>
						</AccordionPanel>
						<AccordionPanel value="2">
							<AccordionHeader>
								<div class="flex items-center gap-2">
									<span class="font-medium text-base">{{ trans("Outer") }}</span>
									<FontAwesomeIcon icon="fal fa-box" class="text-orange-500" />
								</div>
							</AccordionHeader>
							<AccordionContent>
								<div class="space-y-3 py-2">
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Unit per outer") }}</dt>
										<dd class="font-medium">
											-
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Pricing policy") }}</dt>
										<dd class="font-medium">
											-
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Outer price") }}</dt>
										<dd class="font-medium">
											-
										</dd>
									</div>
								</div>
							</AccordionContent>
						</AccordionPanel>
						<AccordionPanel value="3">
							<AccordionHeader>
								<div class="flex items-center gap-2">
									<span class="font-medium text-base">{{ trans("Properties") }}</span>
									<FontAwesomeIcon icon="fal fa-puzzle-piece" class="text-indigo-500" />
								</div>
							</AccordionHeader>
							<AccordionContent>
								<div class="space-y-3 py-2">
									<div>
										<dt class="text-gray-500">{{ trans("Materials/Ingredients") }}</dt>
										<ul class="list-disc list-inside text-gray-700 mt-1 space-y-1">
											<li v-for="ingredient in data?.specifications?.ingredients"
												:key="ingredient.id">
												{{ ingredient }}
											</li>
										</ul>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Country of origin") }}</dt>
										<dd class="font-medium">
											<div v-if="properties?.country_of_origin.code">
												<img class="inline-block h-[14px] w-[20px] object-cover rounded-sm"
													:src="'/flags/' + properties?.country_of_origin.code.toLowerCase() + '.png'"
													:alt="`Bendera ${'us'}`" loading="lazy" />
												<span class="ml-2">{{ properties.country_of_origin.name
													}}</span>
											</div>
											<span v-else>-</span>
										</dd>
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
											{{ properties?.duty_rate }}
										</dd>
									</div>
									<div class="flex justify-between">
										<dt v-tooltip="'Harmonized Tariff Schedule of the United States Code'"
											class="text-gray-500">{{ trans("HTS US") }}
											<img class="inline-block h-[14px] w-[20px] object-cover rounded-sm"
												:src="'/flags/' + 'us' + '.png'" :alt="`Bendera ${'us'}`"
												loading="lazy" />
										</dt>
										<dd class="font-medium">
											-
										</dd>
									</div>
								</div>
							</AccordionContent>
						</AccordionPanel>
						<AccordionPanel value="4">
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
											-
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("UN class") }}</dt>
										<dd class="font-medium">
											-
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Packing group") }}</dt>
										<dd class="font-medium">
											-
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Proper shipping name") }}</dt>
										<dd class="font-medium">
											-
										</dd>
									</div>
									<div class="flex justify-between">
										<dt class="text-gray-500">{{ trans("Hazard identification number") }}</dt>
										<dd class="font-medium">
											-
										</dd>
									</div>
								</div>
							</AccordionContent>
						</AccordionPanel>
						<AccordionPanel value="5" v-if="gpsr">
							<AccordionHeader>
								<div class="flex items-center gap-2">
									<span class="font-medium text-base">{{ trans("GPSR (if empty will use Part GPSR)")
										}}</span>
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
													<span v-if="gpsr?.manufacturer">{{
														gpsr?.manufacturer}}</span>
													<FontAwesomeIcon v-else icon="fal fa-info-circle"
														class="text-gray-400"
														v-tooltip="trans('No manufacturer specified')" />
												</dd>
											</div>

											<div class="flex justify-between items-start">
												<dt class="text-gray-500 text-sm">{{ trans("EU responsible") }}</dt>
												<dd class="font-medium text-sm text-right flex-1 ml-2">
													<span v-if="gpsr?.eu_responsible">{{
														gpsr?.eu_responsible }}</span>
													<FontAwesomeIcon v-else icon="fal fa-info-circle"
														class="text-gray-400"
														v-tooltip="trans('No EU responsible specified')" />
												</dd>
											</div>

											<div class="flex justify-between items-start">
												<dt class="text-gray-500 text-sm">{{ trans("Class & category of danger")
													}}</dt>
												<dd class="font-medium text-sm text-right flex-1 ml-2">
													<span v-if="gpsr?.gpsr_class_category_danger">{{
														gpsr?.gpsr_class_category_danger }}</span>
													<FontAwesomeIcon v-else icon="fal fa-info-circle"
														class="text-gray-400"
														v-tooltip="trans('No danger class specified')" />
												</dd>
											</div>

											<div class="flex justify-between items-start">
												<dt class="text-gray-500 text-sm">{{ trans("Product GPSR Languages")
													}}</dt>
												<dd class="font-medium text-sm text-right flex-1 ml-2">
													<span v-if="gpsr?.product_languages">{{
														gpsr?.product_languages }}</span>
													<FontAwesomeIcon v-else icon="fal fa-info-circle"
														class="text-gray-400"
														v-tooltip="trans('No languages specified')" />
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
										<div v-if="gpsr?.warnings">
											<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
												<div v-if="!showFullWarnings && gpsr?.warnings.length > 200"
													class="space-y-2">
													<p class="text-sm text-gray-700 leading-relaxed">
														{{ gpsr?.warnings.substring(0, 200) }}...
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
														{{ gpsr?.warnings }}</p>
													<button v-if="gpsr?.warnings.length > 200"
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
										<div v-if="gpsr?.how_to_use">
											<div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
												<div v-if="!showFullInstructions && gpsr?.how_to_use.length > 200"
													class="space-y-2">
													<p class="text-sm text-gray-700 leading-relaxed">
														{{ gpsr?.how_to_use.substring(0, 200) }}...
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
														{{ gpsr?.how_to_use }}</p>
													<button v-if="gpsr?.how_to_use.length > 200"
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