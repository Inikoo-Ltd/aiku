<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faThLarge } from "@fal"
import Image from "@common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { ctrans } from "@/Composables/useTrans"

type MenuTemplate = {
	id: number
	code: string
	name?: string | null
	description?: string | null
	screenshot?: object | null
	is_in_test?: boolean
}

defineProps<{
	templates: MenuTemplate[]
	pickingCode: string | null
	errorMessage: string | null
}>()

const emits = defineEmits<{
	(e: "pick", template: MenuTemplate): void
}>()
</script>

<template>
	<div class="h-full overflow-y-auto bg-white">
		<div class="mx-auto max-w-5xl px-6 py-8">
			<div class="mb-6 flex items-start gap-3">
				<div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[var(--app-accent-soft)] text-[var(--app-accent)]">
					<FontAwesomeIcon :icon="faThLarge" fixed-width aria-hidden="true" />
				</div>
				<div>
					<h2 class="text-lg font-semibold text-gray-800">{{ ctrans("Choose a menu template") }}</h2>
					<p class="text-sm text-gray-500">
						{{ ctrans("Pick the layout for your website menu. You can add the menu items and change the style after picking, and switch template later from the Template tab.") }}
					</p>
				</div>
			</div>

			<p v-if="errorMessage" class="mb-4 text-xs text-red-600">{{ errorMessage }}</p>

			<EmptyState v-if="!templates.length"
				:data="{ title: ctrans('No menu templates available'), description: ctrans('No menu template is available for this type of shop yet.') }" />

			<div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
				<div v-for="template in templates" :key="template.id"
					class="relative flex flex-col overflow-hidden rounded-lg border bg-white shadow-sm transition"
					:class="[
						pickingCode === template.code ? 'border-[var(--app-accent)] ring-2 ring-[var(--app-accent-muted)]' : 'border-gray-200',
						pickingCode ? 'cursor-wait' : 'cursor-pointer hover:border-[var(--app-accent)] hover:shadow-md',
					]"
					@click="!pickingCode && emits('pick', template)">
					<span v-if="template.is_in_test"
						class="absolute left-2 top-2 z-10 rounded bg-amber-500 px-2 py-0.5 text-[10px] font-bold uppercase text-white">
						{{ ctrans("Beta") }}
					</span>

					<div class="flex aspect-[16/7] w-full items-center justify-center overflow-hidden border-b border-gray-100 bg-gray-50">
						<Image v-if="template.screenshot" :imageCover="true" :src="template.screenshot"
							:alt="ctrans('Preview of :template', { template: template.name || template.code })" />
						<FontAwesomeIcon v-else :icon="faThLarge" class="text-3xl text-gray-300" fixed-width aria-hidden="true" />
					</div>

					<div class="flex flex-1 flex-col gap-1 p-3">
						<div class="flex items-center justify-between gap-2">
							<span class="truncate text-sm font-semibold text-gray-800">{{ template.name || template.code }}</span>
							<span class="whitespace-nowrap text-[11px] text-gray-400">{{ template.code }}</span>
						</div>
						<p v-if="template.description" class="line-clamp-2 text-xs text-gray-500">{{ template.description }}</p>

						<div class="mt-auto pt-2">
							<Button type="tertiary" size="xs" full :label="ctrans('Use this template')"
								:loading="pickingCode === template.code" :disabled="!!pickingCode && pickingCode !== template.code">
								<template #icon>
									<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
								</template>
							</Button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
