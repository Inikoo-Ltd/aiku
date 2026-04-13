<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { onMounted, ref } from 'vue'
import { router } from "@inertiajs/vue3"
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import Image from '@/Components/Image.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Image as ImageTS } from '@/types/Image'

const model = defineModel('')

const props = defineProps<{
    transaction: {
        id: number
        quantity_waiting_warehouse: string
        quantity_waiting_crm: string
        org_stock_name: string
        org_stock_code: string
        notes: string
        org_stock_image_thumbnail: ImageTS
    }
}>()


const dataToSendAsWaiting = ref({
    note: '',
})
const isLoadingSetAsWaiting = ref(false)
const onPassItemToCs = () => {
    // Section: Submit
    router.post(
        route('grp.models.delivery_note_item.set_as_waiting_crm', {
            deliveryNoteItem: props.transaction?.id
        }),
        {
            ...dataToSendAsWaiting.value,
            transaction_id: props.transaction?.id,
            quantity: Number(props.transaction?.quantity_waiting_warehouse || 0) + Number(props.transaction?.quantity_waiting_crm || 0)
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingSetAsWaiting.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully set item as waiting"),
                    type: "success"
                })
                dataToSendAsWaiting.value.note = ''
                model.value = false
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set item as waiting. Try again"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSetAsWaiting.value = false
            },
        }
    )
}

onMounted(() => {
    // console.log('qqqqqq', props.transaction)
    dataToSendAsWaiting.value.note = props.transaction.notes ?? ''
})
</script>

<template>
    <div>
        <div class="font-semibold text-center text-2xl mb-8">
            {{ ctrans("Pass item to CS") }}
        </div>

        <div class="flex items-center gap-4 mb-2">
            <div class="shrink-0 size-16 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center">
                <Image
                    v-if="transaction?.org_stock_image_thumbnail"
                    :src="transaction.org_stock_image_thumbnail"
                    :alt="transaction.org_stock_name"
                />
                <FontAwesomeIcon v-else icon="fal fa-image" class="text-2xl text-gray-400" fixed-width aria-hidden="true" />
            </div>

            <div class="min-w-0">
                <div class="text-xl leading-tight">
                    {{ transaction?.org_stock_name ?? '-' }}
                </div>
                <div class="text-sm opacity-75 italic">
                    {{ transaction?.org_stock_code }}
                </div>
            </div>
        </div>

        <!-- Section: Quantity badge -->
        <div class="flex items-center gap-2 mb-6 p-3 rounded-lg bg-amber-50 border border-amber-200">
            <FontAwesomeIcon icon="fal fa-hourglass-half" class="text-amber-500" fixed-width aria-hidden="true" />
            <span class="text-sm text-amber-700">
                {{ ctrans('Quantity to pass to CS') }}:
            </span>
            <span class="font-bold text-amber-800">
                <!-- <FractionDisplay
                    v-if="GetQuantityToPickFractional(transaction)"
                    :fractionData="GetQuantityToPickFractional(transaction)"
                />
                <template v-else>{{ locale.number(transaction.quantity_waiting_warehouse + Number(transaction.quantity_waiting_warehouse || 0) + Number(transaction.quantity_waiting_crm || 0)  ) }}</template> -->
                {{ Number(transaction.quantity_waiting_warehouse || 0) + Number(transaction.quantity_waiting_crm || 0)  }}
                
            </span>
        </div>

        <!-- Note textarea -->
        <div>
            <label class="font-medium mb-1 flex items-center gap-x-1 text-sm">
                {{ ctrans('Note') }}:
            </label>
            <PureTextarea v-model="dataToSendAsWaiting.note" :rows="4" counter :maxlength="255" />
        </div>

        <div class="flex gap-2 mt-6">
            <Button
                @click="() => model = false"
                :label="ctrans('Cancel')"
                type="negative"
            />
            <Button
                @click="() => onPassItemToCs()"
                :label="ctrans('Pass item to CS')"
                full
                iconRight="far fa-arrow-right"
                :loading="isLoadingSetAsWaiting"
            />
        </div>
    </div>
</template>