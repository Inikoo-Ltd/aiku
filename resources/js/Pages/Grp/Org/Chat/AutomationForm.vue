<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { Head, useForm } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import CanvasFlowBuilder from '@/Components/Chat/Automation/CanvasFlowBuilder.vue'
import { capitalize } from '@/Composables/capitalize'
import { trans } from 'laravel-vue-i18n'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faRobot, faSave, faPaperPlane, faPaperclip, faRedo, faTimes, faProjectDiagram, faShare, faSlidersH, faAngleLeft, faAngleRight } from '@fal'
import { PageHeadingTypes } from '@/types/PageHeading'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'

library.add(faRobot, faSave, faPaperPlane, faPaperclip, faRedo, faTimes, faProjectDiagram, faShare, faSlidersH, faAngleLeft, faAngleRight)

interface ShopOption { id: number; name: string; code: string }
interface TriggerType { value: string; label: string; description: string }
interface FlowData { start: string; nodes: any[]; edges: any[] }
interface Automation {
    id: number
    shop_id: number
    name: string
    trigger_type: string
    is_enabled: boolean
    message: string
    flow: FlowData | null
    conditions: Record<string, any>
    priority: number
    send_once: boolean
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    automation: Automation | null
    shops: ShopOption[]
    triggerTypes: TriggerType[]
    submitRoute: { name: string; parameters: Record<string, any>; method: string }
}>()

const isEdit = computed(() => !!props.automation)

const form = useForm({
    shop_id:      props.automation?.shop_id ?? (props.shops[0]?.id ?? null),
    name:         props.automation?.name ?? '',
    trigger_type: props.automation?.trigger_type ?? 'welcome',
    is_enabled:   props.automation?.is_enabled ?? true,
    message:      props.automation?.message ?? '',
    flow:         null as FlowData | null,
    priority:     props.automation?.priority ?? 0,
    send_once:    props.automation?.send_once ?? true,
    conditions: {
        delay_seconds: props.automation?.conditions?.delay_seconds ?? 0,
        after_minutes: props.automation?.conditions?.after_minutes ?? 3,
    },
})

const canvasRef = ref<InstanceType<typeof CanvasFlowBuilder> | null>(null)
const settingsOpen = ref(true)

const uploadEndpoint = computed<string | null>(() =>
    isEdit.value
        ? route('grp.models.org.chat.automation.knowledge.upload', props.submitRoute.parameters)
        : null,
)

const fetchUrlEndpoint = computed<string | null>(() =>
    isEdit.value
        ? route('grp.models.org.chat.automation.knowledge.fetch-url', props.submitRoute.parameters)
        : null,
)

const previewAnswerEndpoint = computed<string | null>(() =>
    isEdit.value
        ? route('grp.models.org.chat.automation.knowledge.preview-answer', props.submitRoute.parameters)
        : null,
)

const shopOptions = computed(() => props.shops.map(s => ({ label: `${s.name} (${s.code})`, value: s.id })))
const currentTrigger = computed(() => props.triggerTypes.find(t => t.value === form.trigger_type))
const variables = ['{customer_name}', '{shop_name}', '{business_hours}', '{agent_name}']

function insertVariable(v: string): void {
    form.message = `${form.message}${form.message && !form.message.endsWith(' ') ? ' ' : ''}${v} `
}

const shopName = computed(() => props.shops.find(s => s.id === form.shop_id)?.name ?? 'Shop')

function render(text: string): string {
    return (text || '')
        .replace(/\{customer_name\}/g, 'John')
        .replace(/\{shop_name\}/g, shopName.value)
        .replace(/\{business_hours\}/g, 'Mon–Fri 9am–5pm')
        .replace(/\{agent_name\}/g, 'Sarah')
}

