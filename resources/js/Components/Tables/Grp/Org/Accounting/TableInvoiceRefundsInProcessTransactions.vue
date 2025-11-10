<script setup lang='ts'>

import Table from '@/Components/Table/Table.vue'
import { inject, provide } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { ref, toRaw } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import PureInput from '@/Components/Pure/PureInput.vue'
import InputNumber from 'primevue/inputnumber'
import { get, set } from 'lodash-es'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSave as falSave, faExclamationCircle, faMinus, faPlus, faArrowCircleLeft, faTrash, faTrashAlt } from '@fal'
import { faSave } from '@fad'
import { faArrowAltCircleLeft } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import Tag from '@/Components/Tag.vue'
import { notify } from '@kyvg/vue3-notification'
import ActionCell from '@/Components/Segmented/InvoiceRefund/ActionCell.vue'
import { useForm } from "@inertiajs/vue3"; // Import useForm
library.add(faArrowAltCircleLeft, faSave, falSave, faExclamationCircle, faMinus, faPlus, faArrowCircleLeft)

const props = defineProps<{
    data: {}
    tab: string
}>()

const locale = inject('locale', aikuLocaleStructure)
const _formCell = ref({})

// Section: update refund amount
const isLoadingQuantity = ref<number[]>([])
const onClickQuantity = (routeRefund: routeType, slugRefund: number, amount: FormData) => {
    let tempValue = toRaw(amount.refund_amount)
    router[routeRefund.method || 'post'](
        route(
            routeRefund.name,
            routeRefund.parameters
        ),
        {
            net_amount: amount.refund_amount
        },
        {
            preserveScroll: true,
            onStart : () =>{
                amount.processing = true
            },
            onSuccess: () => {
                amount.defaults()
                amount.reset();
            },
            onFinish: () => {
                amount.processing = false
                const index = isLoadingQuantity.value.indexOf(slugRefund)
                if (index > -1) {
                    isLoadingQuantity.value.splice(index, 1)
                }
            },
            onError: (e) => {
                notify({
                    title: trans("Something went wrong"),
                    text: e.net_amount || e.message,
                    type: "error",
                })
            }
        }
    )
}

const setAllRefund = (index, value) => {
    if (_formCell.value[index])
        _formCell.value[index].form.refund_amount = value
}

const DeleteRefund = (route, index) => {
    if (_formCell.value[index]) {
        _formCell.value[index].form.refund_amount = 0
        onClickQuantity(route, route, _formCell.value[index].form)
    }
}

const itemsInProcessRef = ref(null);

const reloadForm = () => {
    if (_formCell.value) {
        for (const item in _formCell.value) {
            if (_formCell.value[item].form) {
                _formCell.value[item].form.refund_amount = props.data.data[item].max_refundable_amount
                _formCell.value[item].form.defaults();
                _formCell.value[item].form.reset();
            }
        }
    }
};



defineExpose({
    reloadForm
})

const productRoute = (item) => {
    return item.asset_id ? route('grp.helpers.redirect_asset', [item.asset_id]) : '';
}
</script>

