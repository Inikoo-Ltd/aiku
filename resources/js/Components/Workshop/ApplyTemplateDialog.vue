<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import draggable from 'vuedraggable'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faArrowDown, faArrowUp, faCheck, faCodeMerge, faExclamationTriangle, faEyeSlash, faGripVertical, faLayerGroup, faLock, faPlus, faTrashAlt } from '@fal'

import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { WebLayoutTemplate } from '@/types/WebLayoutTemplate'


type MergeSide = 'current' | 'incoming'

interface MergeBlock {
	id: number
	type: string
	position: number
	show: boolean
	visibility: any
	must_have?: boolean
}

interface MergeEntry {
	key: string
	side: MergeSide
	block: MergeBlock
}

interface ResolvedBlock {
	source: MergeSide
	id: number
	type: string
	show: boolean
	visibility: any
	must_have: boolean
	position: number
}

const props = withDefaults(
	defineProps<{
		template: WebLayoutTemplate | null
		current: MergeBlock[]
		incoming: MergeBlock[]
		webBlocks?: any[]
		isLoading?: boolean
	}>(),
	{
		template: null,
		current: () => [],
		incoming: () => [],
		webBlocks: () => [],
		isLoading: false,
	}
)

const emits = defineEmits<{
	(e: 'apply', value: { template_id: number | null; blocks: ResolvedBlock[] }): void
}>()

const visible = defineModel<boolean>('visible', { default: false })

const resolution = ref<MergeEntry[]>([])
const resolutionMode = ref<'current' | 'incoming' | 'both' | 'custom'>('current')
const isDragging = ref(false)

const currentEntries = computed<MergeEntry[]>(() =>
	props.current.map((block, index) => ({ key: `current-${index}`, side: 'current', block }))
)

const incomingEntries = computed<MergeEntry[]>(() =>
	props.incoming.map((block, index) => ({ key: `incoming-${index}`, side: 'incoming', block }))
)

const lockedEntries = computed(() => currentEntries.value.filter((entry) => entry.block.must_have))

const currentIds = computed(() => new Set(props.current.map((block) => block.id)))
const incomingIds = computed(() => new Set(props.incoming.map((block) => block.id)))
const resolvedKeys = computed(() => new Set(resolution.value.map((entry) => entry.key)))

const modeOptions = [
	{ value: 'current', label: trans('Keep current'), hint: trans('Discard the template and keep the page as it is') },
	{ value: 'incoming', label: trans('Take incoming'), hint: trans('Replace the page with the template blocks, required blocks stay') },
	{ value: 'both', label: trans('Accept both'), hint: trans('Keep the current blocks, then append the template blocks') },
]

const getBlockName = (entry: MergeEntry) => {
	if (entry.side === 'current') {
		const webBlock = props.webBlocks.find((item: any) => item.id === entry.block.id)
		const name = webBlock?.web_block?.layout?.data?.fieldValue?.blocks?.name

		if (name) {
			return name
		}
	}

	return entry.block.type
}

const getBlockStatus = (entry: MergeEntry) => {
	if (entry.side === 'incoming') {
		return currentIds.value.has(entry.block.id)
			? { label: trans('Already on page'), theme: 'bg-slate-100 text-slate-600' }
			: { label: trans('New'), theme: 'bg-emerald-100 text-emerald-700' }
	}

	return incomingIds.value.has(entry.block.id)
		? { label: trans('In template'), theme: 'bg-slate-100 text-slate-600' }
		: { label: trans('Not in template'), theme: 'bg-amber-100 text-amber-700' }
}

const isResolved = (entry: MergeEntry) => resolvedKeys.value.has(entry.key)

const isLocked = (entry: MergeEntry) => !!entry.block.must_have

const withLockedBlocks = (entries: MergeEntry[]) => {
	const merged = [...entries]

	lockedEntries.value.forEach((entry) => {
		const index = currentEntries.value.indexOf(entry)
		merged.splice(Math.min(index, merged.length), 0, entry)
	})

	return merged
}

const setMode = (mode: 'current' | 'incoming' | 'both') => {
	resolutionMode.value = mode

	if (mode === 'current') {
		resolution.value = [...currentEntries.value]
	} else if (mode === 'incoming') {
		resolution.value = withLockedBlocks(incomingEntries.value)
	} else {
		resolution.value = [...currentEntries.value, ...incomingEntries.value]
	}
}

const toggleEntry = (entry: MergeEntry) => {
	if (isDragging.value || isLocked(entry)) return

	resolutionMode.value = 'custom'

	if (isResolved(entry)) {
		resolution.value = resolution.value.filter((item) => item.key !== entry.key)
		return
	}

	resolution.value = [...resolution.value, entry]
}

