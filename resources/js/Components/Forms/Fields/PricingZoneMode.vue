<script setup lang="ts">
import { ref, computed, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTruck, faTruckLoading } from "@fal"
import PricingZone from "@/Components/Forms/Fields/Pricing_zone.vue"
import PricingZoneMultiShipper from "@/Components/Forms/Fields/PricingZoneMultiShipper.vue"

library.add(faTruck, faTruckLoading)

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    options?: {
        shippers: Array<{ id: number; name: string; code: string }>
        hide_per_shipper?: boolean
    }
    fieldData?: {
        currency?: { code: string }
    }
}>()

// A zone prices one way or the other: shippers_price wins outright at checkout,
// so showing both invites a price nobody is charging.
const mode = ref(
    !props.options?.hide_per_shipper && props.form[props.fieldName]?.shippers_price?.length
        ? "per_shipper"
        : "normal"
)

const modeOptions = computed(() => [
    {
        value: "normal",
        label: trans("Normal shipping"),
        icon: faTruck,
    },
    {
        value: "per_shipper",
        label: trans("Per shipper pricing"),        
        icon: faTruckLoading,
    },
])

watch(
    mode,
    (value) => {
        if (value === "normal" && props.form[props.fieldName]?.shippers_price?.length) {
            props.form[props.fieldName].shippers_price = []
        }
    },
    { immediate: true }
)
</script>

<template>
    <div>
        <div class="mb-5" v-if="!options?.hide_per_shipper">
            <div class="mb-2 text-sm text-gray-700">
                {{ trans('Method') }}
            </div>

            <div
                role="radiogroup"
                :aria-label="trans('Method')"
                class="inline-flex flex-wrap gap-1 rounded-lg border border-gray-200 bg-gray-100 p-1"
            >
                <button
                    v-for="option in modeOptions"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="mode === option.value"
                    class="flex items-center gap-2 rounded-md px-3.5 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    :class="mode === option.value
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'text-gray-500 hover:bg-white/70 hover:text-gray-700'"
                    @click="mode = option.value"
                >
                    <FontAwesomeIcon
                        :icon="option.icon"
                        class="h-4 w-4 shrink-0"
                        :class="mode === option.value ? 'text-white' : 'text-gray-400'"
                        fixed-width
                        aria-hidden="true"
                    />
                    {{ option.label }}
                </button>
            </div>
        </div>
        
        <PricingZone
            v-if="mode === 'normal'"
            :form="form[fieldName]"
            fieldName="price"
            :fieldData="fieldData"
        />
        <PricingZoneMultiShipper
            v-else
            :form="form[fieldName]"
            fieldName="shippers_price"
            :options="options"
            :fieldData="fieldData"
        />
    </div>
</template>
