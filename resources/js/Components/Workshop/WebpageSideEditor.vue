<script setup lang="ts">
import { ref, inject, onMounted, onUnmounted, toRaw, computed, watch, nextTick } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import draggable from 'vuedraggable'
import { useConfirm } from 'primevue/useconfirm'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Modal from '@/Components/Utils/Modal.vue'

import VisibleCheckmark from '@/Components/CMS/Fields/VisibleCheckmark.vue'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import SiteSettings from '@/Components/Workshop/SiteSettings.vue'
import ConfirmPopup from 'primevue/confirmpopup'
import { useLayoutStore } from '@/Stores/layout'
import {
	getBlueprint,
	getEditPermissions,
	getDeletePermissions,
	getHiddenPermissions,
	getRenamePermision,
} from '@/Composables/getBlueprintWorkshop'
import { Root, Daum } from '@/types/webBlockTypes'
import { Root as RootWebpage } from '@/types/webpageTypes'
import { Collapse } from 'vue-collapsed'
import { trans } from 'laravel-vue-i18n'
import WeblockList from '@/Components/CMS/Webpage/WeblockList.vue'
import WebpageTemplateList from '@/Components/Workshop/WebpageTemplateList.vue'
import { WebLayoutTemplate, WebLayoutTemplateList } from '@/types/WebLayoutTemplate'

import {
	faBrowser,
	faDraftingCompass,
	faRectangleWide,
	faStars,
	faBars,
	faText,
	faEye,
	faEyeSlash,
	faPlus,
	faTrashAlt,
	faCopy,
	faPaste,
	faEdit,
} from '@fal'
import { faBrush, faCogs, faExclamationTriangle, faLayerGroup, faShapes } from '@fas'

library.add(
	faBrowser,
	faDraftingCompass,
	faRectangleWide,
	faStars,
	faBars,
	faText,
	faEye,
	faEyeSlash
)

const layout = useLayoutStore()
const modelModalBlocklist = defineModel()

const props = withDefaults(
	defineProps<{
		webpage: RootWebpage
		webBlockTypes: Daum
		selectedTab: Number
		editable: boolean
		templates?: WebLayoutTemplateList
		isLoadingTemplates?: boolean
		templatesErrorMessage?: string | null
		applyingTemplateId?: number | null
	}>(),
	{
		templates: () => ({ data: [] }),
		isLoadingTemplates: false,
		templatesErrorMessage: null,
		applyingTemplateId: null,
	}
)

const emits = defineEmits<{
	(e: 'add', value: { block: Daum; type: string }): void
	(e: 'delete', value: Daum): void
	(e: 'update', value: Daum): void
	(e: 'order', value: object): void
	(e: 'setVisible', value: object): void
	(e: 'onSaveSiteSettings', value: object): void
	(e: 'openBlockList', value: boolean): void
	(e: 'onDuplicateBlock', value: Number): void
	(e: 'update:selectedTab', value: Number): void
	(e: 'fetchTemplates'): void
	(e: 'searchTemplates', value: string): void
	(e: 'navigateTemplates', value: string): void
	(e: 'useTemplate', value: WebLayoutTemplate): void
}>()

const confirm = useConfirm()
/* const selectedTab = ref(1) */
const addType = ref('current')
const openedBlockSideEditor = inject('openedBlockSideEditor', ref(null))
const openedChildSideEditor = inject('openedChildSideEditor', ref(null))
const isAddBlockLoading = inject('isAddBlockLoading', ref(null))
const isLoadingDeleteBlock = inject('isLoadingDeleteBlock', ref(null))
const isLoadingBlock = inject('isLoadingBlock', ref(null))
const filterBlock = inject('filterBlock')
const changeTab = (index: number) => emits('update:selectedTab', index)
const sendNewBlock = (block: Daum) => {
	emits('add', { block, type: addType.value })
}
const sendBlockUpdate = (block: Daum) => emits('update', block)
const sendOrderBlock = (block: object) => emits('order', block)
const sendDeleteBlock = (block: Daum) => emits('delete', block)

const STYLE_TAB_INDEX = 2
const TEMPLATE_TAB_INDEX = 3
const hasRequestedTemplates = ref(false)

const isOpenedBlockEditable = computed(() => {
	if (openedBlockSideEditor.value === null) {
		return false
	}

	const block = props.webpage?.layout?.web_blocks?.[openedBlockSideEditor.value]

	return !!block && getEditPermissions(block.web_block.layout.data)
})