const removeFromResolution = (index: number) => {
	if (isLocked(resolution.value[index])) return

	resolutionMode.value = 'custom'
	resolution.value.splice(index, 1)
}

const cloneEntry = (entry: MergeEntry) => entry

const onDragStart = () => {
	isDragging.value = true
}

const onDragEnd = () => {
	setTimeout(() => (isDragging.value = false), 0)
}

const onResolutionDrag = () => {
	resolutionMode.value = 'custom'
}

const canDropInResolution = (event: any) => {
	if (event.from === event.to) {
		return true
	}

	return !resolvedKeys.value.has(event.draggedContext?.element?.key)
}

const moveInResolution = (index: number, offset: number) => {
	const target = index + offset

	if (target < 0 || target >= resolution.value.length) {
		return
	}

	resolutionMode.value = 'custom'
	const [entry] = resolution.value.splice(index, 1)
	resolution.value.splice(target, 0, entry)
}

const onApply = () => {
	emits('apply', {
		template_id: props.template?.id ?? null,
		blocks: resolution.value.map((entry, index) => ({
			source: entry.side,
			id: entry.block.id,
			type: entry.block.type,
			ulid : entry.block.ulid,
			show: !!entry.block.show,
			visibility: entry.block.visibility ? JSON.parse(JSON.stringify(entry.block.visibility)) : null,
			must_have: !!entry.block.must_have,
			position: index + 1,
		})),
	})
}

