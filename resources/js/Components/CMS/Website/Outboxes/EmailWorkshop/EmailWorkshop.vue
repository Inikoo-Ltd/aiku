<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faHeading, faParagraph, faListUl, faImage, faSquare, faMinus, faArrowsV, faShareAlt, faVideo, faCode,
    faClone, faTrashAlt, faDesktop, faMobile, faPaperPlane, faColumns, faCog, faCubes, faPuzzlePiece,
    faUndo, faRedo, faTimes, faArrowsAlt, faPlus, faIcons, faText, faUserSlash, faLock, faEye, faEyeSlash, faTable,
    faSpinnerThird, faExclamationTriangle, faCheck, faKeyboard,
} from '@fal'
import { routeType } from '@/types/route'
import Dialog from 'primevue/dialog'
import BeefreeDynamicProducts from '../BeefreeDynamicProducts.vue'
import BeefreeDynamicBlocks from '../BeefreeDynamicBlocks.vue'
import EmailWorkshopProperties from './EmailWorkshopProperties.vue'
import EmailWorkshopSettings from './EmailWorkshopSettings.vue'
import EmailWorkshopInlineEditor from './EmailWorkshopInlineEditor.vue'
import EmailWorkshopTableEditor from './EmailWorkshopTableEditor.vue'
import EmailWorkshopPlaceholder from './EmailWorkshopPlaceholder.vue'
import WorkshopShortcutsDialog from '@/Components/Workshop/WorkshopShortcutsDialog.vue'
import { WorkshopShortcut, formatShortcutCombo, useWorkshopShortcuts } from '@/Composables/useWorkshopShortcuts'
import { ctrans } from '@/Composables/useTrans'
import {
    EmailColumn, EmailJson, EmailModule, EmailRow, INLINE_EDITABLE_TYPES, MailshotMetadata, MODULE_TYPES,
    createMergeContentModule, createModule, createRow, duplicateWithNewUuids, normaliseEmailJson,
    UNSUBSCRIBE_BLOCK, emailHasUnsubscribeBlock, isTableModule, modulePlaceholder, isUnsubscribeMergeTag, isUnsubscribeModule, moduleDisplayName, paletteModuleTypes,
    rowHasUnsubscribeBlock, rowLayouts, setSocialIconSources, hasCurrentVideoEmailThumbnail, videoEmailThumbnailKey, videoThumbnailFromUrl,
} from './emailWorkshopBlocks'
import { columnWidth, createRenderContext, messageWidth, renderEmailHtml, renderModuleHtml, styleToString, withDerivedHtml } from './renderEmailHtml'

library.add(
    faHeading, faParagraph, faListUl, faImage, faSquare, faMinus, faArrowsV, faShareAlt, faVideo, faCode,
    faClone, faTrashAlt, faDesktop, faMobile, faPaperPlane, faColumns, faCog, faCubes, faPuzzlePiece,
    faUndo, faRedo, faTimes, faArrowsAlt, faPlus, faIcons, faText, faUserSlash, faLock, faEye, faEyeSlash, faTable,
    faSpinnerThird, faExclamationTriangle, faCheck, faKeyboard,
)

const props = withDefaults(defineProps<{
    updateRoute?: routeType
    imagesUploadRoute?: routeType
    videoThumbnailRoute?: routeType
    snapshot: any
    unpublished_layout?: any
    mergeTags: Array<any>
    socialIcons?: Record<string, string>
    mergeContents?: Array<any> | null
    organisationSlug: string
    shopSlug?: string
    shopId?: number
    builderType?: string
    mailshot?: MailshotMetadata | null
    updateMailshotRoute?: routeType
    autoSaveRoute?: routeType
}>(), {
    builderType: 'email',
})

const emits = defineEmits<{
    (e: 'onSave', value: { jsonFile: string, htmlFile: string }): void
    (e: 'sendTest', value: { jsonFile: string, htmlFile: string }): void
    (e: 'saveTemplate', value: { jsonFile: string, htmlFile: string }): void
    (e: 'autoSave', value: string): void
    (e: 'ready', value: boolean): void
    (e: 'mailshotSaved', value: MailshotMetadata): void
}>()

const AUTOSAVE_DEBOUNCE_MS = 3000
const AUTOSAVE_MAX_WAIT_MS = 20000
const HISTORY_DEBOUNCE_MS = 400
const HISTORY_LIMIT = 50
const MOBILE_CANVAS_WIDTH = 375

setSocialIconSources(props.socialIcons)

const email = ref<EmailJson>(normaliseEmailJson(props.unpublished_layout ?? props.snapshot?.layout))
const selectedModuleUuid = ref<string | null>(null)
const selectedRowUuid = ref<string | null>(null)
const sidebarTab = ref<'content' | 'rows' | 'settings'>('content')
const device = ref<'desktop' | 'mobile'>('desktop')
const isDirty = ref(false)
const autoSaveStatus = ref<'idle' | 'pending' | 'saving' | 'saved' | 'error'>('idle')
const lastSavedAt = ref<Date | null>(null)
const lastSavedTime = computed(() => lastSavedAt.value
    ? lastSavedAt.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    : '')
const STRUCTURE_VISIBILITY_STORAGE_KEY = 'email-workshop-show-structure'

const readStructureVisibility = (): boolean => {
    try {
        return localStorage.getItem(STRUCTURE_VISIBILITY_STORAGE_KEY) === '1'
    } catch {
        return false
    }
}

const isStructureVisible = ref(readStructureVisibility())

watch(isStructureVisible, (isVisible) => {
    try {
        localStorage.setItem(STRUCTURE_VISIBILITY_STORAGE_KEY, isVisible ? '1' : '0')
    } catch {
        return
    }
})
const editorRevision = ref(0)
const canvasTextRevision = ref(0)
const panelTextRevision = ref(0)
const TEXT_SYNC_DELAY_MS = 300
let textSyncTimer: ReturnType<typeof setTimeout> | null = null

