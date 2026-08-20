<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileDownload, faDownload } from "@fal"
import { faCheckCircle } from "@fas"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

library.add(faFileDownload, faDownload, faCheckCircle)

const hasText = (value: unknown) => String(value ?? "").trim() !== ""

const labelingGuide = computed(() => props.fieldValue?.labeling_guide ?? {})

const containerStyle = computed(() => getStyles(labelingGuide.value?.container?.properties))

const title = computed(() => labelingGuide.value?.title ?? "")

const description = computed(() => labelingGuide.value?.description ?? "")

const cardTitle = computed(() => labelingGuide.value?.card?.title ?? "")

const cardDescription = computed(() => labelingGuide.value?.card?.description ?? "")

const buttonLabel = computed(() => labelingGuide.value?.card?.button?.text ?? "")

const hasButton = computed(() => hasText(buttonLabel.value))

const hasCard = computed(
	() => hasText(cardTitle.value) || hasText(cardDescription.value) || hasButton.value
)

const includesTitle = computed(() => labelingGuide.value?.includes?.title ?? "")

const includes = computed(() => {
	const source = labelingGuide.value?.includes?.items

	if (!Array.isArray(source)) {
		return []
	}

	return source
		.map((item: any) => (typeof item === "string" ? { text: item } : item))
		.filter((item: any) => hasText(item?.text))
})

const note = computed(() => labelingGuide.value?.note ?? "")

const hasSide = computed(
	() => hasText(includesTitle.value) || includes.value.length > 0 || hasText(note.value)
)
</script>

<template>
	<div class="labeling-block" :style="containerStyle">
		<h2 v-if="hasText(title)" class="labeling-title">{{ title }}</h2>

		<div v-if="hasText(description)" class="labeling-description" v-html="description" />

		<div class="labeling-grid" :class="hasCard && hasSide ? 'labeling-grid--split' : ''">
			<div v-if="hasCard" class="labeling-card">
				<div class="labeling-card__row">
					<div class="labeling-card__icon">
						<FontAwesomeIcon
							icon="fal fa-file-download"
							class="labeling-card__icon-glyph" />

						<span class="labeling-card__icon-badge">PDF</span>
					</div>

					<div class="labeling-card__body">
						<h3 v-if="hasText(cardTitle)" class="labeling-card__title">
							{{ cardTitle }}
						</h3>

						<div
							v-if="hasText(cardDescription)"
							class="labeling-card__text"
							v-html="cardDescription" />

						<button
							v-if="hasButton"
							class="labeling-button"
							:style="getStyles(labelingGuide?.card?.button?.container?.properties)">
							<span>{{ buttonLabel }}</span>

							<FontAwesomeIcon icon="fal fa-download" class="labeling-button__icon" />
						</button>
					</div>
				</div>
			</div>

			<div v-if="hasSide" class="labeling-side">
				<h3 v-if="hasText(includesTitle)" class="labeling-side__title">
					{{ includesTitle }}
				</h3>

				<ul v-if="includes.length" class="labeling-list">
					<li v-for="(item, index) in includes" :key="index" class="labeling-list__item">
						<FontAwesomeIcon icon="fas fa-check-circle" class="labeling-list__icon" />

						<span>{{ item.text }}</span>
					</li>
				</ul>

				<div v-if="hasText(note)" class="labeling-note" v-html="note" />
			</div>
		</div>
	</div>
</template>

<style scoped>
.labeling-block {
	width: 100%;
	padding-top: 24px;
	padding-bottom: 24px;
}

.labeling-title {
	margin: 0 !important;
	font-size: 24px !important;
	font-weight: 600 !important;
	line-height: 1.25 !important;
	color: #13294b !important;
}

.labeling-description {
	margin-top: 8px;
	max-width: 640px;
	font-size: 12px;
	line-height: 1.8;
	color: #c0899b;
}

.labeling-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 32px;
	margin-top: 24px;
}

.labeling-card {
	border: 1px solid #dadada;
	border-radius: 10px;
	background-color: rgba(237, 237, 237, 0.6);
	padding: 20px;
}

.labeling-card__row {
	display: flex;
	align-items: flex-start;
	gap: 18px;
}

.labeling-card__icon {
	position: relative;
	flex-shrink: 0;
	color: #13294b;
	line-height: 1;
}

.labeling-card__icon-glyph {
	font-size: 46px !important;
	line-height: 1 !important;
	color: #13294b !important;
}

.labeling-card__icon-badge {
	position: absolute;
	left: -2px;
	bottom: 2px;
	border-radius: 2px;
	background-color: #13294b;
	padding: 1px 3px;
	font-size: 8px;
	font-weight: 700;
	letter-spacing: 0.02em;
	line-height: 1.2;
	color: #ffffff;
}

.labeling-card__body {
	min-width: 0;
}

.labeling-card__title {
	margin: 0 !important;
	font-size: 17px !important;
	font-weight: 600 !important;
	line-height: 1.35 !important;
	color: #13294b !important;
}

.labeling-card__text {
	margin-top: 8px;
	font-size: 12px;
	line-height: 1.7;
	color: #c0899b;
}

