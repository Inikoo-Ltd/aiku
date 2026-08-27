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
		class="grid w-full grid-cols-1 gap-8 py-6 md:py-8 lg:py-10"
		:class="guidelines.length ? 'lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:gap-14' : ''"
		:style="containerStyle">
		<div class="min-w-0">
			<h2
				v-if="hasText(title)"
				class="!m-0 !text-2xl !font-semibold !leading-[1.25]  md:!text-3xl xl:!text-4xl">
				{{ title }}
			</h2>

			<div
				v-if="hasText(description)"
				class="mt-3 max-w-[640px] text-sm leading-[1.8]  [&_a]:text-[#C0899B] [&_a]:underline [&_a]:underline-offset-2 [&_p]:mb-3 [&_p:last-child]:mb-0 xl:max-w-[700px] xl:text-base 2xl:max-w-[780px]"
				v-html="description" />

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

			<div v-if="hasTemperature" class="mt-5 pl-3 xl:pl-[14px] 2xl:mt-6 2xl:pl-4">
				<p
					v-if="hasText(temperatureLabel)"
					class="!m-0 !text-xs !font-semibold !leading-[1.5]  md:!text-sm 2xl:!text-base">
					{{ temperatureLabel }}
				</p>

				<p class="!mx-0 !mb-0 !mt-1 !text-xs !leading-[1.6]  xl:!text-sm">
					{{ temperatureValue }}
				</p>
			</div>
		</div>

		<div v-if="guidelines.length" class="min-w-0">
			<h3
				v-if="hasText(guidelinesTitle)"
				class="!m-0 !text-base !font-semibold !leading-[1.4]  md:!text-lg xl:!text-xl">
				{{ guidelinesTitle }}
			</h3>

			<ul class="!mx-0 !mb-0 !mt-4 flex !list-none flex-col gap-[14px] !p-0">
				<li
					v-for="(guideline, index) in guidelines"
					:key="index"
					class="!m-0 flex items-start gap-[10px] text-xs leading-[1.7] md:text-sm 2xl:text-base">
					<FontAwesomeIcon
						icon="fas fa-check-circle"
						class="!mt-[3px] !flex-shrink-0 !bg-transparent !text-sm !leading-none !text-[#C0899B] 2xl:!text-base" />

					<span>{{ guideline.text }}</span>
				</li>
			</ul>
		</div>
	</div>
</template>

<style scoped>
.storage-table-wrapper {
	width: 100%;
	overflow-x: auto;
	margin-top: 28px;
}

.storage-table {
	width: 100% !important;
	min-width: 420px !important;
	border: 0 !important;
	border-collapse: separate !important;
	border-spacing: 0 !important;
	table-layout: fixed !important;
	background-color: transparent !important;
	text-align: left !important;
	margin: 0 !important;
	font-family: inherit !important;
}

.storage-table thead tr,
.storage-table thead th {
	background :  #dddd !important;
}

.storage-table thead th.storage-table__th {
	border-top: 1px solid #acacac !important;
	border-bottom: 1px solid #acacac !important;
}

.storage-table thead th.storage-table__th:first-child {
	border-left: 1px solid #acacac !important;
	border-top-left-radius: 10px !important;
	border-bottom-left-radius: 10px !important;
}

.storage-table thead th.storage-table__th:last-child {
	border-right: 1px solid #acacac !important;
	border-top-right-radius: 10px !important;
	border-bottom-right-radius: 10px !important;
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
	text-align: left !important;
	vertical-align: top !important;
	word-break: break-word !important;
}

@media (min-width: 768px) {
	.storage-table th.storage-table__th {
		font-size: 14px !important;
	}

	.storage-table td.storage-table__td {
		font-size: 12px !important;
	}
}

@media (min-width: 1280px) {
	.storage-table th.storage-table__th {
		font-size: 15px !important;
		padding: 10px 14px !important;
	}

	.storage-table td.storage-table__td {
		font-size: 13px !important;
		padding: 11px 14px !important;
	}
}

@media (min-width: 1536px) {
	.storage-table th.storage-table__th {
		font-size: 16px !important;
		padding: 12px 16px !important;
	}

	.storage-table td.storage-table__td {
		font-size: 14px !important;
		padding: 12px 16px !important;
	}
}
</style>