const scheduleTextSync = (target: 'canvas' | 'panel') => {
    if (textSyncTimer) {
        clearTimeout(textSyncTimer)
    }
    textSyncTimer = setTimeout(() => {
        if (target === 'canvas') {
            canvasTextRevision.value += 1
        } else {
            panelTextRevision.value += 1
        }
    }, TEXT_SYNC_DELAY_MS)
}
let isLoadingEmail = true
let isApplyingHistory = false
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null
let pendingChangesSince: number | null = null
let isAutoSaveRunning = false
let hasChangesDuringAutoSave = false
let historyTimer: ReturnType<typeof setTimeout> | null = null

const sidebarTabs = [
    { key: 'content', label: 'Content', icon: 'fal fa-cubes' },
    { key: 'rows', label: 'Rows', icon: 'fal fa-columns' },
    { key: 'settings', label: 'Settings', icon: 'fal fa-cog' },
] as const

const findModuleLocation = (uuid: string | null): { row: EmailRow, column: EmailColumn, module: EmailModule, index: number } | null => {
    if (!uuid) {
        return null
    }
    for (const row of email.value.page.rows) {
        for (const column of row.columns) {
            const index = column.modules.findIndex((module) => module.uuid === uuid)
            if (index !== -1) {
                return { row, column, module: column.modules[index], index }
            }
        }
    }

    return null
}

const selectedModule = computed(() => findModuleLocation(selectedModuleUuid.value)?.module ?? null)
const selectedRow = computed(() => email.value.page.rows.find((row) => row.uuid === selectedRowUuid.value) ?? null)
const isRowSelected = (row: EmailRow) => selectedRowUuid.value === row.uuid && !selectedModuleUuid.value

const selectModule = (module: EmailModule | undefined, row: EmailRow) => {
    if (!module) {
        return
    }
    selectedModuleUuid.value = module.uuid ?? null
    selectedRowUuid.value = row.uuid ?? null
}

const selectRow = (row: EmailRow) => {
    selectedModuleUuid.value = null
    selectedRowUuid.value = row.uuid ?? null
}

const clearSelection = () => {
    selectedModuleUuid.value = null
    selectedRowUuid.value = null
}

const propertiesTitle = computed(() => {
    if (selectedModule.value) {
        const type = moduleDisplayName(selectedModule.value)
        return `${type.charAt(0).toUpperCase()}${type.slice(1)} ${ctrans('properties')}`
    }

    return ctrans('Row properties')
})

const contentWidth = computed(() => messageWidth(email.value))
const canvasWidth = computed(() => device.value === 'mobile' ? MOBILE_CANVAS_WIDTH : contentWidth.value)

watch(contentWidth, (width) => {
    for (const row of email.value.page.rows) {
        row.content.style.width = `${width}px`
    }
})

const stripUnsafePreviewTags = (html: string): string =>
    html.replace(/<style[\s\S]*?<\/style>/gi, '').replace(/<script[\s\S]*?<\/script>/gi, '')

const modulePreviewHtml = (row: EmailRow, column: EmailColumn, module: EmailModule): string => {
    const context = createRenderContext(email.value, columnWidth(email.value, row, column), true)
    return stripUnsafePreviewTags(renderModuleHtml(module, context))
}

const isStackedOnMobile = (row: EmailRow): boolean =>
    device.value === 'mobile' && row.content?.computedStyle?.rowColStackOnMobile !== false

const columnStyle = (row: EmailRow, column: EmailColumn): string => {
    const width = isStackedOnMobile(row) ? '100%' : `${(column['grid-columns'] ?? 12) / 12 * 100}%`
    return `${styleToString(column.style)};width:${width}`
}

const rowContentStyle = (row: EmailRow): string => {
    const alignItems = { top: 'flex-start', middle: 'center', bottom: 'flex-end' }[row.content?.computedStyle?.verticalAlign as string] ?? 'flex-start'
    return `${styleToString(row.content?.style, ['width'])};width:${canvasWidth.value}px;max-width:100%;align-items:${alignItems}`
}

const isHiddenOnCurrentDevice = (computedStyle: Record<string, any> | undefined): boolean =>
    device.value === 'mobile' ? !!computedStyle?.hideContentOnMobile : !!computedStyle?.hideContentOnDesktop

const bodyLinkColor = computed(() => email.value.page.body.content?.computedStyle?.linkColor ?? '#0068A5')
const isInlineEditing = (module: EmailModule): boolean =>
    selectedModuleUuid.value === module.uuid && INLINE_EDITABLE_TYPES.includes(module.type)

const bodyStyle = computed(() => styleToString(email.value.page.body.content?.style))
const bodyBackground = computed(() => email.value.page.body.container?.style?.['background-color'] ?? '#FFFFFF')

const addRow = (gridColumns: number[]) => {
    const row = createRow(gridColumns, `${contentWidth.value}px`)
    email.value.page.rows.push(row)
    selectRow(row)
}

const duplicateRow = (row: EmailRow) => {
    if (rowHasUnsubscribeBlock(row)) {
        return
    }
    const index = email.value.page.rows.indexOf(row)
    const copy = duplicateWithNewUuids(row)
    email.value.page.rows.splice(index + 1, 0, copy)
    selectRow(copy)
}

const deleteRow = (row: EmailRow) => {
    const index = email.value.page.rows.indexOf(row)
    if (index === -1 || rowHasUnsubscribeBlock(row)) {
        return
    }
    email.value.page.rows.splice(index, 1)
    if (row.uuid === selectedRowUuid.value) {
        clearSelection()
    }
}

const insertModule = (module: EmailModule) => {
    const location = findModuleLocation(selectedModuleUuid.value)
    if (location) {
        location.column.modules.splice(location.index + 1, 0, module)
        selectModule(module, location.row)
        return
    }

    const targetRow = selectedRow.value ?? email.value.page.rows.find((row) => row.columns.some((column) => column.modules.length === 0))
    const targetColumn = targetRow?.columns.find((column) => column.modules.length === 0) ?? targetRow?.columns[0]
    if (targetRow && targetColumn) {
        targetColumn.modules.push(module)
        selectModule(module, targetRow)
        return
    }

    const row = createRow([12], `${contentWidth.value}px`)
    row.columns[0].modules.push(module)
    email.value.page.rows.push(row)
    selectModule(module, row)
}

const duplicateSelectedModule = () => {
    const location = findModuleLocation(selectedModuleUuid.value)
    if (!location || isUnsubscribeModule(location.module)) {
        return
    }
    const copy = duplicateWithNewUuids(location.module)
    location.column.modules.splice(location.index + 1, 0, copy)
    selectModule(copy, location.row)
}

