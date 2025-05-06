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
      topUpData: {}
  }>()
  
  const locale = inject('locale', {})
  

  
  function routePallet(storageData: any,) {
      console.log(storageData, 'this key');
      
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
                  <div class="mt-4 space-y-4 grid md:grid-cols-2 gap-x-4">
                      <div class="flex  justify-between px-4 py-5 sm:p-6 rounded-lg bg-white border border-gray-300 tabular-nums col-span-2">
                          <div class="">
                              <dt class="text-base font-medium text-gray-400 capitalize">
                              {{topUpData.topUps.label}}
                              </dt>
                              <dd class="mt-2 flex justify-between gap-x-2">
                                  <div
                                      class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
                                      <!-- In Total -->
                                      <div class="flex gap-x-2 items-end">
                                          <Link :href="route(topUpData.topUps.route.name)">
                                              <CountUp
                                              class="primaryLink inline-block"
                                               :endVal="topUpData.topUps.count" :duration="1.5"
                                                  :scrollSpyOnce="true" :options="{
                                                  formattingFn: (value: number) => locale.number(value)
                                              }" />
                                          </Link>
                                          <span class="text-sm font-medium leading-4 text-gray-500 ">
                                            {{ topUpData.topUps.description }}
                                        </span>
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
  