function nowTime(): string {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

interface Block { id: string; type: 'text' | 'button'; text?: string; label?: string; url?: string }
interface SimMessage {
    role: 'bot' | 'user' | 'system'
    text?: string
    blocks?: Block[]
    time: string
}

const flowNodes = computed<any[]>(() => canvasRef.value?.flowNodes ?? [])
const flowEdges = computed<any[]>(() => canvasRef.value?.flowEdges ?? [])
const startNodeId = computed<string>(() => canvasRef.value?.startId ?? 'start')
const startOptions = computed<{ id: string; label: string }[]>(() => canvasRef.value?.startOptions ?? [])

function nodeById(id: string): any {
    return flowNodes.value.find(n => n.id === id)
}

function targetFor(nodeId: string, optionId: string): any {
    const edge = flowEdges.value.find(e => e.source === nodeId && e.sourceHandle === optionId)
    return edge ? nodeById(edge.target) : null
}

function knowledgeNodeIdsFor(nodeId: string): string[] {
    return flowEdges.value
        .filter(e => e.target === nodeId && e.targetHandle === 'kb')
        .map(e => e.source)
}

const simLog = ref<SimMessage[]>([])
const simActiveNodeId = ref<string>('start')
const simActiveOptions = ref<{ id: string; label: string }[]>([])
const simEnded = ref(false)
const simAiNode = ref<any | null>(null)
const aiQuestion = ref('')
const simAiThinking = ref(false)
const hasInteracted = computed(() => simLog.value.some(m => m.role === 'user'))

function blocksForNode(node: any): Block[] {
    const blocks: Block[] = []
    if (node?.id === startNodeId.value) {
        const text = render(form.message)
        if (text) {
            blocks.push({ id: 'start-text', type: 'text', text })
        }
    }
    for (const b of (node?.data?.blocks ?? [])) {
        blocks.push(b.type === 'text' ? { ...b, text: render(b.text ?? '') } : { ...b })
    }
    return blocks
}

function resetSim(): void {
    const startNode = nodeById(startNodeId.value)
    const blocks = startNode
        ? blocksForNode(startNode)
        : (render(form.message) ? [{ id: 'start-text', type: 'text', text: render(form.message) } as Block] : [])
    simLog.value = [{
        role: 'bot',
        blocks: blocks.length ? blocks : [{ id: 'ph', type: 'text', text: trans('Your message preview will appear here…') }],
        time: nowTime(),
    }]
    simActiveNodeId.value = startNodeId.value
    simActiveOptions.value = startOptions.value
    simEnded.value = false
    simAiNode.value = null
    aiQuestion.value = ''
    simAiThinking.value = false
}

const actionFeedback: Record<string, { role: SimMessage['role']; text: string; end: boolean }> = {
    end:           { role: 'system', text: trans('— Conversation ended —'),                                  end: true },
    handoff:       { role: 'system', text: trans('— Connecting you to a live agent… —'),                     end: true },
    ai_answer:     { role: 'bot',    text: trans('🤖 The AI assistant answers from the knowledge base…'),     end: true },
    collect_input: { role: 'bot',    text: trans('Please type your reply below…'),                           end: true },
}

function advanceTo(node: any): void {
    if (!node) {
        simLog.value.push({ role: 'system', text: trans('— Chat ended —'), time: nowTime() })
        simEnded.value = true
        return
    }
    if (node.type === 'step') {
        const blocks = blocksForNode(node)
        simLog.value.push({ role: 'bot', blocks: blocks.length ? blocks : [{ id: 'ph', type: 'text', text: trans('…') }], time: nowTime() })
        simActiveNodeId.value = node.id
        simActiveOptions.value = node.data.options ?? []
        if (!simActiveOptions.value.length) {
            simEnded.value = true
        }
    } else if (node.data.action === 'ai_answer') {
        simLog.value.push({ role: 'system', text: trans('🤖 Ask the AI a question about your knowledge base'), time: nowTime() })
        simActiveNodeId.value = node.id
        simActiveOptions.value = []
        simAiNode.value = node
    } else {
        const fb = actionFeedback[node.data.action] ?? actionFeedback.end
        simLog.value.push({ role: fb.role, text: fb.text, time: nowTime() })
        simEnded.value = fb.end
    }
}

function activeAiNode(): any {
    return simAiNode.value
        ?? flowNodes.value.find(n => n.type === 'action' && n.data?.action === 'ai_answer')
}

function linkify(text: string): string {
    const escaped = (text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')

    return escaped.replace(/(https?:\/\/[^\s<]+[^\s<.,;:!?)\]])/g, url =>
        `<a href="${url}" target="_blank" rel="noopener" class="text-indigo-600 underline break-all">${url}</a>`,
    )
}

