<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
	faShapes,
	faSync,
	faSearch,
	faExclamationTriangle,
	faLayerPlus,
	faChevronLeft,
	faChevronRight,
	faUser,
} from '@fal'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { WebLayoutTemplate, WebLayoutTemplateList } from '@/types/WebLayoutTemplate'

const props = withDefaults(
	defineProps<{
		templates?: WebLayoutTemplateList
		isLoading?: boolean
		errorMessage?: string | null
		applyingTemplateId?: number | null
		editable?: boolean
	}>(),
	{
		templates: () => ({ data: [] }),
		isLoading: false,
		errorMessage: null,
		applyingTemplateId: null,
		editable: true,
	}
)

const emits = defineEmits<{
	(e: 'refresh'): void
	(e: 'search', value: string): void
	(e: 'navigate', value: string): void
	(e: 'use', value: WebLayoutTemplate): void
}>()

const search = ref('')

const templateList = computed(() => props.templates?.data ?? [])

const meta = computed(() => props.templates?.meta)

const total = computed(() => meta.value?.total ?? templateList.value.length)

const currentPage = computed(() => meta.value?.current_page ?? 1)

const lastPage = computed(() => meta.value?.last_page ?? 1)

const previousPageUrl = computed(() => props.templates?.links?.prev ?? null)

const nextPageUrl = computed(() => props.templates?.links?.next ?? null)

const hasPagination = computed(() => lastPage.value > 1)

const emitSearch = debounce(() => emits('search', search.value.trim()), 400)

watch(search, () => emitSearch())

const getBlockCount = (template: WebLayoutTemplate) =>
	template.blocks_count ?? template.blocks?.length ?? 0

const isApplying = (template: WebLayoutTemplate) => props.applyingTemplateId === template.id

const onUseTemplate = (template: WebLayoutTemplate) => {
	if (!props.editable || props.applyingTemplateId !== null) {
		return
	}

	emits('use', template)
}

const onGoToPage = (url: string | null) => {
	if (!url || props.isLoading) {
		return
	}

	emits('navigate', url)
}

const onClearSearch = () => {
	search.value = ''
	emitSearch.flush()
}
</script>

