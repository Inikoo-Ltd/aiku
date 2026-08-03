<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faExclamationCircle, faCheckCircle } from "@fas"
import { faCopy } from "@fal"
import { faSpinnerThird } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { set, get } from "lodash-es"
import ListSelector from "@/Components/ListSelectorForCreateMasterProduct.vue"
import { watch, ref, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSave as fadSave } from "@fad"
import { faSave as falSave, faInfoCircle } from "@fal"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"

library.add(fadSave, falSave, faInfoCircle)

library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)
defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
        type: string
        withQuantity?: boolean
        routeFetch: routeType
        key_quantity?: string
        tabs: {
            label: string
            routeFetch: routeType
        }[]
        is_dropship: boolean
        priceContext?: {
            price: number
            rrp: number
            currency: string
            units: number
        }
    }
}>()

const emits = defineEmits()
const showValidationDialog = ref(false)
const loadingValidation = ref(false)

const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        return data[fieldName]
    }
}

const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return null
    }, obj)
}

const value = ref(setFormValue(props.form, props.fieldName))

watch(value, (newValue) => {
    updateFormValue(newValue)
    props.form.errors[props.fieldName] = ""
})

const updateFormValue = (newValue) => {
    let target = props.form
    if (Array.isArray(props.fieldName)) {
        set(target, props.fieldName, newValue)
    } else {
        target[props.fieldName] = newValue
    }
    emits("update:form", target)
}

const checkValidation = async () => {
    loadingValidation.value = true
    try {
        const response = await axios.post(route("grp.json.master_product.check_org_stock_existence", { masterAsset: route().params["masterProduct"] }), {
            trade_units: value.value
        })

        if (response.data.status) emits("submit")
        else showValidationDialog.value = true

    } catch (error) {
        console.log(error)
        notify({
            title: trans("Something went wrong."),
            text: trans("Failed to get the options list"),
            type: "error"
        })
    } finally {
        loadingValidation.value = false
    }
}

const onSave = () => {
    showValidationDialog.value = false
    emits("submit")
}

/*
 * The chicken-and-egg of composition edits: either the product is being repackaged and the
 * price must scale with it, or the composition was wrong and the price already fits the real
 * thing. Nothing is repriced automatically; both readings are shown so a human picks one.
 */
const priceImpact = computed(() => {
    const priceContext = props.fieldData.priceContext
    const rows = setFormValue(props.form, props.fieldName)
    if (!priceContext?.price || !Array.isArray(rows) || rows.length !== 1) {
        return null
    }

    const newUnits = Number(rows[0][props.fieldData.key_quantity ?? 'quantity']) || 1
    const oldUnits = Number(priceContext.units) || 1
    if (newUnits === oldUnits) {
        return null
    }

    return {
        oldUnits,
        newUnits,
        price: priceContext.price,
        currency: priceContext.currency,
        perUnitNow: priceContext.price / oldUnits,
        scaledPrice: (priceContext.price / oldUnits) * newUnits,
        perUnitIfKept: priceContext.price / newUnits,
    }
})
</script>
<template>
    <div class="relative">
        <div class="relative flex gap-4">
            <div :class="fieldData.use_confirm ? 'w-[90%]' : 'w-full'">
                <ListSelector :modelValue="value" @update:modelValue="(e) => updateFormValue(e)" v-bind="fieldData" />
            </div>

            <div v-if="fieldData.use_confirm">
                <div class="h-9 align-bottom text-center" :disabled="form.processing || !form.isDirty || loadingValidation">
                    <template v-if="form.isDirty">
                        <div @click="checkValidation">
                            <FontAwesomeIcon v-if="form.processing || loadingValidation" icon="fad fa-spinner-third"
                                             class="text-2xl animate-spin" fixed-width aria-hidden="true" />
                            <FontAwesomeIcon v-else icon="fad fa-save" class="h-8"
                                             :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                        </div>
                    </template>
                    <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                </div>
            </div>
        </div>
    </div>

    <!-- Price impact: repackaging scales the price, fixing a wrong composition keeps it -->
    <div v-if="priceImpact" class="mt-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm">
        <div class="font-medium text-amber-800">
            {{ trans('Units change :old → :new — the price does not change by itself. Which is true?', { old: priceImpact.oldUnits, new: priceImpact.newUnits }) }}
        </div>
        <div class="mt-1 text-gray-700">
            {{ trans('Repackaging the product: price should become') }}
            <span class="font-semibold">{{ priceImpact.scaledPrice.toFixed(2) }} {{ priceImpact.currency }}</span>
            ({{ trans('keeps :per/unit', { per: priceImpact.perUnitNow.toFixed(2) }) }}) —
            {{ trans('update it in the price field below.') }}
        </div>
        <div class="text-gray-700">
            {{ trans('Fixing a wrong composition: keep') }}
            <span class="font-semibold">{{ priceImpact.price.toFixed(2) }} {{ priceImpact.currency }}</span>
            ({{ trans('becomes :per/unit', { per: priceImpact.perUnitIfKept.toFixed(2) }) }}).
        </div>
    </div>

    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>


    <Dialog v-model:visible="showValidationDialog" :closable="false" modal :style="{ width: '28rem' }">
        <template #header>
            <div class="flex items-center gap-4 text-[20px]">
                <FontAwesomeIcon :icon="faExclamationCircle" class="text-orange-500" />
                <span class="font-medium ">{{ trans("Are you sure?") }}</span>
            </div>
        </template>

        <div class="text-sm">
            <span>
                {{ trans("Product quantity will be set to 0 due to no available organization stock") }}
            </span>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Cancel" type="tertiary" @click="showValidationDialog = false" />
                <Button label="Confirm" type="negative" @click="onSave" />
            </div>
        </template>
    </Dialog>
</template>