<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-26 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { SupplierOptionFields } from "@/types/supplier"

const props = defineProps<{
	supplier: SupplierOptionFields
	search?: string
}>()

const maxMatchedProductCodes = 5

const matchedProductCodes = computed(() => {
	const needle = props.search?.trim().toLowerCase()
	if (!needle || !props.supplier.product_codes) {
		return []
	}

	return props.supplier.product_codes.split(" ").filter((code) => code.toLowerCase().includes(needle))
})

const contactLine = computed(() => [props.supplier.company_name, props.supplier.contact_name].filter(Boolean).join(" · "))
</script>

<template>
	<div class="flex w-full min-w-0 items-start justify-between gap-3">
		<div class="min-w-0">
			<div class="flex min-w-0 items-center gap-2">
				<span class="whitespace-nowrap font-semibold">{{ supplier.code }}</span>
				<span v-if="supplier.nickname" class="truncate rounded bg-[var(--app-accent-soft)] px-1.5 text-xs text-[var(--app-accent-deep)]" :title="ctrans('Nickname')">
					{{ supplier.nickname }}
				</span>
			</div>
			<div v-if="contactLine" class="truncate text-xs text-gray-500">{{ contactLine }}</div>
			<div v-if="matchedProductCodes.length" class="mt-0.5 flex flex-wrap gap-1 text-xs">
				<span v-for="code in matchedProductCodes.slice(0, maxMatchedProductCodes)" :key="code" class="whitespace-nowrap rounded border border-gray-300 px-1 text-gray-700">
					{{ code }}
				</span>
				<span v-if="matchedProductCodes.length > maxMatchedProductCodes" class="text-gray-500">
					{{ ctrans("+:count more", { count: String(matchedProductCodes.length - maxMatchedProductCodes) }) }}
				</span>
			</div>
		</div>
		<div v-if="$slots.aside" class="shrink-0">
			<slot name="aside" />
		</div>
	</div>
</template>