const deleteSelectedModule = () => {
    const location = findModuleLocation(selectedModuleUuid.value)
    if (!location || isUnsubscribeModule(location.module)) {
        return
    }
    location.column.modules.splice(location.index, 1)
    selectedModuleUuid.value = null
}

const isSelectionLocked = computed(() => selectedModule.value
    ? isUnsubscribeModule(selectedModule.value)
    : !!selectedRow.value && rowHasUnsubscribeBlock(selectedRow.value))

const duplicateSelection = () => {
    if (selectedModule.value) {
        duplicateSelectedModule()
    } else if (selectedRow.value) {
        duplicateRow(selectedRow.value)
    }
}

const deleteSelection = () => {
    if (selectedModule.value) {
        deleteSelectedModule()
    } else if (selectedRow.value) {
        deleteRow(selectedRow.value)
    }
}

const cloneRowLayout = (gridColumns: number[]) => createRow(gridColumns, `${contentWidth.value}px`)
const clonePaletteModule = (item: { type: string }) => createModule(item.type)

const dynamicProductsRef = ref<InstanceType<typeof BeefreeDynamicProducts> | null>(null)
const dynamicBlocksRef = ref<InstanceType<typeof BeefreeDynamicBlocks> | null>(null)
const isDynamicContentChooserOpen = ref(false)
const isReplacingDynamicContent = ref(false)

const openDynamicContentChooser = (replace = false) => {
    isReplacingDynamicContent.value = replace
    isDynamicContentChooserOpen.value = true
}

const chooseDynamicContent = async (picker: typeof dynamicProductsRef.value | typeof dynamicBlocksRef.value) => {
    isDynamicContentChooserOpen.value = false
    try {
        const content = await picker?.openModal() as { name: string, value: string } | undefined
        if (!content) {
            return
        }
        if (isReplacingDynamicContent.value && selectedModule.value?.type === MODULE_TYPES.mergeContent) {
            selectedModule.value.descriptor.mergeContent = { name: content.name, value: content.value }
            return
        }
        insertModule(createMergeContentModule(content.name, content.value))
    } catch {
        return
    }
}

const history = ref<string[]>([])
const historyIndex = ref(-1)
const canUndo = computed(() => historyIndex.value > 0)
const canRedo = computed(() => historyIndex.value < history.value.length - 1)

const recordHistory = () => {
    const snapshot = JSON.stringify(email.value)
    if (history.value[historyIndex.value] === snapshot) {
        return
    }
    const nextHistory = [...history.value.slice(0, historyIndex.value + 1), snapshot].slice(-HISTORY_LIMIT)
    history.value = nextHistory
    historyIndex.value = nextHistory.length - 1
}

const resetHistory = () => {
    history.value = []
    historyIndex.value = -1
    recordHistory()
}

const applyHistory = async (index: number) => {
    if (index < 0 || index >= history.value.length) {
        return
    }
    if (historyTimer) {
        clearTimeout(historyTimer)
    }
    isApplyingHistory = true
    historyIndex.value = index
    email.value = JSON.parse(history.value[index])
    editorRevision.value += 1
    markDirty()
    await nextTick()
    isApplyingHistory = false
}

const undo = () => applyHistory(historyIndex.value - 1)
const redo = () => applyHistory(historyIndex.value + 1)

const VIDEO_THUMBNAIL_DEBOUNCE_MS = 800
const videoThumbnailStates = ref<Record<string, 'loading' | 'error'>>({})
const videoThumbnailRequests = new Map<string, Promise<void>>()
let videoThumbnailTimer: ReturnType<typeof setTimeout> | null = null

const videoModules = (): EmailModule[] =>
    email.value.page.rows.flatMap((row) => row.columns.flatMap((column) => column.modules)).filter((module) => module.type === MODULE_TYPES.video)

const needsVideoThumbnail = (module: EmailModule): boolean => {
    const video = module.descriptor?.video
    return !!props.videoThumbnailRoute && !!video?.src && !!video?.thumbSrc && !hasCurrentVideoEmailThumbnail(video)
}

const videoThumbnailPayload = (video: Record<string, any>) => ({
    video_url: video.src,
    thumbnail_url: video.thumbSrc === videoThumbnailFromUrl(video.src) ? null : video.thumbSrc,
    ratio: video.thumbRatio ?? '16-9',
    show_play_button: String(video.iconType ?? 1) !== '0',
    play_button_size: Math.min(160, Math.max(24, parseInt(String(video.iconSize ?? 64), 10) || 64)),
    play_button_color: video.iconColor2 ?? '#000000',
    play_icon_color: video.iconColor1 ?? '#ffffff',
})

const preloadImage = (src: string | undefined): Promise<void> => new Promise((resolve, reject) => {
    if (!src) {
        reject(new Error('Missing image source'))
        return
    }
    const image = new Image()
    image.onload = () => resolve()
    image.onerror = () => reject(new Error('Image could not be loaded'))
    image.src = src
})

const requestVideoThumbnail = (module: EmailModule): Promise<void> => {
    const video = module.descriptor.video
    const key = videoEmailThumbnailKey(video)
    const requestId = `${module.uuid}:${key}`
    const pendingRequest = videoThumbnailRequests.get(requestId)
    if (pendingRequest) {
        return pendingRequest
    }

    videoThumbnailStates.value[module.uuid!] = 'loading'
    const request = axios.post(route(props.videoThumbnailRoute!.name, props.videoThumbnailRoute!.parameters), videoThumbnailPayload(video))
        .then(async ({ data }) => {
            const src = (data?.data ?? data)?.source?.original
            await preloadImage(src)
            if (videoEmailThumbnailKey(module.descriptor.video) === key) {
                module.descriptor.video.emailThumbnail = { src, key }
            }
            delete videoThumbnailStates.value[module.uuid!]
        })
        .catch(() => {
            videoThumbnailStates.value[module.uuid!] = 'error'
        })
        .finally(() => videoThumbnailRequests.delete(requestId))

    videoThumbnailRequests.set(requestId, request)

    return request
}

