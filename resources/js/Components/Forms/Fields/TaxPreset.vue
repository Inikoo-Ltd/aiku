<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { ref, computed, watch } from "vue"
import TaxPresetCards from "@/Components/Utils/TaxPresetCards.vue"
import TaxSweepProgressModal from "@/Components/Utils/TaxSweepProgressModal.vue"

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: {
        options: { value: string; title: string; description?: string }[]
        master_asset_id?: number
        sweep?: any
        [key: string]: any
    }
}>()

/** What is stored, as opposed to what is clicked; the cards show the difference loudly. */
const savedValue = ref<string>(props.form[props.fieldName])

watch(() => props.form.recentlySuccessful, (success) => {
    if (success) savedValue.value = props.form[props.fieldName]
})

// While a sweep runs, starting another would overlap it: the cards lock.
const sweepRunning = ref(false)

const model = computed({
    get: () => props.form[props.fieldName],
    set: (value) => props.form[props.fieldName] = value,
})
</script>

<template>
    <div>
        <TaxPresetCards
            v-model="model"
            :options="fieldData.options"
            :savedValue="savedValue"
            :disabled="sweepRunning" />

        <TaxSweepProgressModal
            :masterAssetId="fieldData.master_asset_id ?? null"
            :initial="fieldData.sweep"
            @update:running="sweepRunning = $event" />
    </div>
</template>
