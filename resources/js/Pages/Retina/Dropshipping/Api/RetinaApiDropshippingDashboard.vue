<script setup lang="ts">
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { Link } from "@inertiajs/vue3";

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	data: any
}>()
</script>

<template>
	<div class="flex flex-col items-center justify-center h-48 bg-gray-50 rounded">
		<p class="mb-2 text-base">click hare</p>
		<Link
			as="button"
			:href="route(data.route_generate.name, data.route_generate.parameters)"
			:method="'get'"
			>
			<button class="px-4 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded">
				Get Started
			</button>
		</Link>
	</div>
</template>
