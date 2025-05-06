  <script setup lang="ts">
  import { Pie } from 'vue-chartjs'
  import { trans } from "laravel-vue-i18n"
  import { capitalize } from '@/Composables/capitalize'
  import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from 'chart.js'
  import { useLocaleStore } from "@/Stores/locale"
  import { PalletCustomer, FulfilmentCustomerStats } from '@/types/Pallet'
  import { Link } from "@inertiajs/vue3"
  
  import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
  import { faCheckCircle, faInfoCircle, faExclamationTriangle } from '@fal'
  import { faSeedling, faShare, faSpellCheck, faCheck, faTimes, faSignOutAlt, faTruck, faCheckDouble, faCross } from '@fal'
  import { library } from '@fortawesome/fontawesome-svg-core'
  import { useFormatTime } from '@/Composables/useFormatTime'
  import CountUp from 'vue-countup-v3'
  import { Head } from '@inertiajs/vue3'
  import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
  import PageHeading from "@/Components/Headings/PageHeading.vue"
  
  import DataTable from "primevue/datatable"
  import Column from "primevue/column"
  import Row from "primevue/row"
  import { routeType } from '@/types/route'
  
  import '@/Composables/Icon/PalletStateEnum'
  import Tag from '@/Components/Tag.vue'
  import { inject } from 'vue'
  import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
  
  library.add(faCheckCircle, faInfoCircle, faExclamationTriangle, faSeedling, faShare, faSpellCheck, faCheck, faTimes, faSignOutAlt, faTruck, faCheckDouble, faCross)
  
  ChartJS.register(ArcElement, Tooltip, Legend, Colors)
  
  const props = defineProps<{
      title: string
      pageHead : PageHeadingTypes
      customer: PalletCustomer
      storageData: {
          [key: string]: FulfilmentCustomerStats
      }
      discounts: {}
      route_action: {
          route: routeType
          label: string
          style: string
          type: string
      }[]
      currency: {
          code: string
          symbol: string
      }
      rental_agreement: {}
  }>()
  
  const locale = inject('locale', {})
  
  const options = {
      responsive: true,
      plugins: {
          emptyPie: {
              color: 'rgba(255, 128, 0, 0.5)',
              width: 2,
              radiusDecrease: 20
          },
          legend: {
              display: false
          },
          tooltip: {
              // Popup: When the data set is hovered
              // enabled: false,
              titleFont: {
                  size: 10,
                  weight: 'lighter'
              },
              bodyFont: {
                  size: 11,
                  weight: 'bold'
              }
          },
      }
  }
  
  function routePallet(storageData: any, key: string) {
      console.log(storageData, key , 'this key');
      
      if (storageData[key].route) {
          return route(storageData[key].route.name)
      }
  }
  </script>
  
  <template>
  
      <Head :title="title" />
      <PageHeading :data="pageHead">
      </PageHeading>
  
      <!-- <pre>{{ discounts }}</pre> -->
      <div class="px-4 py-5 md:px-6 lg:px-8 ">
          <!-- <h1 class="text-2xl font-bold">Storage Dashboard</h1>
          <hr class="border-slate-200 rounded-full mb-5 mt-2"> -->
  
          <div class="grid md:grid-cols-2 gap-y-4 md:gap-y-0 gap-x-8">
              <!-- Section: Profile box -->
              <div>
                 <!--  <div
                      class="h-fit bg-slate-50 border border-slate-200 text-retina-600 p-6 flex flex-col justify-between rounded-lg shadow overflow-hidden">
                      <div class="w-full">
                          <h2 v-if="customer?.name" class="text-3xl font-bold"> {{ customer?.name }}</h2>
                          <h2 v-else class="text-3xl font-light italic brightness-75">{{ trans('No name') }}</h2>
                          <div class="text-lg">
                              {{ customer?.shop }} -->
                              <!-- <span class="text-gray-400">
                                  ({{ customer?.number_current_customer_clients || 0 }} clients)
                              </span> -->
                    <!--       </div>
                      </div>
  
                      <div class="mt-4 space-y-3 text-sm text-slate-500"> -->
                          <!-- <div class="border-l-2 border-slate-500 pl-4">
                              <h3 class="font-light">Phone</h3>
                              <address class="text-base font-medium not-italic text-gray-600">
                                  <p>{{ customer?.phone || '-' }}</p>
                              </address>
                          </div> -->
                          <!-- <div class="border-l-2 border-slate-500 pl-4">
                              <h3 class="font-light">Email</h3>
                              <address class="text-base font-medium not-italic text-gray-600">
                                  <p>{{ customer?.email || '-' }}</p>
                              </address>
                          </div> -->
                       <!--    <div class="border-l-2 border-slate-500 pl-4">
                              <h3 class="font-light">Member since</h3>
                              <address class="text-base font-medium not-italic text-gray-600">
                                  <p>{{ useFormatTime(customer?.created_at) || '-' }}</p>
                              </address>
                          </div>
                          
                          <div class="border-l-2 border-slate-500 pl-4">
                              <h3 class="font-light">{{ trans('Billing Cycle') }}</h3>
                              <address class="text-base font-medium not-italic text-gray-600 capitalize">
                                  <p>{{ rental_agreement.billing_cycle }}</p>
                              </address>
                          </div>
                          
                          <div class="border-l-2 border-slate-500 pl-4">
                              <h3 class="font-light">{{ trans('Pallet Limit') }}</h3>
                              <address class="text-base font-medium not-italic text-gray-600">
                                  <p>{{ rental_agreement.pallets_limit || `(${trans('No limit')})` }}</p>
                              </address>
                          </div>
  
  
  
                      </div>
                  </div> -->
  
                  <div class="mt-4 space-y-4 grid md:grid-cols-2 gap-x-4">
                      <div class="flex  justify-between px-4 py-5 sm:p-6 rounded-lg bg-white border border-gray-300 tabular-nums col-span-2">
                          <div class="">
                              <dt class="text-base font-medium text-gray-400 capitalize">{{ storageData.pallets.label }}
                              </dt>
                              <dd class="mt-2 flex justify-between gap-x-2">
                                  <div
                                      class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
                                      <!-- In Total -->
                                      <div class="flex gap-x-2 items-end">
                                          <Link :href="routePallet(storageData, 'pallets')">
                                              <CountUp
                                              class="primaryLink inline-block"
                                               :endVal="storageData.pallets.count" :duration="1.5"
                                                  :scrollSpyOnce="true" :options="{
                                                  formattingFn: (value: number) => locale.number(value)
                                              }" />
                                          </Link>
                                          <span class="text-sm font-medium leading-4 text-gray-400 ">
                                              {{ storageData.pallets.description }}
                                          </span>
                                      </div>
  
                                      <div v-if="storageData.pallets.state?.damaged?.count || storageData.pallets.state?.lost?.count || storageData.pallets.state?.['other_incident'].count"
                                          class="">
                                          <div
                                              class="text-sm text-red-400 border border-red-300 bg-red-50 rounded px-2 py-2 font-normal">
                                              <div v-if="!storageData.pallets.state?.damaged?.count">
                                                  <FontAwesomeIcon :icon='storageData.pallets.state?.damaged?.icon.icon'
                                                      :class='storageData.pallets.state?.damaged?.icon.class' fixed-width
                                                      aria-hidden='true' />
                                                  <!-- Damaged: -->
                                                  {{ storageData.pallets.state?.damaged?.count }}
                                              </div>
                                              <div v-if="!storageData.pallets.state?.lost?.count">
                                                  <FontAwesomeIcon :icon='storageData.pallets.state?.lost?.icon.icon'
                                                      :class='storageData.pallets.state?.lost?.icon.class' fixed-width
                                                      aria-hidden='true' />
                                                  <!-- Lost: -->
                                                  {{ storageData.pallets.state?.lost?.count }}
                                              </div>
                                              <div v-if="!storageData.pallets.state?.['other_incident'].count">
                                                  <FontAwesomeIcon
                                                      :icon="storageData.pallets.state?.['other_incident']?.icon?.icon"
                                                      :class="storageData.pallets.state?.['other_incident']?.icon?.class"
                                                      fixed-width aria-hidden='true' />
                                                  <!-- Other incident: -->
                                                  {{ storageData.pallets.state?.['other_incident'].count }}
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                              </dd>
                          </div>
                      </div>
  
                      <!-- Box: Pallet Delivery -->
                      <div class="flex  justify-between px-4 py-5 sm:p-6 rounded-lg bg-white border border-gray-300 tabular-nums">
                          <div class="">
                              <dt class="text-base font-medium text-gray-400 capitalize">{{
                                  storageData.pallet_deliveries.label }}
                              </dt>
                              <dd class="mt-2 flex justify-between gap-x-2">
                                  <div
                                      class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
                                      <!-- In Total -->
                                      <div class="flex gap-x-2 items-end">
                                          <Link :href="routePallet(storageData, 'pallet_deliveries')">
                                              <CountUp 
                                              class="primaryLink inline-block"
                                               :endVal="storageData.pallet_deliveries.count" :duration="1.5"
                                                  :scrollSpyOnce="true" :options="{
                                                  formattingFn: (value: number) => locale.number(value)
                                              }" />
                                          </Link>
                                          <span class="text-sm font-medium leading-4 text-gray-500 ">
                                              {{ storageData.pallet_deliveries.description }}
                                          </span>
                                      </div>
                                  </div>
                              </dd>
                          </div>
                      </div>
  
  
                      <div
                          class="flex  justify-between px-4 py-5 sm:p-6 rounded-lg bg-white border border-gray-300 tabular-nums">
                          <div class="">
                              <dt class="text-base font-medium text-gray-400 capitalize">{{
                                  storageData.pallet_returns.label }}</dt>
                              <dd class="mt-2 flex justify-between gap-x-2">
                                  <div
                                      class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
                                      <!-- In Total -->
                                      <div class="flex gap-x-2 items-end">
                                          <Link :href="routePallet(storageData, 'pallet_returns')">
                                              <CountUp
                                              class="primaryLink inline-block"
                                               :endVal="storageData.pallet_returns.count" :duration="1.5"
                                                  :scrollSpyOnce="true" :options="{
                                                  formattingFn: (value: number) => locale.number(value)
                                              }" />
                                          </Link>
                                          <span class="text-sm font-medium leading-4 text-gray-500 ">{{
                                              storageData.pallet_returns.description }}</span>
                                      </div>
                                  </div>
                              </dd>
                          </div>
                      </div>
                  </div>
              </div>
  
              <div v-if="route_action" class="mt-4 flex">
                  <div class="w-64 border-gray-300 ">
                      <div class="p-1" v-for="(btn, index) in route_action" :key="index">
                      <ButtonWithLink
                          :label="btn.label"
                          :bindToLink="{ preserveScroll: true, preserveState: true }"
                          :type="btn.style"
                          :tooltip="btn.tooltip"  
                          full
                          :routeTarget="btn.route"
                          :disabled="btn.disabled"
                      />
                      </div>
                  </div>
              </div>
  
              <!-- Section: Stats box -->
            <!--   <div class="h-fit grid md:grid-cols-2 gap-y-3 gap-x-2 text-gray-600">
                  <div class="w-full md:col-span-2">
                      <div class="text-2xl font-semibold text-gray-600">
                          {{ trans("Discounts") }}🎉
                      </div>
  
                      <DataTable :value="discounts" stripedRows removableSort paginator :rows="10"
                          :rowsPerPageOptions="[5, 10, 15]" tableStyle="min-width: 30rem"
                          class="border border-gray-300 rounded-md">
                          <Column field="name" sortable header="Name">
                              <template #body="{ data }">
                                  <div class="flex flex-col justify-end relative">
                                      <span>{{ data.name }}</span>
                                      <span class="text-gray-400">({{ data.type }})</span>
                                  </div>
                              </template>
                          </Column>
                          <Column field="price" sortable headerClass="flex justify-end">
                              <template #header>
                                  <div class="flex justify-end items-end">
                                      <span class="font-bold text-right">{{ trans("Discounts") }}</span>
                                  </div>
                              </template>
  
                              <template #body="{ data }">
                                  <div class="flex justify-end relative">
                                      <span class="line-through text-gray-400">
                                          {{ locale.currencyFormat(currency.code, data.price) }}
                                      </span>
                                      <span class="ml-1 font mr-1">{{ locale.currencyFormat(currency.code,
                                          data.agreed_price) }}</span>
                                      <Tag :label="data.percentage_off + '%'" :theme="3" noHoverColor />
                                  </div>
                              </template>
                          </Column>
                      </DataTable>
                  </div>
  
              </div> -->
          </div>
          <!-- <pre>{{ props }}</pre> -->
      </div>
  </template>
  