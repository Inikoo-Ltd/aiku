<script setup lang="ts">
import { ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import ToggleSwitch from "primevue/toggleswitch"
import Checkbox from "primevue/checkbox"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSlidersH, faTimes, faCheck } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

export type ClaimSettings = { self_service: boolean, auto_approve: boolean, max_claim_amount: number, window_days: number, allowance_floor: number, allowance_share: number, max_claims_in_window: number, framework: boolean, framework_since: string | null }

const props = defineProps<{
    settings: ClaimSettings
    settingsRoute: { name: string, parameters: any }
    currencyCode: string | null
}>()

const locale = useLocaleStore()
const currency_code = props.currencyCode
const showSettings = ref(false)
const understood = ref(false)
const form = useForm<ClaimSettings>({ ...props.settings })
const openSettings = () => {
    form.defaults({ ...props.settings })
    form.reset()
    form.clearErrors()
    understood.value = false
    showSettings.value = true
}
const saveSettings = () => {
    form.patch(route(props.settingsRoute.name, props.settingsRoute.parameters), {
        preserveScroll: true,
        onSuccess: () => { showSettings.value = false },
    })
}
</script>

<template>
    <Button type="tertiary" :label="ctrans('Settings')" @click="openSettings">
        <template #icon><FontAwesomeIcon :icon="faSlidersH" class="text-gray-500" fixed-width aria-hidden="true" /></template>
    </Button>

    <Dialog v-model:visible="showSettings" modal :header="ctrans('Claim settings')" :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-4 text-sm">
            <label class="flex items-start justify-between gap-4">
                <span>
                    <span class="block font-semibold text-gray-800">{{ ctrans("Customers can claim themselves") }}</span>
                    <span class="block text-xs text-gray-500">{{ ctrans("Merlin opens the claim form when a customer reports damaged, missing or wrong items.") }}</span>
                </span>
                <ToggleSwitch v-model="form.self_service" />
            </label>

            <label class="flex items-start justify-between gap-4">
                <span>
                    <span class="block font-semibold text-gray-800">{{ ctrans("Approve small claims automatically") }}</span>
                    <span class="block text-xs text-gray-500">{{ ctrans("Off: every claim waits for a person.") }}</span>
                </span>
                <ToggleSwitch v-model="form.auto_approve" :disabled="!form.self_service" />
            </label>

            <div v-if="form.auto_approve" class="space-y-3 rounded border bg-gray-50 p-3">
                <label class="block">
                    <span class="mb-1 block text-xs text-gray-600">{{ ctrans("Largest claim approved automatically") }} <span class="text-red-500">*</span></span>
                    <InputNumber v-model="form.max_claim_amount" v-bind="locale.currencyInputOptions(currency_code)" :min="0" class="w-full" input-class="w-full" />
                    <span v-if="form.errors.max_claim_amount" class="text-xs text-red-500">{{ form.errors.max_claim_amount }}</span>
                </label>

                <p class="text-xs text-gray-600">{{ ctrans("Per customer, over the last :days days:", { days: String(form.window_days) }) }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="mb-1 block text-xs text-gray-600">{{ ctrans("Days counted") }} <span class="text-red-500">*</span></span>
                        <InputNumber v-model="form.window_days" :min="1" :max="365" class="w-full" input-class="w-full" />
                        <span v-if="form.errors.window_days" class="text-xs text-red-500">{{ form.errors.window_days }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs text-gray-600">{{ ctrans("Most claims") }} <span class="text-red-500">*</span></span>
                        <InputNumber v-model="form.max_claims_in_window" :min="1" class="w-full" input-class="w-full" />
                        <span v-if="form.errors.max_claims_in_window" class="text-xs text-red-500">{{ form.errors.max_claims_in_window }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs text-gray-600">{{ ctrans("Allowance at least") }} <span class="text-red-500">*</span></span>
                        <InputNumber v-model="form.allowance_floor" v-bind="locale.currencyInputOptions(currency_code)" :min="0" class="w-full" input-class="w-full" />
                        <span v-if="form.errors.allowance_floor" class="text-xs text-red-500">{{ form.errors.allowance_floor }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs text-gray-600">{{ ctrans("Or % of what they bought") }} <span class="text-red-500">*</span></span>
                        <InputNumber v-model="form.allowance_share" suffix="%" :min="0" :max="100" :max-fraction-digits="1" class="w-full" input-class="w-full" />
                        <span v-if="form.errors.allowance_share" class="text-xs text-red-500">{{ form.errors.allowance_share }}</span>
                    </label>
                </div>

                <p class="text-xs text-gray-500">
                    {{ ctrans("A claim is approved with no person when it is no bigger than the largest claim, the customer made fewer claims than the most claims, and everything approved automatically for them, this claim included, stays within the larger allowance. Claims a person approves do not count. Lost parcels always go to a person.") }}
                </p>
            </div>

            <div class="rounded border p-3" :class="settings.framework ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'">
                <p class="font-semibold text-gray-800">{{ ctrans("Work through claims only") }}</p>
                <p v-if="settings.framework" class="text-xs text-green-700">
                    {{ ctrans("On since :date. Every replacement and refund in this shop starts from a claim.", { date: String(useFormatTime(settings.framework_since ?? undefined)) }) }}
                </p>
                <template v-else>
                    <p class="mt-1 text-xs text-gray-700">{{ ctrans("On orders, + Replacement becomes + Claim. On invoices, Create refund becomes + Claim. Tax refunds stay with accountants. This cannot be switched off once on: tell your customer services team first.") }}</p>
                    <label class="mt-2 flex items-center gap-2 text-xs text-gray-700">
                        <Checkbox v-model="understood" binary />
                        {{ ctrans("I have told the team and understand it cannot be undone") }}
                    </label>
                    <label class="mt-2 flex items-center justify-between gap-4">
                        <span class="text-xs font-semibold text-gray-800">{{ ctrans("Switch this shop to claims only") }}</span>
                        <ToggleSwitch v-model="form.framework" :disabled="!understood" />
                    </label>
                </template>
            </div>
        </div>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="showSettings = false">
                    <template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="primary" full :label="ctrans('Save')" :loading="form.processing" :disabled="!form.isDirty" @click="saveSettings">
                    <template #icon><FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
