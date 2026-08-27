<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBox, faSmoke, faTint, faFlask, faTags, faBoxOpen } from "@fal"
import { faCheckCircle } from "@fas"
import Image from "@common/Components/Image.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

library.add(faBox, faSmoke, faTint, faFlask, faTags, faBoxOpen, faCheckCircle)

const hasText = (value: unknown) => String(value ?? "").trim() !== ""

const customisation = computed(() => props.fieldValue?.customisation ?? {})

const family = computed(() => props.fieldValue?.family ?? {})

const containerStyle = computed(() => getStyles(customisation.value?.container?.properties))

const title = computed(() => customisation.value?.title ?? "")

const description = computed(() => customisation.value?.description ?? "")

const hasDescription = computed(() => hasText(description.value))

const link = computed(() => {
	const value = customisation.value?.link?.url

	return typeof value === "string"
		? { type: "external", href: value, canonical_url: null, id: null, target: "_self" }
		: (value ?? null)
})

const linkHref = computed(() => String(link.value?.href ?? "").trim())

const linkLabel = computed(() => customisation.value?.link?.text ?? "")

const hasLink = computed(
	() => hasText(linkHref.value) && linkHref.value !== "#" && hasText(linkLabel.value)
)

const image = computed(() => {
	const source = family.value?.extra_description_image
	const values = Array.isArray(source) ? source : Object.values(source ?? {})

	return values.filter((value: any) => hasText(value?.original))[0] ?? null
})

const hasImage = computed(() => Boolean(image.value))

const familyOptions = computed(() => {
	const source = family.value?.customize_option

	return Array.isArray(source) ? source : []
})

const hasOptionData = (option: any) =>
	Boolean(option?.available) || hasText(option?.moq) || hasText(option?.notes)

const highlights = computed(() =>
	familyOptions.value
		.filter((option: any) => option?.available)
		.map((option: any) => ({
			key: option.key,
			icon: option.icon,
			label: option.label,
		}))
)

const rows = computed(() =>
	familyOptions.value.filter(hasOptionData).map((option: any) => ({
		key: option.key,
		option: option.label,
		available: Boolean(option.available),
		moq: String(option.moq ?? "").trim(),
		notes: String(option.notes ?? "").trim(),
	}))
)

const columns = computed(() => ({
	option: customisation.value?.table?.option ?? "",
	available: customisation.value?.table?.available ?? "",
	moq: customisation.value?.table?.moq ?? "",
	notes: customisation.value?.table?.notes ?? "",
}))

const contactTitle = computed(() => String(customisation.value?.contact?.title ?? "").trim())

const contactDescription = computed(() => customisation.value?.contact?.description ?? "")

const contactButtonLabel = computed(() =>
	String(customisation.value?.contact?.button?.text ?? "").trim()
)

const contactButtonUrl = computed(() =>
	String(customisation.value?.contact?.button?.url ?? "").trim()
)

const hasContactButton = computed(
	() => hasText(contactButtonLabel.value) && hasText(contactButtonUrl.value)
)

const hasContact = computed(
	() => hasText(contactTitle.value) || hasText(contactDescription.value) || hasContactButton.value
)

const isMobile = computed(() => props.screenType === "mobile")
</script>