async function sendMessage(): Promise<void> {
    const question = aiQuestion.value.trim()
    if (!question || simAiThinking.value) {
        return
    }

    const node = activeAiNode()
    const history = simLog.value
        .filter(m => m.role === 'user' || m.role === 'bot')
        .slice(-8)
        .map(m => ({
            role: m.role,
            text: m.text ?? (m.blocks ?? []).map(b => b.text || b.label).filter(Boolean).join(' '),
        }))
        .filter(m => m.text.trim() !== '')

    simLog.value.push({ role: 'user', text: question, time: nowTime() })
    aiQuestion.value = ''

    if (!node) {
        simLog.value.push({ role: 'system', text: trans('Add an “Ask AI” node to chat with the AI.'), time: nowTime() })
        return
    }
    if (!previewAnswerEndpoint.value) {
        simLog.value.push({ role: 'system', text: trans('Save the automation first to test the AI.'), time: nowTime() })
        return
    }

    const isFlowDriven = simAiNode.value === node

    simAiThinking.value = true
    try {
        const { data } = await axios.post(previewAnswerEndpoint.value, {
            question,
            knowledge_node_ids: knowledgeNodeIdsFor(node.id),
            threshold: node.data.threshold ?? 0.3,
            max_chunks: node.data.maxChunks ?? 4,
            persona: node.data.persona ?? '',
            fallback_message: node.data.fallbackMessage ?? '',
            history,
        })
        simLog.value.push({ role: 'bot', text: data?.text ?? trans('…'), time: nowTime() })

        if (isFlowDriven) {
            simAiNode.value = null
            advanceTo(targetFor(node.id, data?.answered ? 'answered' : 'fallback'))
        }
    } catch (e: any) {
        simLog.value.push({ role: 'system', text: e?.response?.data?.message ?? trans('AI request failed.'), time: nowTime() })
    } finally {
        simAiThinking.value = false
    }
}

function pickOption(opt: { id: string; label: string }): void {
    if (simEnded.value) {
        return
    }
    const fromNode = simActiveNodeId.value
    simLog.value.push({ role: 'user', text: opt.label || trans('Option'), time: nowTime() })
    simActiveOptions.value = []
    advanceTo(targetFor(fromNode, opt.id))
}

watch(
    [() => form.message, () => canvasRef.value?.flowNodes, () => canvasRef.value?.flowEdges],
    () => {
        if (!hasInteracted.value) {
            resetSim()
        }
    },
    { deep: true, immediate: true },
)

watch(() => form.trigger_type, () => {
    if (!isEdit.value && !form.name) {
        form.name = currentTrigger.value?.label ?? ''
    }
})

