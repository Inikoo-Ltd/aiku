<script setup lang="ts">
import { computed, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
	faShieldCheck,
	faGlobe,
	faBarcode,
	faIndustry,
	faLanguage,
	faCalendarAlt,
	faWeightHanging,
	faRecycle,
	faBoxOpen,
	faCertificate,
	faExclamationTriangle,
	faFlask,
	faBookOpen,
	faFireAlt,
	faHashtag,
	faUserShield,
	faMapMarkerAlt,
	faDewpoint,
	faChevronDown,
	faCheckCircle,
} from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { getStyles } from "@/Composables/styles"
import { candleSafetyIcons } from "@/Composables/useSafetyIcons"
import { hasRegulatoryRowContent } from "@/Iris/Components/BlocksUtils/ProductDescription/regulatoryRows"

interface LabelInfoItem {
	show: boolean
	label: string
	value: any
}

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
	isWorkshop?: boolean
}>()

const CARD_ICONS: Record<string, any> = {
	markets: faGlobe,
	batch_number: faHashtag,
	country_of_origin: faMapMarkerAlt,
	barcode: faBarcode,
	manufacturer: faIndustry,
	uk_responsible_person: faUserShield,
	eu_responsible_person: faUserShield,
	languages: faLanguage,
}

const ROW_ICONS: Record<string, any> = {
	ingredients: faFlask,
	direction_for_use: faBookOpen,
	warnings_and_precautions: faExclamationTriangle,
	clp_ghs_pictograms: faFireAlt,
	ufi_number: faHashtag,
	safety_icons: faShieldCheck,
	best_before: faCalendarAlt,
	net_quantity: faWeightHanging,
	sorting_recycling_information: faRecycle,
	packaging_material_codes: faBoxOpen,
	ce_marking: faCertificate,
	ukca_marking: faCertificate,
	weee_symbol: faRecycle,
	ip_rating: faDewpoint,
}

const ROW_LABELS: Record<string, string> = {
	ingredients: "Ingredients/ INCI",
	direction_for_use: "Direction for Use",
	warnings_and_precautions: "Warning & Precautions",
	clp_ghs_pictograms: "CLP / GHS Pictograms",
	ufi_number: "UFI Number",
	safety_icons: "Safety Icons",
	best_before: "PAO / Expiry Date / Best Before",
	net_quantity: "Net Quantity",
	sorting_recycling_information: "Sorting / Recycling Information",
	packaging_material_codes: "Packaging Material Codes",
	ce_marking: "CE Markings",
	ukca_marking: "UKCA Marking",
	weee_symbol: "WEEE Symbol",
	ip_rating: "IP Rating",
}

const PRESENCE_KEYS = [
	"batch_number",
	"safety_icons",
	"sorting_recycling_information",
	"ce_marking",
	"ukca_marking",
	"weee_symbol",
	"ip_rating",
]

const containerStyle = computed(() =>
	getStyles(props.fieldValue?.regulatory?.container?.properties)
)

const labelInfo = computed<Record<string, LabelInfoItem>>(
	() => props.fieldValue?.product?.label_info ?? {}
)

const cards = computed(() =>
	Object.keys(CARD_ICONS)
		.map(key => ({ key, icon: CARD_ICONS[key], ...labelInfo.value[key] }))
		.filter(card => card.show)
)

const rows = computed(() =>
	Object.keys(ROW_ICONS)
		.filter(key => props.isWorkshop || hasRegulatoryRowContent(labelInfo.value[key]))
		.map(key => ({
			key,
			icon: ROW_ICONS[key],
			label: ctrans(ROW_LABELS[key]),
			show: false,
			value: null,
			...labelInfo.value[key],
		}))
)

const isPresenceRow = (key: string) => PRESENCE_KEYS.includes(key)

const flagSource = (code: string) => `/flags/${String(code).toLowerCase()}.png`

const openRow = ref<string | null>(null)

const toggleRow = (key: string) => {
	openRow.value = openRow.value === key ? null : key
}
</script>

