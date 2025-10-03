<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 03 Nov 2023 13:05:39 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">


  import {Chart as ChartJS, ArcElement, Tooltip, Legend, Colors} from 'chart.js'
  import {Pie} from 'vue-chartjs'
  import {trans} from "laravel-vue-i18n";
  import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome'
  import {faSeedling, faChair, faThumbsDown, faLaugh, faUnlink, faExclamationTriangle, faExclamationCircle, faSignIn, faDungeon, faEye, faEyeSlash, faMousePointer, faSnooze} from '@fal'
  import {library} from '@fortawesome/fontawesome-svg-core'
  import {useLocaleStore} from "@/Stores/locale";
  import {capitalize} from '@/Composables/capitalize'
  import { onUnmounted, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { ref } from 'vue'
  
  library.add(faSeedling, faChair, faThumbsDown, faLaugh, faUnlink, faExclamationTriangle, faExclamationCircle, faSignIn,
      faDungeon, faEye, faEyeSlash, faMousePointer, faSnooze)
  
  ChartJS.register(ArcElement, Tooltip, Legend, Colors)
  
  const locale = useLocaleStore()
  const props = defineProps<{
      data: {
          prospectStats: {
              [key: string]: {
                  label: string
                  count: number
                  cases: {
                      value: string
                      count: number
                      label: string
                      icon: {
                          icon: string | string[]
                          tooltip: string
                          class: string
                      }
                  }[]
              }
          }
  
      }
  }>()
  
  const options = {
      responsive: true,
      plugins: {
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
  
  
  onMounted(() => {
      window.Echo.private('org.general').listen('.prospects.dashboard', (e) => {
  
  
          if (e.data.counts !== undefined) {
              Object.keys(e.data.counts).forEach(key => {
                  if (key !== 'no-contacted') {
                      props.data.prospectStats[key].count = e.data.counts[key]
                  }
  
                  if (key !== 'prospects') {
                      props.data.prospectStats['prospects'].cases[key].count = e.data.counts[key]
                  }
              });
          }
  
          if (e.data.contacted !== undefined) {
              Object.keys(e.data.contacted).forEach(key => {
                  props.data.prospectStats.contacted.cases[key].count = e.data.contacted[key]
              });
          }
          if (e.data.fail !==  undefined) {
              Object.keys(e.data.fail).forEach(key => {
                  props.data.prospectStats.fail.cases[key].count = e.data.fail[key]
              });
          }
          if (e.data.success !== undefined) {
              Object.keys(e.data.success).forEach(key => {
                  props.data.prospectStats.success.cases[key].count = e.data.success[key]
              });
          }
  
  
  
      })
  })
  
  onUnmounted(() => {
      window.Echo.private(`org.general`)
      .stopListening('.prospects.dashboard')
  })
  
  // console.log('qwe', props.data.prospectStats)
  
  const isLoadingVisit = ref<number | null>(null)
  </script>
  
  
  <template>
  
      <div class="px-6">
          <dl class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-x-2 gap-y-3">
  
              <div v-if="data?.prospectStats" v-for="prospectState in data.prospectStats" class="px-4 py-5 sm:p-6 rounded-lg bg-white shadow tabular-nums">
                  <dt class="text-base font-medium text-gray-400">{{ prospectState.label }}</dt>
                  <dd class="mt-2 flex justify-between gap-x-2">
                      <div class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
                          <!-- In Total -->
                          <div class="flex gap-x-2 items-end">
                              {{ locale.number(prospectState.count) }}
                              <span class="text-sm font-medium leading-4 text-gray-500 ">{{ trans('in total') }}</span>
                          </div>
  
                          <!-- Statistic -->
                          <div class="text-sm text-gray-500 flex gap-x-5 gap-y-1 items-center flex-wrap">
                              <template v-for="(dCase, idxCase) in prospectState.cases" :key="idxCase">
                                  <component
                                    :is="dCase.route?.name ? Link : 'div'"
                                    :href="dCase.route?.name ? route(dCase.route.name, dCase.route.parameters) : null"
                                    :class="dCase.route?.name ? 'hover:bg-gray-200 px-1 py-0.5 rounded' : ''"
                                    class="flex gap-x-0.5 items-center font-normal"
                                    v-tooltip="capitalize(dCase.icon.tooltip)"
                                    @start="() => isLoadingVisit = idxCase"
                                    @finish="() => isLoadingVisit = null"
                                >
                                    <LoadingIcon v-if="isLoadingVisit === idxCase" class="text-gray-500" />
                                      <FontAwesomeIcon v-else :icon='dCase.icon.icon' :class='dCase.icon.class' fixed-width :title="dCase.icon.tooltip" aria-hidden='true'/>
                                      <span class="font-semibold">
                                          {{ locale.number(dCase.count) }}
                                      </span>
                                  </component>
                              </template>
                          </div>
                      </div>
  
                      <!-- Donut -->
                      <div class="w-20">
                          <Pie :data="{
                              labels: Object.entries(prospectState.cases).map(([, value]) => value.label),
                              datasets: [{
                                  data: Object.entries(prospectState.cases).map(([, value]) => value.count),
                                  hoverOffset: 4
                              }]
                          }" :options="options"/>
                      </div>
  
                  </dd>
              </div>
          </dl>
      </div>
  
  </template>
  