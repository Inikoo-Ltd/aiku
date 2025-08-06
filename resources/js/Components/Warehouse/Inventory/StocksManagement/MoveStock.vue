<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle } from "@fal"
import { faDotCircle as fasDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { InputNumber } from 'primevue'
import { ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useForm } from '@inertiajs/vue3'
library.add(faDotCircle, fasDotCircle)

const emits = defineEmits<{
    (e: "onClickBackground"): void
}>()

const dummyData = ref([
    { id: 1, name: 'E1', lastAudit: new Date(), stock: 45, isAudited: true },
    { id: 2, name: 'E2', lastAudit: new Date(), stock: 30, isAudited: false },
    { id: 3, name: 'E3', lastAudit: new Date(), stock: 60, isAudited: true },
    { id: 4, name: 'E4', lastAudit: new Date(), stock: 20, isAudited: false },
    { id: 5, name: 'E5', lastAudit: new Date(), stock: 80, isAudited: true }
])

const form = useForm({
    stockCheck: dummyData.value.map(item => ({
        id: item.id,
        name: item.name,
        stock: item.stock,
        isAudited: item.isAudited
    }))
})

const submitCheckStock = () => {
    // form.post(route('grp.dashboard.show'), {
    //     preserveScroll: true,
    //     onStart: () => {
    //         console.log("Submitting stock check...")
    //     },
    //     onSuccess: () => {
    //         console.log("Stock check submitted successfully!")
    //         emits('onClickBackground')
    //     },
    //     onError: (errors) => {
    //         console.error("Failed to submit stock check:", errors)
    //     },
    //     onFinish: () => {
    //         console.log("Stock check submission finished.")
    //     }
    // })

    console.log("Submitting stock check data:", form)
}
</script>

<template>
    <div>
        <div @click="() => emits('onClickBackground')" class="cursor-pointer fixed inset-0 bg-black/40 z-30" />
        <div class="relative bg-white z-40 xpy-2 xpx-3 space-y-1">
            <div class="text-center">Move stock</div>
            <div v-for="(forrrmm, idx) in form.stockCheck" class="grid grid-cols-7 gap-x-3 items-center gap-2">
                <div class="col-span-4 flex items-center gap-x-2">
                    {{ forrrmm.name }}
                </div>
                <div v-tooltip="trans('Last audit :date', { date: useFormatTime(new Date()) })" class="text-right">
                    0
                    <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width aria-hidden="true" />
                </div>
                <div class="col-span-2 text-right flex items-center justify-end gap-x-1">
                    <div v-if="forrrmm.stock != dummyData[idx].stock">
                        <span v-if="forrrmm.stock > dummyData[idx].stock" class="text-green-600">
                            +{{ forrrmm.stock - dummyData[idx].stock }}
                        </span>
                        <span v-else class="text-red-500">
                            -{{ dummyData[idx].stock - forrrmm.stock }}
                        </span>
                    </div>
                    <div v-else @click="() => forrrmm.isAudited = !forrrmm.isAudited" class="cursor-pointer" :class="forrrmm.isAudited ? 'text-green-500' : 'text-gray-400 hover:text-green-500'">
                        <FontAwesomeIcon
                            :icon="forrrmm.isAudited ? 'fas fa-dot-circle' : 'fal fa-dot-circle'"
                            fixed-width
                            aria-hidden="true"
                        />
                    </div>

                    <div class="w-14">
                        <InputNumber
                            :modelValue="forrrmm.stock"
                            @input="e => forrrmm.stock = e.value"
                            :min="0"
                            :disabled="forrrmm.isAudited"
                            :step="1"
                            size="small"
                            fluid
                            inputClass="!py-0"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: buttons -->
        <div class="relative flex gap-x-2 z-40 mt-4">
            <Button
                label="Cancel"
                type="cancel"
                key="2"
                class="bg-red-100"
                @click="() => emits('onClickBackground')"
            />

            <Button
                :disabled="!form.isDirty"
                label="Save"
                full
                @click="() => submitCheckStock()"
            />

        </div>
        <pre>{{ form }}</pre>
    </div>
</template>