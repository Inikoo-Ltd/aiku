<script setup lang="ts">
import { faCube, faLink,  } from "@fal"
import { faHeart as  faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject, computed } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
// import { Popover, PopoverButton, PopoverPanel } from "@headlessui/vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

import { Popover } from "primevue"
import { ProductResource } from "@/types/Iris/Products"

library.add(faCube, faLink, faFilePdf, faFileDownload)

const props = defineProps<{
	product: ProductResource
	currency_code: string
}>()
// console.log('zcxzcxzcxzcxz', props.currency_code)

const locale = inject('locale', aikuLocaleStructure)


const popoverTimeout = ref()

const _popover = ref(null)

const hoverPopover = (e: any): void => {
    _popover.value?.show(e)
	
    // if (!open) {
    //     e.target.parentNode.click()
    // }
}

const closePopover = (): void => {

    if (popoverTimeout.value) clearTimeout(popoverTimeout.value)
    popoverTimeout.value = setTimeout(() => {
        if (!_popover.value?.visible) {
            _popover.value?.hide()
        }
    }, 100)
}
</script>

<template>
	<div class="border-y border-gray-200 p-1 mb-2 tabular-nums">
		<div class="grid grid-cols-6 gap-4 items-start" @mouseover="(e) => hoverPopover(e)"
			@mouseleave="closePopover()">
			<!-- Retail -->
			<div class="flex flex-col text-left col-span-4">
				<span class="text-sm font-medium text-gray-600 mb-1">{{ trans('Retail Price') }} </span>
				<div class="flex flex-wrap items-baseline gap-1">
					<span class="text-base font-semibold">
						{{ locale.currencyFormat(currency_code, product?.rrp_per_unit || 0) }}
					</span>
					<span class="text-sm text-gray-500">/ {{ product.unit }}</span>

				</div>
			</div>

			<!-- Profit -->
			<div class="flex flex-col items-end text-right col-span-2 justify-end">
				<div>
					<span class="text-sm font-medium text-gray-600 mb-1 flex justify-end">
						<span v-tooltip="trans('Profit margin')" class="mr-3 text-xs ml-1 font-medium text-gray-400">
						</span> {{trans('Profit') }} ({{ product?.margin }})</span>
					<div class="flex flex-wrap items-baseline justify-end gap-1">
						<span class="text-base font-semibold text-gray-700">
							{{ locale.currencyFormat(currency_code, product?.profit_per_unit || 0) }}  
						</span>
						<span class="text-sm text-gray-500 ">/ {{ product.unit }}</span>

					</div>
				</div>
			</div>
		</div>
	</div>

	<Popover ref="_popover" class="max-w-md w-full p-2">
		<div class="text-sm font-semibold border-gray-300 pb-2">
			{{ trans('Profit Margin Breakdown') }}
		</div>

		<div class="p-5 bg-gray-50 rounded-md shadow-sm border border-gray-200 space-y-2">

			<!-- Retail Price -->
			<div class="flex justify-between items-center text-sm">
				<span class="text-gray-700">{{ trans('Retail Price') }}</span>
				<div class="flex items-center gap-4 text-right">
					<span class="font-semibold min-w-[90px] text-end">
						{{ locale.currencyFormat(currency_code, product.rrp) }} / <span v-if="product.units != 1">{{trans('Outer') }}</span><span v-else>{{ product.unit }}</span>
					</span>
					<span v-if="product.units != 1"
						class="text-xs text-gray-500 border-gray-300 pl-3 min-w-[90px] text-start leading-none">
						{{ locale.currencyFormat(currency_code, product.rrp_per_unit.toFixed(2)) }} / {{
							product.unit }}
					</span>
				</div>
			</div>

			<!-- Cost Price -->
			<div class="flex justify-between items-center text-sm">
				<span class="text-gray-700">{{ trans('Cost Price') }}</span>
				<div class="flex items-center gap-4 text-right">
					<span class="font-semibold min-w-[90px] text-end">
						{{ locale.currencyFormat(currency_code, product.price) }} / <span v-if="product.units != 1">{{trans('Outer') }}</span><span v-else>{{ product.unit }}</span>
					</span>
					<span v-if="product.units != 1"
						class="text-xs text-gray-500 border-gray-300 pl-3 min-w-[90px] text-start leading-none">
						{{ locale.currencyFormat(currency_code, (product.price / product.units).toFixed(2)) }} / {{ product.unit }}
					</span>
				</div>
			</div>

			<!-- Profit -->
			<div class="flex justify-between items-center text-sm border-t border-gray-300 pt-2 mt-2">
				<span class="font-semibold">{{ trans('Profit') }}</span>
				<div class="flex items-center gap-4 text-right">
					<span class="font-bold  min-w-[90px] text-end">
						{{ locale.currencyFormat(currency_code, product.profit) }} / <span v-if="product.units != 1">{{trans('Outer') }}</span><span v-else>{{ product.unit }}</span>
					</span>
					<span v-if="product.units != 1"
						class="text-xs  border-gray-300 pl-3 min-w-[90px] text-start leading-none">
						{{ locale.currencyFormat(currency_code, (product.rrp_per_unit - (product.price / product.units)).toFixed(2)) }} / {{product.unit }}
					</span>
				</div>
			</div>
		</div>


		<!-- Notes -->
		<div class="text-xs text-gray-500 border-dashed border-gray-300 pt-2 mt-1 italic">
			{{ trans('All prices exclude tax.') }}
		</div>
    </Popover>
    
    <div class="p-1 px-0 mb-3 flex flex-col gap-1 tabular-nums">
        <div v-if="product.units === 1" class="flex justify-between">
            <div>
                {{ trans("Price") }}:
                <span class="font-semibold text-green-600 text-xl">
                    {{ locale.currencyFormat(currency_code, product.price) }}
                    <span class="text-xs text-gray-600"> / {{ product.unit }}</span>
                </span>
            </div>
        </div>
        <div v-else>
            <div class="flex justify-between">
                <div v-tooltip="trans('Wholesale Price')" class="flex items-center gap-1">
                    {{ trans("Price") }}:
                    <span class="font-semibold text-green-600 text-xl">{{ locale.currencyFormat(currency_code,
                        product.price) }}</span><span class="text-sm text-gray-500">/ {{trans('Outer') }}</span> 
                </div>
                <div>
                    <span class="text-xs price_per_unit">
                        <span class="text-green-600 text-xl font-semibold">
                            {{ locale.currencyFormat(currency_code, Number((product.price / product.units).toFixed(2) || 0).toFixed(2)) }}
						</span>
						<span class="text-gray-600 text-sm"> / {{ product.unit }}</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>