const refreshVideoThumbnails = async (): Promise<void> => {
    if (videoThumbnailTimer) {
        clearTimeout(videoThumbnailTimer)
        videoThumbnailTimer = null
    }
    await Promise.all(videoModules().filter(needsVideoThumbnail).map(requestVideoThumbnail))
}

watch(
    () => videoModules().map((module) => `${module.uuid}:${videoEmailThumbnailKey(module.descriptor?.video)}`).join('|'),
    () => {
        if (videoThumbnailTimer) {
            clearTimeout(videoThumbnailTimer)
        }
        videoThumbnailTimer = setTimeout(refreshVideoThumbnails, VIDEO_THUMBNAIL_DEBOUNCE_MS)
    },
    { immediate: true },
)

const exportFiles = () => ({
    jsonFile: JSON.stringify(withDerivedHtml(email.value)),
    htmlFile: renderEmailHtml(email.value),
})

const hasUnsubscribeMergeTag = computed(() => (props.mergeTags ?? []).some(isUnsubscribeMergeTag))

const editorMergeTags = computed(() => (props.mergeTags ?? []).filter((tag) => !isUnsubscribeMergeTag(tag)))

const availablePaletteModuleTypes = computed(() =>
    paletteModuleTypes.filter((item) => item.type !== UNSUBSCRIBE_BLOCK || (hasUnsubscribeMergeTag.value && !emailHasUnsubscribeBlock(email.value)))
)

const clearAutoSaveTimer = () => {
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer)
        autoSaveTimer = null
    }
}

const persistDraft = async () => {
    clearAutoSaveTimer()
    if (!isDirty.value) {
        return
    }
    if (!props.autoSaveRoute) {
        isDirty.value = false
        pendingChangesSince = null
        emits('autoSave', JSON.stringify(withDerivedHtml(email.value)))
        return
    }
    if (isAutoSaveRunning) {
        hasChangesDuringAutoSave = true
        return
    }

    isAutoSaveRunning = true
    isDirty.value = false
    pendingChangesSince = null
    autoSaveStatus.value = 'saving'
    try {
        await axios.patch(route(props.autoSaveRoute.name, props.autoSaveRoute.parameters), { layout: withDerivedHtml(email.value) })
        lastSavedAt.value = new Date()
        autoSaveStatus.value = isDirty.value ? 'pending' : 'saved'
    } catch {
        isDirty.value = true
        autoSaveStatus.value = 'error'
    } finally {
        isAutoSaveRunning = false
        if (hasChangesDuringAutoSave) {
            hasChangesDuringAutoSave = false
            scheduleAutoSave()
        }
    }
}

const scheduleAutoSave = () => {
    clearAutoSaveTimer()
    pendingChangesSince ??= Date.now()
    if (autoSaveStatus.value !== 'saving') {
        autoSaveStatus.value = 'pending'
    }
    const waitedFor = Date.now() - pendingChangesSince
    autoSaveTimer = setTimeout(persistDraft, Math.max(0, Math.min(AUTOSAVE_DEBOUNCE_MS, AUTOSAVE_MAX_WAIT_MS - waitedFor)))
}

const markDirty = () => {
    isDirty.value = true
    if (isAutoSaveRunning) {
        hasChangesDuringAutoSave = true
        return
    }
    scheduleAutoSave()
}

const saveDraftNow = () => {
    if (isDirty.value) {
        persistDraft()
    }
}

const isShortcutsDialogVisible = ref(false)

const moveSelectedModule = (direction: -1 | 1) => {
    const location = findModuleLocation(selectedModuleUuid.value)
    if (!location) {
        return
    }
    const target = location.index + direction
    if (target < 0 || target >= location.column.modules.length) {
        return
    }
    location.column.modules.splice(target, 0, location.column.modules.splice(location.index, 1)[0])
}

const hasSelection = () => !!selectedModule.value || !!selectedRow.value

const shortcuts: WorkshopShortcut[] = [
    {
        id: 'save', group: 'Editor', label: 'Save and publish', combos: [['Mod', 'S']],
        run: () => save(), allowWhileTyping: true,
    },
    {
        id: 'undo', group: 'History', label: 'Undo', combos: [['Mod', 'Z']],
        run: () => undo(), isAvailable: () => canUndo.value,
    },
    {
        id: 'redo', group: 'History', label: 'Redo', combos: [['Mod', 'Shift', 'Z'], ['Mod', 'Y']],
        run: () => redo(), isAvailable: () => canRedo.value,
    },
    {
        id: 'duplicate', group: 'Blocks', label: 'Duplicate selected block or row', combos: [['Mod', 'D']],
        run: () => duplicateSelection(), isAvailable: () => hasSelection() && !isSelectionLocked.value,
    },
    {
        id: 'delete', group: 'Blocks', label: 'Delete selected block or row', combos: [['Delete'], ['Backspace']],
        run: () => deleteSelection(), isAvailable: () => hasSelection() && !isSelectionLocked.value,
    },
    {
        id: 'move-up', group: 'Blocks', label: 'Move selected block up', combos: [['Alt', 'ArrowUp']],
        run: () => moveSelectedModule(-1), isAvailable: () => !!selectedModule.value,
    },
    {
        id: 'move-down', group: 'Blocks', label: 'Move selected block down', combos: [['Alt', 'ArrowDown']],
        run: () => moveSelectedModule(1), isAvailable: () => !!selectedModule.value,
    },
    {
        id: 'deselect', group: 'Blocks', label: 'Deselect', combos: [['Escape']],
        run: () => clearSelection(), isAvailable: hasSelection,
    },
    {
        id: 'device', group: 'View', label: 'Switch between desktop and mobile', combos: [['Mod', 'Shift', 'M']],
        run: () => device.value = device.value === 'desktop' ? 'mobile' : 'desktop',
    },
    {
        id: 'structure', group: 'View', label: 'Show or hide structure', combos: [['Mod', 'Shift', 'O']],
        run: () => isStructureVisible.value = !isStructureVisible.value,
    },
    {
        id: 'shortcuts', group: 'View', label: 'Show keyboard shortcuts', combos: [['?'], ['Mod', '/']],
        run: () => isShortcutsDialogVisible.value = true,
    },
]

const publishShortcutLabel = formatShortcutCombo(['Mod', 'S'])

