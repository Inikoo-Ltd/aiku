<script setup lang="ts">
import ProductTranslation from '@/Components/Showcases/Grp/ProductTranslation.vue';
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'

const props = defineProps<{
    product: {

    }
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)

</script>

<template>
  <div>
    <!-- Title -->
    <div class="flex items-center justify-between mb-2 pl-1 pt-2">
      <div class="font-semibold text-[1.2em]">
        {{ trans("Profit Breakdown") }}:
      </div>
    </div>

    <div class="pt-2 space-y-2 bg-gray-100 pr-4 rounded-t border-b border-gray-500 pb-2">

      <!-- Retail -->
      <div class="flex items-center justify-between pl-4 pr-24">
        <div>{{ trans("Retail") }}:</div>

        <div class="font-semibold">
          {{ locale.currencyFormat(layout?.iris?.currency?.code, product.rrp) }}
          <span class="font-normal text-slate-500">{{ trans("Outer") }}</span>

          <template v-if="product.units > 1">
            <span class="ml-3">
              {{ locale.currencyFormat(layout?.iris?.currency?.code, product.rrp_per_unit) }}
            </span>
            <span class="font-normal text-slate-500">/{{ product.unit }}</span>
          </template>
        </div>
      </div>

      <!-- Cost -->
      <div class="flex items-center justify-between pl-4 pr-24">
        <div>{{ trans("Cost Price") }}:</div>

        <div class="font-semibold">
          {{ locale.currencyFormat(layout?.iris?.currency?.code, product.price) }}
          <span class="font-normal text-slate-500">{{ trans("Outer") }}</span>

          <template v-if="product.units > 1">
            <span class="ml-3">
              {{
                locale.currencyFormat(
                  layout?.iris?.currency?.code,
                  Number((product.price / product.units).toFixed(2) || 0).toFixed(2)
                )
              }}
            </span>
            <span class="font-normal text-slate-500">/{{ product.unit }}</span>
          </template>
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-slate-300 my-1 mr-24"></div>

      <!-- Profit normal -->
      <div class="flex items-center justify-between pl-4">
        <div>
          {{ trans("Profit") }}
          <span class="text-emerald-600 font-semibold">
            ({{ product.margin }})
          </span>:
        </div>

        <div class="flex font-semibold text-emerald-600">
          {{ locale.currencyFormat(layout?.iris?.currency?.code, product.profit) }}
          <span class="font-normal text-slate-500 ml-1">
            {{ trans("Outer") }}
          </span>

          <template v-if="product.units > 1">
            <span class="ml-3">
              {{ locale.currencyFormat(layout?.iris?.currency?.code, product.profit_per_unit) }}
            </span>
            <span class="font-normal text-slate-500">/{{ product.unit }}</span>
          </template>

          <div class="w-24">
            <div class="w-fit ml-auto text-[0.9em] px-2 py-[2px] rounded-full bg-gray-200 border border-slate-300 text-slate-600">
              {{ trans("Excl. Vat") }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Discounted Profit -->
    <div
      v-if="layout.iris.website.slug != 'acar'"
      class="flex items-center justify-between pl-4 mt-2"
    >
      <div>
        {{ trans("Profit") }}
        <span class="text-primary font-semibold">
          ({{ product.discounted_margin }})
        </span>:
      </div>

      <div class="flex items-center gap-2x">
        <div class="font-semibold text-primary mr-4">
          {{
            locale.currencyFormat(
              layout?.iris?.currency?.code,
              product.rrp - product.discounted_price
            )
          }}
          <span class="font-normal text-slate-500">
            {{ trans("Outer") }}
          </span>

          <template v-if="product.units > 1">
            <span class="ml-3">
              {{
                locale.currencyFormat(
                  layout?.iris?.currency?.code,
                  product.discounted_price_per_unit
                )
              }}
            </span>
            <span class="font-normal text-slate-500">/{{ product.unit }}</span>
          </template>
        </div>

        <div class="w-24 flex items-center gap-x-2 justify-end pr-2">
          <img
            :src="`/assets/promo/gr-${layout.retina.organisation}.png`"
            alt="Gold Reward Logo"
            class="h-[2em]"
          />
          <span class="text-[0.9em] leading-[1.2em] text-primary flex items-center gap-1">
            {{ trans("Members") }} <br />
            & {{ trans("Volume") }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