watch(visible, (isVisible) => {
	if (!isVisible) return

	setMode('current')
})
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		maximizable
		:header="template ? trans('Apply template: :name', { name: template.name }) : trans('Apply template')"
		:style="{ width: '86rem' }"
		:breakpoints="{ '1280px': '95vw' }">
		<div class="flex flex-col gap-2 h-[70vh]">
			<!-- Resolution shortcuts -->
			<div class="shrink-0 flex flex-wrap items-center justify-between gap-2">
				<div class="flex items-center gap-1">
					<button
						v-for="option in modeOptions"
						:key="option.value"
						type="button"
						v-tooltip="option.hint"
						class="rounded border px-2 py-1 text-[11px] font-medium transition-colors"
						:class="resolutionMode === option.value
							? 'border-slate-800 bg-slate-800 text-white'
							: 'border-slate-300 text-slate-600 hover:bg-slate-100'"
						@click="setMode(option.value as 'current' | 'incoming' | 'both')">
						{{ option.label }}
					</button>
				</div>

				<span class="text-[11px] text-slate-400">
					{{ trans('Pick the blocks you want to keep, the result is what the page will look like') }}
				</span>
			</div>

			<!-- Dynamic block warning -->
			<div class="shrink-0 flex items-start gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2 py-1.5">
				<FontAwesomeIcon
					:icon="faExclamationTriangle"
					class="mt-px shrink-0 text-[11px] text-amber-500"
					fixed-width />
				<span class="text-[11px] leading-tight text-amber-800">
					{{ trans('Settings on individual or dynamic blocks (Product, Product List, etc) are not passed down from the template to the page') }}
				</span>
			</div>

			<div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-3 gap-2">
				<!-- Current -->
				<div class="flex flex-col min-h-0 rounded-md border border-blue-200 bg-blue-50/40">
					<div class="shrink-0 flex items-center justify-between gap-2 px-2 py-1.5 border-b border-blue-200">
						<span class="text-xs font-semibold text-blue-800">
							{{ trans('Current') }}
							<span class="ml-0.5 text-[10px] font-normal text-blue-600">
								{{ trans('page') }}
							</span>
						</span>
						<span class="text-[10px] tabular-nums text-blue-600">
							{{ currentEntries.length }}
						</span>
					</div>

					<div class="flex-1 min-h-0 overflow-y-auto p-1">
						<draggable
							:list="currentEntries"
							:group="{ name: 'merge', pull: 'clone', put: false }"
							:sort="false"
							:clone="cloneEntry"
							:move="canDropInResolution"
							itemKey="key"
							ghost-class="ghost"
							class="space-y-0.5"
							@start="onDragStart"
							@end="onDragEnd">
							<template #item="{ element: entry }">
								<div
									class="group flex items-center gap-1.5 rounded px-1.5 py-1 cursor-grab active:cursor-grabbing bg-white border"
									:class="isResolved(entry) ? 'border-blue-300' : 'border-transparent opacity-60 hover:opacity-100'"
									@click="toggleEntry(entry)">
									<FontAwesomeIcon
										:icon="faGripVertical"
										class="shrink-0 text-[10px] text-slate-300"
										fixed-width />

									<span class="shrink-0 w-4 text-[10px] font-semibold tabular-nums text-center text-slate-300">
										{{ entry.block.position }}
									</span>

									<div class="min-w-0 flex-1">
										<span class="block text-xs font-medium leading-tight truncate text-slate-700">
											{{ getBlockName(entry) }}
										</span>
										<span class="block text-[10px] leading-tight truncate text-slate-400">
											{{ entry.block.type }}
										</span>
									</div>

									<FontAwesomeIcon
										v-if="!entry.block.show"
										v-tooltip="trans('Hidden block')"
										:icon="faEyeSlash"
										class="shrink-0 text-[11px] text-amber-500"
										fixed-width />

									<span
										v-if="isLocked(entry)"
										v-tooltip="trans('This block is required, it always stays in the result')"
										class="shrink-0 px-1 py-px rounded text-[10px] font-medium leading-tight bg-blue-100 text-blue-700">
										{{ trans('Required') }}
									</span>

									<span
										v-else
										class="shrink-0 px-1 py-px rounded text-[10px] font-medium leading-tight"
										:class="getBlockStatus(entry).theme">
										{{ getBlockStatus(entry).label }}
									</span>

									<FontAwesomeIcon
										:icon="isLocked(entry) ? faLock : isResolved(entry) ? faCheck : faPlus"
										class="shrink-0 text-[11px]"
										:class="isResolved(entry) ? 'text-blue-600' : 'text-slate-300'"
										fixed-width />
								</div>
							</template>
						</draggable>

						<div
							v-if="!currentEntries.length"
							class="px-3 py-5 text-center text-xs text-slate-400">
							{{ trans('This page has no blocks') }}
						</div>
					</div>
				</div>
				<!-- Result -->
				<div class="flex flex-col min-h-0 rounded-md border border-slate-300 bg-white">
					<div class="shrink-0 flex items-center justify-between gap-2 px-2 py-1.5 border-b border-slate-200">
						<span class="text-xs font-semibold text-slate-700">
							<FontAwesomeIcon :icon="faCodeMerge" class="mr-1 text-[11px] text-slate-400" fixed-width />
							{{ trans('Result') }}
						</span>
						<span class="text-[10px] tabular-nums text-slate-400">
							{{ resolution.length }}
						</span>
					</div>

					<div class="relative flex-1 min-h-0 overflow-y-auto p-1">
						<draggable
							v-model="resolution"
							:group="{ name: 'merge', pull: true, put: true }"
							:move="canDropInResolution"
							itemKey="key"
							ghost-class="ghost"
							class="space-y-0.5 min-h-full"
							@start="onDragStart"
							@end="onDragEnd"
							@change="onResolutionDrag">
							<template #item="{ element: entry, index }">
								<div
									class="group flex items-center gap-1.5 rounded border px-1.5 py-1 cursor-grab active:cursor-grabbing"
									:class="entry.side === 'incoming'
										? 'border-emerald-200 bg-emerald-50/50'
										: 'border-blue-200 bg-blue-50/50'">
									<FontAwesomeIcon
										:icon="faGripVertical"
										class="shrink-0 text-[10px] text-slate-300"
										fixed-width />

									<span class="shrink-0 w-4 text-[10px] font-semibold tabular-nums text-center text-slate-400">
										{{ index + 1 }}
									</span>

									<div class="min-w-0 flex-1">
										<span class="block text-xs font-medium leading-tight truncate text-slate-700">
											{{ getBlockName(entry) }}
										</span>
										<span class="block text-[10px] leading-tight truncate text-slate-400">
											{{ entry.block.type }}
										</span>
									</div>

									<FontAwesomeIcon
										v-if="isLocked(entry)"
										v-tooltip="trans('This block is required, it can only be moved')"
										:icon="faLock"
										class="shrink-0 text-[11px] text-blue-500"
										fixed-width />

									<span
										class="shrink-0 px-1 py-px rounded text-[10px] font-medium leading-tight"
										:class="entry.side === 'incoming'
											? 'bg-emerald-100 text-emerald-700'
											: 'bg-blue-100 text-blue-700'">
										{{ entry.side === 'incoming' ? trans('Incoming') : trans('Current') }}
									</span>

									<div class="shrink-0 flex items-center opacity-0 group-hover:opacity-100">
										<button
											type="button"
											v-tooltip="trans('Move up')"
											class="h-5 w-5 flex items-center justify-center rounded text-[11px] text-slate-400 hover:bg-slate-200 hover:text-slate-700"
											:disabled="index === 0"
											@click="moveInResolution(index, -1)">
											<FontAwesomeIcon :icon="faArrowUp" fixed-width />
										</button>
										<button
											type="button"
											v-tooltip="trans('Move down')"
											class="h-5 w-5 flex items-center justify-center rounded text-[11px] text-slate-400 hover:bg-slate-200 hover:text-slate-700"
											:disabled="index === resolution.length - 1"
											@click="moveInResolution(index, 1)">
											<FontAwesomeIcon :icon="faArrowDown" fixed-width />
										</button>
										<button
											v-if="!isLocked(entry)"
											type="button"
											v-tooltip="trans('Remove from result')"
											class="h-5 w-5 flex items-center justify-center rounded text-[11px] text-slate-400 hover:bg-red-100 hover:text-red-600"
											@click="removeFromResolution(index)">
											<FontAwesomeIcon :icon="faTrashAlt" fixed-width />
										</button>
									</div>
								</div>
							</template>
						</draggable>

						<div
							v-if="!resolution.length"
							class="pointer-events-none absolute inset-1 flex flex-col items-center justify-center gap-1 px-3 text-center rounded-md border border-dashed border-slate-200 text-slate-500">
							<FontAwesomeIcon :icon="faLayerGroup" class="text-2xl text-slate-300" />
							<span class="text-xs font-medium">{{ trans('The page will end up with no blocks') }}</span>
							<span class="text-[10px]">{{ trans('Drag blocks here, or pick them from either side') }}</span>
						</div>
					</div>
				</div>

				<!-- Incoming -->
				<div class="flex flex-col min-h-0 rounded-md border border-emerald-200 bg-emerald-50/40">
					<div class="shrink-0 flex items-center justify-between gap-2 px-2 py-1.5 border-b border-emerald-200">
						<span class="text-xs font-semibold text-emerald-800">
							{{ trans('Incoming') }}
							<span class="ml-0.5 text-[10px] font-normal text-emerald-600">
								{{ trans('template') }}
							</span>
						</span>
						<span class="text-[10px] tabular-nums text-emerald-600">
							{{ incomingEntries.length }}
						</span>
					</div>

					<div class="flex-1 min-h-0 overflow-y-auto p-1">
						<draggable
							:list="incomingEntries"
							:group="{ name: 'merge', pull: 'clone', put: false }"
							:sort="false"
							:clone="cloneEntry"
							:move="canDropInResolution"
							itemKey="key"
							ghost-class="ghost"
							class="space-y-0.5"
							@start="onDragStart"
							@end="onDragEnd">
							<template #item="{ element: entry }">
								<div
									class="group flex items-center gap-1.5 rounded px-1.5 py-1 cursor-grab active:cursor-grabbing bg-white border"
									:class="isResolved(entry) ? 'border-emerald-300' : 'border-transparent opacity-60 hover:opacity-100'"
									@click="toggleEntry(entry)">
									<FontAwesomeIcon
										:icon="faGripVertical"
										class="shrink-0 text-[10px] text-slate-300"
										fixed-width />

									<span class="shrink-0 w-4 text-[10px] font-semibold tabular-nums text-center text-slate-300">
										{{ entry.block.position }}
									</span>

									<div class="min-w-0 flex-1">
										<span class="block text-xs font-medium leading-tight truncate text-slate-700">
											{{ getBlockName(entry) }}
										</span>
										<span class="block text-[10px] leading-tight truncate text-slate-400">
											{{ entry.block.type }}
										</span>
									</div>

									<FontAwesomeIcon
										v-if="!entry.block.show"
										v-tooltip="trans('Hidden block')"
										:icon="faEyeSlash"
										class="shrink-0 text-[11px] text-amber-500"
										fixed-width />

									<span
										class="shrink-0 px-1 py-px rounded text-[10px] font-medium leading-tight"
										:class="getBlockStatus(entry).theme">
										{{ getBlockStatus(entry).label }}
									</span>

									<FontAwesomeIcon
										:icon="isResolved(entry) ? faCheck : faPlus"
										class="shrink-0 text-[11px]"
										:class="isResolved(entry) ? 'text-emerald-600' : 'text-slate-300'"
										fixed-width />
								</div>
							</template>
						</draggable>

						<div
							v-if="!incomingEntries.length"
							class="px-3 py-5 text-center text-xs text-slate-400">
							{{ trans('This template has no blocks') }}
						</div>
					</div>
				</div>

			</div>
		</div>

		<template #footer>
			<div class="flex items-center justify-between gap-2">
				<span class="text-[11px] text-slate-400">
					<LoadingIcon v-if="isLoading" class="mr-1" />
					{{ trans(':count blocks in the result', { count: String(resolution.length) }) }}
				</span>
				<div class="flex items-center gap-2">
					<Button type="tertiary" size="xs" :label="trans('Cancel')" @click="visible = false" />
					<Button
						type="save"
						size="xs"
						:icon="faCodeMerge"
						:loading="isLoading"
						:label="trans('Apply result')"
						@click="onApply" />
				</div>
			</div>
		</template>
	</Dialog>
</template>

<style scoped>
.ghost {
	opacity: 0.5;
	background-color: #e2e8f0;
	border: 2px dashed #94a3b8;
}
</style>
