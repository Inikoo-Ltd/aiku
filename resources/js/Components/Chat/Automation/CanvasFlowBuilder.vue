<script setup lang="ts">
import { ref, computed } from 'vue'
import axios from 'axios'
import { VueFlow, Handle, Position, useVueFlow } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faRobot, faPlus, faTrash, faCommentDots, faFlagCheckered,
    faHeadset, faBrain, faKeyboard, faPlay,
    faAlignLeft, faLink, faAngleUp, faAngleDown,
    faBookOpen, faFile, faUpload, faSlidersV,
} from '@fal'
import { trans } from 'laravel-vue-i18n'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import '@vue-flow/controls/dist/style.css'

library.add(
    faRobot, faPlus, faTrash, faCommentDots, faFlagCheckered,
    faHeadset, faBrain, faKeyboard, faPlay,
    faAlignLeft, faLink, faAngleUp, faAngleDown,
    faBookOpen, faFile, faUpload, faSlidersV,
)

type BlockType = 'text' | 'button'
type SourceType = 'file' | 'text' | 'url'
interface Block { id: string; type: BlockType; text?: string; label?: string; url?: string }
interface KnowledgeSource { id: string; type: SourceType; name: string; title?: string; text?: string; url?: string; crawl?: boolean; maxPages?: number; uploading?: boolean; uploaded?: boolean; error?: string }
interface FlowOption { id: string; label: string }
interface VFNode { id: string; type: string; position: { x: number; y: number }; data: any }
interface VFEdge { id: string; source: string; sourceHandle?: string; target: string; targetHandle?: string }
interface FlowValue { start: string; nodes: VFNode[]; edges: VFEdge[] }

const props = defineProps<{
    modelValue: FlowValue | null
    startMessage: string
    uploadEndpoint?: string | null
    fetchUrlEndpoint?: string | null
}>()

let counter = 0
const uid = (p: string) => `${p}_${Date.now().toString(36)}${counter++}`

// ── Initialise nodes/edges ──────────────────────────────────────────────
const nodes = ref<VFNode[]>([])
const edges = ref<VFEdge[]>([])
const startId = ref('start')

function aiDefaults(): Record<string, any> {
    return {
        persona: '',
        threshold: 0.3,
        maxChunks: 4,
        fallbackMessage: trans("Sorry, I don't have that info yet."),
    }
}

function normalizeNode(n: VFNode): VFNode {
    if (n.type === 'step') {
        if (!Array.isArray(n.data.options)) {
            n.data.options = [] as FlowOption[]
        }
        if (!Array.isArray(n.data.blocks)) {
            n.data.blocks = (!n.data.isStart && n.data.message)
                ? [{ id: uid('b'), type: 'text', text: n.data.message }]
                : ([] as Block[])
        }
    } else if (n.type === 'knowledge') {
        if (!Array.isArray(n.data.sources)) {
            n.data.sources = [] as KnowledgeSource[]
        }
        if (typeof n.data.name !== 'string') {
            n.data.name = trans('Knowledge')
        }
    } else if (n.type === 'action' && n.data.action === 'ai_answer') {
        n.data = { ...aiDefaults(), ...n.data }
    }
    return n
}

if (props.modelValue?.nodes?.length) {
    nodes.value = props.modelValue.nodes.map(n => normalizeNode({ ...n, data: { ...n.data } }))
    edges.value = (props.modelValue.edges ?? []).map(e => ({ ...e }))
    startId.value = props.modelValue.start ?? 'start'
    // keep the start node message synced with the parent's message field
    const start = nodes.value.find(n => n.id === startId.value)
    if (start) start.data.message = props.startMessage
} else {
    startId.value = 'start'
    nodes.value = [{
        id: 'start',
        type: 'step',
        position: { x: 80, y: 120 },
        data: { message: props.startMessage, blocks: [] as Block[], options: [] as FlowOption[], isStart: true },
    }]
}

const { onConnect, addEdges, removeNodes, removeEdges } = useVueFlow()

// one outgoing edge per option/branch handle: replace existing.
// knowledge sources may fan out to several AI nodes, so they are exempt.
onConnect((conn) => {
    const sourceNode = nodes.value.find(n => n.id === conn.source)
    if (sourceNode?.type !== 'knowledge') {
        const duplicates = edges.value
            .filter(e => e.source === conn.source && e.sourceHandle === conn.sourceHandle)
            .map(e => e.id)
        if (duplicates.length) {
            removeEdges(duplicates)
        }
    }
    addEdges([{ ...conn, id: uid('e') }])
})

