<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted, nextTick, computed, defineExpose } from 'vue';
import PureInput from '@/Components/Pure/PureInput.vue';
// import RetinaShowIris from '@/Layouts/RetinaShowIris.vue';
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAsterisk } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faAsterisk)

// Set default layout
// defineOptions({ layout: RetinaShowIris });

const props = defineProps({
  form: Object,
  data: {
    product : String,
    shipments_per_week : String,
    size_and_weight : String
  }
});

// Buat form baru jika props.form tidak tersedia
const defaultForm = useForm({
  product: props?.data?.product || "",
  shipments_per_week: props?.data?.shipments_per_week || "",
  size_and_weight: props?.data?.size_and_weight || "",
});

// Gunakan computed untuk fallback ke defaultForm jika props.form tidak diberikan
const form = computed(() => props.form || defaultForm);


const optionsSend = ['0-50', "51-100", "100+"]

// Autofocus pertama kali
onMounted(async () => {
  await nextTick();
  document.getElementById('product')?.focus();
});

defineExpose({
    form : form
})
</script>


<template>
    <div class="border-gray-900/10 xpb-12">
      <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

        <!-- What Do You Sell -->
        <div class="sm:col-span-6">
          <label for="what-you-sell" class="capitalize block text-sm font-medium text-gray-700">
            <FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
            {{trans("What Do You Sell")}}
          </label>
          <div class="mt-2">
            <PureInput
              xv-model="form.product"
              :modelValue="form.product"
              @update:model-value="(e) => {
                form.product = e
                form.clearErrors('product')
              }"
              type="text" id="what-you-sell" name="product"
              :class="form.errors.product ? 'errorShake' : ''"  
            />
            <p v-if="form.errors.product" class="text-sm text-red-600 mt-1">{{ form.errors.product }}</p>
          </div>
        </div>

        <!-- Shipments Sent Per Week -->
        <div class="sm:col-span-3">
          <label for="shipments-per-week" class="capitalize block text-sm font-medium text-gray-700">
            <FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
            {{trans("Shipments Sent PerWeek")}}
          </label>
          <div class="mt-2">
            <select v-model="form.shipments_per_week"
              @change="form.clearErrors('shipments_per_week')"
              id="shipments-per-week" name="shipments_per_week" 
              class="block w-full mt-1 py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="form.errors.shipments_per_week ? 'errorShake' : ''"
            >
              <option value="" disabled selected>Select shipments per week</option>
              <option v-for="option in optionsSend" :key="option" :value="option">{{ option }}</option>
            </select>
            <p v-if="form.errors.shipments_per_week" class="text-sm text-red-600 mt-1">{{ form.errors.shipments_per_week }}</p>
          </div>
        </div>


        <!-- Goods Size and Weight -->
        <div class="sm:col-span-3">
          <label for="goods-size-and-weight" class="capitalize block text-sm font-medium text-gray-700">
            <FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
            {{trans("Size and Weight of Your Goods")}}
          </label>
          <div class="mt-2">
            <PureInput v-model="form.size_and_weight"
              @update:model-value="(e) => form.clearErrors('size_and_weight')"
              :class="form.errors.size_and_weight ? 'errorShake' : ''"
            type="text" id="goods-size-and-weight"
              name="size_and_weight"  />
              <p v-if="form.errors.size_and_weight" class="text-sm text-red-600 mt-1">{{ form.errors.size_and_weight }}</p>
          </div>
        </div>

      </div>
    </div>
</template>
