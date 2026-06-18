<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { trans } from 'laravel-vue-i18n'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faRobot, faSave } from '@fal'
import { PageHeadingTypes } from '@/types/PageHeading'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'

library.add(faRobot, faSave)

interface ShopOption { id: number; name: string; code: string }
interface TriggerType { value: string; label: string; description: string }
interface Automation {
    id: number
    shop_id: number
    name: string
    trigger_type: string
    is_enabled: boolean
    message: string
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
    priority:     props.automation?.priority ?? 0,
    send_once:    props.automation?.send_once ?? true,
    conditions: {
        delay_seconds: props.automation?.conditions?.delay_seconds ?? 0,
        after_minutes: props.automation?.conditions?.after_minutes ?? 3,
    },
})

const shopOptions = computed(() => props.shops.map(s => ({ label: `${s.name} (${s.code})`, value: s.id })))

const currentTrigger = computed(() => props.triggerTypes.find(t => t.value === form.trigger_type))

const variables = ['{customer_name}', '{shop_name}', '{business_hours}', '{agent_name}']

function insertVariable(v: string): void {
    form.message = `${form.message}${form.message && !form.message.endsWith(' ') ? ' ' : ''}${v} `
}

const previewText = computed(() => {
    return (form.message || trans('Your message preview will appear here…'))
        .replace(/\{customer_name\}/g, 'John')
        .replace(/\{shop_name\}/g, props.shops.find(s => s.id === form.shop_id)?.name ?? 'Shop')
        .replace(/\{business_hours\}/g, 'Mon–Fri 9am–5pm')
        .replace(/\{agent_name\}/g, 'Sarah')
})

// default a friendly name when picking a trigger on create
watch(() => form.trigger_type, (t) => {
    if (!isEdit.value && !form.name) {
        form.name = currentTrigger.value?.label ?? ''
    }
})

function submit(): void {
    const url = route(props.submitRoute.name, props.submitRoute.parameters)
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            const orgSlug = (route().params as any)?.organisation
            window.location.href = route('grp.org.chat.automations.show', { organisation: orgSlug })
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

    <div class="max-w-5xl mx-auto px-4 py-6 grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Form -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Details -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Details') }}</h3>

                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('Shop') }} <span class="text-red-500">*</span></label>
                    <Select
                        v-model="form.shop_id"
                        :options="shopOptions"
                        option-label="label"
                        option-value="value"
                        :disabled="isEdit"
                        filter
                        class="w-full"
                    />
                    <p v-if="form.errors.shop_id" class="text-xs text-red-500">{{ form.errors.shop_id }}</p>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('Name') }} <span class="text-red-500">*</span></label>
                    <InputText v-model="form.name" class="w-full" :placeholder="trans('e.g. Out of hours reply')" />
                    <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('Trigger') }} <span class="text-red-500">*</span></label>
                    <Select
                        v-model="form.trigger_type"
                        :options="triggerTypes"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                    <p v-if="currentTrigger" class="text-[11px] text-gray-400">{{ currentTrigger.description }}</p>
                </div>
            </div>

            <!-- Message -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Message') }}</h3>

                <Textarea v-model="form.message" rows="4" class="w-full text-sm" auto-resize :placeholder="trans('Type the automated message…')" />
                <p v-if="form.errors.message" class="text-xs text-red-500">{{ form.errors.message }}</p>

                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="v in variables"
                        :key="v"
                        type="button"
                        class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 text-[11px] font-mono hover:bg-indigo-100 transition-colors"
                        @click="insertVariable(v)"
                    >
                        {{ v }}
                    </button>
                </div>
            </div>

            <!-- Conditions -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Conditions') }}</h3>

                <div v-if="form.trigger_type === 'welcome'" class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('Delay (seconds)') }}</label>
                    <InputNumber v-model="form.conditions.delay_seconds" :min="0" :max="120" show-buttons class="w-full" />
                    <p class="text-[11px] text-gray-400">{{ trans('Wait this long before sending the welcome message') }}</p>
                </div>

                <div v-else-if="form.trigger_type === 'no_reply'" class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('After (minutes)') }}</label>
                    <InputNumber v-model="form.conditions.after_minutes" :min="1" :max="120" show-buttons class="w-full" />
                    <p class="text-[11px] text-gray-400">{{ trans('Send if the customer has not been answered within this time') }}</p>
                </div>

                <p v-else class="text-xs text-gray-400 italic">
                    {{ form.trigger_type === 'offline'
                        ? trans('Fires automatically based on the shop business hours — no extra condition needed.')
                        : trans('Fires while the conversation is waiting for an agent — no extra condition needed.') }}
                </p>
            </div>

            <!-- Advanced -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ trans('Advanced') }}</h3>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-700">{{ trans('Send once per conversation') }}</p>
                        <p class="text-[11px] text-gray-400">{{ trans('Avoid sending the same message repeatedly') }}</p>
                    </div>
                    <ToggleSwitch v-model="form.send_once" />
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-700">{{ trans('Enabled') }}</p>
                        <p class="text-[11px] text-gray-400">{{ trans('Turn this automation on or off') }}</p>
                    </div>
                    <ToggleSwitch v-model="form.is_enabled" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">{{ trans('Priority') }}</label>
                    <InputNumber v-model="form.priority" :min="0" :max="1000" show-buttons class="w-full" />
                    <p class="text-[11px] text-gray-400">{{ trans('Lower number runs first when several rules match') }}</p>
                </div>
            </div>

            <div class="flex justify-end">
                <Button :disabled="form.processing" @click="submit">
                    <FontAwesomeIcon :icon="['fal', 'fa-save']" class="mr-1.5" />
                    {{ form.processing ? trans('Saving…') : (isEdit ? trans('Save Changes') : trans('Create Automation')) }}
                </Button>
            </div>
        </div>

        <!-- Live Preview -->
        <div class="lg:col-span-1">
            <div class="sticky top-6">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ trans('Live Preview') }}</p>
                <div class="rounded-xl border border-gray-200 bg-[#f0f4f8] p-4 min-h-[180px]">
                    <div class="flex justify-start">
                        <div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-white text-gray-800 px-3.5 py-2.5 text-sm shadow-sm">
                            <div class="text-[11px] font-semibold mb-0.5 text-gray-400 flex items-center gap-1">
                                <FontAwesomeIcon :icon="['fal', 'fa-robot']" class="text-indigo-400" />
                                {{ trans('Automated') }}
                            </div>
                            <p class="whitespace-pre-wrap break-words">{{ previewText }}</p>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-gray-400">{{ trans('Variables are shown with sample values.') }}</p>
            </div>
        </div>
    </div>
</template>
