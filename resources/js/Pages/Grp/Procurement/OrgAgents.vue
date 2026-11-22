<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 12:20:38 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AgentWorkCard, { type AgentCard, type CoverBucket } from "@/Components/Procurement/AgentWorkCard.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { RouteParams } from "@/types/route-params"

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	currency_code: string
	agents: AgentCard[]
	cover?: Record<number, CoverBucket[]>
	card_sections: Record<string, boolean>
	suspended_count: number
}>()

const organisation = (route().params as RouteParams).organisation

const expanded = ref<Record<string, boolean>>({ ...props.card_sections })
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="grid max-w-[120rem] gap-4 p-4 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
		<AgentWorkCard v-for="agent in agents" :key="agent.id" :agent="agent" :cover="cover" :sections="expanded" />

		<p v-if="!agents.length" class="text-gray-500">{{ ctrans("No agents found") }}</p>
	</div>

	<div v-if="suspended_count" class="max-w-[120rem] px-4 pb-4 text-right text-xs">
		<Link
			:href="route('grp.org.procurement.org_agents.suspended', [organisation])"
			class="text-gray-400 hover:text-gray-600 hover:underline"
			>{{ ctrans("Suspended agents (:count)", { count: suspended_count }) }}</Link
		>
	</div>
</template>