const tabs = computed(() => [
	{ label: 'Settings', icon: faCogs, tooltip: 'Page Setting', hidden: false },
	{ label: 'Layer', icon: faLayerGroup, tooltip: 'Blocks', hidden: false },
	{
		label: 'Style',
		icon: faBrush,
		tooltip: 'Style',
		hidden: !isOpenedBlockEditable.value,
	},
	{ label: 'Template', icon: faShapes, tooltip: 'Page templates', hidden: false },
])

const requestTemplates = () => {
	hasRequestedTemplates.value = true
	emits('fetchTemplates')
}

const onSearchTemplates = (value: string) => emits('searchTemplates', value)

const onNavigateTemplates = (url: string) => emits('navigateTemplates', url)

const onUseTemplate = (template: WebLayoutTemplate) => emits('useTemplate', template)

const filterOptions = [
	{ label: 'All', value: 'all' },
	{ label: 'Logged out', value: 'logged-out' },
	{ label: 'Logged in', value: 'logged-in' },
]

const onChangeOrderBlock = () => {
	const payload = {}
	props.webpage.layout.web_blocks.forEach((item, index) => {
		payload[item.web_block.id] = { position: index }
	})
	sendOrderBlock(payload)
}

const onPickBlock = async (block: Daum) => {
	await sendNewBlock(block)
	modelModalBlocklist.value = false
}

const openModalBlockList = () => {
	addType.value = 'current'
	modelModalBlocklist.value = !modelModalBlocklist.value
	emits('openBlockList', !modelModalBlocklist.value)
}

const setShowBlock = (e: Event, value: Daum) => {
	e.stopPropagation()
	e.preventDefault()
	emits('setVisible', value)
	closeContextMenu()
}

const confirmDelete = (event: Event, data: Daum) => {
	confirm.require({
		target: event.currentTarget,
		message: trans("move this block? This action can't be undone."),
		rejectProps: { label: trans('Cancel'), severity: 'secondary', outlined: true },
		acceptProps: { label: trans('Yes, delete'), severity: 'danger' },
		accept: () => sendDeleteBlock(data),
		onHide: () => closeContextMenu(),
	})
}

const showWebpage = (block: Daum) => {
	if (!block?.visibility) return true
	if (filterBlock.value === 'all') return true
	if (filterBlock.value === 'logged-out') return block.visibility.out
	if (filterBlock.value === 'logged-in') return block.visibility.in
	return true
}

const visibleBlockCount = computed(
	() => props.webpage?.layout?.web_blocks?.filter(showWebpage).length ?? 0
)

const contextMenu = ref({
	visible: false,
	top: 0,
	left: 0,
	block: null as Daum | null,
})
const copiedBlock = ref<Daum | null>(null)

const contextMenuEl = ref<HTMLElement | null>(null)
const CONTEXT_MENU_MARGIN = 8

const openContextMenu = async (event: MouseEvent, block: Daum | null = null) => {
	event.preventDefault()
	event.stopPropagation()
	contextMenu.value = {
		visible: true,
		top: event.clientY,
		left: event.clientX,
		block,
	}

	await nextTick()

	const menu = contextMenuEl.value
	if (!menu) return

	const { width, height } = menu.getBoundingClientRect()

	contextMenu.value.top = Math.max(
		CONTEXT_MENU_MARGIN,
		Math.min(event.clientY, window.innerHeight - height - CONTEXT_MENU_MARGIN)
	)
	contextMenu.value.left = Math.max(
		CONTEXT_MENU_MARGIN,
		Math.min(event.clientX, window.innerWidth - width - CONTEXT_MENU_MARGIN)
	)
}

const closeContextMenu = () => {
	if (!contextMenu.value.visible) return

	contextMenu.value.visible = false
	contextMenu.value.block = null
}

const copyBlock = () => {
	if (contextMenu.value.block) {
		copiedBlock.value = structuredClone(toRaw(contextMenu.value.block))
	}
	closeContextMenu()
}
const pasteBlock = () => {
	if (!copiedBlock.value) return
	emits('onDuplicateBlock', copiedBlock.value.id)
	closeContextMenu()
}

const duplicateBlock = (block: Daum) => {
	copiedBlock.value = structuredClone(toRaw(block))
	emits('onDuplicateBlock', copiedBlock.value.id)
	closeContextMenu()
}