<template>
	<div v-if="cards.length || rows.length" class="regulatory-panel py-5 md:py-6 lg:py-8" :style="containerStyle">
		<div class="flex items-start gap-3">
			<FontAwesomeIcon :icon="faShieldCheck" class="mt-1  text-primary " style="font-size: 40px;" />
			<div>
				<h2 class="!mt-0 !mb-1 text-[16px] font-semibold text-[#22374a] md:text-[18px]">
					{{ ctrans("Regulatory & Label Information") }}
				</h2>
				<p class="text-[12px] text-[#9a9a9a]">
					{{ ctrans("Key certifications and labeling details for this product.") }}
				</p>
			</div>
		</div>

		<div
			v-if="cards.length"
			class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
			<div
				v-for="card in cards"
				:key="card.key"
				class="flex items-start gap-3 rounded-[8px] border panel-border bg-white px-3 py-3">
				<span
					class="flex h-7 w-7 shrink-0 items-center justify-center  text-primary">
					<FontAwesomeIcon :icon="card.icon" class="text-[16px]" />
				</span>

				<div class="min-w-0">
					<div class="text-[10px] uppercase tracking-wide text-[#9a9a9a]">
						{{ card.label }}
					</div>

					<div class="mt-1 text-[12px] leading-[1.5] text-[#334155]">
						<div v-if="card.key === 'markets'" class="flex flex-wrap gap-1">
							<span
								v-for="market in card.value"
								:key="market.value"
								class="rounded border accent-chip px-1.5 py-0.5 text-[10px]">
								{{ market.label }}
							</span>
						</div>

						<div v-else-if="card.key === 'languages'" class="flex flex-wrap gap-1">
							<span
								v-for="language in card.value"
								:key="language.code"
								:title="language.name"
								class="rounded border accent-chip px-1.5 py-0.5 text-[10px] uppercase">
								{{ language.code }}
							</span>
						</div>

						<div v-else-if="card.key === 'country_of_origin'" class="flex items-center gap-2">
							<img
								:src="flagSource(card.value.code)"
								:alt="card.value.name"
								loading="lazy"
								class="h-[12px] w-[18px] rounded-sm object-cover" />
							<span>{{ card.value.name }}</span>
						</div>

						<div
							v-else-if="card.key === 'uk_responsible_person' || card.key === 'eu_responsible_person'">
							<div class="font-medium">{{ card.value?.name }}</div>
							<div class="text-[11px] text-[#64748b]">{{ card.value?.address }}</div>
						</div>

						<div v-else-if="isPresenceRow(card.key)">
							{{ ctrans("On the product") }}
						</div>

						<div v-else class="whitespace-pre-line break-words">
							{{ card.value }}
						</div>
					</div>
				</div>
			</div>
		</div>

		<div v-if="rows.length" class="mt-4 space-y-2">
			<div
				v-for="row in rows"
				:key="row.key"
				class="overflow-hidden rounded-[8px] border panel-border bg-white">
				<button
					type="button"
					class="flex w-full items-center gap-3 px-3 py-3 text-left"
					:aria-expanded="openRow === row.key"
					@click="toggleRow(row.key)">
					<FontAwesomeIcon :icon="row.icon" class="shrink-0 text-[12px] text-primary" />
					<span class="flex-1 text-[12px] text-[#334155] md:text-[13px]">{{ row.label }}</span>
					<FontAwesomeIcon
						:icon="faChevronDown"
						class="shrink-0 text-[11px] text-[#9a9a9a] transition-transform duration-200"
						:class="{ 'rotate-180': openRow === row.key }" />
				</button>

				<div
					v-show="openRow === row.key"
					class="border-t panel-divider px-3 py-3 text-[12px] leading-[1.7] text-[#334155]">
					<template v-if="!row.show">
						<span class="italic text-[#9a9a9a]">{{ ctrans("Not specified") }}</span>
					</template>

					<template v-else-if="row.key === 'safety_icons'">
						<div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
							<div
								v-for="safetyIcon in candleSafetyIcons"
								:key="safetyIcon.key"
								class="flex flex-col items-center gap-1 text-center">
								<img
									:src="safetyIcon.image"
									:alt="ctrans(safetyIcon.label)"
									loading="lazy"
									class="h-11 w-11 object-contain" />
								<span class="text-[9px] leading-tight text-[#64748b]">
									{{ ctrans(safetyIcon.label) }}
								</span>
							</div>
						</div>
					</template>

					<template v-else-if="row.key === 'clp_ghs_pictograms'">
						<div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
							<div
								v-for="pictogram in row.value"
								:key="pictogram.key"
								class="flex flex-col items-center gap-1 text-center">
								<img
									:src="pictogram.image"
									:alt="pictogram.label"
									loading="lazy"
									class="h-11 w-11 object-contain" />
								<span class="text-[9px] leading-tight text-[#64748b]">
									{{ pictogram.label }}
								</span>
							</div>
						</div>
					</template>

					<template v-else-if="row.key === 'packaging_material_codes'">
						<div class="flex flex-wrap gap-1">
							<span
								v-for="packagingMaterial in row.value"
								:key="packagingMaterial.value"
								:title="packagingMaterial.material"
								class="rounded border accent-chip px-1.5 py-0.5 text-[10px]">
								{{ packagingMaterial.code }}
							</span>
						</div>
					</template>

					<template v-else-if="row.key === 'best_before'">
						{{ row.value?.label }}
					</template>

					<template v-else-if="row.key === 'net_quantity'">
						{{ row.value?.formatted }}
					</template>

					<template v-else-if="isPresenceRow(row.key)">
						<span class="inline-flex items-center gap-1 accent-text">
							<FontAwesomeIcon :icon="faCheckCircle" class="text-[11px]" />
							{{ ctrans("Present on the label") }}
						</span>
					</template>

					<template v-else>
						<div class="whitespace-pre-line break-words">{{ row.value }}</div>
					</template>
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
.regulatory-panel {
	--regulatory-accent: var(--theme-color-4, #64748b);
}

.accent-surface {
	background-color: color-mix(in srgb, var(--regulatory-accent) 10%, white);
}

.panel-border {
	border-color: #e5e7eb;
}

.panel-divider {
	border-color: #f3f4f6;
}

.accent-chip {
	border-color: #d1d5db;
	background-color: color-mix(in srgb, var(--regulatory-accent) 8%, white);
	color: color-mix(in srgb, var(--regulatory-accent) 70%, black);
}

.accent-text {
	color: var(--regulatory-accent);
}
</style>
