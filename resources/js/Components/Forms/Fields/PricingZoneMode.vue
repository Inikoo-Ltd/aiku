<script setup lang="ts">
import { ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import PricingZone from "@/Components/Forms/Fields/Pricing_zone.vue"
import PricingZoneMultiShipper from "@/Components/Forms/Fields/PricingZoneMultiShipper.vue"

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    options?: {
        shippers: Array<{ id: number; name: string; code: string }>
        can_use_shipper_pricing?: boolean
    }
    fieldData?: {
        currency?: { code: string }
    }
}>()

// A zone prices one way or the other: shippers_price wins outright at checkout,
// so showing both invites a price nobody is charging.
const canUseShipperPricing = props.options?.can_use_shipper_pricing !== false

const mode = ref(canUseShipperPricing && props.form[props.fieldName]?.shippers_price?.length ? "per_shipper" : "normal")

watch(mode, (value) => {
    if (value === "normal") {
        props.form[props.fieldName].shippers_price = []
    }
})
</script>

<template>
    <div>
        <div v-if="canUseShipperPricing" class="flex gap-6 mb-4">
            <label
                v-for="option in [
                    { value: 'normal', label: trans('Normal shipping') },
                    { value: 'per_shipper', label: trans('Per shipper pricing') },
                ]"
                :key="option.value"
                class="inline-flex items-center gap-2 text-sm cursor-pointer"
            >
                <input
                    type="radio"
                    :value="option.value"
                    v-model="mode"
                    class="cursor-pointer text-indigo-600 focus:ring-indigo-500"
                />
                {{ option.label }}
            </label>
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
