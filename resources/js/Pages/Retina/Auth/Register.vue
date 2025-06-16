<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted, nextTick, watch} from 'vue';
import PureInput from '@/Components/Pure/PureInput.vue';
import RetinaShowIris from '@/Layouts/RetinaShowIris.vue';
import { trans } from 'laravel-vue-i18n'
import Multiselect from '@vueform/multiselect'
import Address from '@/Components/Forms/Fields/Address.vue';
import FulfilmentCustomer from '@/Pages/Grp/Org/Fulfilment/FulfilmentCustomer.vue';
import CustomerDataForm from '@/Components/CustomerDataForm.vue';


// Set default layout
defineOptions({ layout: RetinaShowIris });
const props = defineProps({
  countriesAddressData : Array,
  registerRoute: {
    name : String,
    parameters : String
  }
});

console.log('sdsd',props)

// Define form using Inertia's useForm
const form = useForm({
  contact_name: '',
  email: '',
  phone: '',
  company_name: '',
  website: '',
  product: '',
  shipments_per_week: '',
  size_and_weight: '',
  password: '',
  password_confirmation: '',
  interest: [],
/*   country_id: '',
  postal_code : '',
  post_town : '',
  address_line_1 : '',
  address_line_2 : '', */
  contact_address : {}
});

// Define reactive variables
const isLoading = ref(false);
/* const optionsSend = [
  '0-50', "51-100", "100+"
] */
// Form submission handler
const submit = () => {
  isLoading.value = true;

  // Gabungkan field address
  // form.contact_address = {
  //   country_id: form.country_id,
  //   postal_code: form.postal_code,
  //   post_town: form.post_town,
  //   address_line_1: form.address_line_1,
  //   address_line_2: form.address_line_2,
  // };

  if(form.password == form.password_confirmation){
    form.post(route(props.registerRoute.name,props.registerRoute.parameters ), {
    onError: () => {
      isLoading.value = false;
    },
    onFinish: () => {
      /* form.reset(); */
    },
  });
  }else{
    form.setError('password',"password not match")
  }

};


const interestsList = ref([
  { label: 'Pallets Storage', value: 'pallets_storage' },
  { label: 'Dropshipping', value: 'dropshipping' },
  { label: 'Space (Parking)', value: 'rental_space' },
]);

const addressFieldData = 
  {
    type: "address",
    label: "Address",
    value: {
        address_line_1: null,
        address_line_2: null,
        sorting_code: null,
        postal_code: null,
        locality: null,
        dependent_locality: null,
        administrative_area: null,
        country_code: null,
        country_id: 48
    },
    options: props.countriesAddressData
}
/* const countries = {}; */

/* for (const item in props.countriesAddressData) {
    countries[item] = props.countriesAddressData[item]['label']
} */

// Autofocus first PureInput on mount
onMounted(async () => {
  await nextTick();
  document.getElementById('contact_name')?.focus();
});
</script>