<template>
    <div class="h-min">
        <Table :resource="data" :name="tab">
            <template #cell(net_amount)="{ item}">
                <div class="flex flex-col items-end">
                    <!-- Net Amount (yang tersisa setelah refund) -->
                    <div>
                        {{ locale.currencyFormat(item.currency_code, item.net_amount) }}
                    </div>

                    <!-- Previous Refund -->
                    <small v-if="item.total_last_refund" class="text-gray-500 text-xs">
                        {{ trans("Previous refund") }}: {{ locale.currencyFormat(item.currency_code, item.total_last_refund) }}
                    </small>

                    <!-- Refundable Amount -->
                    <button
                        v-if="item.total_last_refund != item.net_amount && item.net_amount - item.refund_net_amount - item.total_last_refund > 0"
                        @click="()=>setAllRefund(item.rowIndex, item.max_refundable_amount)"
                        :disabled="item.net_amount - item.refund_net_amount - item.total_last_refund <= 0"
                        class="px-2 py-1 text-xs bg-gray-300 rounded disabled:bg-gray-300 disabled:cursor-not-allowed hover:text-blue-500 disabled:hover:bg-gray-300 transition">
                        {{ trans("Refundable") }}: {{ locale.currencyFormat(item.currency_code, item.max_refundable_amount)}}
                    </button>
                </div>
            </template>

            <template #cell(code)="{ item }">
                <Link v-if="productRoute(item)" :href="productRoute(item)" class="whitespace-normal primaryLink">
                    {{ item.code }}
                </Link>
                <div v-else class="whitespace-normal">
                    {{ item.code }}
                </div>
            </template>

            <template #cell(description)="{ item }">
                <div class="whitespace-normal">
                    {{item.name}}
                </div>
            </template>

            <!-- <template #cell(prev_refund)="{ item }">
                <div :class="item.net_amount < 0 ? 'text-red-500' : ''">
                    <Tag v-if="Number(item.total_last_refund)" v-tooltip="trans('Total previous refund')" :label="locale.currencyFormat(item.currency_code, item.total_last_refund)" noHoverColor :theme="2" size="sm" />
                    <span v-else>-</span>
                </div>
            </template> -->

            <template #cell(action)="{ item, proxyItem }">
                <!-- <div class="space-x-2 w-[350px]">
                    <div v-if="Number(item.refund_net_amount)" v-tooltip="trans('Selected amount to refund')" class="w-fit font-semibold">
                        {{ locale.currencyFormat(item.currency_code, item.refund_net_amount) }}
                    </div>
                    <template v-if="item.net_amount !=  item.total_last_refund">
                        <ButtonWithLink
                            v-if="!get(proxyItem, ['refund_net_amount'], 0) && get(proxyItem, ['refund_net_amount'], 0) != item.net_amount"
                            @xclick="() => get(proxyItem, 'refund_type', null) == 'full' ? set(proxyItem, 'refund_type', null): set(proxyItem, 'refund_type', 'full')"
                            :key="item.code"
                            :routeTarget="item.refund_transaction_full_refund"
                            :label="trans('Refund item')"
                            icon="fas fa-arrow-alt-circle-left"
                            size="s"
                            :bindToLink="{ preserveScroll: true }"
                            type="secondary"
                            :xtype="get(proxyItem, 'refund_type', null) == 'full' ? 'black' : 'secondary'"
                        />

                        <Button
                            v-if="!get(proxyItem, ['refund_net_amount'], 0)"
                            @click="() => get(proxyItem, 'refund_type', null) == 'partial' ? set(proxyItem, 'refund_type', null): set(proxyItem, 'refund_type', 'partial')"
                            :key="get(proxyItem, 'refund_type', null) + '-' + item.code"
                            :label="trans('Refund Item Partially')"
                            icon="fal fa-arrow-circle-left"
                            size="s"
                            :bindToLink="{ preserveScroll: true }"
                            :type="get(proxyItem, 'refund_type', null) == 'partial' ? 'gray' : 'tertiary'"
                        />
                    </template>
                </div> -->
                <!--  <div>
                            <div v-show="Number(item.total_last_refund) < Number(item.net_amount)"  class="w-fit flex items-center gap-x-1 mt-2">
                            <div>
                                <InputNumber
                                    :modelValue="get(proxyItem, ['new_refund_amount'], get(proxyItem, ['refund_net_amount'], 0))"
                                    @input="(e) => (set(proxyItem, ['new_refund_amount'], e.value))"
                                    @update:model-value="(e) => set(proxyItem, ['new_refund_amount'], e)"
                                    :class="get(proxyItem, ['new_refund_amount'], null) > item.net_amount ? 'errorShake' : ''"
                                    inputClass="width-12"
                                    :max="Number(item.net_amount) - Number(item.refund_net_amount) - Number(item.total_last_refund)"
                                    :min="0"
                                    placeholder="0"
                                    mode="currency"
                                    :currency="item.currency_code"
                                    :locale="localeCode"
                                    showButtons buttonLayout="horizontal"
                                    :step="item.unit_price"
                                    size="small"
                                >
                                    <template #decrementicon>
                                        <FontAwesomeIcon icon="fal fa-minus" aria-hidden="true" />
                                    </template>
                                    <template #incrementicon>
                                        <FontAwesomeIcon icon="fal fa-plus" aria-hidden="true" />
                                    </template>
                                </InputNumber>

                                <p v-if="get(proxyItem, ['new_refund_amount'], null) > item.net_amount" class="italic text-red-500 text-xs mt-1">
                                    {{ trans('Refund amount should not over the net amount') }}
                                </p>
                            </div>
                            <LoadingIcon v-if="isLoadingQuantity.includes(item.rowIndex)" class="h-8" />
                            <FontAwesomeIcon
                                v-else-if="proxyItem.new_refund_amount >= 0 ? proxyItem.new_refund_amount !== (proxyItem.refund_net_amount || 0) : false"
                                @click="() => onClickQuantity(item.refund_route, item.rowIndex, get(proxyItem, ['new_refund_amount'], 0))"
                                icon="fad fa-save"
                                class="h-8 cursor-pointer"
                                :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }"
                                aria-hidden="true"
                            />
                            <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                        </div>
                        <div class="py-1">
                            <ButtonWithLink
                                v-if="Number(item.total_last_refund) < Number(item.net_amount)"
                                @click="() => proxyItem.new_refund_amount = (item.net_amount - item.total_last_refund)"
                                :key="item.code"
                                :label="trans('Refund All')"
                                size="xxs"
                                :disabled="proxyItem.new_refund_amount == (item.net_amount - item.total_last_refund)"
                                :bindToLink="{ preserveScroll: true }"
                                type="tertiary"
                                :xtype="get(proxyItem, 'refund_type', null) == 'full' ? 'black' : 'secondary'"
                            />
                        </div>
                        </div> -->

                        <div class="flex items-center gap-3 w-fit">
                            <ActionCell
                                v-if="Number(item.total_last_refund) < Number(item.net_amount)"
                                :ref="(e) => _formCell[item.rowIndex] = e"
                                :modelValue="get(proxyItem, ['new_refund_amount'], get(proxyItem, ['refund_net_amount'], 0))"
                                :max="item.max_refundable_amount"
                                @input="(e) => set(proxyItem, ['new_refund_amount'], e.value)"
                                @update:model-value="(e) => set(proxyItem, ['new_refund_amount'], e)"
                                :min="0"
                                placeholder="0"
                                mode="currency"
                                :currency="item.currency_code"
                                :step="item.original_item_net_price"
                                @refund="(form) => onClickQuantity(item.refund_route, item.rowIndex, form)"
                            />

                            <!-- <Button :style="'negative'" :icon="faTrash" @click="" ></Button> -->
                            <FontAwesomeIcon
                                v-if="_formCell[item.rowIndex]?.form?.refund_amount > 0"
                                @click="DeleteRefund(item.refund_route, item.rowIndex)"
                                :icon="faTrashAlt"
                                class="h-7 w-7 cursor-pointer text-red-500"
                                aria-hidden="true"
                            />
                        </div>
            </template>
        </Table>
    </div>
</template>