<template>
	<div class="w-full py-6 md:py-8 lg:py-10" :style="containerStyle">
		<div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between lg:gap-12">
			<div class="flex-1">
				<h2
					v-if="hasText(title)"
					class="text-[26px] font-semibold leading-tight md:text-[30px] lg:text-[34px]">
					{{ title }}
				</h2>

				<div
					v-if="hasDescription"
					class="mt-3 max-w-2xl text-[13px] leading-[1.75] md:text-[14px]"
					v-html="description" />

				<LinkIris
					v-if="hasLink"
					:href="link?.href"
					:type="link?.type"
					:canonical_url="link?.canonical_url"
					:id="link?.id"
					:target="link?.target ?? '_self'"
					class="mt-4 inline-block text-[13px] font-medium underline underline-offset-4 transition hover:text-[#C0899B] md:text-[14px]">
					{{ linkLabel }}
				</LinkIris>
			</div>

			<div
				v-if="hasImage"
				class="w-full max-w-[240px] shrink-0 self-center overflow-hidden rounded-[8px] lg:max-w-[280px] lg:self-start">
				<Image
					:src="image?.original"
					:srcset="image?.srcset"
					sizes="(min-width: 1024px) 280px, 60vw"
					:image-cover="false"
					class="h-auto w-full object-contain"
					:alt="family?.name || title" />
			</div>
		</div>

		<div
			v-if="highlights.length"
			class="mt-8 grid gap-3 sm:gap-4"
			:class="isMobile ? 'grid-cols-2' : 'grid-cols-3 lg:grid-cols-6'">
			<div v-for="highlight in highlights" :key="highlight.key ?? highlight.label">
				<div
					class="flex h-[72px] items-center justify-center rounded-[8px] bg-[#C0899B] text-white md:h-[80px] mb-4"
					:style="getStyles(customisation?.highlight?.container?.properties)">
					<FontAwesomeIcon :icon="highlight.icon" class="text-[30px] md:text-[34px]" />
				</div>

				<p class="mt-4 text-center text-[12px] leading-snug md:text-[16px]">
					{{ highlight.label }}
				</p>
			</div>
		</div>

		<div
			v-if="rows.length || hasContact"
			class="mt-8 grid grid-cols-1 gap-8 lg:gap-12"
			:class="rows.length && hasContact ? 'lg:grid-cols-[1.7fr_1fr]' : 'lg:grid-cols-1'">
			<div v-if="rows.length">
				<div v-if="isMobile" class="customisation-cards">
					<div v-for="row in rows" :key="row.key" class="customisation-card">
						<div class="customisation-card__head">
							<p class="customisation-card__title">{{ row.option }}</p>

							<FontAwesomeIcon
								v-if="row.available"
								icon="fas fa-check-circle"
								class="customisation-check" />

							<span v-else class="customisation-dash">—</span>
						</div>

						<p v-if="row.moq" class="customisation-card__moq">
							<span>{{ columns.moq }}:</span> {{ row.moq }}
						</p>

						<div
							v-if="row.notes"
							class="customisation-card__notes"
							v-html="row.notes" />
					</div>
				</div>

				<div
					v-else
					class="customisation-table-wrapper"
					:class="{ 'customisation-table-wrapper--solo': !hasContact }">
					<table class="customisation-table">
						<thead>
							<tr>
								<th class="customisation-table__th">{{ columns.option }}</th>
								<th
									class="customisation-table__th customisation-table__th--available">
									{{ columns.available }}
								</th>
								<th class="customisation-table__th customisation-table__th--moq">
									{{ columns.moq }}
								</th>
								<th class="customisation-table__th">{{ columns.notes }}</th>
							</tr>
						</thead>

						<tbody>
							<tr v-for="row in rows" :key="row.key">
								<td class="customisation-table__td customisation-table__td--option">
									{{ row.option }}
								</td>

								<td class="customisation-table__td customisation-table__td--center">
									<FontAwesomeIcon
										v-if="row.available"
										icon="fas fa-check-circle"
										class="customisation-check" />

									<span v-else class="customisation-dash">—</span>
								</td>

								<td class="customisation-table__td customisation-table__td--center">
									{{ row.moq }}
								</td>

								<td class="customisation-table__td" v-html="row.notes" />
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div v-if="hasContact">
				<h3 v-if="contactTitle" class="text-xl font-semibold md:text-2xl">
					{{ contactTitle }}
				</h3>

				<div
					v-if="contactDescription"
					class="mt-3 text-[12px] leading-[1.8] md:text-[13px]"
					v-html="contactDescription" />

				<a
					v-if="hasContactButton"
					:href="contactButtonUrl"
					class="mt-5 inline-block w-full md:w-auto">
					<button
						class="inline-flex w-full items-center justify-between gap-6 rounded-[6px] bg-[#0F1E2E] px-5 py-3 text-[14px] text-white transition hover:bg-[#1c2f43] md:w-auto md:text-[15px]"
						:style="getStyles(customisation?.contact?.button?.container?.properties)">
						<span>{{ contactButtonLabel }}</span>
						<span class="text-lg">›</span>
					</button>
				</a>
			</div>
		</div>
	</div>