.labeling-card__link {
	display: inline-block;
	text-decoration: none !important;
}

.labeling-button {
	display: inline-flex;
	align-items: center;
	gap: 24px;
	margin-top: 18px;
	border: 0;
	border-radius: 6px;
	background-color: #13294b;
	padding: 10px 18px;
	font-size: 13px;
	line-height: 1.2;
	color: #ffffff;
	cursor: pointer;
	transition: background-color 0.2s ease;
}

.labeling-button:hover {
	background-color: #1c2f43;
}

.labeling-button__icon {
	font-size: 14px !important;
	color: #ffffff !important;
}

.labeling-side {
	min-width: 0;
}

.labeling-side__title {
	margin: 0 !important;
	font-size: 14px !important;
	font-weight: 600 !important;
	line-height: 1.4 !important;
	color: #13294b !important;
}

.labeling-list {
	list-style: none !important;
	margin: 14px 0 0 !important;
	padding: 0 !important;
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.labeling-list__item {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	margin: 0 !important;
	font-size: 12px;
	line-height: 1.6;
	color: #c0899b;
}

.labeling-list__icon {
	flex-shrink: 0 !important;
	margin-top: 2px !important;
	font-size: 12px !important;
	line-height: 1 !important;
	color: #c0899b !important;
	background-color: transparent !important;
}

.labeling-note {
	margin-top: 28px;
	border: 1px solid #c0899b;
	border-radius: 6px;
	background-color: rgba(192, 137, 155, 0.1);
	padding: 10px 14px;
	font-size: 12px;
	line-height: 1.6;
	color: #c0899b;
}

.labeling-description :deep(p),
.labeling-card__text :deep(p),
.labeling-note :deep(p) {
	margin-bottom: 8px;
}

.labeling-description :deep(p:last-child),
.labeling-card__text :deep(p:last-child),
.labeling-note :deep(p:last-child) {
	margin-bottom: 0;
}

.labeling-description :deep(a),
.labeling-card__text :deep(a),
.labeling-note :deep(a) {
	color: #c0899b;
	text-decoration: underline;
	text-underline-offset: 2px;
}

@media (min-width: 768px) {
	.labeling-block {
		padding-top: 32px;
		padding-bottom: 32px;
	}

	.labeling-title {
		font-size: 28px !important;
	}

	.labeling-description {
		font-size: 13px;
	}

	.labeling-card {
		padding: 24px;
	}

	.labeling-card__icon-glyph {
		font-size: 52px !important;
	}

	.labeling-card__title {
		font-size: 18px !important;
	}

	.labeling-card__text {
		font-size: 13px;
	}

	.labeling-button {
		font-size: 14px;
	}

	.labeling-side__title {
		font-size: 15px !important;
	}

	.labeling-list__item {
		font-size: 13px;
	}

	.labeling-list__icon {
		font-size: 13px !important;
	}

	.labeling-note {
		font-size: 13px;
	}
}

@media (min-width: 1024px) {
	.labeling-block {
		padding-top: 40px;
		padding-bottom: 40px;
	}

	.labeling-grid--split {
		grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
		gap: 40px;
	}

	.labeling-title {
		font-size: 30px !important;
	}
}

@media (min-width: 1280px) {
	.labeling-title {
		font-size: 32px !important;
	}

	.labeling-description {
		font-size: 14px;
		max-width: 700px;
	}

	.labeling-card {
		padding: 26px;
	}

	.labeling-card__icon-glyph {
		font-size: 56px !important;
	}

	.labeling-card__icon-badge {
		font-size: 9px;
	}

	.labeling-card__title {
		font-size: 19px !important;
	}

	.labeling-card__text {
		font-size: 14px;
	}

	.labeling-button {
		font-size: 15px;
		padding: 11px 20px;
	}

	.labeling-button__icon {
		font-size: 15px !important;
	}

	.labeling-side__title {
		font-size: 16px !important;
	}

	.labeling-list__item {
		font-size: 14px;
	}

	.labeling-list__icon {
		font-size: 14px !important;
	}

	.labeling-note {
		font-size: 14px;
	}
}

@media (min-width: 1536px) {
	.labeling-grid--split {
		gap: 48px;
	}

	.labeling-title {
		font-size: 36px !important;
	}

	.labeling-description {
		font-size: 15px;
		max-width: 780px;
	}

	.labeling-card {
		padding: 28px;
	}

	.labeling-card__icon-glyph {
		font-size: 60px !important;
	}

	.labeling-card__icon-badge {
		font-size: 10px;
	}

	.labeling-card__title {
		font-size: 20px !important;
	}

	.labeling-card__text {
		font-size: 15px;
	}

	.labeling-button {
		font-size: 16px;
		padding: 12px 22px;
	}

	.labeling-button__icon {
		font-size: 16px !important;
	}

	.labeling-side__title {
		font-size: 17px !important;
	}

	.labeling-list__item {
		font-size: 15px;
	}

	.labeling-list__icon {
		font-size: 15px !important;
	}

	.labeling-note {
		font-size: 15px;
	}
}
</style>
