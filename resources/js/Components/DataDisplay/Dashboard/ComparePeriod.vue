<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Popover from "primevue/popover"
import Checkbox from "primevue/checkbox"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { faTimes, faCheck } from "@fal"
import { faExchange } from "@far"
import { compareQueryKeys, type CompareProp } from "@/Components/DataDisplay/Dashboard/comparePeriod"

library.add(faExchange, faTimes, faCheck)

const props = defineProps<{
    compare: CompareProp
    reloadOnly: string[]
    note?: string
}>()

const popover = ref<InstanceType<typeof Popover> | null>(null)
const isLoading = ref(false)

const isEnabled = ref(props.compare.mode !== null)
const mode = ref(props.compare.mode ?? props.compare.default_mode ?? "previous_period")
const customFrom = ref<string | null>(props.compare.custom_from ?? props.compare.from)
const customTo = ref<string | null>(props.compare.custom_to ?? props.compare.to)

const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const dateModel = (target: typeof customFrom) => computed<Date | null>({
    get: () => target.value ? fromIsoDate(target.value) : null,
    set: (value) => { target.value = value ? toLocalIsoDate(value) : null },
})
const customFromDate = dateModel(customFrom)
const customToDate = dateModel(customTo)

const datePickerPt = { pcInputText: { root: { class: "!w-32 !py-1 !text-xs" } } }
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const modeLabel = computed(() => props.compare.options.find(option => option.value === props.compare.mode)?.label)
const buttonTooltip = computed(() => {
    if (!props.compare.available) {
        return ctrans("Pick a period other than All to compare it with another")
    }
    return props.compare.mode
        ? ctrans("Compared with :period (:from to :to)", { period: (modeLabel.value ?? "").toLowerCase(), from: props.compare.from ?? "", to: props.compare.to ?? "" })
        : ctrans("Compare with another period")
})
const canApply = computed(() => !isEnabled.value || mode.value !== "custom" || (customFrom.value !== null && customTo.value !== null))

const toggle = (event: Event) => {
    if (!props.compare.available) {
        return
    }
    isEnabled.value = props.compare.mode !== null
    mode.value = props.compare.mode ?? props.compare.default_mode ?? "previous_period"
    popover.value?.toggle(event)
}

const apply = () => {
    const url = new URL(window.location.href)
    compareQueryKeys.forEach(key => url.searchParams.delete(key))

    if (isEnabled.value) {
        if (mode.value !== props.compare.default_mode) {
            url.searchParams.set("compare", mode.value)
        }
        if (mode.value === "custom" && customFrom.value && customTo.value) {
            url.searchParams.set("compare_from", customFrom.value)
            url.searchParams.set("compare_to", customTo.value)
        }
    } else if (props.compare.default_mode) {
        url.searchParams.set("compare", "none")
    }

    router.get(url.pathname + url.search, {}, {
        only: props.reloadOnly,
        preserveState: true,
        preserveScroll: true,
        onStart: () => { isLoading.value = true },
        onFinish: () => {
            isLoading.value = false
            popover.value?.hide()
        },
    })
}
</script>

<template>
    <div class="flex flex-shrink-0">
        <button type="button" v-tooltip="buttonTooltip" :aria-label="buttonTooltip" :aria-disabled="!compare.available" @click="toggle"
                class="flex items-center justify-center rounded border px-2 sm:p-2"
                :class="[
                    compare.mode ? 'border-transparent bg-[--app-accent-soft] text-[--app-accent-deep]' : 'border-gray-300 text-gray-400',
                    compare.available ? 'cursor-pointer hover:bg-gray-200' : 'cursor-not-allowed opacity-50',
                ]">
            <LoadingIcon v-if="isLoading" class="text-lg sm:text-2xl" />
            <FontAwesomeIcon v-else icon="far fa-exchange" fixed-width aria-hidden="true" class="text-lg sm:text-2xl" />
        </button>
        <Popover ref="popover">
            <div class="w-72 space-y-3 text-sm">
                <label class="flex items-center gap-2 text-gray-700">
                    <Checkbox v-model="isEnabled" binary inputId="v2-compare-enabled" />
                    {{ ctrans("Compare with") }}
                </label>
                <Select v-model="mode" :options="compare.options" optionLabel="label" optionValue="value" :disabled="!isEnabled"
                        class="w-full" :class="fieldFocusClass" :aria-label="ctrans('Compared period')" />
                <div v-if="isEnabled && mode === 'custom'" class="flex items-center gap-2">
                    <DatePicker v-model="customFromDate" :maxDate="customToDate ?? undefined" dateFormat="d M yy" :manualInput="false"
                                :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('Compare from')" />
                    <span class="text-gray-400">–</span>
                    <DatePicker v-model="customToDate" :minDate="customFromDate ?? undefined" dateFormat="d M yy" :manualInput="false"
                                :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('Compare to')" />
                </div>
                <p v-if="compare.mode && compare.from" class="text-xs text-gray-500">
                    {{ ctrans("Now comparing with :from to :to", { from: compare.from, to: compare.to ?? "" }) }}
                </p>
                <p v-if="note" class="text-xs text-gray-400">{{ note }}</p>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="tertiary" :label="ctrans('Cancel')" @click="popover?.hide()">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Apply')" :disabled="!canApply" :loading="isLoading" @click="apply">
                        <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </div>
        </Popover>
    </div>
</template>
