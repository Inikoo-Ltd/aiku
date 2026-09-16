<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { trans } from "laravel-vue-i18n"

/**
 * Both controls reach a live ad account, so both confirm first and neither is optimistic: the value
 * on screen only changes once Google has accepted the change, because a budget that looks applied
 * and was rejected is worse than one that plainly refused to move.
 */
const props = defineProps<{
    status: string | null
    budgetAmount: number | null
    budgetIsShared: boolean
    currency: string
    updateRoute: { name: string; parameters: Record<string, unknown> }
}>()

const locale = useLocaleStore()
const confirm = useConfirm()

const isPausing = ref(false)
const isSavingBudget = ref(false)
const budget = ref<number | null>(props.budgetAmount)
const errors = ref<Record<string, string>>({})

// The server is the source of truth: after a save the page reloads and the field follows it back.
watch(
    () => props.budgetAmount,
    (value) => (budget.value = value)
)

const isEnabled = computed(() => props.status === "ENABLED")

const budgetChanged = computed(
    () => budget.value !== null && Number(budget.value) !== Number(props.budgetAmount)
)

const budgetError = computed(() => errors.value.budget_amount ?? null)
const statusError = computed(() => errors.value.status ?? null)

const submit = (payload: Record<string, unknown>, busy: { value: boolean }) => {
    router.patch(route(props.updateRoute.name, props.updateRoute.parameters), payload, {
        preserveScroll: true,
        onStart: () => {
            busy.value = true
            errors.value = {}
        },
        onError: (formErrors) => (errors.value = formErrors as Record<string, string>),
        onFinish: () => (busy.value = false),
    })
}

const toggleStatus = () => {
    const next = isEnabled.value ? "PAUSED" : "ENABLED"

    confirm.require({
        header: isEnabled.value ? ctrans("Pause campaign") : ctrans("Resume campaign"),
        message: isEnabled.value
            ? ctrans("This stops the campaign showing ads in Google Ads straight away.")
            : ctrans("This starts the campaign spending its daily budget in Google Ads straight away."),
        rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
        acceptProps: { label: isEnabled.value ? ctrans("Pause it") : ctrans("Resume it") },
        accept: () => submit({ status: next }, isPausing),
    })
}

const saveBudget = () => {
    confirm.require({
        header: ctrans("Change daily budget"),
        message:
            ctrans("The new daily budget takes effect in Google Ads immediately: ") +
            locale.currencyFormat(props.currency, Number(budget.value)),
        rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
        acceptProps: { label: ctrans("Change it") },
        accept: () => submit({ budget_amount: Number(budget.value) }, isSavingBudget),
    })
}
</script>

<template>
    <div class="mt-5 border-t border-gray-100 pt-4">
        <h3 class="text-xs font-medium uppercase tracking-wide text-gray-500">
            {{ trans("Manage in Google Ads") }}
        </h3>

        <div class="mt-3 space-y-4">
            <div>
                <Button
                    :type="isEnabled ? 'warning' : 'positive'"
                    :label="isEnabled ? trans('Pause campaign') : trans('Resume campaign')"
                    :loading="isPausing"
                    :disabled="isPausing || status === null"
                    full
                    @click="toggleStatus" />
                <p v-if="statusError" class="mt-1 text-xs text-[#d03b3b]">{{ statusError }}</p>
            </div>

            <div>
                <label :for="'gads-budget'" class="block text-xs text-gray-500">
                    {{ trans("Daily budget") }} ({{ currency }})
                </label>

                <div class="mt-1 flex items-center gap-2">
                    <input
                        id="gads-budget"
                        v-model.number="budget"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :disabled="budgetIsShared || isSavingBudget"
                        :aria-describedby="budgetIsShared ? 'gads-budget-shared' : undefined"
                        class="w-32 rounded-md border-gray-300 text-sm tabular-nums focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500" />
                    <Button
                        type="tertiary"
                        :label="trans('Save')"
                        :loading="isSavingBudget"
                        :disabled="budgetIsShared || !budgetChanged || isSavingBudget"
                        @click="saveBudget" />
                </div>

                <p v-if="budgetIsShared" id="gads-budget-shared" class="mt-1 text-xs text-[#a15c00]">
                    {{ trans("Shared with other campaigns, so it has to be changed in Google Ads.") }}
                </p>
                <p v-else-if="budgetError" class="mt-1 text-xs text-[#d03b3b]">{{ budgetError }}</p>
            </div>
        </div>
    </div>
</template>