// ── Node operations ─────────────────────────────────────────────────────
function spawnPosition(): { x: number; y: number } {
    return { x: 360 + Math.random() * 120, y: 80 + Math.random() * 280 }
}

function addStepNode(): void {
    nodes.value.push({
        id: uid('s'),
        type: 'step',
        position: spawnPosition(),
        data: { message: '', blocks: [{ id: uid('b'), type: 'text', text: '' }] as Block[], options: [] as FlowOption[], isStart: false },
    })
}

function addKnowledgeNode(): void {
    nodes.value.push({
        id: uid('k'),
        type: 'knowledge',
        position: spawnPosition(),
        data: { name: trans('Knowledge'), sources: [] as KnowledgeSource[] },
    })
}

const sourceMeta: Record<SourceType, { label: string; icon: string }> = {
    file: { label: trans('File'), icon: 'fa-upload' },
    text: { label: trans('Text'), icon: 'fa-file' },
    url:  { label: trans('URL'),  icon: 'fa-link' },
}

const readableFileAccept = '.txt,.md,.markdown,.csv,.json,.jsonl,.html,.htm'

function addSource(data: any, type: SourceType): void {
    if (type === 'file') {
        data.sources.push({ id: uid('src'), type, name: '' })
    } else if (type === 'url') {
        data.sources.push({ id: uid('src'), type, name: '', url: '', crawl: true, maxPages: 50 })
    } else {
        data.sources.push({ id: uid('src'), type, name: trans('Note'), text: '' })
    }
}

function removeSource(data: any, sourceId: string): void {
    data.sources = data.sources.filter((s: KnowledgeSource) => s.id !== sourceId)
}

async function onSourceFile(nodeId: string, source: KnowledgeSource, event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) {
        return
    }

    source.name = file.name
    source.error = undefined

    if (!props.uploadEndpoint) {
        source.error = trans('Save the automation first, then upload files.')
        return
    }

    const payload = new FormData()
    payload.append('file', file)
    payload.append('knowledge_node_id', nodeId)
    payload.append('source_id', source.id)
    payload.append('title', source.title ?? '')

    source.uploading = true
    try {
        const { data } = await axios.post(props.uploadEndpoint, payload)
        source.uploaded = true
        source.name = data?.name ?? source.name
    } catch (e: any) {
        source.error = e?.response?.data?.message ?? trans('Upload failed.')
    } finally {
        source.uploading = false
        input.value = ''
    }
}

async function onFetchUrl(nodeId: string, source: KnowledgeSource): Promise<void> {
    source.error = undefined
    source.uploaded = false

    if (!source.url) {
        source.error = trans('Enter a URL first.')
        return
    }
    if (!props.fetchUrlEndpoint) {
        source.error = trans('Save the automation first, then fetch URLs.')
        return
    }

    source.uploading = true
    try {
        const { data } = await axios.post(props.fetchUrlEndpoint, {
            knowledge_node_id: nodeId,
            source_id: source.id,
            url: source.url,
            title: source.title ?? '',
            crawl: source.crawl ?? true,
            max_pages: source.maxPages ?? 50,
        })
        source.uploaded = true
        source.name = data?.name ?? source.url
    } catch (e: any) {
        source.error = e?.response?.data?.message ?? trans('Fetch failed.')
    } finally {
        source.uploading = false
    }
}

function addBlock(data: any, type: BlockType): void {
    const base = { id: uid('b'), type }
    if (type === 'text') {
        data.blocks.push({ ...base, text: '' })
    } else {
        data.blocks.push({ ...base, label: trans('Button'), url: '' })
    }
}

function removeBlock(data: any, blockId: string): void {
    data.blocks = data.blocks.filter((b: Block) => b.id !== blockId)
}

function moveBlock(data: any, index: number, dir: number): void {
    const target = index + dir
    if (target < 0 || target >= data.blocks.length) {
        return
    }
    const arr = data.blocks
    ;[arr[index], arr[target]] = [arr[target], arr[index]]
}

const blockMeta: Record<BlockType, { label: string; icon: string }> = {
    text:   { label: trans('Text'),   icon: 'fa-align-left' },
    button: { label: trans('Button'), icon: 'fa-link' },
}