const onClickBlock = (index: number) => {
	openedBlockSideEditor.value = index
}

const onDoubleClickBlock = (index: number) => {
	openedBlockSideEditor.value = index

	if (getEditPermissions(props.webpage.layout.web_blocks[index]?.web_block.layout.data)) {
		changeTab(STYLE_TAB_INDEX)
	}
}

watch(openedBlockSideEditor, (newVal) => {
	if (newVal === null) {
		changeTab(1)
	}
})

watch(
	() => props.selectedTab,
	(tabIndex) => {
		if (tabIndex === TEMPLATE_TAB_INDEX && !hasRequestedTemplates.value) {
			requestTemplates()
		}
	},
	{ immediate: true }
)

watch(isOpenedBlockEditable, (isEditable) => {
	if (!isEditable && props.selectedTab === STYLE_TAB_INDEX) {
		changeTab(1)
	}
})

const onContextMenuKeydown = (event: KeyboardEvent) => {
	if (event.key === 'Escape') closeContextMenu()
}

const onContextMenuScroll = (event: Event) => {
	if (contextMenuEl.value?.contains(event.target as Node)) return
	closeContextMenu()
}

onMounted(() => {
	window.addEventListener('click', closeContextMenu)
	window.addEventListener('keydown', onContextMenuKeydown)
	document.addEventListener('scroll', onContextMenuScroll, true)
	// Method to handle in browser
	window.openSideEditor = (index: number) => {
		openedBlockSideEditor.value = index
	}

	window.listSideWebBlocks = props?.webpage?.layout?.web_blocks
})

onUnmounted(() => {
	window.removeEventListener('click', closeContextMenu)
	window.removeEventListener('keydown', onContextMenuKeydown)
	document.removeEventListener('scroll', onContextMenuScroll, true)
})

defineExpose({
	addType,
})

const editingIndex = ref<number | null>(null)
const renameValue = ref("")
const MAX_RENAME_LENGTH = 35

const startRename = (index: number) => {
	const block = props.webpage.layout.web_blocks[index]
	if (!block || !getRenamePermision(block.web_block.layout.data)) return

	editingIndex.value = index
	renameValue.value = block.web_block.layout.data.fieldValue?.blocks?.name || ""
}

const onRenameBlock = () => {
	if (!contextMenu.value.block) return

	const index = props.webpage.layout.web_blocks.findIndex(
		(b) => b.id === contextMenu.value.block?.id
	)

	if (index === -1) return

	startRename(index)
	closeContextMenu()
}

const saveRename = (index: number) => {
  const block = props.webpage.layout.web_blocks[index]
  if (!block) return

  const value = renameValue.value.trim()

  const fieldValue = (block.web_block.layout.data.fieldValue ??= {})

  if (
    !fieldValue.blocks ||
    Array.isArray(fieldValue.blocks) ||
    typeof fieldValue.blocks !== 'object'
  ) {
    fieldValue.blocks = {}
  }

  if (!value) {
    delete fieldValue.blocks.name
  } else {
    fieldValue.blocks.name = value
  }

  sendBlockUpdate(block)
  editingIndex.value = null
}


const cancelRename = () => {
	editingIndex.value = null
	renameValue.value = ""
}


const blockNotEditableVisible = [
	    "sub-departments-1",
		"sub-departments-2",
	/* 	'collection-description-1' ,
		'department-description-1' ,
		'sub-department-description-1' ,
		'family-1', */
		"families-1",
		"families-2",
		"families-3",
		"products-1",
		"products-2",
		"product-1",
		"product-2",

]
</script>