const isShortcutBlocked = () => isShortcutsDialogVisible.value || !!document.querySelector('.p-dialog-mask')

const { listenTo: listenForShortcuts } = useWorkshopShortcuts(shortcuts, isShortcutBlocked)

const onBeforeUnload = (event: BeforeUnloadEvent) => {
    if (isDirty.value || isAutoSaveRunning) {
        event.preventDefault()
        event.returnValue = ''
    }
}

const save = async () => {
    await refreshVideoThumbnails()
    clearAutoSaveTimer()
    isDirty.value = false
    pendingChangesSince = null
    emits('onSave', exportFiles())
}

const sendTest = async () => {
    await refreshVideoThumbnails()
    emits('sendTest', exportFiles())
}

const saveAsTemplate = async () => {
    await refreshVideoThumbnails()
    emits('saveTemplate', exportFiles())
}

watch(email, () => {
    if (isLoadingEmail || isApplyingHistory) {
        return
    }
    markDirty()
    if (historyTimer) {
        clearTimeout(historyTimer)
    }
    historyTimer = setTimeout(recordHistory, HISTORY_DEBOUNCE_MS)
}, { deep: true })

watch(
    () => props.snapshot,
    async (newSnapshot) => {
        if (!newSnapshot) {
            return
        }
        isLoadingEmail = true
        email.value = normaliseEmailJson(newSnapshot)
        editorRevision.value += 1
        clearSelection()
        await nextTick()
        isLoadingEmail = false
        markDirty()
        resetHistory()
    },
    { deep: true },
)

const wrapperRef = ref<HTMLElement | null>(null)
const wrapperTop = ref(177)
const wrapperHeight = computed(() => wrapperTop.value < window.innerHeight / 2 ? `calc(100vh - ${wrapperTop.value}px)` : '100vh')

onMounted(async () => {
    await nextTick()
    isLoadingEmail = false
    resetHistory()
    if (wrapperRef.value) {
        wrapperTop.value = Math.round(wrapperRef.value.getBoundingClientRect().top)
    }
    listenForShortcuts(window)
    window.addEventListener('beforeunload', onBeforeUnload)
    emits('ready', true)
})

onBeforeUnmount(() => {
    persistDraft()
    if (historyTimer) {
        clearTimeout(historyTimer)
    }
    if (textSyncTimer) {
        clearTimeout(textSyncTimer)
    }
    if (videoThumbnailTimer) {
        clearTimeout(videoThumbnailTimer)
    }
    window.removeEventListener('beforeunload', onBeforeUnload)
})

defineExpose({
    save,
    saveDraftNow,
    email,
})
</script>

