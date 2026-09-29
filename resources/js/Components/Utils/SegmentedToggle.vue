<!--
  - Author Louis Perez
  - Created on 29-09-2026-14h-00m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts" generic="T extends string">
import SelectButton from "primevue/selectbutton"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

defineProps<{
	options: Array<{ label: string; value: T; icon?: any }>
	ariaLabel?: string
}>()

const model = defineModel<T>()
</script>

<template>
	<SelectButton v-model="model" :options="options" optionLabel="label" optionValue="value" dataKey="value" :allowEmpty="false" class="segmented-toggle" :aria-label="ariaLabel">
		<template #option="{ option }">
			<span class="flex items-center gap-1.5">
				<FontAwesomeIcon v-if="option.icon" :icon="option.icon" fixed-width aria-hidden="true" />
				{{ option.label }}
			</span>
		</template>
	</SelectButton>
</template>

<style scoped>
.segmented-toggle {
	gap: 2px;
	padding: 2px;
	border-radius: 0.375rem;
	background-color: rgb(243 244 246);
}
.segmented-toggle :deep(.p-togglebutton) {
	padding: 0;
	border: 0;
	border-radius: 0.25rem;
	background: transparent;
	color: rgb(75 85 99);
	font-size: 0.8125rem;
	line-height: 1.25rem;
	transition: background-color 0.15s, color 0.15s;
}
.segmented-toggle :deep(.p-togglebutton-content) {
	padding: 0.25rem 0.75rem;
	border-radius: 0.25rem;
	background: transparent;
	box-shadow: none;
}
.segmented-toggle :deep(.p-togglebutton:not(.p-togglebutton-checked):hover) {
	background-color: var(--app-accent-soft);
	color: var(--app-accent-strong);
}
.segmented-toggle :deep(.p-togglebutton:not(.p-togglebutton-checked):active) {
	background-color: var(--app-accent-muted);
}
.segmented-toggle :deep(.p-togglebutton-checked) {
	font-weight: 600;
}
.segmented-toggle :deep(.p-togglebutton-checked .p-togglebutton-content) {
	background-color: var(--app-accent);
	color: var(--app-accent-text);
	box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
}
.segmented-toggle :deep(.p-togglebutton-checked:hover .p-togglebutton-content) {
	background-color: var(--app-accent-strong);
}
.segmented-toggle :deep(.p-togglebutton-checked:active .p-togglebutton-content) {
	background-color: var(--app-accent-deep);
}
.segmented-toggle :deep(.p-togglebutton:focus-visible) {
	outline: 2px solid var(--app-accent);
	outline-offset: 1px;
}
</style>