<template>
	<div class="flex flex-col h-full min-h-0">
		<TabGroup :selectedIndex="selectedTab" @change="changeTab">
			<TabList class="flex shrink-0 bg-slate-50 border-b border-slate-200">
				<Tab
					v-for="(tab, index) in tabs"
					:key="index"
					v-tooltip.bottom="tab.tooltip"
					:disabled="tab.hidden"
					class="relative flex flex-1 items-center justify-center gap-1.5 px-2 py-2 text-xs font-medium transition-colors focus:outline-none"
					:class="[
						tab.hidden ? 'hidden' : '',
						selectedTab === index
							? 'bg-white text-theme'
							: 'text-slate-500 hover:bg-white/60 hover:text-slate-700',
					]">
					<FontAwesomeIcon :icon="tab.icon" class="text-xs" fixed-width />
					{{ tab.label }}
					<span
						v-if="selectedTab === index"
						class="absolute inset-x-0 bottom-0 h-0.5 bg-theme"
						aria-hidden="true" />
				</Tab>
			</TabList>

			<TabPanels class="flex-1 min-h-0">
				<TabPanel class="w-[340px] h-full p-1.5 flex flex-col">
					<div class="flex-1 min-h-0 overflow-y-auto">
						<SiteSettings
							:webpage="webpage"
							:webBlockTypes="webBlockTypes"
							@onSaveSiteSettings="(v: any) => emits('onSaveSiteSettings', v)" />
					</div>
				</TabPanel>

				<!-- Blocks Tab -->
				<TabPanel class="w-[340px] h-full p-1.5 flex flex-col">
					<div
						class="flex-1 min-h-0 flex flex-col relative"
						@contextmenu="openContextMenu($event, null)">
						<!-- Header Controls -->
						<div class="shrink-0 mb-1.5 flex items-center gap-1.5">
							<Button
								type="dashed"
								@click="openModalBlockList"
								:icon="faPlus"
								class="text-xs text-theme border-theme"
								:size="'xs'"
								label="Block" />
							<select
								id="block-visibility-filter"
								:aria-label="trans('Show')"
								v-tooltip="trans('Preview which blocks visitors see when logged in or out')"
								class="flex-1 min-w-0 text-xs border border-slate-300 rounded px-1.5 py-0.5 bg-white text-slate-700 focus:outline-none focus:ring-1 focus:ring-slate-400"
								:value="filterBlock"
								@change="(event: { target: { value: any } }) => filterBlock = event.target.value">
								<option disabled value="">Filter</option>
								<option
									v-for="option in filterOptions"
									:key="option.value"
									:value="option.value">
									{{ option.label }}
								</option>
							</select>
							<span
								v-tooltip="trans('Blocks shown by this filter of the total on the page')"
								class="shrink-0 text-[10px] tabular-nums text-slate-400">
								{{ visibleBlockCount }}/{{ webpage?.layout?.web_blocks?.length ?? 0 }}
							</span>
						</div>

						<!-- Blocks List -->
						<div class="flex-1 min-h-0 overflow-y-auto">
						<template v-if="webpage?.layout?.web_blocks.length">
							<draggable
								:list="webpage.layout.web_blocks"
								handle=".handle"
								@change="onChangeOrderBlock"
								ghost-class="ghost"
								group="column"
								itemKey="id"
								:disabled="!editable"
								class="space-y-0.5">
								<template #item="{ element, index }">
									<div
										v-if="showWebpage(element)"
										class="group relative overflow-hidden rounded transition-colors"
										:class="[
											openedBlockSideEditor === index
												? 'bg-theme text-white'
												: 'hover:bg-slate-100',
											!element.show ? 'opacity-60' : '',
										]"
										@contextmenu="(event: any) => openContextMenu(event, element)">
										<div
											class="flex justify-between items-center gap-1 pl-1.5 pr-1 py-1"
											v-tooltip="
												getEditPermissions(element.web_block.layout.data)
													? trans('Double-click to open Style')
													: trans(
															'This block is reserved by system. Not editable.'
													  )
											"
											:class="
												getEditPermissions(element.web_block.layout.data)
													? 'cursor-pointer'
													: 'cursor-not-allowed'
											">
											<button
												type="button"
												class="flex items-center gap-1.5 min-w-0 flex-1 text-left"
												@click.stop="onClickBlock(index)"
												@dblclick.stop.prevent="onDoubleClickBlock(index)"
												:disabled="
													!getEditPermissions(
														element.web_block.layout.data
													)
												">
												<FontAwesomeIcon
													icon="fal fa-bars"
													v-tooltip="editable ? trans('Drag to reorder') : ''"
													class="handle shrink-0 text-xs cursor-grab active:cursor-grabbing"
													:class="
														openedBlockSideEditor === index
															? 'text-white/70'
															: 'text-slate-300 group-hover:text-slate-400'
													" />

												<span
													class="shrink-0 w-4 text-[10px] font-semibold tabular-nums text-center"
													:class="
														openedBlockSideEditor === index
															? 'text-white/70'
															: 'text-slate-300'
													">
													{{ index + 1 }}
												</span>

												<!-- block Display Name -->
												<div class="min-w-0 flex-1">
													<input
														v-if="editingIndex === index"
														v-model="renameValue"
														:maxlength="MAX_RENAME_LENGTH"
														:aria-label="trans('Rename')"
														class="text-xs font-medium border border-slate-300 rounded px-1 py-0.5 w-full text-gray-700 focus:outline-none focus:ring-1 focus:ring-slate-400"
														@vue:mounted="(vnode: any) => (vnode.el.focus(), vnode.el.select())"
														@keydown.enter.prevent="saveRename(index)"
														@keydown.esc.prevent="cancelRename"
														@blur="saveRename(index)"
														@click.stop
														@dblclick.stop />

													<template v-else>
														<span class="block text-xs font-medium leading-tight truncate">
															<template
																v-if="element.web_block.layout.data.fieldValue?.blocks?.name">
																{{element.web_block.layout.data.fieldValue.blocks.name}}
															</template>
															<template v-else>
																{{ element.type }}
															</template>
														</span>
														<span
															v-if="element.web_block.layout.data.fieldValue?.blocks?.name"
															class="block text-[10px] leading-tight truncate"
															:class="
																openedBlockSideEditor === index
																	? 'text-white/60'
																	: 'text-slate-400'
															">
															{{ element.type }}
														</span>
													</template>
												</div>

												<span
													v-if="!element.show"
													v-tooltip="trans('This block is hidden on the page')"
													class="shrink-0 px-1 py-px rounded text-[10px] font-medium leading-tight"
													:class="
														openedBlockSideEditor === index
															? 'bg-white/20 text-white'
															: 'bg-slate-100 text-slate-500'
													">
													{{ trans('Hidden') }}
												</span>

												<LoadingIcon v-if="isLoadingBlock === element.id" class="shrink-0" />
											</button>

											<div class="flex shrink-0 items-center gap-0.5">
												<!-- Duplicate Block Button -->
												<button
													type="button"
													v-if="
														getEditPermissions(
															element.web_block.layout.data
														) && !blockNotEditableVisible.includes(element.type)
													"
													v-tooltip="trans('Duplicate this block')"
													@click.stop.prevent="duplicateBlock(element)"
													class="h-5 w-5 flex items-center justify-center rounded text-[11px] transition-colors"
													:class="[
														'opacity-0 group-hover:opacity-100 focus:opacity-100',
														openedBlockSideEditor === index
															? 'text-white hover:bg-white/20'
															: 'text-slate-400 hover:bg-slate-100 hover:text-slate-700',
													]">
													<FontAwesomeIcon :icon="faCopy" fixed-width />
												</button>

												<button
													type="button"
													v-if="
														getHiddenPermissions(
															element.web_block.layout.data
														) && !blockNotEditableVisible.includes(element.type)
													"
													v-tooltip="
														element.show
															? trans('Hide this block from the page')
															: trans('Show this block on the page')
													"
													@click.stop.prevent="
														setShowBlock($event, element)
													"
													class="h-5 w-5 flex items-center justify-center rounded text-[11px] transition-colors"
													:class="[
														element.show
															? 'opacity-0 group-hover:opacity-100 focus:opacity-100'
															: '',
														openedBlockSideEditor === index
															? 'text-white hover:bg-white/20'
															: 'text-slate-400 hover:bg-slate-100 hover:text-slate-700',
													]">
													<FontAwesomeIcon
														:icon="
															element.show
																? 'fal fa-eye'
																: 'fal fa-eye-slash'
														"
														fixed-width />
												</button>

												<button
													type="button"
													v-if="
														getDeletePermissions(
															element.web_block.layout.data
														) && !blockNotEditableVisible.includes(element.type)
													"
													v-tooltip="trans('Delete this block')"
													@click="(event: any) => isLoadingDeleteBlock !== element.id && confirmDelete(event, element)"
													class="h-5 w-5 flex items-center justify-center rounded text-[11px] transition-colors"
													:class="[
														isLoadingDeleteBlock === element.id
															? ''
															: 'opacity-0 group-hover:opacity-100 focus:opacity-100',
														openedBlockSideEditor === index
															? 'text-white hover:bg-white/20'
															: 'text-slate-400 hover:bg-red-50 hover:text-red-600',
													]">
													<LoadingIcon
														v-if="
															isLoadingDeleteBlock === element.id
														" />
													<FontAwesomeIcon
														v-else
														icon="fal fa-trash-alt"
														fixed-width />
												</button>
											</div>
										</div>
									</div>
								</template>
							</draggable>
						</template>
						<div
							v-else
							class="flex flex-col items-center text-center gap-0.5 px-3 py-5 rounded-md border border-dashed border-slate-200 text-slate-500">
							<FontAwesomeIcon :icon="['fal', 'browser']" class="text-2xl mb-1 text-slate-300" />
							<span class="text-xs font-medium">{{trans("You don't have any blocks")}}</span>
							<span class="text-[11px] text-slate-400">
								{{ trans('Use the Block button above to add your first one.') }}
							</span>
						</div>

						<div
							v-if="webpage?.layout?.web_blocks.length && visibleBlockCount === 0"
							class="flex flex-col items-center text-center gap-0.5 px-3 py-5 rounded-md border border-dashed border-slate-200 text-slate-500">
							<FontAwesomeIcon :icon="faEyeSlash" class="text-2xl mb-1 text-slate-300" />
							<span class="text-xs font-medium">{{ trans('No blocks match this filter') }}</span>
							<button
								type="button"
								class="text-[11px] underline text-theme"
								@click="filterBlock = 'all'">
								{{ trans('Show all blocks') }}
							</button>
						</div>
						<div
							v-if="isAddBlockLoading"
							class="mt-2 skeleton min-h-10 w-full rounded bg-red-500" />
						</div>
					</div>
				</TabPanel>

				<!-- Tab 3: Style -->
				<TabPanel class="w-[340px] h-full p-1.5 flex flex-col">
					<div class="flex-1 min-h-0 overflow-y-auto pb-14">
						<template v-if="isOpenedBlockEditable">
							<Collapse :when="true">
								<div class="p-1 space-y-1.5">
									<VisibleCheckmark
										:disabled="!editable"
										v-if="!blockNotEditableVisible.includes(webpage.layout.web_blocks?.[openedBlockSideEditor]?.type)"
										v-model="
											webpage.layout.web_blocks[openedBlockSideEditor]
												.visibility
										"
										@update:modelValue="
											sendBlockUpdate(
												webpage.layout.web_blocks[openedBlockSideEditor]
											)
										" />
									<SideEditor
										v-model="
											webpage.layout.web_blocks[openedBlockSideEditor]
												.web_block.layout.data.fieldValue
										"
										:panelOpen="openedChildSideEditor"
										:editable="editable"
										:blueprint="
											getBlueprint(
												webpage.layout.web_blocks[openedBlockSideEditor].type, webpage , webpage.layout.web_blocks[openedBlockSideEditor].id
											)
										"
										:block="webpage.layout.web_blocks[openedBlockSideEditor]"
										@update:modelValue="
											() =>
												sendBlockUpdate(
													webpage.layout.web_blocks[openedBlockSideEditor]
												)
										"
										:uploadImageRoute="{
											...webpage.images_upload_route,
											parameters: {
												modelHasWebBlocks:
													webpage.layout.web_blocks[openedBlockSideEditor]
														.id,
											},
										}" />
								</div>
							</Collapse>
						</template>
						<template v-else>
							<div
								class="flex flex-col items-center text-center gap-0.5 px-3 py-5 rounded-md border border-dashed border-slate-200 text-slate-500">
								<FontAwesomeIcon :icon="faBrush" class="text-2xl mb-1 text-slate-300" />
								<template v-if="openedBlockSideEditor !== null">
									<span class="text-xs font-medium">{{ trans('Not editable') }}</span>
									<span class="text-[11px] text-slate-400">
										{{ trans('This block is reserved by system. Not editable.') }}
									</span>
								</template>
								<template v-else>
									<span class="text-xs font-medium">{{ trans('No block selected') }}</span>
									<span class="text-[11px] text-slate-400">
										{{ trans('Pick a block in the Layer tab to edit its style.') }}
									</span>
								</template>
							</div>
						</template>
					</div>
				</TabPanel>

				<!-- Tab 4: Template -->
				<TabPanel class="w-[340px] h-full p-1.5 flex flex-col">
					<WebpageTemplateList
						:templates="templates"
						:isLoading="isLoadingTemplates"
						:errorMessage="templatesErrorMessage"
						:applyingTemplateId="applyingTemplateId"
						:editable="editable"
						@refresh="requestTemplates"
						@search="onSearchTemplates"
						@navigate="onNavigateTemplates"
						@use="onUseTemplate" />
				</TabPanel>
			</TabPanels>
		</TabGroup>

		<Modal :isOpen="modelModalBlocklist" @onClose="openModalBlockList">
			<WeblockList :onPickBlock="onPickBlock" :webBlockTypes="webBlockTypes" scope="all" />
		</Modal>

		<ConfirmPopup>
			<template #icon>
				<FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
			</template>
		</ConfirmPopup>

	<div
		v-if="contextMenu.visible"
		ref="contextMenuEl"
		:style="{ top: `${contextMenu.top}px`, left: `${contextMenu.left}px` }"
		class="fixed z-50 py-0.5 bg-white border border-slate-200 shadow-lg rounded-md text-xs min-w-[140px] overflow-hidden">
		<ul>
			<template v-if="contextMenu.block">
				<!-- Rename -->
				<li
					@click="
						getRenamePermision(contextMenu.block.web_block.layout.data) &&
							onRenameBlock()
					"
					:class="[
						'flex items-center gap-2 px-2.5 py-1',
						getRenamePermision(contextMenu.block.web_block.layout.data)
							? 'hover:bg-slate-100 text-slate-800 cursor-pointer'
							: 'text-gray-400 cursor-not-allowed pointer-events-none',
					]">
					<font-awesome-icon :icon="faEdit" fixed-width />
					{{ trans('Rename') }}
				</li>

				<!-- Copy -->
				<li
					@click="
						getEditPermissions(contextMenu.block.web_block.layout.data) && copyBlock()
					"
					:class="[
						'flex items-center gap-2 px-2.5 py-1',
						getEditPermissions(contextMenu.block.web_block.layout.data)
							? 'hover:bg-slate-100 text-slate-800 cursor-pointer'
							: 'text-gray-400 cursor-not-allowed pointer-events-none',
					]">
					<font-awesome-icon :icon="faCopy" fixed-width />
					{{ trans('Copy') }}
				</li>

				<!-- Toggle Visibility -->
				<li
					@click="
						getHiddenPermissions(contextMenu.block.web_block.layout.data) &&
							setShowBlock($event, contextMenu.block!)
					"
					:class="[
						'flex items-center gap-2 px-2.5 py-1 cursor-pointer',
						getHiddenPermissions(contextMenu.block.web_block.layout.data)
							? 'hover:bg-slate-100 text-slate-800'
							: 'text-gray-400 cursor-not-allowed pointer-events-none',
					]">
					<font-awesome-icon
						:icon="contextMenu.block?.show ? faEyeSlash : faEye"
						fixed-width />
					{{ contextMenu.block?.show ? trans('Hide') : trans('Unhide') }}
				</li>

				<li class="my-0.5 h-px bg-slate-200" aria-hidden="true" />

				<!-- Delete: keep the menu mounted so the confirm popup keeps its anchor -->
				<li
					@click.stop="
						getDeletePermissions(contextMenu.block.web_block.layout.data) &&
							confirmDelete($event, contextMenu.block!)
					"
					:class="[
						'flex items-center gap-2 px-2.5 py-1',
						getDeletePermissions(contextMenu.block.web_block.layout.data)
							? 'hover:bg-red-50 text-red-600 cursor-pointer'
							: 'text-gray-400 cursor-not-allowed pointer-events-none',
					]">
					<font-awesome-icon :icon="faTrashAlt" fixed-width />
					{{ trans('Delete') }}
				</li>
			</template>
			<template v-else>
				<li
					@click="pasteBlock"
					class="flex items-center gap-2 px-2.5 py-1 hover:bg-slate-100 cursor-pointer"
					:class="{ 'text-gray-400 pointer-events-none': !copiedBlock }">
					<font-awesome-icon :icon="faPaste" fixed-width />
					{{ copiedBlock ? trans('Paste') : trans('Nothing to paste') }}
				</li>
			</template>
		</ul>
	</div>
	</div>
</template>

<style scoped>
.text-theme {
	color: v-bind('layout?.app?.theme[4]') !important;
}

.bg-theme {
	background-color: v-bind('layout?.app?.theme[4]') !important;
}

.border-theme {
	border-color: v-bind('layout?.app?.theme[4]') !important;
}

.ghost {
	opacity: 0.5;
	background-color: #e2e8f0;
	border: 2px dashed v-bind('layout?.app?.theme[4]');
}
</style>