function submit(): void {
    form.flow = canvasRef.value?.serialize() ?? null
    const url = route(props.submitRoute.name, props.submitRoute.parameters)
    const options = {
        preserveScroll: true,
        onSuccess: () => {

        },
    }
    if (props.submitRoute.method === 'patch') {
        form.patch(url, options)
    } else {
        form.post(url, options)
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="w-full px-4  py-6 grid grid-cols-1 gap-5 items-start"
        :class="settingsOpen ? 'xl:grid-cols-[360px_minmax(0,1fr)_340px]' : 'xl:grid-cols-[48px_minmax(0,1fr)_340px]'">

        <!-- Left column collapsed: thin rail -->
        <div v-if="!settingsOpen" class="hidden xl:flex flex-col items-center">
            <button type="button"
                class="w-10 h-10 rounded-xl bg-white border border-gray-200 text-gray-500 hover:text-indigo-600 hover:border-indigo-300 flex items-center justify-center shadow-sm"
                v-tooltip="trans('Show settings')"
                @click="settingsOpen = true">
                <FontAwesomeIcon :icon="['fal', 'fa-sliders-h']" />
            </button>
            <button type="button"
                class="mt-2 w-10 h-10 rounded-xl bg-white border border-gray-200 text-gray-400 hover:text-indigo-600 hover:border-indigo-300 flex items-center justify-center shadow-sm"
                v-tooltip="trans('Show settings')"
                @click="settingsOpen = true">
                <FontAwesomeIcon :icon="['fal', 'fa-angle-right']" />
            </button>
        </div>

        <!-- Left column: settings -->
        <div v-show="settingsOpen" class="space-y-5">

            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Settings') }}</span>
                <button type="button"
                    class="inline-flex items-center gap-1 text-[11px] font-medium text-gray-500 hover:text-indigo-600"
                    v-tooltip="trans('Collapse to widen the canvas')"
                    @click="settingsOpen = false">
                    <FontAwesomeIcon :icon="['fal', 'fa-angle-left']" class="text-[11px]" />
                    {{ trans('Hide') }}
                </button>
            </div>

            <!-- Details -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Details') }}</h3>

                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('Shop') }} <span class="text-red-500">*</span></label>
                    <Select v-model="form.shop_id" :options="shopOptions" option-label="label" option-value="value" :disabled="isEdit" filter class="w-full" />
                    <p v-if="form.errors.shop_id" class="text-xs text-red-500">{{ form.errors.shop_id }}</p>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('Name') }} <span class="text-red-500">*</span></label>
                    <InputText v-model="form.name" class="w-full" :placeholder="trans('e.g. Out of hours reply')" />
                    <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('Trigger') }} <span class="text-red-500">*</span></label>
                    <Select v-model="form.trigger_type" :options="triggerTypes" option-label="label" option-value="value" class="w-full" />
                    <p v-if="currentTrigger" class="text-[11px] text-gray-400">{{ currentTrigger.description }}</p>
                </div>
            </div>

            <!-- Welcome message -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Message') }}</h3>
                <Textarea v-model="form.message" rows="3" class="w-full text-sm" auto-resize :placeholder="trans('Type the automated message…')" />
                <p v-if="form.errors.message" class="text-xs text-red-500">{{ form.errors.message }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <button v-for="v in variables" :key="v" type="button"
                        class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 text-[11px] font-mono hover:bg-indigo-100 transition-colors"
                        @click="insertVariable(v)">
                        {{ v }}
                    </button>
                </div>
                <p class="text-[11px] text-gray-400">{{ trans('This is the first message — the Start node in the flow below.') }}</p>
            </div>

            <!-- Conditions -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Conditions') }}</h3>
                <div v-if="form.trigger_type === 'welcome'" class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('Delay (seconds)') }}</label>
                    <InputNumber v-model="form.conditions.delay_seconds" :min="0" :max="120" show-buttons class="w-full" />
                </div>
                <div v-else-if="form.trigger_type === 'no_reply'" class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('After (minutes)') }}</label>
                    <InputNumber v-model="form.conditions.after_minutes" :min="1" :max="120" show-buttons class="w-full" />
                </div>
                <p v-else class="text-xs text-gray-400 italic">
                    {{ form.trigger_type === 'offline'
                        ? trans('Fires automatically based on the shop business hours.')
                        : trans('Fires while the conversation is waiting for an agent.') }}
                </p>
            </div>

            <!-- Advanced -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Advanced') }}</h3>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-700">{{ trans('Send once') }}</p>
                        <p class="text-[11px] text-gray-400">{{ trans('Avoid repeats') }}</p>
                    </div>
                    <ToggleSwitch v-model="form.send_once" />
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-700">{{ trans('Enabled') }}</p>
                    </div>
                    <ToggleSwitch v-model="form.is_enabled" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('Priority') }}</label>
                    <InputNumber v-model="form.priority" :min="0" :max="1000" show-buttons class="w-full" />
                </div>
            </div>
        </div>

        <!-- Middle column: flow canvas -->
        <div class="space-y-5">

            <!-- Canvas builder -->
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <FontAwesomeIcon :icon="['fal', 'fa-project-diagram']" class="text-indigo-500" />
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Conversation Flow') }}</h3>
                    <span class="text-[11px] text-gray-400">{{ trans('Optional — add reply buttons & branches') }}</span>
                </div>
                <CanvasFlowBuilder
                    ref="canvasRef"
                    :model-value="props.automation?.flow ?? null"
                    :start-message="form.message"
                    :upload-endpoint="uploadEndpoint"
                    :fetch-url-endpoint="fetchUrlEndpoint"
                />
            </div>
        </div>

        <!-- Right column: preview + save -->
        <div class="space-y-5 xl:sticky xl:top-6">
            <!-- Live preview -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Live Preview') }}</p>
                    <button type="button" class="inline-flex items-center gap-1.5 text-[11px] font-medium text-indigo-600 hover:text-indigo-800"
                        @click="resetSim">
                        <FontAwesomeIcon :icon="['fal', 'fa-redo']" class="text-[10px]" />
                        {{ trans('Restart') }}
                    </button>
                </div>
                <div class="mx-auto w-[300px] max-w-full rounded-[2rem] bg-gray-900 p-2.5 shadow-2xl">
                    <div class="rounded-[1.5rem] overflow-hidden bg-white flex flex-col h-[480px]">
                        <div class="flex items-center gap-2.5 px-4 py-3 bg-gradient-to-r from-indigo-600 to-indigo-500 text-white shrink-0">
                            <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                                <FontAwesomeIcon :icon="['fal', 'fa-robot']" class="text-sm" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold truncate">{{ shopName }} {{ trans('Assistant') }}</p>
                                <p class="text-[11px] text-white/80 truncate">{{ trans('Virtual Assistant') }}</p>
                            </div>
                            <FontAwesomeIcon :icon="['fal', 'fa-redo']" class="text-sm text-white/80 cursor-pointer" @click="resetSim" />
                            <FontAwesomeIcon :icon="['fal', 'fa-times']" class="text-base text-white/80" />
                        </div>

                        <div class="flex-1 overflow-y-auto px-3 py-4 bg-[#f0f4f8] space-y-3">
                            <template v-for="(msg, i) in simLog" :key="i">
                                <!-- bot bubble -->
                                <div v-if="msg.role === 'bot'" class="flex items-end gap-2">
                                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-500 flex items-center justify-center shrink-0 text-[10px]">
                                        <FontAwesomeIcon :icon="['fal', 'fa-robot']" />
                                    </div>
                                    <div class="max-w-[85%] space-y-1.5">
                                        <p class="text-[10px] text-gray-400 mb-1 ml-1">{{ shopName }} {{ trans('Assistant') }}</p>

                                        <div class="rounded-2xl rounded-bl-md bg-white text-gray-800 px-3 py-3 text-sm shadow-sm space-y-2">
                                            <template v-if="msg.blocks?.length">
                                                <template v-for="block in msg.blocks" :key="block.id">
                                                    <p v-if="block.type === 'text'" class="whitespace-pre-wrap break-words leading-relaxed px-0.5">
                                                        {{ block.text }}
                                                    </p>

                                                    <a v-else
                                                        :href="block.url || undefined" target="_blank" rel="noopener"
                                                        class="flex items-center justify-center gap-1.5 rounded-xl bg-white border border-gray-200 text-indigo-600 px-3 py-2 text-xs font-medium hover:bg-indigo-50">
                                                        <FontAwesomeIcon :icon="['fal', 'fa-share']" class="text-[10px]" />
                                                        {{ block.label || trans('Button') }}
                                                    </a>
                                                </template>
                                            </template>

                                            <p v-else class="whitespace-pre-wrap break-words leading-relaxed px-0.5" v-html="linkify(msg.text)"></p>
                                        </div>

                                        <div class="text-[10px] text-gray-400 ml-1">{{ msg.time }}</div>
                                    </div>
                                </div>

                                <!-- user bubble -->
                                <div v-else-if="msg.role === 'user'" class="flex justify-end">
                                    <div class="max-w-[80%]">
                                        <div class="rounded-2xl rounded-br-md bg-indigo-500 text-white px-3.5 py-2.5 text-sm shadow-sm">
                                            <p class="whitespace-pre-wrap break-words leading-relaxed">{{ msg.text }}</p>
                                            <div class="text-[10px] text-white/70 text-right mt-1">{{ msg.time }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- system note -->
                                <div v-else class="flex justify-center">
                                    <span class="text-[11px] text-gray-400 bg-gray-200/60 rounded-full px-3 py-1">{{ msg.text }}</span>
                                </div>
                            </template>

                            <!-- active option buttons -->
                            <div v-if="simActiveOptions.length && !simEnded" class="flex flex-wrap gap-2 justify-end pl-8">
                                <button v-for="opt in simActiveOptions" :key="opt.id" type="button"
                                    class="px-3 py-1.5 rounded-full border border-indigo-300 text-indigo-600 text-xs bg-white hover:bg-indigo-50 active:scale-95 transition"
                                    @click="pickOption(opt)">
                                    {{ opt.label || trans('Option') }}
                                </button>
                            </div>

                            <!-- AI thinking indicator -->
                            <div v-if="simAiThinking" class="flex items-center gap-2 pl-8 text-[11px] text-purple-500">
                                <FontAwesomeIcon :icon="['fal', 'fa-robot']" class="animate-pulse" />
                                {{ trans('AI is thinking…') }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2 px-3 py-2.5 border-t border-gray-100 bg-white shrink-0">
                            <FontAwesomeIcon :icon="['fal', 'fa-paperclip']" class="text-gray-300 text-sm" />
                            <input
                                v-model="aiQuestion"
                                :disabled="simAiThinking"
                                type="text"
                                :placeholder="simAiNode ? trans('Ask the AI…') : trans('Type your message…')"
                                class="flex-1 text-xs text-gray-700 bg-gray-50 rounded-full px-3 py-2 border focus:outline-none"
                                :class="simAiNode ? 'border-purple-200 focus:border-purple-400' : 'border-gray-100 focus:border-indigo-400'"
                                @keyup.enter="sendMessage"
                            />
                            <button type="button"
                                :disabled="simAiThinking || !aiQuestion.trim()"
                                class="w-8 h-8 rounded-full text-white flex items-center justify-center shrink-0 disabled:opacity-40"
                                :class="simAiNode ? 'bg-purple-500' : 'bg-indigo-500'"
                                @click="sendMessage">
                                <FontAwesomeIcon :icon="['fal', 'fa-paper-plane']" class="text-xs" />
                            </button>
                        </div>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 text-center mt-2">{{ trans('Click a button to simulate the flow.') }}</p>
            </div>

            <!-- Save -->
            <div class="flex flex-col items-stretch justify-start gap-2">
                <Button :disabled="form.processing" @click="submit" class="w-full">
                    <FontAwesomeIcon :icon="['fal', 'fa-save']" class="mr-1.5" />
                    {{ form.processing ? trans('Saving…') : (isEdit ? trans('Save Changes') : trans('Create Automation')) }}
                </Button>
                <p class="text-[11px] text-gray-400">{{ trans('Connect each button to a Step or Action node. Unconnected buttons end the chat.') }}</p>
            </div>
        </div>
    </div>
</template>
