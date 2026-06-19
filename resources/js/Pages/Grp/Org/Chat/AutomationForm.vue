<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import CanvasFlowBuilder from '@/Components/Chat/Automation/CanvasFlowBuilder.vue'
import { capitalize } from '@/Composables/capitalize'
import { trans } from 'laravel-vue-i18n'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faRobot, faSave, faPaperPlane, faPaperclip, faRedo, faTimes, faProjectDiagram } from '@fal'
import { PageHeadingTypes } from '@/types/PageHeading'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'

library.add(faRobot, faSave, faPaperPlane, faPaperclip, faRedo, faTimes, faProjectDiagram)

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

const previewText = computed(() => render(form.message) || trans('Your message preview will appear here…'))
const previewTime = computed(() => new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }))
const previewOptions = computed<{ id: string; label: string }[]>(() => canvasRef.value?.startOptions ?? [])

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

    <div class="w-full px-4 xl:px-0 py-6 grid grid-cols-1 xl:grid-cols-[360px_minmax(0,1fr)_340px] gap-5 items-start">

        <!-- Left column: settings -->
        <div class="space-y-5">

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
                />
            </div>
        </div>

        <!-- Right column: preview + save -->
        <div class="space-y-5 xl:sticky xl:top-6">
            <!-- Live preview -->
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">{{ trans('Live Preview') }}</p>
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
                            <FontAwesomeIcon :icon="['fal', 'fa-redo']" class="text-sm text-white/80" />
                            <FontAwesomeIcon :icon="['fal', 'fa-times']" class="text-base text-white/80" />
                        </div>

                        <div class="flex-1 overflow-y-auto px-3 py-4 bg-[#f0f4f8] space-y-3">
                            <div class="flex items-end gap-2">
                                <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-500 flex items-center justify-center shrink-0 text-[10px]">
                                    <FontAwesomeIcon :icon="['fal', 'fa-robot']" />
                                </div>
                                <div class="max-w-[80%]">
                                    <p class="text-[10px] text-gray-400 mb-1 ml-1">{{ shopName }} {{ trans('Assistant') }}</p>
                                    <div class="rounded-2xl rounded-bl-md bg-white text-gray-800 px-3.5 py-2.5 text-sm shadow-sm">
                                        <p class="whitespace-pre-wrap break-words leading-relaxed">{{ previewText }}</p>
                                        <div class="text-[10px] text-gray-400 text-right mt-1">{{ previewTime }}</div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="previewOptions.length" class="flex flex-wrap gap-2 justify-end pl-8">
                                <button v-for="opt in previewOptions" :key="opt.id" type="button"
                                    class="px-3 py-1.5 rounded-full border border-indigo-300 text-indigo-600 text-xs bg-white">
                                    {{ opt.label || trans('Option') }}
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 px-3 py-2.5 border-t border-gray-100 bg-white shrink-0">
                            <FontAwesomeIcon :icon="['fal', 'fa-paperclip']" class="text-gray-300 text-sm" />
                            <div class="flex-1 text-xs text-gray-400 bg-gray-50 rounded-full px-3 py-2 border border-gray-100">
                                {{ trans('Type your message…') }}
                            </div>
                            <div class="w-8 h-8 rounded-full bg-indigo-500 text-white flex items-center justify-center shrink-0">
                                <FontAwesomeIcon :icon="['fal', 'fa-paper-plane']" class="text-xs" />
                            </div>
                        </div>
                    </div>
                </div>
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
