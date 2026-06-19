<script setup lang="ts">
import { ref, computed } from 'vue'
import { VueFlow, Handle, Position, useVueFlow } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faRobot, faPlus, faTrash, faCommentDots, faFlagCheckered,
    faHeadset, faBrain, faKeyboard, faPlay,
} from '@fal'
import { trans } from 'laravel-vue-i18n'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import '@vue-flow/controls/dist/style.css'

library.add(faRobot, faPlus, faTrash, faCommentDots, faFlagCheckered, faHeadset, faBrain, faKeyboard, faPlay)

interface FlowOption { id: string; label: string }
interface VFNode { id: string; type: string; position: { x: number; y: number }; data: any }
interface VFEdge { id: string; source: string; sourceHandle?: string; target: string }
interface FlowValue { start: string; nodes: VFNode[]; edges: VFEdge[] }

const props = defineProps<{
    modelValue: FlowValue | null
    startMessage: string
}>()

let counter = 0
const uid = (p: string) => `${p}_${Date.now().toString(36)}${counter++}`

// ── Initialise nodes/edges ──────────────────────────────────────────────
const nodes = ref<VFNode[]>([])
const edges = ref<VFEdge[]>([])
const startId = ref('start')

if (props.modelValue?.nodes?.length) {
    nodes.value = props.modelValue.nodes.map(n => ({ ...n, data: { ...n.data } }))
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
        data: { message: props.startMessage, options: [] as FlowOption[], isStart: true },
    }]
}

const { onConnect, addEdges } = useVueFlow()

// one outgoing edge per option handle: replace existing
onConnect((conn) => {
    edges.value = edges.value.filter(e => !(e.source === conn.source && e.sourceHandle === conn.sourceHandle))
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
        data: { message: '', options: [] as FlowOption[], isStart: false },
    })
}

function addActionNode(action: string): void {
    nodes.value.push({
        id: uid('a'),
        type: 'action',
        position: spawnPosition(),
        data: { action },
    })
}

function deleteNode(id: string): void {
    if (id === startId.value) return
    nodes.value = nodes.value.filter(n => n.id !== id)
    edges.value = edges.value.filter(e => e.source !== id && e.target !== id)
}

function addOption(node: VFNode): void {
    node.data.options.push({ id: uid('o'), label: trans('New option') })
}

function removeOption(node: VFNode, optId: string): void {
    node.data.options = node.data.options.filter((o: FlowOption) => o.id !== optId)
    edges.value = edges.value.filter(e => !(e.source === node.id && e.sourceHandle === optId))
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

    return {
        start: startId.value,
        nodes: nodes.value.map(n => ({
            id: n.id,
            type: n.type,
            position: { x: Math.round(n.position.x), y: Math.round(n.position.y) },
            data: n.type === 'step'
                ? {
                    message: n.id === startId.value ? props.startMessage : n.data.message,
                    options: n.data.options,
                    isStart: n.data.isStart,
                }
                : { action: n.data.action },
        })),
        edges: edges.value.map(e => ({ id: e.id, source: e.source, sourceHandle: e.sourceHandle, target: e.target })),
    }
}

const startOptions = computed<FlowOption[]>(() => nodes.value.find(n => n.id === startId.value)?.data.options ?? [])

defineExpose({ serialize, startOptions })
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
                    <div class="w-60 rounded-xl bg-white border-2 shadow-sm"
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

                        <!-- message -->
                        <div class="px-3 py-2">
                            <p v-if="data.isStart" class="text-xs text-gray-600 whitespace-pre-wrap break-words min-h-[2rem]">
                                {{ startMessage || trans('Edit the message in the field above ↑') }}
                            </p>
                            <textarea
                                v-else
                                v-model="data.message"
                                rows="2"
                                class="nodrag w-full text-xs border border-gray-200 rounded-md px-2 py-1.5 resize-none focus:outline-none focus:border-indigo-400"
                                :placeholder="trans('Message…')"
                            />
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

                <!-- Action node -->
                <template #node-action="{ id, data }">
                    <div class="rounded-xl bg-white border-2 border-gray-200 shadow-sm px-4 py-3 flex items-center gap-2">
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
