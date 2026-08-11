<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue'
import Dialog from 'primevue/dialog'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faEye, faEyeSlash, faLayerPlus, faBrowser } from '@fal'

import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import ScreenView from '@/Components/ScreenView.vue'
import { setIframeView } from '@/Composables/Workshop'
import { Root as RootWebpage } from '@/types/webpageTypes'
import { ulid } from 'ulid'

const props = defineProps<{
	webpage: RootWebpage
	previewSrc: string
	isLoading?: boolean
}>()

interface TemplateBlockPayload {
	id: number
	type: string
	position: number
	show: boolean
	visibility: any
	web_block_id: number | undefined
	web_block_type_id: number | undefined
	fieldValue: any
}

const emits = defineEmits<{
	(
		e: 'create',
		value: { name: string; scope: 'shown' | 'all'; blocks: TemplateBlockPayload[] }
	): void
}>()

const visible = defineModel<boolean>('visible', { default: false })

const templateName = ref('')
const includedBlocks = ref<Record<number, boolean>>({})
const blockScope = ref<'shown' | 'all'>('shown')
const currentView = ref('desktop')
const isPreviewLoading = ref(true)
const _previewIframe = ref<HTMLIFrameElement | null>(null)

const scopeOptions = [
	{
		value: 'shown',
		label: trans('Shown blocks'),
		hint: trans('Only the blocks visible on the page'),
	},
	{
		value: 'all',
		label: trans('All blocks'),
		hint: trans('Every block, hidden ones included'),
	},
]

const iframeClass = computed(() => setIframeView(currentView.value))

const webBlocks = computed(() => props.webpage?.layout?.web_blocks ?? [])

const listedBlocks = computed(() =>
	blockScope.value === 'all'
		? webBlocks.value
		: webBlocks.value.filter((block: any) => block.show)
)

const selectedBlocks = computed(() =>
	listedBlocks.value.filter((block: any) => includedBlocks.value[block.id])
)

const hiddenOnPageCount = computed(
	() => webBlocks.value.filter((block: any) => !block.show).length
)

const isBlockIncluded = (block: any) => !!includedBlocks.value[block.id]

const getBlockPosition = (block: any) =>
	webBlocks.value.findIndex((item: any) => item.id === block.id) + 1

const getBlockName = (block: any) =>
	block?.web_block?.layout?.data?.fieldValue?.blocks?.name || block?.type

const selectedBlockIds = computed(
	() => new Set(selectedBlocks.value.map((block: any) => block.id))
)

const templatePayload = computed(() => ({
	...props.webpage,
	layout: {
		...props.webpage?.layout,
		web_blocks: webBlocks.value.map((block: any) => ({
			...block,
			show: selectedBlockIds.value.has(block.id),
		})),
	},
}))

const sendToPreview = () => {
	_previewIframe.value?.contentWindow?.postMessage(
		{ key: 'setWebpage', value: JSON.parse(JSON.stringify(templatePayload.value)) },
		'*'
	)
}

const toggleBlock = (block: any) => {
	includedBlocks.value[block.id] = !includedBlocks.value[block.id]
	sendToPreview()
}

const setAllBlocks = (value: boolean) => {
	listedBlocks.value.forEach((block: any) => (includedBlocks.value[block.id] = value))
	sendToPreview()
}

const setBlockScope = (scope: 'shown' | 'all') => {
	blockScope.value = scope
	includedBlocks.value = webBlocks.value.reduce(
		(carry: Record<number, boolean>, block: any) => {
			carry[block.id] = scope === 'all' ? true : !!block.show
			return carry
		},
		{}
	)
	sendToPreview()
}

const onPreviewLoaded = () => {
	isPreviewLoading.value = false
	nextTick(sendToPreview)
}

