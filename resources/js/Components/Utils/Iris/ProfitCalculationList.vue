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
  <div class="text-[0.65rem] sm:text-[0.8rem] leading-tight tracking-tight sm:tracking-normal md:w-max max-w-[96vw] sm:max-w-none shadow-sm bg-white rounded-b">
    
    <div class="font-semibold text-[1.1em] mb-1.5 pl-1.5 pt-2">
      {{ trans("Profit Breakdown") }}:
    </div>

    <div class="rounded-t overflow-hidden pb-1.5 sm:pb-3">
      <table class="w-full text-left whitespace-nowrap border-collapse">
        <tbody class="bg-gray-100">
          
          <!-- Retail | Row 1 -->
          <tr>
            <td class="pt-1.5 sm:pt-3 pb-0.5 sm:pb-1 pl-1.5 sm:pl-3 pr-2.5 sm:pr-6">
              {{ trans("Retail") }}:
            </td>
            <td class="pt-1.5 sm:pt-3 pb-0.5 sm:pb-1 pr-2.5 sm:pr-6 font-semibold">
              {{ locale.currencyFormat(layout?.iris?.currency?.code, product.rrp) }}
              <span class="font-normal text-slate-500">/{{ trans("Outer") }}</span>
            </td>
            <td v-if="product.units > 1" class="pt-1.5 sm:pt-3 pb-0.5 sm:pb-1 pr-2.5 sm:pr-6 font-semibold">
              {{ locale.currencyFormat(layout?.iris?.currency?.code, product.rrp_per_unit) }}
              <span class="font-normal text-slate-500">/{{ product.unit }}</span>
            </td>
            <td class="pt-1.5 sm:pt-3 pb-0.5 sm:pb-1 pr-1.5 sm:pr-3"></td>
          </tr>

          <!-- Row 2 -->
          <tr>
            <td class="py-0.5 sm:py-1 pl-1.5 sm:pl-3 pr-2.5 sm:pr-6">
              {{ trans("Cost Price") }}:
            </td>
            <td class="py-0.5 sm:py-1 pr-2.5 sm:pr-6 font-semibold">
              {{ locale.currencyFormat(layout?.iris?.currency?.code, product.price) }}
              <span class="font-normal text-slate-500">/{{ trans("Outer") }}</span>
            </td>
            <td v-if="product.units > 1" class="py-0.5 sm:py-1 pr-2.5 sm:pr-6 font-semibold">
              {{
                locale.currencyFormat(
                  layout?.iris?.currency?.code,
                  Number((product.price / product.units).toFixed(2) || 0).toFixed(2)
                )
              }}
              <span class="font-normal text-slate-500">/{{ product.unit }}</span>
            </td>
            <td class="py-0.5 sm:py-1 pr-1.5 sm:pr-3"></td>
          </tr>

          <!-- Empty | Row 3 -->
          <tr>
            <td :colspan="product.units > 1 ? 4 : 3" class="px-1.5 sm:px-3">
              <div class="border-t border-slate-300 my-0.5 sm:my-1"></div>
            </td>
          </tr>

          <!-- Profit | Row 4-->
          <tr class="border-b border-gray-400">
            <td class="pt-0.5 sm:pt-1 pb-1.5 sm:pb-3 pl-1.5 sm:pl-3 pr-2.5 sm:pr-6">
              <div class="flex items-center gap-0.5 sm:gap-1 text-slate-800">
                {{ trans("Profit") }}
                <span class="text-emerald-600 font-semibold text-[0.95em]">[{{ product.margin }}]:</span>
              </div>
            </td>
            <td class="pt-0.5 sm:pt-1 pb-1.5 sm:pb-3 pr-2.5 sm:pr-6 font-semibold text-emerald-600">
              {{ locale.currencyFormat(layout?.iris?.currency?.code, product.profit) }}
              <span class="font-normal text-slate-500">/{{ trans("Outer") }}</span>
            </td>
            <td v-if="product.units > 1" class="pt-0.5 sm:pt-1 pb-1.5 sm:pb-3 pr-2.5 sm:pr-6 font-semibold text-emerald-600">
              {{ locale.currencyFormat(layout?.iris?.currency?.code, product.profit_per_unit) }}
              <span class="font-normal text-slate-500">/{{ product.unit }}</span>
            </td>
            <td class="pt-0.5 sm:pt-1 pb-1.5 sm:pb-3 pr-1.5 sm:pr-3 text-right">
              <span class="inline-block text-[0.75em] sm:text-[0.8em] px-1 py-[1px] sm:px-1.5 sm:py-[2px] rounded-full bg-gray-200 border border-slate-300 text-slate-600 whitespace-nowrap">
                {{ trans("Excl. Vat") }}
              </span>
            </td>
          </tr>
        </tbody>

        <!-- Discounted Profit -->
        <tbody v-if="layout.iris.website.slug != 'acar'" class="bg-white">
          <tr>
            <td class="pt-1.5 sm:pt-3 pl-1.5 sm:pl-3 pr-2.5 sm:pr-6">
              <div class="flex items-center gap-0.5 sm:gap-1 text-slate-800">
                {{ trans("Profit") }}
                <span class="text-primary font-semibold text-[0.95em]">[{{ product.discounted_margin }}]:</span>
              </div>
            </td>
            <td class="pt-1.5 sm:pt-3 pr-2.5 sm:pr-6 font-semibold text-primary">
              {{
                locale.currencyFormat(
                  layout?.iris?.currency?.code,
                  product.rrp - product.discounted_price
                )
              }}
              <span class="font-normal text-slate-500">/{{ trans("Outer") }}</span>
            </td>
            <td v-if="product.units > 1" class="pt-1.5 sm:pt-3 pr-2.5 sm:pr-6 font-semibold text-primary">
              {{
                locale.currencyFormat(
                  layout?.iris?.currency?.code,
                  product.discounted_price_per_unit
                )
              }}
              <span class="font-normal text-slate-500">/{{ product.unit }}</span>
            </td>
            <td class="pt-1.5 sm:pt-3 pr-1.5 sm:pr-3 text-right align-middle">
              <div class="flex items-center justify-end gap-1">
                <img :src="`/assets/promo/gr-${layout.retina.organisation}.png`" alt="Gold Reward Logo" class="h-[1.2em] sm:h-[1.5em]" />
                <span class="text-[0.65em] sm:text-[0.75em] leading-[1em] sm:leading-[1.1em] text-primary whitespace-normal text-left">
                  {{ trans("Members") }} <br />
                  & {{ trans("Volume") }}
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>