<template>
  <form @submit.prevent="submit" class="space-y-12 px-14 py-10">
    <div class="text-xl font-semibold flex justify-center">
     {{trans("Join Our Fulfillment – Register Now!")}}
    </div>
    <div class="border-b border-gray-900/10 pb-12">
      <div class="mt-10 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">


        <!-- First Name -->
        <div class="sm:col-span-6">
          <label for="name" class="capitalize block text-sm font-medium text-gray-700">{{trans("Name")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.contact_name" type="text" id="contact_name" name="contact_name" required />
            <p v-if="form.errors.contact_name" class="text-sm text-red-600 mt-1">{{ form.errors.contact_name }}</p>
          </div>
        </div>
        

        <!-- Email -->
        <div class="sm:col-span-3">
          <label for="email" class="capitalize block text-sm font-medium text-gray-700">{{trans("Email")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.email" type="email" id="email" name="email" required />
            <p v-if="form.errors.email" class="text-sm text-red-600 mt-1">{{ form.errors.email }}</p>
          </div>
        </div>

        <!-- Phone Number -->
        <div class="sm:col-span-3">
          <label for="phone-number" class="capitalize block text-sm font-medium text-gray-700">{{trans("Phone Number")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.phone" type="text" id="phone-number" name="phone" required />
            <p v-if="form.errors.phone" class="text-sm text-red-600 mt-1">{{ form.errors.phone }}</p>
          </div>
        </div>

        <!-- Business Name -->
        <div class="sm:col-span-6">
          <label for="business-name" class="capitalize block text-sm font-medium text-gray-700">{{trans("Business Name")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.company_name" type="text" id="business-name" name="company_name" />
            <p v-if="form.errors.company_name" class="text-sm text-red-600 mt-1">{{ form.errors.company_name }}</p>
          </div>
        </div>

        <!-- Website -->
        <div class="sm:col-span-6">
          <label for="website" class="capitalize block text-sm font-medium text-gray-700">{{trans("Website")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.website" />
            <p v-if="form.errors.website" class="text-sm text-red-600 mt-1">{{ form.errors.website }}</p>
          </div>
        </div>

        <div class="sm:col-span-6">
            <hr/>
        </div>

        <div class="sm:col-span-6">
          <label for="website" class="capitalize block text-sm font-medium text-gray-700">{{trans("Country")}}</label>
          <Address v-model="form[contact_address]" fieldName="contact_address" :form="form" :options="{countriesAddressData :countriesAddressData}" :fieldData="addressFieldData" />
        </div>

       <!--  <div class="sm:col-span-6">
        <label for="country_id" class="capitalize block text-sm font-medium text-gray-700">{{ trans("Country") }}</label>
        <div class="mt-2">
          <Multiselect 
            v-model="form.country_id" 
            :options="countries" 
            placeholder="Select a country"
            :searchable="true" 
            :clearable="true" 
          />
          <p v-if="form.errors.country_id" class="text-sm text-red-600 mt-1">
            {{ form.errors.country_id }}
          </p>
        </div>
      </div>

      <div class="sm:col-span-3">
          <label for="post_town" class="capitalize block text-sm font-medium text-gray-700">{{trans("Post Town")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.post_town" type="post_town" id="post_town" name="post_town" />
            <p v-if="form.errors.email" class="text-sm text-red-600 mt-1">{{ form.errors.post_town }}</p>
          </div>
        </div>

        <div class="sm:col-span-3">
          <label for="postal_code" class="capitalize block text-sm font-medium text-gray-700">{{trans("Postal Code")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.postal_code" type="text" id="postal_code" name="postal_code" />
            <p v-if="form.errors.postal_code" class="text-sm text-red-600 mt-1">{{ form.errors.postal_code }}</p>
          </div>
        </div>

        <div class="sm:col-span-6">
          <label for="address_line_1" class="capitalize block text-sm font-medium text-gray-700">{{trans("Address")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.address_line_1" type="text" id="address_line_1" name="address_line_1" />
            <p v-if="form.errors.address_line_1" class="text-sm text-red-600 mt-1">{{ form.errors.address_line_1 }}</p>
          </div>
        </div>

        <div class="sm:col-span-6">
          <label for="address_line_2" class="capitalize block text-sm font-medium text-gray-700">{{trans("Address 2")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.address_line_2" type="text" id="address_line_1" name="address_line_1" required />
            <p v-if="form.errors.address_line_2" class="text-sm text-red-600 mt-1">{{ form.errors.address_line_2 }}</p>
          </div>
        </div> -->

        <div class="sm:col-span-6">
            <hr/>
        </div>

        <!-- What Do You Sell -->
       <!--  <div class="sm:col-span-6">
          <label for="what-you-sell" class="capitalize block text-sm font-medium text-gray-700">{{trans("What Do You Sell")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.product" type="text" id="what-you-sell" name="product"  />
            <p v-if="form.errors.product" class="text-sm text-red-600 mt-1">{{ form.errors.product }}</p>
          </div>
        </div> -->

        <!-- Shipments Sent Per Week -->
       <!--  <div class="sm:col-span-3">
          <label for="shipments-per-week" class="capitalize block text-sm font-medium text-gray-700">{{trans("Shipments Sent PerWeek")}}</label>
          <div class="mt-2">
            <select v-model="form.shipments_per_week" id="shipments-per-week" name="shipments_per_week" 
              class="block w-full mt-1 py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="" disabled selected>Select shipments per week</option>
              <option v-for="option in optionsSend" :key="option" :value="option">{{ option }}</option>
            </select>
            <p v-if="form.errors.shipments_per_week" class="text-sm text-red-600 mt-1">{{ form.errors.shipments_per_week }}</p>
          </div>
        </div> -->


        <!-- Goods Size and Weight -->
        <!-- <div class="sm:col-span-3">
          <label for="goods-size-and-weight" class="capitalize block text-sm font-medium text-gray-700">{{trans("Size and Weight of Your Goods")}}</label>
          <div class="mt-2">
            <PureInput v-model="form.size_and_weight" type="text" id="goods-size-and-weight"
              name="size_and_weight"  />
              <p v-if="form.errors.size_and_weight" class="text-sm text-red-600 mt-1">{{ form.errors.size_and_weight }}</p>
          </div>
        </div> -->
        <div class="sm:col-span-6 flex flex-col">
          <CustomerDataForm :form="form" />
        </div>

        <div class="sm:col-span-6 flex flex-col">
          <label class="capitalize block text-sm font-medium text-gray-700">{{ trans("User Interests") }}</label>
          <div class="mt-2 flex flex-wrap gap-6">
            <!-- Loop through the interests -->
            <div
              v-for="interest in interestsList"
              :key="interest.value"
              class="flex items-center space-x-3 border-2 py-3 px-6 rounded-lg transition-all duration-200 ease-in-out hover:bg-indigo-50 hover:shadow-lg cursor-pointer"
              :class="{ 'bg-indigo-50 shadow-lg': form.interest.includes(interest.value) }"
              @click="toggleInterest(interest.value)"
            >
              <!-- Checkbox -->
              <input
                v-model="form.interest"
                type="checkbox"
                :id="interest.value"
                :value="interest.value"
                class="h-5 w-5 text-indigo-600 border-gray-300 rounded-sm focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                @click.stop
              />
              <label :for="interest.value" class="text-sm font-medium text-gray-900 cursor-pointer">
                {{ interest.label }}
              </label>
            </div>
            <p v-if="form.errors.interest" class="text-sm text-red-600 mt-1">{{ form.errors.interest }}</p>
          </div>
        </div>


        <!-- Password -->
        <div class="sm:col-span-3">
          <label for="password" class="capitalize block text-sm font-medium text-gray-700">Password</label>
          <div class="mt-2 password">
            <PureInput v-model="form.password" @update:modelValue="(e)=>form.clearErrors('password')" :type="'password'" required/>
            <p v-if="form.errors.password" class="text-sm text-red-600 mt-1">{{ form.errors.password }}</p>
          </div>
        </div>

        <!-- Retype Password -->
        <div class="sm:col-span-3">
          <label for="password-confirmation" class="capitalize block text-sm font-medium text-gray-700">Retype Password</label>
          <div class="mt-2 password">
            <PureInput v-model="form.password_confirmation" :type="'password'" required/>
            <p v-if="form.errors.password_confirmation" class="text-sm text-red-600 mt-1">{{ form.errors.password_confirmation }}</p>
          </div>
        </div>


      </div>
    </div>

    <!-- Submit Button -->
    <div class="flex justify-end">
      <button type="submit"
        class="inline-flex items-center px-6 bg-[#C1A027] text-white py-3 border border-transparent text-sm font-medium rounded-md shadow-sm">
        <span v-if="isLoading" class="loader mr-2"></span>
        {{trans("Register")}}
      </button>
    </div>
  </form>
</template>

<style scoped lang="scss">
.password {
  .p-PureInputtext {
    width: 100% !important;
  }
}
</style>