<template>
	<div class="summaryCard overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-md">
		<slot />
	</div>
</template>

<style scoped>
.summaryCard :deep(.p-accordion) {
	margin: 0 -1rem;
	border-top: 1px solid #e5e7eb;
}

.summaryCard :deep(.p-accordionpanel) {
	border-width: 0 0 1px 0;
	border-color: #e5e7eb;
}

.summaryCard :deep(.p-accordionpanel:last-child) {
	border-bottom-width: 0;
}

.summaryCard :deep(.p-accordionheader) {
	padding: 0.75rem 1rem;
	border-radius: 0;
}

.summaryCard :deep(.p-accordioncontent-content) {
	padding: 0 1rem 0.75rem !important;
}

@media (min-width: 1024px) {
	.summaryCard :deep(.p-accordion) {
		margin: 0 -1.25rem;
	}

	.summaryCard :deep(.p-accordionheader) {
		padding: 0.75rem 1.25rem;
	}

	.summaryCard :deep(.p-accordioncontent-content) {
		padding: 0 1.25rem 0.75rem !important;
	}
}
</style>
