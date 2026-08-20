<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle } from "@fas"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

library.add(faCheckCircle)

const hasText = (value: unknown) => String(value ?? "").trim() !== ""

const storage = computed(() => props.fieldValue?.storage ?? {})

const familyStorage = computed(() => props.fieldValue?.family?.storage_option ?? {})

const containerStyle = computed(() => getStyles(storage.value?.container?.properties))

const title = computed(() => storage.value?.title ?? "")

const description = computed(() => storage.value?.description ?? "")

const conditions = computed(() => {
	const source = familyStorage.value?.storage_conditions

	if (!Array.isArray(source)) {
		return []
	}

	return source
		.filter((condition: any) => hasText(condition?.value))
		.map((condition: any) => ({
			key: condition.key,
			label: condition.label,
			value: condition.value,
		}))
})

const temperatureLabel = computed(() => storage.value?.temperature?.label ?? "")

const temperatureValue = computed(() =>
	String(familyStorage.value?.storage_temperature ?? "").trim()
)

const hasTemperature = computed(() => hasText(temperatureValue.value))

const guidelinesTitle = computed(() => storage.value?.guidelines?.title ?? "")

const guidelines = computed(() => {
	const source = familyStorage.value?.storage_guidelines

	if (!Array.isArray(source)) {
		return []
	}

	return source.filter((guideline: any) => hasText(guideline?.text))
})
</script>