<template>
    <div>
        <div ref="wrapperRef" class="flex flex-col border-t border-gray-200 bg-white" :style="{ height: wrapperHeight }">
            <div class="flex h-12 shrink-0 items-center justify-between gap-x-3 border-b border-gray-200 bg-white px-3">
                <div class="flex items-center gap-x-1">
                    <button type="button" class="h-8 w-8 rounded text-gray-600 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30"
                        :disabled="!canUndo" v-tooltip="ctrans('Undo')" @click="undo">
                        <FontAwesomeIcon icon="fal fa-undo" fixed-width aria-hidden="true" />
                    </button>
                    <button type="button" class="h-8 w-8 rounded text-gray-600 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30"
                        :disabled="!canRedo" v-tooltip="ctrans('Redo')" @click="redo">
                        <FontAwesomeIcon icon="fal fa-redo" fixed-width aria-hidden="true" />
                    </button>
                    <div class="mx-2 h-5 w-px bg-gray-200" />
                    <div class="flex items-center rounded bg-gray-100 p-0.5">
                        <button type="button" class="h-7 w-9 rounded text-sm"
                            :class="device === 'desktop' ? 'bg-white text-[var(--theme-color-4)] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            v-tooltip="ctrans('Desktop')" @click="device = 'desktop'">
                            <FontAwesomeIcon icon="fal fa-desktop" fixed-width aria-hidden="true" />
                        </button>
                        <button type="button" class="h-7 w-9 rounded text-sm"
                            :class="device === 'mobile' ? 'bg-white text-[var(--theme-color-4)] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            v-tooltip="ctrans('Mobile')" @click="device = 'mobile'">
                            <FontAwesomeIcon icon="fal fa-mobile" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                    <button type="button" class="ml-2 flex h-8 items-center gap-x-1.5 rounded px-2.5 text-[13px] transition"
                        :class="isStructureVisible ? 'bg-[color-mix(in_srgb,var(--theme-color-4)_12%,white)] text-[var(--theme-color-4)]' : 'text-gray-600 hover:bg-gray-100'"
                        :aria-pressed="isStructureVisible" v-tooltip="ctrans('Show the outline of every row, column and block')"
                        @click="isStructureVisible = !isStructureVisible">
                        <FontAwesomeIcon :icon="isStructureVisible ? 'fal fa-eye-slash' : 'fal fa-eye'" fixed-width aria-hidden="true" />
                        {{ ctrans('Show structure') }}
                    </button>
                    <span v-if="autoSaveRoute" class="ml-3 flex items-center gap-x-1.5 text-xs" aria-live="polite">
                        <template v-if="autoSaveStatus === 'saving'">
                            <FontAwesomeIcon icon="fal fa-spinner-third" spin class="text-gray-400" fixed-width aria-hidden="true" />
                            <span class="text-gray-500">{{ ctrans('Saving') }}…</span>
                        </template>
                        <template v-else-if="autoSaveStatus === 'error'">
                            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-500" fixed-width aria-hidden="true" />
                            <span class="text-red-600">{{ ctrans('Autosave failed') }}</span>
                            <button type="button" class="font-medium text-[var(--theme-color-4)] hover:underline" @click="saveDraftNow">{{ ctrans('Retry') }}</button>
                        </template>
                        <template v-else-if="autoSaveStatus === 'pending' || isDirty">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400" />
                            <span class="text-gray-500" v-tooltip="ctrans('Saved automatically in a few seconds')">{{ ctrans('Unsaved changes') }}</span>
                        </template>
                        <template v-else-if="autoSaveStatus === 'saved'">
                            <FontAwesomeIcon icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                            <span class="text-gray-500">{{ ctrans('Draft saved') }} {{ lastSavedTime }}</span>
                        </template>
                    </span>
                    <span v-else-if="isDirty" class="ml-3 text-xs text-gray-400">{{ ctrans('Unsaved changes') }}</span>
                </div>

                <div class="flex items-center gap-x-1">
                     <button type="button" class="flex h-8 w-8 items-center justify-center rounded text-gray-600 hover:bg-gray-100"
                        v-tooltip="`${ctrans('Keyboard shortcuts')} (?)`" :aria-label="ctrans('Keyboard shortcuts')" @click="isShortcutsDialogVisible = true">
                        <FontAwesomeIcon icon="fal fa-keyboard" fixed-width aria-hidden="true" />
                    </button>
                    <span class="mx-1 h-5 w-px bg-gray-200" />
                    <slot name="toolbar" />
                    <button type="button" class="flex h-8 items-center gap-x-1.5 rounded px-3 text-[13px] text-gray-700 hover:bg-gray-100" @click="sendTest">
                        <FontAwesomeIcon icon="fal fa-paper-plane" fixed-width aria-hidden="true" />
                        {{ ctrans('Send test') }}
                    </button>
                    <button type="button" class="h-8 rounded border border-gray-300 px-3 text-[13px] text-gray-700 hover:bg-gray-50" @click="saveAsTemplate">
                        {{ ctrans('Save as template') }}
                    </button>
                    <button type="button" class="h-8 rounded bg-[var(--theme-color-4)] px-3 text-[13px] text-white hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]"
                        v-tooltip="`${ctrans('Save and publish')} (${publishShortcutLabel})`" @click="save">
                        {{ ctrans('Save') }}
                    </button>
                 
                    <!-- <span class="hidden items-center gap-x-1.5 text-xs text-gray-500 lg:flex" v-tooltip="ctrans('Saves and publishes the email. Drafts are saved automatically.')">
                        {{ ctrans('Publish') }}
                        <kbd class="rounded border border-b-2 border-gray-200 bg-gray-50 px-1.5 py-0.5 font-sans text-[11px] leading-none text-gray-600">{{ publishShortcutLabel }}</kbd>
                    </span> -->
                </div>
            </div>

            <div class="flex min-h-0 flex-1">
                <main class="min-h-0 flex-1 overflow-auto py-8" :class="{ 'show-structure': isStructureVisible, 'mobile-canvas': device === 'mobile', 'desktop-canvas': device === 'desktop' }" :style="{ backgroundColor: bodyBackground }" @click.self="clearSelection">
                    <div :style="bodyStyle">
                        <draggable v-model="email.page.rows" item-key="uuid" group="email-rows" handle=".row-handle" ghost-class="opacity-40" class="min-h-[200px]">
                            <template #item="{ element: row }">
                                <div class="email-row group/row relative"
                                    :class="[isRowSelected(row) ? 'z-[1] outline outline-2 -outline-offset-2 outline-[var(--theme-color-4)]' : 'hover:outline hover:outline-2 hover:-outline-offset-2 hover:outline-[color-mix(in_srgb,var(--theme-color-4)_45%,white)]', isHiddenOnCurrentDevice(row.content?.computedStyle) ? 'opacity-40' : '']"
                                    :style="styleToString(row.container?.style)"
                                    @click.self="selectRow(row)">
                                    <div class="row-handle absolute right-0 top-1/2 z-10 hidden -translate-y-1/2 cursor-grab items-center gap-x-1 rounded-l bg-[var(--theme-color-4)] px-2 py-1 text-[11px] font-medium text-[var(--theme-color-5)] group-hover/row:flex"
                                        :class="isRowSelected(row) ? '!flex' : ''" @click="selectRow(row)">
                                        <FontAwesomeIcon icon="fal fa-arrows-alt" fixed-width aria-hidden="true" />
                                        {{ ctrans('Row') }}
                                    </div>

                                    <div v-if="isRowSelected(row)" class="absolute bottom-0 right-0 z-10 flex overflow-hidden rounded-tl bg-[var(--theme-color-4)] text-[var(--theme-color-5)]">
                                        <template v-if="!rowHasUnsubscribeBlock(row)">
                                            <button type="button" class="px-2 py-1 hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]" v-tooltip="ctrans('Delete')" @click.stop="deleteRow(row)">
                                                <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                                            </button>
                                            <button type="button" class="px-2 py-1 hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]" v-tooltip="ctrans('Duplicate')" @click.stop="duplicateRow(row)">
                                                <FontAwesomeIcon icon="fal fa-clone" fixed-width aria-hidden="true" />
                                            </button>
                                        </template>
                                        <span v-else class="px-2 py-1" v-tooltip="ctrans('This row holds the unsubscribe block, so it cannot be deleted or duplicated')">
                                            <FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" />
                                        </span>
                                    </div>

                                    <div class="mx-auto flex transition-[width]" :class="isStackedOnMobile(row) ? 'flex-col' : ''" :style="rowContentStyle(row)" @click.self="selectRow(row)">
                                        <div v-for="column in row.columns" :key="column.uuid" :style="columnStyle(row, column)" class="email-column relative min-w-0">
                                            <draggable v-model="column.modules" item-key="uuid" group="email-modules" ghost-class="opacity-40" class="min-h-[40px]"
                                                filter=".email-inline-editor" :prevent-on-filter="false"
                                                @add="(event: any) => selectModule(column.modules[event.newIndex], row)">
                                                <template #item="{ element: module }">
                                                    <div class="email-module group/module relative cursor-pointer"
                                                        :class="[selectedModuleUuid === module.uuid ? 'z-[2] outline outline-2 -outline-offset-1 outline-[var(--theme-color-4)]' : 'hover:outline hover:outline-1 hover:-outline-offset-1 hover:outline-[var(--theme-color-4)]', isHiddenOnCurrentDevice(module.descriptor?.computedStyle) ? 'opacity-40' : '']"
                                                        @click.stop="selectModule(module, row)">
                                                        <div v-if="module.type === MODULE_TYPES.empty" class="p-4 text-center text-xs text-gray-400">
                                                            {{ ctrans('Empty block') }}
                                                        </div>
                                                        <EmailWorkshopTableEditor v-else-if="selectedModuleUuid === module.uuid && isTableModule(module)" :module="module" />
                                                        <EmailWorkshopInlineEditor v-else-if="isInlineEditing(module)" :key="`${module.uuid}-${editorRevision}-${canvasTextRevision}`"
                                                            :module="module" :linkColor="bodyLinkColor" :mergeTags="editorMergeTags"
                                                            @edited="scheduleTextSync('panel')" />
                                                        <EmailWorkshopPlaceholder v-else-if="modulePlaceholder(module)" :placeholder="modulePlaceholder(module)!" />
                                                        <div v-else class="pointer-events-none" v-html="modulePreviewHtml(row, column, module)" />

                                                        <span class="absolute left-0 top-0 hidden -translate-y-full rounded-t bg-[var(--theme-color-4)] px-1.5 py-0.5 text-[10px] capitalize text-[var(--theme-color-5)] group-hover/module:block"
                                                            :class="selectedModuleUuid === module.uuid ? '!block' : ''">
                                                            {{ moduleDisplayName(module) }}
                                                        </span>

                                                        <div v-if="selectedModuleUuid === module.uuid" class="absolute bottom-0 right-0 z-10 flex overflow-hidden rounded-tl bg-[var(--theme-color-4)] text-[11px] text-[var(--theme-color-5)]">
                                                            <template v-if="!isUnsubscribeModule(module)">
                                                                <button type="button" class="px-1.5 py-0.5 hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]" v-tooltip="ctrans('Delete')" @click.stop="deleteSelectedModule">
                                                                    <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                                                                </button>
                                                                <button type="button" class="px-1.5 py-0.5 hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]" v-tooltip="ctrans('Duplicate')" @click.stop="duplicateSelectedModule">
                                                                    <FontAwesomeIcon icon="fal fa-clone" fixed-width aria-hidden="true" />
                                                                </button>
                                                            </template>
                                                            <span v-else class="px-1.5 py-0.5" v-tooltip="ctrans('The unsubscribe block is required and cannot be deleted')">
                                                                <FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" />
                                                            </span>
                                                            <span class="cursor-grab px-1.5 py-0.5" v-tooltip="ctrans('Drag to move')">
                                                                <FontAwesomeIcon icon="fal fa-arrows-alt" fixed-width aria-hidden="true" />
                                                            </span>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template #footer>
                                                    <div v-if="column.modules.length === 0"
                                                        class="m-1 flex h-[72px] items-center justify-center border border-dashed border-[var(--theme-color-4)] bg-[color-mix(in_srgb,var(--theme-color-4)_8%,white)] text-xs text-[var(--theme-color-4)]">
                                                        {{ ctrans('Drag content here') }}
                                                    </div>
                                                </template>
                                            </draggable>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template #footer>
                                <div class="mx-auto mt-3" :style="{ width: `${canvasWidth}px`, maxWidth: '100%' }">
                                    <button type="button" class="flex w-full items-center justify-center gap-x-1.5 border border-dashed border-gray-400 bg-white/70 py-3 text-xs text-gray-600 hover:border-[var(--theme-color-4)] hover:text-[var(--theme-color-4)]"
                                        @click="addRow([12])">
                                        <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                                        {{ ctrans('Add row') }}
                                    </button>
                                </div>
                            </template>
                        </draggable>
                    </div>
                </main>

                <aside class="flex w-[360px] shrink-0 flex-col border-l border-gray-200 bg-white">
                    <template v-if="selectedModule || selectedRow">
                        <div class="flex h-12 shrink-0 items-center justify-between border-b border-gray-200 px-4">
                            <span class="text-[13px] font-semibold text-gray-800">{{ propertiesTitle }}</span>
                            <div class="flex items-center gap-x-1 text-gray-500">
                                <template v-if="!isSelectionLocked">
                                    <button type="button" class="h-7 w-7 rounded hover:bg-gray-100 hover:text-red-500" v-tooltip="ctrans('Delete')" @click="deleteSelection">
                                        <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                                    </button>
                                    <button type="button" class="h-7 w-7 rounded hover:bg-gray-100" v-tooltip="ctrans('Duplicate')" @click="duplicateSelection">
                                        <FontAwesomeIcon icon="fal fa-clone" fixed-width aria-hidden="true" />
                                    </button>
                                </template>
                                <span v-else class="flex h-7 w-7 items-center justify-center text-gray-400" v-tooltip="ctrans('Required for unsubscribe, cannot be deleted')">
                                    <FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" />
                                </span>
                                <button type="button" class="h-7 w-7 rounded hover:bg-gray-100" v-tooltip="ctrans('Close')" @click="clearSelection">
                                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <EmailWorkshopProperties :module="selectedModule" :row="selectedModule ? null : selectedRow" :body="email.page.body"
                                :imagesUploadRoute="imagesUploadRoute" :mergeTags="editorMergeTags" :textRevision="editorRevision + panelTextRevision"
                                :videoThumbnailState="selectedModule ? videoThumbnailStates[selectedModule.uuid!] : undefined"
                                @textEdited="scheduleTextSync('canvas')"
                                @replaceDynamicContent="openDynamicContentChooser(true)" />
                        </div>
                    </template>

                    <template v-else>
                        <div class="flex h-12 shrink-0 border-b border-gray-200">
                            <button v-for="tab in sidebarTabs" :key="tab.key" type="button"
                                class="flex flex-1 items-center justify-center gap-x-1.5 border-b-2 text-[13px]"
                                :class="sidebarTab === tab.key ? 'border-[var(--theme-color-4)] font-medium text-[var(--theme-color-4)]' : 'border-transparent text-gray-500 hover:text-gray-800'"
                                @click="sidebarTab = tab.key">
                                <FontAwesomeIcon :icon="tab.icon" fixed-width aria-hidden="true" />
                                {{ ctrans(tab.label) }}
                            </button>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <div v-if="sidebarTab === 'content'" class="p-4">
                                <draggable :list="availablePaletteModuleTypes" item-key="type" :group="{ name: 'email-modules', pull: 'clone', put: false }"
                                    :sort="false" :clone="clonePaletteModule" class="grid grid-cols-3 gap-2">
                                    <template #item="{ element }">
                                        <button type="button"
                                            class="flex h-[84px] cursor-grab flex-col items-center justify-center gap-y-2 rounded border border-gray-200 bg-white text-[12px] text-gray-700 transition hover:border-[var(--theme-color-4)] hover:shadow-md active:cursor-grabbing"
                                            @click="insertModule(createModule(element.type))">
                                            <FontAwesomeIcon :icon="element.icon" class="text-2xl text-gray-500" fixed-width aria-hidden="true" />
                                            {{ ctrans(element.label) }}
                                        </button>
                                    </template>
                                    <template #footer>
                                        <button type="button"
                                            class="flex h-[84px] flex-col items-center justify-center gap-y-2 rounded border border-gray-200 bg-white px-1 text-center text-[12px] leading-tight text-gray-700 transition hover:border-[var(--theme-color-4)] hover:shadow-md"
                                            @click="openDynamicContentChooser(false)">
                                            <FontAwesomeIcon icon="fal fa-puzzle-piece" class="text-2xl text-gray-500" fixed-width aria-hidden="true" />
                                            {{ ctrans('Products') }}
                                        </button>
                                    </template>
                                </draggable>
                            </div>

                            <div v-else-if="sidebarTab === 'rows'" class="p-4">
                                <div class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-gray-700">{{ ctrans('Empty rows') }}</div>
                                <draggable :list="rowLayouts" :item-key="(layout: number[]) => layout.join('-')" :group="{ name: 'email-rows', pull: 'clone', put: false }"
                                    :sort="false" :clone="cloneRowLayout" class="space-y-2">
                                    <template #item="{ element }">
                                        <button type="button" class="flex w-full cursor-grab gap-x-1 rounded border border-gray-200 bg-white p-2 transition hover:border-[var(--theme-color-4)] hover:shadow-md"
                                            @click="addRow(element)">
                                            <div v-for="(gridColumns, index) in element" :key="index"
                                                class="flex h-10 items-center justify-center rounded-sm border border-dashed border-gray-300 bg-gray-50 text-[10px] text-gray-400"
                                                :style="{ width: `${gridColumns / 12 * 100}%` }">
                                                {{ gridColumns }}
                                            </div>
                                        </button>
                                    </template>
                                </draggable>
                            </div>

                            <EmailWorkshopSettings v-else :email="email" :mailshot="mailshot" :updateMailshotRoute="updateMailshotRoute"
                                :imagesUploadRoute="imagesUploadRoute" @mailshotSaved="emits('mailshotSaved', $event)" />
                        </div>
                    </template>
                </aside>
            </div>
        </div>

        <BeefreeDynamicProducts ref="dynamicProductsRef" :shopSlug="shopSlug" :shopId="shopId" :organisationSlug="organisationSlug" />
        <BeefreeDynamicBlocks ref="dynamicBlocksRef" :shopSlug="shopSlug" />

        <Dialog v-model:visible="isDynamicContentChooserOpen" modal dismissableMask :draggable="false" :header="ctrans('Insert dynamic content')"
            :style="{ width: '34rem' }" :breakpoints="{ '640px': '95vw' }">
            <div>
                <p class="mb-4 text-sm text-gray-500">{{ ctrans('Choose what you want to add to the email.') }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button"
                        class="flex flex-col items-start gap-y-2 rounded-lg border border-gray-200 p-4 text-left transition hover:border-[var(--theme-color-4)] hover:shadow-md"
                        @click="chooseDynamicContent(dynamicProductsRef)">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[color-mix(in_srgb,var(--theme-color-4)_10%,white)] text-lg text-[var(--theme-color-4)]">
                            <FontAwesomeIcon icon="fal fa-cubes" fixed-width aria-hidden="true" />
                        </span>
                        <span class="text-sm font-semibold text-gray-900">{{ ctrans('Products') }}</span>
                        <span class="text-xs text-gray-500">{{ ctrans('Pick products from the shop and show them with image, name and a shop button.') }}</span>
                    </button>
                    <button type="button"
                        class="flex flex-col items-start gap-y-2 rounded-lg border border-gray-200 p-4 text-left transition hover:border-[var(--theme-color-4)] hover:shadow-md"
                        @click="chooseDynamicContent(dynamicBlocksRef)">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[color-mix(in_srgb,var(--theme-color-4)_10%,white)] text-lg text-[var(--theme-color-4)]">
                            <FontAwesomeIcon icon="fal fa-puzzle-piece" fixed-width aria-hidden="true" />
                        </span>
                        <span class="text-sm font-semibold text-gray-900">{{ ctrans('Dynamic blocks') }}</span>
                        <span class="text-xs text-gray-500">{{ ctrans('Insert a saved block, such as a shared header or footer.') }}</span>
                    </button>
                </div>
            </div>
        </Dialog>

        <WorkshopShortcutsDialog v-model:visible="isShortcutsDialogVisible" :shortcuts="shortcuts" />
    </div>
</template>

<style scoped>
.show-structure .email-row::after,
.show-structure .email-column::after,
.show-structure .email-module::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.show-structure .email-row::after {
    border: 1px dashed #9ca3af;
}

.show-structure .email-column::after {
    border: 1px dashed #c7d2fe;
}

.show-structure .email-module::after {
    border: 1px dotted #d1d5db;
}

.desktop-canvas :deep(.merge_content_block .desktop_hide),
.desktop-canvas :deep(.merge_content_block .desktop_hide table) {
    display: none !important;
}

.mobile-canvas :deep(.row-content) {
    width: 100% !important;
}

.mobile-canvas :deep(.stack .column),
.mobile-canvas :deep(.product-cell),
.mobile-canvas :deep(.icons-stack .icon-item) {
    display: block !important;
    width: 100% !important;
}

.mobile-canvas :deep(.icons-stack .icons-row),
.mobile-canvas :deep(.icons-stack .icons-row tbody),
.mobile-canvas :deep(.icons-stack .icons-row tr) {
    display: block !important;
    width: 100% !important;
}

.mobile-canvas :deep(.merge_content_block .mobile_hide) {
    display: none !important;
}

.mobile-canvas :deep(.merge_content_block .desktop_hide),
.mobile-canvas :deep(.merge_content_block .desktop_hide table) {
    display: table !important;
    max-height: none !important;
}

.mobile-canvas :deep(img) {
    max-width: 100%;
    height: auto;
}
</style>
