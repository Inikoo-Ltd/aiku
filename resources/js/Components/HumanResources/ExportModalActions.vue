<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPrint, faTimes } from "@fal"

library.add(faPrint, faTimes)

const props = defineProps<{
	loading?: boolean
	exportIcon?: any
}>()

const emit = defineEmits<{
	cancel: []
	export: []
}>()
</script>

<template>
	<div class="flex justify-end gap-3 mt-6">
		<Button
			type="button"
			:label="trans('Cancel')"
			:disabled="loading"
			theme="secondary"
			@click="emit('cancel')" />
		<Button
			type="button"
			:label="trans('Export')"
			:loading="loading"
			:icon="exportIcon"
			theme="primary"
			@click="emit('export')" />
	</div>
</template>