function addActionNode(action: string): void {
    nodes.value.push({
        id: uid('a'),
        type: 'action',
        position: spawnPosition(),
        data: action === 'ai_answer' ? { action, ...aiDefaults() } : { action },
    })
}

function deleteNode(id: string): void {
    if (id === startId.value) {
        return
    }
    removeNodes([id], true)
}

function addOption(node: VFNode): void {
    node.data.options.push({ id: uid('o'), label: trans('New option') })
}

function removeOption(node: VFNode, optId: string): void {
    node.data.options = node.data.options.filter((o: FlowOption) => o.id !== optId)
    const dead = edges.value
        .filter(e => e.source === node.id && e.sourceHandle === optId)
        .map(e => e.id)
    if (dead.length) {
        removeEdges(dead)
    }
}

const actionMeta: Record<string, { label: string; icon: string; color: string }> = {
    end:           { label: trans('End'),          icon: 'fa-flag-checkered', color: 'text-gray-500' },
    handoff:       { label: trans('Handoff Agent'), icon: 'fa-headset',       color: 'text-blue-500' },
    ai_answer:     { label: trans('Ask AI (RAG)'),  icon: 'fa-brain',         color: 'text-purple-500' },
    collect_input: { label: trans('Collect Input'), icon: 'fa-keyboard',      color: 'text-amber-500' },
}

// ── Serialize for parent ────────────────────────────────────────────────
function serialize(): FlowValue | null {
    // a flow only exists if the start node has at least one option
    const start = nodes.value.find(n => n.id === startId.value)
    const hasOptions = !!start && start.data.options?.length > 0
    if (!hasOptions && nodes.value.length <= 1) return null

    function knowledgeNodeIdsFor(aiNodeId: string): string[] {
        return edges.value
            .filter(e => e.target === aiNodeId && e.targetHandle === 'kb')
            .map(e => e.source)
    }

    function nodeData(n: VFNode): Record<string, any> {
        if (n.type === 'step') {
            return {
                message: n.id === startId.value ? props.startMessage : n.data.message,
                blocks: n.data.blocks ?? [],
                options: n.data.options,
                isStart: n.data.isStart,
            }
        }
        if (n.type === 'knowledge') {
            const sources = (n.data.sources ?? []).map((s: KnowledgeSource) => ({
                id: s.id,
                type: s.type,
                name: s.name,
                title: s.title ?? '',
                ...(s.type === 'text' ? { text: s.text ?? '' } : {}),
                ...(s.type === 'url' ? { url: s.url ?? '', crawl: s.crawl ?? true, maxPages: s.maxPages ?? 50 } : {}),
            }))
            return { name: n.data.name, sources }
        }
        if (n.data.action === 'ai_answer') {
            return {
                action: n.data.action,
                persona: n.data.persona ?? '',
                threshold: n.data.threshold ?? 0.75,
                maxChunks: n.data.maxChunks ?? 4,
                fallbackMessage: n.data.fallbackMessage ?? '',
                knowledgeNodeIds: knowledgeNodeIdsFor(n.id),
            }
        }
        return { action: n.data.action }
    }

    return {
        start: startId.value,
        nodes: nodes.value.map(n => ({
            id: n.id,
            type: n.type,
            position: { x: Math.round(n.position.x), y: Math.round(n.position.y) },
            data: nodeData(n),
        })),
        edges: edges.value.map(e => ({ id: e.id, source: e.source, sourceHandle: e.sourceHandle, target: e.target, targetHandle: e.targetHandle })),
    }
}

const startOptions = computed<FlowOption[]>(() => nodes.value.find(n => n.id === startId.value)?.data.options ?? [])

defineExpose({
    serialize,
    startOptions,
    flowNodes: computed(() => nodes.value),
    flowEdges: computed(() => edges.value),
    startId: computed(() => startId.value),
})
</script>