</template>

<style scoped>
.customisation-table-wrapper {
	width: 100%;
	overflow-x: auto;
}

.customisation-table-wrapper--solo {
	max-width: 980px;
}

.customisation-table {
	width: 100% !important;
	min-width: 520px !important;
	border-collapse: collapse !important;
	border-spacing: 0 !important;
	table-layout: auto !important;
	background-color: transparent !important;
	text-align: left !important;
	margin: 0 !important;
	font-family: inherit !important;
}

.customisation-table thead,
.customisation-table thead tr,
.customisation-table thead th {
	background-color: #e6e6e6 !important;
	background-image: none !important;
	border: none !important;
}

.customisation-table tbody tr,
.customisation-table tbody tr:nth-child(even),
.customisation-table tbody tr:nth-child(odd) {
	background-color: transparent !important;
	background-image: none !important;
}

.customisation-table th.customisation-table__th {
	border: none !important;
	padding: 10px 12px !important;
	font-size: 13px !important;
	font-weight: 600 !important;
	line-height: 1.5 !important;
	text-align: left !important;
	vertical-align: middle !important;
	white-space: nowrap !important;
	text-transform: none !important;
	letter-spacing: normal !important;
}

.customisation-table th.customisation-table__th--available {
	width: 96px !important;
	text-align: center !important;
}

.customisation-table th.customisation-table__th--moq {
	width: 136px !important;
	text-align: center !important;
}

.customisation-table td.customisation-table__td {
	border: 3px solid #e6e6e6 !important;
	padding: 10px 12px !important;
	font-size: 13px !important;
	font-weight: 400 !important;
	line-height: 1.7 !important;
	text-align: left !important;
	vertical-align: top !important;
}

.customisation-table td.customisation-table__td--option {
	white-space: nowrap !important;
}

.customisation-table td.customisation-table__td--center {
	text-align: center !important;
	vertical-align: middle !important;
}

.customisation-check {
	display: inline-block !important;
	flex-shrink: 0 !important;
	vertical-align: middle !important;
	font-size: 22px !important;
	line-height: 1 !important;
	color: #c0899b !important;
	background-color: transparent !important;
}

.customisation-dash {
	color: #e6e6e6 !important;
}

.customisation-cards {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.customisation-card {
	border: 3px solid #e6e6e6;
	border-radius: 8px;
	background-color: rgba(255, 255, 255, 0.6);
	padding: 16px;
}

.customisation-card__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.customisation-card__title {
	font-size: 13px;
	font-weight: 600;
	color: #c0899b;
	margin-bottom: 0;
}

.customisation-card__moq {
	margin-top: 8px;
	margin-bottom: 0;
	font-size: 12px;
}

.customisation-card__moq span {
	font-weight: 500;
}

.customisation-card__notes {
	margin-top: 4px;
	font-size: 12px;
	line-height: 1.7;
}

.customisation-table :deep(p),
.customisation-card__notes :deep(p) {
	margin-bottom: 0;
}

.customisation-table :deep(a) {
	color: #c0899b;
	text-decoration: underline;
	text-underline-offset: 2px;
}

:deep(a) {
	color: #c0899b;
	text-decoration: underline;
	text-underline-offset: 2px;
}

:deep(p) {
	margin-bottom: 12px;
}

:deep(p:last-child) {
	margin-bottom: 0;
}
</style>
