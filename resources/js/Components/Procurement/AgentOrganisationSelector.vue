<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { ref, watch } from "vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
	selector: {
		current: string
		options: { value: string; label: string }[]
	}
}>()

const current = ref(props.selector.current)

watch(() => props.selector.current, (value) => (current.value = value))

const select = (value: string) => {
	if (!value || value === props.selector.current) {
		return
	}

	const url = new URL(window.location.href)
	if (value === "all") {
		url.searchParams.delete("organisation")
	} else {
		url.searchParams.set("organisation", value)
	}
	url.searchParams.delete("page")

	router.get(`${url.pathname}${url.search}`, {}, { preserveScroll: true })
}
</script>

<template>
	<SegmentedToggle v-model="current" :options="selector.options" :ariaLabel="ctrans('Organisation')" @update:modelValue="select" />
</template>