const onCreate = () => {
	emits('create', {
		name: templateName.value.trim(),
		scope: blockScope.value,
		blocks: selectedBlocks.value.map((block: any) => ({
			ulid : ulid(),
			id: block.id,
			type: block.type,
			position: getBlockPosition(block),
			show: !!block.show,
			visibility: block.visibility ? JSON.parse(JSON.stringify(block.visibility)) : null,
			web_block_id: block.web_block?.id,
			web_block_type_id: block.web_block?.layout?.id,
			fieldValue: JSON.parse(
				JSON.stringify(block.web_block?.layout?.data?.fieldValue ?? {})
			),
		})),
	})
}

watch(visible, (isVisible) => {
	if (!isVisible) return

	isPreviewLoading.value = true
	currentView.value = 'desktop'
	templateName.value = props.webpage?.code ?? ''
	blockScope.value = 'shown'
	includedBlocks.value = webBlocks.value.reduce(
		(carry: Record<number, boolean>, block: any) => {
			carry[block.id] = !!block.show
			return carry
		},
		{}
	)
})
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		maximizable
		:header="trans('Create as template')"
		:style="{ width: '80rem' }"
		:breakpoints="{ '1280px': '95vw' }">
		<div class="flex flex-col lg:flex-row gap-3 h-[70vh]">
			<!-- Preview -->
			<div class="flex-1 min-w-0 flex flex-col rounded-md border border-slate-200 bg-slate-100">
				<div
					class="flex shrink-0 items-center justify-between gap-2 px-2 py-1 border-b border-slate-200 bg-white rounded-t-md">
					<span class="text-xs font-medium text-slate-600">
						{{ trans('Preview') }}
					</span>
					<div class="flex items-center rounded border border-slate-200 overflow-hidden">
						<ScreenView v-model="currentView" />
					</div>
				</div>

				<div class="relative flex-1 min-h-0 overflow-auto">
					<div
						v-if="isPreviewLoading"
						class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white">
						<LoadingIcon class="w-12 h-12 text-4xl text-slate-400" />
						<span class="text-sm text-slate-400">{{ trans('Loading preview…') }}</span>
					</div>
					<iframe
						ref="_previewIframe"
						:src="previewSrc"
						:title="trans('Template preview')"
						:class="[iframeClass, isPreviewLoading ? 'invisible' : '', 'h-full border-0 bg-white']"
						@load="onPreviewLoaded" />
				</div>
			</div>

			<!-- Blocks -->
			<div class="w-full lg:w-[340px] shrink-0 flex flex-col min-h-0">
				<label class="block text-xs font-medium text-slate-600 mb-1" for="template-name">
					{{ trans('Template name') }}
				</label>
				<input
					id="template-name"
					v-model="templateName"
					type="text"
					:placeholder="trans('Template name')"
					class="mb-2 w-full text-xs border border-slate-300 rounded px-2 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-slate-400" />

				<span class="block text-xs font-medium text-slate-600 mb-1">
					{{ trans('Take blocks from') }}
				</span>
				<div class="shrink-0 mb-2 grid grid-cols-2 gap-1">
					<button
						v-for="option in scopeOptions"
						:key="option.value"
						type="button"
						v-tooltip="option.hint"
						class="rounded border px-2 py-1 text-[11px] font-medium transition-colors"
						:class="blockScope === option.value
							? 'border-slate-800 bg-slate-800 text-white'
							: 'border-slate-300 text-slate-600 hover:bg-slate-100'"
						@click="setBlockScope(option.value as 'shown' | 'all')">
						{{ option.label }}
						<span
							v-if="option.value === 'all' && hiddenOnPageCount"
							class="ml-0.5 text-[10px] tabular-nums"
							:class="blockScope === option.value ? 'text-white/70' : 'text-slate-400'">
							+{{ hiddenOnPageCount }}
						</span>
					</button>
				</div>

				<div class="shrink-0 flex items-center justify-between gap-2 mb-1.5">
					<span class="text-xs font-medium text-slate-600">
						{{ trans('Blocks') }}
						<span class="text-[10px] tabular-nums text-slate-400">
							{{ selectedBlocks.length }}/{{ listedBlocks.length }}
						</span>
					</span>
					<div class="flex items-center gap-1.5 text-[11px]">
						<button
							type="button"
							class="underline text-slate-500 hover:text-slate-800"
							@click="setAllBlocks(true)">
							{{ trans('Show all') }}
						</button>
						<span class="h-3 w-px bg-slate-200" aria-hidden="true" />
						<button
							type="button"
							class="underline text-slate-500 hover:text-slate-800"
							@click="setAllBlocks(false)">
							{{ trans('Hide all') }}
						</button>
					</div>
				</div>

				<div class="flex-1 min-h-0 overflow-y-auto space-y-0.5 pr-0.5">
					<template v-if="listedBlocks.length">
						<div
							v-for="block in listedBlocks"
							:key="block.id"
							class="group flex items-center gap-1.5 rounded px-1.5 py-1 cursor-pointer hover:bg-slate-100"
							:class="isBlockIncluded(block) ? '' : 'opacity-60'"
							@click="toggleBlock(block)">
							<span
								class="shrink-0 w-4 text-[10px] font-semibold tabular-nums text-center text-slate-300">
								{{ getBlockPosition(block) }}
							</span>

							<div class="min-w-0 flex-1">
								<span class="block text-xs font-medium leading-tight truncate text-slate-700">
									{{ getBlockName(block) }}
								</span>
								<span
									v-if="block.web_block?.layout?.data?.fieldValue?.blocks?.name"
									class="block text-[10px] leading-tight truncate text-slate-400">
									{{ block.type }}
								</span>
							</div>

							<span
								v-if="!block.show"
								v-tooltip="trans('This block is hidden on the page')"
								class="shrink-0 px-1 py-px rounded text-[10px] font-medium leading-tight bg-amber-100 text-amber-700">
								{{ trans('Hidden on page') }}
							</span>

							<span
								v-else-if="!isBlockIncluded(block)"
								class="shrink-0 px-1 py-px rounded text-[10px] font-medium leading-tight bg-slate-100 text-slate-500">
								{{ trans('Excluded') }}
							</span>

							<button
								type="button"
								v-tooltip="isBlockIncluded(block)
									? trans('Hide this block in the template')
									: trans('Show this block in the template')"
								class="h-5 w-5 shrink-0 flex items-center justify-center rounded text-[11px] text-slate-400 hover:bg-slate-200 hover:text-slate-700"
								@click.stop="toggleBlock(block)">
								<FontAwesomeIcon
									:icon="isBlockIncluded(block) ? faEye : faEyeSlash"
									fixed-width />
							</button>
						</div>
					</template>

					<div
						v-else
						class="flex flex-col items-center text-center gap-0.5 px-3 py-5 rounded-md border border-dashed border-slate-200 text-slate-500">
						<FontAwesomeIcon :icon="faBrowser" class="text-2xl mb-1 text-slate-300" />
						<template v-if="webBlocks.length">
							<span class="text-xs font-medium">{{ trans('No shown blocks on this page') }}</span>
							<button
								type="button"
								class="text-[11px] underline text-slate-500 hover:text-slate-800"
								@click="setBlockScope('all')">
								{{ trans('Use all blocks, hidden ones included') }}
							</button>
						</template>
						<span v-else class="text-xs font-medium">
							{{ trans("You don't have any blocks") }}
						</span>
					</div>
				</div>
			</div>
		</div>

		<template #footer>
			<div class="flex items-center justify-end gap-2">
				<Button
					type="tertiary"
					size="xs"
					:label="trans('Cancel')"
					@click="visible = false" />
				<Button
					type="save"
					size="xs"
					:icon="faLayerPlus"
					:loading="isLoading"
					:disabled="!templateName.trim() || !selectedBlocks.length"
					:label="trans('Create template')"
					@click="onCreate" />
			</div>
		</template>
	</Dialog>
</template>
