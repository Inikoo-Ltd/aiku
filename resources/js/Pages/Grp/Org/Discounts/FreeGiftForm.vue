<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import type { PageHeadingTypes } from '@/types/PageHeading'
import { computed } from 'vue'

import { library } from "@fortawesome/fontawesome-svg-core"
import { faCommentDollar, faInfoCircle } from '@fal'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { trans } from "laravel-vue-i18n"

library.add(faCommentDollar, faInfoCircle)

const props = defineProps<{
  title: string
  pageHead: PageHeadingTypes
}>()

const form = useForm({
  amount: 0,
  product: [] as any[]
})

const submit = () => {
  // form.post(route('free-gift.store'))
}

const routeParams = route().params as {
  organisation: string | number
  shop: string | number
}

const productFetchRoute = computed(() => ({
  name: 'grp.org.shops.show.catalogue.products.all_products.index',
  parameters: {
    organisation: routeParams.organisation,
    shop: routeParams.shop,
  }
}))
</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />

  <div class="mt-6 max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-800">
          {{ trans('Create Free Gift') }}
        </h2>
        <p class="text-sm text-gray-500 mt-1">
          {{ trans('Set gift value and assign products') }}
        </p>
      </div>

      <form @submit.prevent="submit" class="px-6 py-6 space-y-5">
        <!-- Amount -->
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-700">
            {{ trans('Gift Amount') }}
          </label>
          <PureInputNumber v-model="form.amount" :min-value="0" class="w-full" />
          <p v-if="form.errors.amount" class="mt-1 text-sm text-red-600">
            {{ form.errors.amount }}
          </p>
        </div>

        <!-- Product -->

        <div>
          <label class="block mb-1 text-sm font-medium text-gray-700">
            {{ trans('Products') }}
          </label>
          <PureMultiselectInfiniteScroll v-model="form.product" mode="tags" :fetchRoute="productFetchRoute"
            valueProp="id" label-prop="name" :object="true" :caret="false" :placeholder="trans('Select Product')" />

          <p v-if="form.errors.product" class="mt-1 text-sm text-red-600">
            {{ form.errors.product }}
          </p>
        </div>


        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
          <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium
                   hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed" :disabled="form.processing">
            {{ form.processing ? trans('Saving...') : trans('Save Free Gift') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