<template>
	<div
		class="storage-block"
		:class="guidelines.length ? 'storage-block--split' : ''"
		:style="containerStyle">
		<div class="storage-main">
			<h2 v-if="hasText(title)" class="storage-title">
				{{ title }}
			</h2>

			<div v-if="hasText(description)" class="storage-description" v-html="description" />

			<div v-if="conditions.length" class="storage-table-wrapper">
				<table class="storage-table">
					<thead>
						<tr>
							<th
								v-for="condition in conditions"
								:key="condition.key"
								class="storage-table__th">
								{{ condition.label }}
							</th>
						</tr>
					</thead>

					<tbody>
						<tr>
							<td
								v-for="condition in conditions"
								:key="condition.key"
								class="storage-table__td">
								{{ condition.value }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div v-if="hasTemperature" class="storage-temperature">
				<p v-if="hasText(temperatureLabel)" class="storage-temperature__label">
					{{ temperatureLabel }}
				</p>

				<p class="storage-temperature__value">
					{{ temperatureValue }}
				</p>
			</div>
		</div>

		<div v-if="guidelines.length" class="storage-guidelines">
			<h3 v-if="hasText(guidelinesTitle)" class="storage-guidelines__title">
				{{ guidelinesTitle }}
			</h3>

			<ul class="storage-guidelines__list">
				<li
					v-for="(guideline, index) in guidelines"
					:key="index"
					class="storage-guidelines__item">
					<FontAwesomeIcon icon="fas fa-check-circle" class="storage-guidelines__icon" />

					<span>{{ guideline.text }}</span>
				</li>
			</ul>
		</div>
	</div>
</template>

<style scoped>
.storage-block {
	display: grid;
	width: 100%;
	grid-template-columns: 1fr;
	gap: 32px;
	padding-top: 24px;
	padding-bottom: 24px;
}

.storage-main {
	min-width: 0;
}

.storage-title {
	margin: 0 !important;
	font-size: 24px !important;
	font-weight: 600 !important;
	line-height: 1.25 !important;
	color: #13294b !important;
}

.storage-description {
	margin-top: 12px;
	max-width: 640px;
	font-size: 13px;
	line-height: 1.8;
	color: #c0899b;
}

.storage-table-wrapper {
	width: 100%;
	overflow-x: auto;
	margin-top: 28px;
}

.storage-table {
	width: 100% !important;
	min-width: 420px !important;
	border: 0 !important;
	border-collapse: collapse !important;
	border-spacing: 0 !important;
	table-layout: fixed !important;
	background-color: transparent !important;
	text-align: left !important;
	margin: 0 !important;
	font-family: inherit !important;
}

.storage-table thead tr,
.storage-table thead th {
	background-color: #ededed !important;
	background-image: none !important;
}

.storage-table tbody tr,
.storage-table tbody tr:nth-child(even),
.storage-table tbody tr:nth-child(odd) {
	background-color: transparent !important;
	background-image: none !important;
}

.storage-table th.storage-table__th {
	width: 33.333% !important;
	border: 0 !important;
	padding: 9px 12px !important;
	font-size: 13px !important;
	font-weight: 600 !important;
	line-height: 1.5 !important;
	color: #13294b !important;
	text-align: left !important;
	vertical-align: middle !important;
	text-transform: none !important;
	letter-spacing: normal !important;
}

.storage-table td.storage-table__td {
	width: 33.333% !important;
	border: 0 !important;
	padding: 10px 12px !important;
	font-size: 11px !important;
	font-weight: 400 !important;
	line-height: 1.6 !important;
	color: #c0899b !important;
	text-align: left !important;
	vertical-align: top !important;
	word-break: break-word !important;
}

.storage-temperature {
	margin-top: 20px;
	padding-left: 12px;
}

.storage-temperature__label {
	margin: 0 !important;
	font-size: 12px !important;
	font-weight: 600 !important;
	line-height: 1.5 !important;
	color: #13294b !important;
}

.storage-temperature__value {
	margin: 4px 0 0 !important;
	font-size: 11px !important;
	line-height: 1.6 !important;
	color: #c0899b !important;
}

.storage-guidelines {
	min-width: 0;
}

.storage-guidelines__title {
	margin: 0 !important;
	font-size: 16px !important;
	font-weight: 600 !important;
	line-height: 1.4 !important;
	color: #13294b !important;
}

.storage-guidelines__list {
	list-style: none !important;
	margin: 16px 0 0 !important;
	padding: 0 !important;
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.storage-guidelines__item {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	margin: 0 !important;
	font-size: 12px;
	line-height: 1.7;
	color: #c0899b;
}

.storage-guidelines__icon {
	flex-shrink: 0 !important;
	margin-top: 3px !important;
	font-size: 14px !important;
	line-height: 1 !important;
	color: #c0899b !important;
	background-color: transparent !important;
}

.storage-description :deep(p) {
	margin-bottom: 12px;
}

.storage-description :deep(p:last-child) {
	margin-bottom: 0;
}

.storage-description :deep(a) {
	color: #c0899b;
	text-decoration: underline;
	text-underline-offset: 2px;
}

@media (min-width: 768px) {
	.storage-block {
		padding-top: 32px;
		padding-bottom: 32px;
	}

	.storage-title {
		font-size: 28px !important;
	}

	.storage-description {
		font-size: 14px;
	}

	.storage-table th.storage-table__th {
		font-size: 14px !important;
	}

	.storage-table td.storage-table__td {
		font-size: 12px !important;
	}

	.storage-temperature__label {
		font-size: 13px !important;
	}

	.storage-temperature__value {
		font-size: 12px !important;
	}

	.storage-guidelines__title {
		font-size: 17px !important;
	}

	.storage-guidelines__item {
		font-size: 13px;
	}
}

@media (min-width: 1024px) {
	.storage-block {
		padding-top: 40px;
		padding-bottom: 40px;
	}

	.storage-block--split {
		grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
		gap: 56px;
	}

	.storage-title {
		font-size: 30px !important;
	}

	.storage-guidelines__title {
		font-size: 18px !important;
	}
}

@media (min-width: 1280px) {
	.storage-title {
		font-size: 32px !important;
	}

	.storage-description {
		font-size: 15px;
		max-width: 700px;
	}

	.storage-table th.storage-table__th {
		font-size: 15px !important;
		padding: 10px 14px !important;
	}

	.storage-table td.storage-table__td {
		font-size: 13px !important;
		padding: 11px 14px !important;
	}

	.storage-temperature {
		padding-left: 14px;
	}

	.storage-temperature__label {
		font-size: 14px !important;
	}

	.storage-temperature__value {
		font-size: 13px !important;
	}

	.storage-guidelines__title {
		font-size: 19px !important;
	}

	.storage-guidelines__item {
		font-size: 14px;
	}

	.storage-guidelines__icon {
		font-size: 15px !important;
	}
}

@media (min-width: 1536px) {
	.storage-title {
		font-size: 36px !important;
	}

	.storage-description {
		font-size: 16px;
		max-width: 780px;
	}

	.storage-table th.storage-table__th {
		font-size: 16px !important;
		padding: 12px 16px !important;
	}

	.storage-table td.storage-table__td {
		font-size: 14px !important;
		padding: 12px 16px !important;
	}

	.storage-temperature {
		margin-top: 24px;
		padding-left: 16px;
	}

	.storage-temperature__label {
		font-size: 15px !important;
	}

	.storage-temperature__value {
		font-size: 14px !important;
	}

	.storage-guidelines__title {
		font-size: 20px !important;
	}

	.storage-guidelines__item {
		font-size: 15px;
	}

	.storage-guidelines__icon {
		font-size: 16px !important;
	}
}
</style>