<template>
    <div class="rounded-xl border border-gray-200 overflow-hidden bg-gray-50">
        <!-- Toolbar -->
        <div class="flex items-center gap-2 px-3 py-2 bg-white border-b border-gray-100">
            <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mr-1">{{ trans('Add') }}</span>
            <button type="button" class="flow-add" @click="addStepNode">
                <FontAwesomeIcon :icon="['fal', 'fa-comment-dots']" class="text-indigo-500" /> {{ trans('Step') }}
            </button>
            <button type="button" class="flow-add" @click="addActionNode('handoff')">
                <FontAwesomeIcon :icon="['fal', 'fa-headset']" class="text-blue-500" /> {{ trans('Handoff') }}
            </button>
            <button type="button" class="flow-add" @click="addActionNode('ai_answer')">
                <FontAwesomeIcon :icon="['fal', 'fa-brain']" class="text-purple-500" /> {{ trans('Ask AI') }}
            </button>
            <button type="button" class="flow-add" @click="addKnowledgeNode">
                <FontAwesomeIcon :icon="['fal', 'fa-book-open']" class="text-emerald-500" /> {{ trans('Knowledge') }}
            </button>
            <button type="button" class="flow-add" @click="addActionNode('collect_input')">
                <FontAwesomeIcon :icon="['fal', 'fa-keyboard']" class="text-amber-500" /> {{ trans('Collect Input') }}
            </button>
            <button type="button" class="flow-add" @click="addActionNode('end')">
                <FontAwesomeIcon :icon="['fal', 'fa-flag-checkered']" class="text-gray-500" /> {{ trans('End') }}
            </button>
            <span class="ml-auto text-[11px] text-gray-400">{{ trans('Drag from a button dot → into another node') }}</span>
        </div>

        <!-- Canvas -->
        <div class="h-[520px]">
            <VueFlow
                v-model:nodes="nodes"
                v-model:edges="edges"
                :default-viewport="{ zoom: 0.9 }"
                :min-zoom="0.3"
                :max-zoom="1.6"
                fit-view-on-init
            >
                <Background pattern-color="#d1d5db" :gap="18" />
                <Controls />

                <!-- Step node -->
                <template #node-step="{ id, data }">
                    <div class="w-72 rounded-xl bg-white border-2 shadow-sm"
                        :class="data.isStart ? 'border-green-400' : 'border-indigo-200'">
                        <Handle v-if="!data.isStart" type="target" :position="Position.Left" class="!bg-indigo-400" />

                        <!-- header -->
                        <div class="flex items-center justify-between px-3 py-2 rounded-t-xl"
                            :class="data.isStart ? 'bg-green-50' : 'bg-indigo-50'">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold"
                                :class="data.isStart ? 'text-green-700' : 'text-indigo-700'">
                                <FontAwesomeIcon :icon="['fal', data.isStart ? 'fa-play' : 'fa-comment-dots']" />
                                {{ data.isStart ? trans('Start') : trans('Step') }}
                            </span>
                            <button v-if="!data.isStart" type="button" class="text-gray-300 hover:text-red-500 nodrag" @click="deleteNode(id)">
                                <FontAwesomeIcon :icon="['fal', 'fa-trash']" class="text-[11px]" />
                            </button>
                        </div>

                        <!-- content blocks -->
                        <div class="px-3 py-2 space-y-2">
                            <div v-if="data.isStart" class="text-xs text-gray-600 whitespace-pre-wrap break-words bg-green-50/60 border border-green-100 rounded-md px-2 py-1.5">
                                {{ startMessage || trans('Edit the first message in the field on the left ↖') }}
                            </div>

                            <div v-for="(block, idx) in data.blocks" :key="block.id"
                                class="rounded-md border border-gray-200 bg-gray-50/60 p-2 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                                        <FontAwesomeIcon :icon="['fal', blockMeta[block.type].icon]" />{{ blockMeta[block.type].label }}
                                    </span>
                                    <div class="nodrag flex items-center gap-0.5 text-gray-300">
                                        <button type="button" class="hover:text-gray-600 px-0.5" @click="moveBlock(data, idx, -1)">
                                            <FontAwesomeIcon :icon="['fal', 'fa-angle-up']" class="text-[11px]" />
                                        </button>
                                        <button type="button" class="hover:text-gray-600 px-0.5" @click="moveBlock(data, idx, 1)">
                                            <FontAwesomeIcon :icon="['fal', 'fa-angle-down']" class="text-[11px]" />
                                        </button>
                                        <button type="button" class="hover:text-red-500 px-0.5" @click="removeBlock(data, block.id)">
                                            <FontAwesomeIcon :icon="['fal', 'fa-trash']" class="text-[10px]" />
                                        </button>
                                    </div>
                                </div>

                                <textarea v-if="block.type === 'text'" v-model="block.text" rows="2"
                                    class="nodrag w-full text-xs border border-gray-200 rounded-md px-2 py-1.5 resize-none focus:outline-none focus:border-indigo-400"
                                    :placeholder="trans('Text…')" />

                                <template v-else>
                                    <input v-model="block.label"
                                        class="nodrag w-full text-[11px] border border-gray-200 rounded-md px-2 py-1 mb-1 focus:outline-none focus:border-indigo-400"
                                        :placeholder="trans('Button label')" />
                                    <input v-model="block.url"
                                        class="nodrag w-full text-[11px] border border-gray-200 rounded-md px-2 py-1 focus:outline-none focus:border-indigo-400"
                                        :placeholder="trans('https://… (opens link)')" />
                                </template>
                            </div>

                            <div class="nodrag flex flex-wrap gap-1">
                                <button v-for="t in (['text','button'] as const)" :key="t" type="button"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-dashed border-gray-200 text-[10px] font-medium text-gray-500 hover:bg-gray-100"
                                    @click="addBlock(data, t)">
                                    <FontAwesomeIcon :icon="['fal', blockMeta[t].icon]" />{{ blockMeta[t].label }}
                                </button>
                            </div>
                        </div>

                        <!-- options -->
                        <div class="px-3 pb-2 space-y-1.5">
                            <div v-for="opt in data.options" :key="opt.id" class="relative flex items-center gap-1.5">
                                <input
                                    v-model="opt.label"
                                    class="nodrag flex-1 text-[11px] border border-gray-200 rounded-full px-2.5 py-1 focus:outline-none focus:border-indigo-400"
                                    :placeholder="trans('Button label')"
                                />
                                <button type="button" class="nodrag text-gray-300 hover:text-red-500" @click="removeOption(nodes.find(n => n.id === id)!, opt.id)">
                                    <FontAwesomeIcon :icon="['fal', 'fa-trash']" class="text-[10px]" />
                                </button>
                                <!-- source handle for this option -->
                                <Handle :id="opt.id" type="source" :position="Position.Right" class="!bg-indigo-500 !w-2.5 !h-2.5" style="right:-14px" />
                            </div>

                            <button type="button" class="nodrag w-full text-[11px] text-indigo-600 hover:text-indigo-800 font-medium py-1 border border-dashed border-indigo-200 rounded-md"
                                @click="addOption(nodes.find(n => n.id === id)!)">
                                <FontAwesomeIcon :icon="['fal', 'fa-plus']" class="mr-1" />{{ trans('Add option') }}
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Knowledge node -->
                <template #node-knowledge="{ id, data }">
                    <div class="w-64 rounded-xl bg-white border-2 border-emerald-200 shadow-sm">
                        <div class="flex items-center gap-1.5 px-3 py-2 rounded-t-xl bg-emerald-50">
                            <FontAwesomeIcon :icon="['fal', 'fa-book-open']" class="text-emerald-600" />
                            <input v-model="data.name"
                                class="nodrag flex-1 bg-transparent text-[11px] font-semibold text-emerald-700 focus:outline-none"
                                :placeholder="trans('Knowledge name')" />
                            <button type="button" class="nodrag text-gray-300 hover:text-red-500" @click="deleteNode(id)">
                                <FontAwesomeIcon :icon="['fal', 'fa-trash']" class="text-[11px]" />
                            </button>
                        </div>

                        <div class="px-3 py-2 space-y-2">
                            <p v-if="!data.sources.length" class="text-[10px] text-gray-400">
                                {{ trans('Add files or notes the AI may read from.') }}
                            </p>

                            <div v-for="source in data.sources" :key="source.id"
                                class="rounded-md border border-gray-200 bg-gray-50/60 p-2 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                                        <FontAwesomeIcon :icon="['fal', sourceMeta[source.type].icon]" />{{ sourceMeta[source.type].label }}
                                    </span>
                                    <button type="button" class="nodrag text-gray-300 hover:text-red-500 px-0.5" @click="removeSource(data, source.id)">
                                        <FontAwesomeIcon :icon="['fal', 'fa-trash']" class="text-[10px]" />
                                    </button>
                                </div>

                                <template v-if="source.type === 'file'">
                                    <input v-model="source.title"
                                        class="nodrag w-full text-[11px] border border-gray-200 rounded-md px-2 py-1 mb-1 focus:outline-none focus:border-emerald-400"
                                        :placeholder="trans('Title, e.g. Pricing documentation')" />
                                    <input type="file" :accept="readableFileAccept" class="nodrag w-full text-[10px] text-gray-600 file:mr-2 file:rounded file:border-0 file:bg-emerald-50 file:px-2 file:py-0.5 file:text-emerald-700"
                                        @change="onSourceFile(id, source, $event)" />
                                    <p class="text-[10px] text-gray-400">{{ trans('Supported: TXT, MD, CSV, JSON, JSONL, HTML. Max 10MB.') }}</p>
                                    <p v-if="source.name" class="text-[10px] text-gray-500 truncate">{{ source.name }}</p>
                                    <p v-if="source.uploading" class="text-[10px] text-indigo-500">{{ trans('Uploading…') }}</p>
                                    <p v-else-if="source.uploaded" class="text-[10px] text-emerald-600">{{ trans('Uploaded ✓') }}</p>
                                    <p v-if="source.error" class="text-[10px] text-red-500">{{ source.error }}</p>
                                </template>
                                <template v-else-if="source.type === 'url'">
                                    <input v-model="source.title"
                                        class="nodrag w-full text-[11px] border border-gray-200 rounded-md px-2 py-1 mb-1 focus:outline-none focus:border-emerald-400"
                                        :placeholder="trans('Title, e.g. Pricing documentation')" />
                                    <input v-model="source.url"
                                        class="nodrag w-full text-[11px] border border-gray-200 rounded-md px-2 py-1 mb-1 focus:outline-none focus:border-emerald-400"
                                        :placeholder="trans('https://example.com/knowledge-base/')" />
                                    <label class="nodrag flex items-center gap-1.5 text-[10px] text-gray-500 mb-1">
                                        <input v-model="source.crawl" type="checkbox" class="rounded border-gray-300" />
                                        {{ trans('Crawl the whole section') }}
                                    </label>
                                    <div v-if="source.crawl" class="flex items-center gap-1 mb-1">
                                        <span class="text-[10px] text-gray-400">{{ trans('Max pages') }}</span>
                                        <input v-model.number="source.maxPages" type="number" min="1" max="200" step="1"
                                            class="nodrag w-16 text-[11px] border border-gray-200 rounded-md px-2 py-0.5 focus:outline-none focus:border-emerald-400" />
                                    </div>
                                    <button type="button"
                                        class="nodrag w-full inline-flex items-center justify-center gap-1 text-[11px] font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-md py-1"
                                        :disabled="source.uploading"
                                        @click="onFetchUrl(id, source)">
                                        <FontAwesomeIcon :icon="['fal', 'fa-link']" />
                                        {{ source.uploading ? trans('Fetching…') : trans('Fetch & index') }}
                                    </button>
                                    <p v-if="source.uploaded" class="text-[10px] text-emerald-600">{{ trans('Indexed ✓') }}</p>
                                    <p v-if="source.error" class="text-[10px] text-red-500">{{ source.error }}</p>
                                </template>

                                <template v-else>
                                    <input v-model="source.name"
                                        class="nodrag w-full text-[11px] border border-gray-200 rounded-md px-2 py-1 mb-1 focus:outline-none focus:border-emerald-400"
                                        :placeholder="trans('Note title')" />
                                    <textarea v-model="source.text" rows="2"
                                        class="nodrag w-full text-xs border border-gray-200 rounded-md px-2 py-1.5 resize-none focus:outline-none focus:border-emerald-400"
                                        :placeholder="trans('Knowledge text…')" />
                                </template>
                            </div>

                            <div class="nodrag flex flex-wrap gap-1">
                                <button v-for="t in (['file','text','url'] as const)" :key="t" type="button"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-dashed border-gray-200 text-[10px] font-medium text-gray-500 hover:bg-gray-100"
                                    @click="addSource(data, t)">
                                    <FontAwesomeIcon :icon="['fal', sourceMeta[t].icon]" />{{ sourceMeta[t].label }}
                                </button>
                            </div>
                        </div>

                        <Handle id="kb" type="source" :position="Position.Right" class="!bg-emerald-500 !w-2.5 !h-2.5" />
                    </div>
                </template>

                <!-- Action node -->
                <template #node-action="{ id, data }">
                    <div v-if="data.action === 'ai_answer'" class="w-72 rounded-xl bg-white border-2 border-purple-200 shadow-sm">
                        <Handle id="in" type="target" :position="Position.Left" class="!bg-purple-400" />
                        <Handle id="kb" type="target" :position="Position.Top" class="!bg-emerald-400 !w-2.5 !h-2.5" />

                        <div class="flex items-center gap-1.5 px-3 py-2 rounded-t-xl bg-purple-50">
                            <FontAwesomeIcon :icon="['fal', 'fa-brain']" class="text-purple-600" />
                            <span class="flex-1 text-[11px] font-semibold text-purple-700">{{ trans('Ask AI (RAG)') }}</span>
                            <button type="button" class="nodrag text-gray-300 hover:text-red-500" @click="deleteNode(id)">
                                <FontAwesomeIcon :icon="['fal', 'fa-trash']" class="text-[11px]" />
                            </button>
                        </div>

                        <div class="px-3 py-2 space-y-2 nodrag">
                            <p class="inline-flex items-center gap-1 text-[10px] text-emerald-600">
                                <FontAwesomeIcon :icon="['fal', 'fa-book-open']" />{{ trans('Connect Knowledge nodes ↑ to set its scope') }}
                            </p>

                            <div>
                                <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{{ trans('Persona') }}</label>
                                <textarea v-model="data.persona" rows="2"
                                    class="w-full mt-0.5 text-xs border border-gray-200 rounded-md px-2 py-1.5 resize-none focus:outline-none focus:border-purple-400"
                                    :placeholder="trans('Friendly, concise, answers in the customer language…')" />
                            </div>

                            <div class="flex items-center gap-2">
                                <div class="flex-1">
                                    <label class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                                        <FontAwesomeIcon :icon="['fal', 'fa-sliders-v']" />{{ trans('Match threshold') }}
                                    </label>
                                    <input v-model.number="data.threshold" type="number" min="0" max="1" step="0.05"
                                        class="w-full mt-0.5 text-[11px] border border-gray-200 rounded-md px-2 py-1 focus:outline-none focus:border-purple-400" />
                                    <span class="text-[9px] text-gray-400">{{ trans('0.25–0.4 typical') }}</span>
                                </div>
                                <div class="w-16">
                                    <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{{ trans('Chunks') }}</label>
                                    <input v-model.number="data.maxChunks" type="number" min="1" max="10" step="1"
                                        class="w-full mt-0.5 text-[11px] border border-gray-200 rounded-md px-2 py-1 focus:outline-none focus:border-purple-400" />
                                </div>
                            </div>

                            <div>
                                <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{{ trans('Fallback (not found)') }}</label>
                                <input v-model="data.fallbackMessage"
                                    class="w-full mt-0.5 text-[11px] border border-gray-200 rounded-md px-2 py-1 focus:outline-none focus:border-purple-400"
                                    :placeholder="trans('Said when nothing matches…')" />
                            </div>
                        </div>

                        <div class="px-3 pb-2 space-y-1.5">
                            <div class="relative flex items-center justify-end gap-1.5 text-[11px] font-medium text-green-600">
                                {{ trans('Answered') }}
                                <Handle id="answered" type="source" :position="Position.Right" class="!bg-green-500 !w-2.5 !h-2.5" style="right:-14px" />
                            </div>
                            <div class="relative flex items-center justify-end gap-1.5 text-[11px] font-medium text-amber-600">
                                {{ trans('Not found') }}
                                <Handle id="fallback" type="source" :position="Position.Right" class="!bg-amber-500 !w-2.5 !h-2.5" style="right:-14px" />
                            </div>
                        </div>
                    </div>

                    <div v-else class="rounded-xl bg-white border-2 border-gray-200 shadow-sm px-4 py-3 flex items-center gap-2">
                        <Handle type="target" :position="Position.Left" class="!bg-gray-400" />
                        <FontAwesomeIcon :icon="['fal', actionMeta[data.action]?.icon ?? 'fa-flag-checkered']" :class="actionMeta[data.action]?.color" />
                        <span class="text-xs font-semibold text-gray-700">{{ actionMeta[data.action]?.label ?? data.action }}</span>
                        <button type="button" class="nodrag text-gray-300 hover:text-red-500 ml-1" @click="deleteNode(id)">
                            <FontAwesomeIcon :icon="['fal', 'fa-trash']" class="text-[11px]" />
                        </button>
                    </div>
                </template>
            </VueFlow>
        </div>
    </div>
</template>

<style scoped>
.flow-add {
    @apply inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border border-gray-200 text-[11px] font-medium text-gray-600 hover:bg-gray-50 transition-colors;
}
</style>