<template>
	<div class="flex flex-col h-full min-h-0">
		<div class="shrink-0 mb-1.5 flex items-center gap-1.5">
			<div class="relative flex-1 min-w-0">
				<FontAwesomeIcon
					:icon="faSearch"
					class="absolute left-1.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-400"
					fixed-width />
				<input
					v-model="search"
					type="search"
					:aria-label="trans('Search template or author')"
					:placeholder="trans('Search template or author')"
					class="w-full text-xs border border-slate-300 rounded pl-6 pr-1.5 py-1 bg-white text-slate-700 focus:outline-none focus:ring-1 focus:ring-slate-400" />
			</div>
			<button
				type="button"
				v-tooltip="trans('Reload templates')"
				class="h-6 w-6 shrink-0 flex items-center justify-center rounded text-[11px] text-slate-400 hover:bg-slate-100 hover:text-slate-700"
				:disabled="isLoading"
				@click="emits('refresh')">
				<LoadingIcon v-if="isLoading" />
				<FontAwesomeIcon v-else :icon="faSync" fixed-width />
			</button>
			<span
				v-tooltip="trans('Total templates available')"
				class="shrink-0 text-[10px] tabular-nums text-slate-400">
				{{ total }}
			</span>
		</div>

		<div class="flex-1 min-h-0 overflow-y-auto space-y-0.5 pr-0.5">
			<template v-if="isLoading && !templateList.length">
				<div
					v-for="skeleton in 4"
					:key="skeleton"
					class="skeleton h-11 w-full rounded bg-slate-100" />
			</template>

			<div
				v-else-if="errorMessage"
				class="flex flex-col items-center text-center gap-0.5 px-3 py-5 rounded-md border border-dashed border-red-200 text-slate-500">
				<FontAwesomeIcon :icon="faExclamationTriangle" class="text-2xl mb-1 text-red-300" />
				<span class="text-xs font-medium">{{ trans('Unable to load templates') }}</span>
				<span class="text-[11px] text-slate-400">{{ errorMessage }}</span>
				<button
					type="button"
					class="mt-1 text-[11px] underline text-slate-500 hover:text-slate-800"
					@click="emits('refresh')">
					{{ trans('Try again') }}
				</button>
			</div>

			<template v-else-if="templateList.length">
				<div
					v-for="template in templateList"
					:key="template.id"
					class="group flex items-center gap-1.5 rounded px-1.5 py-1 transition-colors hover:bg-slate-100"
					:class="editable ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'"
					@click="onUseTemplate(template)">
					<span
						class="shrink-0 h-6 w-6 flex items-center justify-center rounded bg-slate-100 text-[11px] text-slate-400 group-hover:bg-white">
						<FontAwesomeIcon :icon="faShapes" fixed-width />
					</span>

					<div class="min-w-0 flex-1">
						<span class="block text-xs font-medium leading-tight truncate text-slate-700">
							{{ template.name }}
						</span>
						<span class="block text-[10px] leading-tight truncate text-slate-400">
							{{ getBlockCount(template) }} {{ trans('blocks') }}
							<template v-if="template.username">
								·
								<FontAwesomeIcon :icon="faUser" class="text-[9px]" fixed-width />
								{{ template.username }}
							</template>
							<template v-if="template.created_at">
								· {{ useFormatTime(template.created_at, { formatTime: 'aiku' }) }}
							</template>
						</span>
					</div>

					<span
						v-if="template.scope"
						class="shrink-0 px-1 py-px rounded text-[10px] font-medium leading-tight bg-slate-100 text-slate-500">
						{{ template.scope }}
					</span>

					<button
						type="button"
						v-tooltip="editable
							? trans('Replace the page blocks with this template')
							: trans('This page is not editable')"
						class="h-5 w-5 shrink-0 flex items-center justify-center rounded text-[11px] transition-colors text-slate-400 hover:bg-slate-200 hover:text-slate-700"
						:class="isApplying(template) ? '' : 'opacity-0 group-hover:opacity-100 focus:opacity-100'"
						:disabled="!editable || applyingTemplateId !== null"
						@click.stop="onUseTemplate(template)">
						<LoadingIcon v-if="isApplying(template)" />
						<FontAwesomeIcon v-else :icon="faLayerPlus" fixed-width />
					</button>
				</div>
			</template>

			<div
				v-else
				class="flex flex-col items-center text-center gap-0.5 px-3 py-5 rounded-md border border-dashed border-slate-200 text-slate-500">
				<FontAwesomeIcon :icon="faShapes" class="text-2xl mb-1 text-slate-300" />
				<template v-if="search">
					<span class="text-xs font-medium">{{ trans('No template matches this search') }}</span>
					<button
						type="button"
						class="text-[11px] underline text-slate-500 hover:text-slate-800"
						@click="onClearSearch">
						{{ trans('Show all templates') }}
					</button>
				</template>
				<template v-else>
					<span class="text-xs font-medium">{{ trans("You don't have any template") }}</span>
					<span class="text-[11px] text-slate-400">
						{{ trans('Save a page as template to reuse its blocks here.') }}
					</span>
				</template>
			</div>
		</div>

		<div
			v-if="hasPagination"
			class="shrink-0 mt-1.5 pt-1.5 flex items-center justify-between gap-1.5 border-t border-slate-200">
			<button
				type="button"
				v-tooltip="trans('Previous page')"
				class="h-6 w-6 flex items-center justify-center rounded text-[11px]"
				:class="previousPageUrl && !isLoading
					? 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'
					: 'text-slate-300 cursor-not-allowed'"
				:disabled="!previousPageUrl || isLoading"
				@click="onGoToPage(previousPageUrl)">
				<FontAwesomeIcon :icon="faChevronLeft" fixed-width />
			</button>

			<span class="text-[10px] tabular-nums text-slate-400">
				{{ currentPage }} / {{ lastPage }}
			</span>

			<button
				type="button"
				v-tooltip="trans('Next page')"
				class="h-6 w-6 flex items-center justify-center rounded text-[11px]"
				:class="nextPageUrl && !isLoading
					? 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'
					: 'text-slate-300 cursor-not-allowed'"
				:disabled="!nextPageUrl || isLoading"
				@click="onGoToPage(nextPageUrl)">
				<FontAwesomeIcon :icon="faChevronRight" fixed-width />
			</button>
		</div>
	</div>
</template>
