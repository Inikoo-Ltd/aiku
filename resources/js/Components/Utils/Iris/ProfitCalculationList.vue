<script setup lang="ts">
import ProductTranslation from '@/Components/Showcases/Grp/ProductTranslation.vue';
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'

const props = defineProps<{
  product: {
    [key: string]: any
  }
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
  <div class="text-[0.65rem] sm:text-[0.8rem] leading-tight tracking-tight sm:tracking-normal w-max max-w-[96vw] sm:max-w-none shadow-sm bg-white rounded-b">
    
    <div class="font-semibold text-[1.1em] mb-1.5 pl-1.5 pt-2">
      {{ trans("Profit Breakdown") }}:
    </div>

    <div class="bg-gray-100 rounded-t border-b border-gray-400 p-1.5 sm:p-3">
      
      <div class="grid grid-cols-[auto_auto_auto_1fr] gap-x-1.5 sm:gap-x-4 gap-y-1 sm:gap-y-1.5 items-center whitespace-nowrap">

        <!-- Retail -->
        <div class="pr-0.5 sm:pr-2">{{ trans("Retail") }}:</div>
        <div class="font-semibold">
          {{ locale.currencyFormat(layout?.iris?.currency?.code, product.rrp) }}
          <span class="font-normal text-slate-500">/{{ trans("Outer") }}</span>
        </div>
        <div class="font-semibold">
          <template v-if="product.units > 1">
            {{ locale.currencyFormat(layout?.iris?.currency?.code, product.rrp_per_unit) }}
            <span class="font-normal text-slate-500">/{{ product.unit }}</span>
          </template>
        </div>
        <div></div> <div class="pr-0.5 sm:pr-2">{{ trans("Cost Price") }}:</div>
        <div class="font-semibold">
          {{ locale.currencyFormat(layout?.iris?.currency?.code, product.price) }}
          <span class="font-normal text-slate-500">/{{ trans("Outer") }}</span>
        </div>
        <div class="font-semibold">
          <template v-if="product.units > 1">
            {{
              locale.currencyFormat(
                layout?.iris?.currency?.code,
                Number((product.price / product.units).toFixed(2) || 0).toFixed(2)
              )
            }}
            <span class="font-normal text-slate-500">/{{ product.unit }}</span>
          </template>
        </div>
        <div></div> <div class="col-span-4 border-t border-slate-300 my-0.5"></div>

        <!-- Profit -->
        <div class="flex items-center gap-0.5 sm:gap-1 pr-0.5 sm:pr-2">
          {{ trans("Profit") }}
          <span class="text-emerald-600 font-semibold text-[0.95em]">[{{ product.margin }}]:</span>
        </div>
        <div class="font-semibold text-emerald-600">
          {{ locale.currencyFormat(layout?.iris?.currency?.code, product.profit) }}
          <span class="font-normal text-slate-500">/{{ trans("Outer") }}</span>
        </div>
        <div class="font-semibold text-emerald-600">
          <template v-if="product.units > 1">
            {{ locale.currencyFormat(layout?.iris?.currency?.code, product.profit_per_unit) }}
            <span class="font-normal text-slate-500">/{{ product.unit }}</span>
          </template>
        </div>
        <div class="justify-self-end pl-0.5 sm:pl-2">
          <div class="text-[0.75em] sm:text-[0.8em] px-1 py-[1px] sm:px-1.5 sm:py-[2px] rounded-full bg-gray-200 border border-slate-300 text-slate-600">
            {{ trans("Excl. Vat") }}
          </div>
        </div>
      </div>
    </div>

    <!-- Discounted Profit -->
    <div v-if="layout.iris.website.slug != 'acar'" class="p-1.5 sm:p-3 pt-1.5 sm:pt-2">
      <div class="grid grid-cols-[auto_auto_auto_1fr] gap-x-1.5 sm:gap-x-4 items-center whitespace-nowrap">
        
        <div class="flex items-center gap-0.5 sm:gap-1 pr-0.5 sm:pr-2">
          {{ trans("Profit") }}
          <span class="text-primary font-semibold text-[0.95em]">[{{ product.discounted_margin }}]:</span>
        </div>
        
        <div class="font-semibold text-primary">
          {{
            locale.currencyFormat(
              layout?.iris?.currency?.code,
              product.rrp - product.discounted_price
            )
          }}
          <span class="font-normal text-slate-500">/{{ trans("Outer") }}</span>
        </div>
        
        <div class="font-semibold text-primary">
          <template v-if="product.units > 1">
            {{
              locale.currencyFormat(
                layout?.iris?.currency?.code,
                product.discounted_price_per_unit
              )
            }}
            <span class="font-normal text-slate-500">/{{ product.unit }}</span>
          </template>
        </div>
        
        <div class="justify-self-end pl-0.5 sm:pl-2 flex items-center gap-1">
          <img :src="`/assets/promo/gr-${layout.retina.organisation}.png`" alt="Gold Reward Logo" class="h-[1.2em] sm:h-[1.5em]" />
          <span class="text-[0.65em] sm:text-[0.75em] leading-[1em] sm:leading-[1.1em] text-primary whitespace-normal text-left">
            {{ trans("Members") }} <br />
            & {{ trans("Volume") }}
          </span>
        </div>

      </div>
    </div>

  </div>
</template>