<script setup lang='ts'>
import OrderSummary from '@/Components/Summary/OrderSummary.vue'
import { FieldOrderSummary } from '@/types/Pallet'
import { library } from "@fortawesome/fontawesome-svg-core"
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { Links, Meta, Table as TableTS } from '@/types/Table'
import { faRobot, faBadgePercent, faTag, faTrashAlt } from '@fal'
import Table from "@/Components/Table/Table.vue"
import { inject } from 'vue'
import Tag from '@/Components/Tag.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Link, router } from "@inertiajs/vue3"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import InputNumber from 'primevue/inputnumber'
import { set } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
library.add(faTag,faTrashAlt)

const props = defineProps<{
    data: TableTS
    tab?: string
    status?:string
}>()
const layout = inject("layout", layoutStructure)

const getRoute = (item: {}) => {
    const routeUpdate = <routeType>{}

    if (item?.fulfilment_transaction_id) {
        if (layout.app.name === "Aiku") {
            routeUpdate.name = "grp.models.fulfilment-transaction.update"
            routeUpdate.parameters = { fulfilmentTransaction: item?.fulfilment_transaction_id }
        } else {
            routeUpdate.name = "retina.models.fulfilment-transaction.update"
            routeUpdate.parameters = { fulfilmentTransaction: item?.fulfilment_transaction_id }
        }
    } else {
        routeUpdate.name = "grp.models.recurring_bill_transaction.update"
        routeUpdate.parameters = { recurringBillTransaction: item?.id }
    }

    routeUpdate.method = 'patch'

    return routeUpdate
}

const onUpdateQuantity = (id:Number,fulfilment_transaction_id: number, value: number) => {
	/* console.log(idFulfilmentTransaction, 'loasding', value); */

	const routeUpdate = <routeType>{}
    if (fulfilment_transaction_id) {
        if (layout.app.name === "Aiku") {
            routeUpdate.name = "grp.models.fulfilment-transaction.update"
            routeUpdate.parameters = { fulfilmentTransaction: fulfilment_transaction_id }
        } else {
            routeUpdate.name = "retina.models.fulfilment-transaction.update"
            routeUpdate.parameters = { fulfilmentTransaction: fulfilment_transaction_id }
        }
    }else {
        routeUpdate.name = "grp.models.recurring_bill_transaction.update"
        routeUpdate.parameters = { recurringBillTransaction: id }
    }
	value.patch(route(routeUpdate.name, routeUpdate.parameters),{
        preserveScroll: true,
    })
}

const isLoading = ref<string | boolean>(false)
const onDeleteTransaction = (id:Number, fulfilment_transaction_id: number) => {
	const routeDelete = <routeType>{}
    if(fulfilment_transaction_id){
        if (layout.app.name === "Aiku") {
		routeDelete.name = "grp.models.fulfilment-transaction.delete"
		routeDelete.parameters = { fulfilmentTransaction: fulfilment_transaction_id }
	} else {
		routeDelete.name = "retina.models.fulfilment-transaction.delete"
		routeDelete.parameters = { fulfilmentTransaction: fulfilment_transaction_id }
	}
    }else{
        routeDelete.name = "grp.models.recurring_bill_transaction.delete"
        routeDelete.parameters = { recurringBillTransaction: id }
    }
	
	router.delete(route(routeDelete.name, routeDelete.parameters), {
        preserveScroll: true,
		onStart: () => (isLoading.value = "buttonReset" + id),
		onFinish: () => (isLoading.value = false),
	})
}

const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :is-check-box="false">
        <template #cell(description)="{ item }">
            <!-- edit type : {{ item.edit_type }} -->
            <div v-if="item.description?.model || item.description?.title || item.description?.after_title">
                <span v-if="item.description?.model">{{ item.description.model }}:</span>
                <Link v-if="item.description?.title && item.description.route?.name" :href="route(item.description.route?.name, item.description.route?.parameters)" class="primaryLink">
                    {{ item.description.title }}
                </Link>
                <span v-else>&nbsp;{{ item.description.title }}</span>
                
                <div v-if="item.description.after_title" class="text-gray-400 italic text-xs">({{ item.description.after_title }})</div>
            </div>
            
            <div v-else>

            </div>
        </template>

        <!-- Column: asset code -->
        <template #cell(asset_code)="{ item }">
			<div>
                {{ item.asset_code }} <br>
                <span class="text-gray-400">({{ item.asset_name }})</span>
            </div>
		</template>

        <!-- Column: quantity -->
        <template #cell(quantity)="{ item }">
			<div class="flex justify-end">
				<div v-if="item.edit_type !== 'net' && status == 'current' &&  (item.data.type !== 'Pallet' && item.data.type !== 'Space')">
					<NumberWithButtonSave v-model="item.quantity"   @onSave="(e)=>onUpdateQuantity(item.id,item.fulfilment_transaction_id, e)"/>
				</div>
				<div v-else-if="item.data.type == 'Pallet'">
                    {{ locale.number(item.quantity) }} {{ item.quantity > 1 ? trans("days") : trans("day") }}
				</div>
				<div v-else-if="item.data.type == 'Product'">
                    {{ locale.number(item.quantity) }} {{ item.quantity > 1 ? trans("pcs") : trans("pc") }}
				</div>
                <div v-else class="text-gray-500">
                    
                </div>
			</div>
		</template>

        <!-- Column: asset price -->
        <template #cell(asset_price)="{ item }">
            {{ locale.currencyFormat(item.currency_code, item.asset_price || 0) }}/{{ item.unit_label }}
            <Tag v-if="item['discount'] > 0" :theme="17" noHoverColor>
                <template #label>
                    <font-awesome-icon icon="fal fa-tag" class="text-xs text-emerald-700"/>
                    {{ item['discount'] }}%
                </template>
            </Tag>
        </template>

        <template #cell(total)="{ item, proxyItem }">
            <div class="relative">
                <template v-if="item.edit_type === 'net'">
                    <div class="w-72 float-right">
                        <NumberWithButtonSave
                            v-model="proxyItem.total"
                            :saveOnForm="true"
                            :routeSubmit="getRoute(item)"
                            keySubmit="net_amount"
                            :bindToTarget="{
                                mode: 'currency',
                                fluid: true,
                                currency: item.currency_code,
                                locale: 'en-US',
                                step: 0.25
                            }"
                        />
                    </div>
                </template>

                <Transition v-else name="spin-to-right">
                    <span :key="item.total">
                        {{ locale.currencyFormat(item.currency_code, item.total || 0) }}
                    </span>
                </Transition>
            </div>
        </template>

        <!-- Column: Action -->
		<template #cell(actions)="{ item }">
			<Button v-if="item.data.type !== 'Pallet' && item.data.type !== 'Space'" @click="() => onDeleteTransaction(item.id,item.fulfilment_transaction_id)"
				:loading="isLoading === 'buttonReset' + item.id" icon="fal fa-trash-alt" type="negative"
				v-tooltip="'Unselect this field'" />
		</template>
    </Table>
</template>