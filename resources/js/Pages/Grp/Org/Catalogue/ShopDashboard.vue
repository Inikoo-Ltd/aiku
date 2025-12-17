<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 19:55:46 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { Head, Link } from '@inertiajs/vue3'
  import PageHeading from "@/Components/Headings/PageHeading.vue"
  import { library } from "@fortawesome/fontawesome-svg-core"
  import { faTruckCouch } from "@fal"
  import { capitalize } from "@/Composables/capitalize"
  import StatsDoughnut from "@/Components/Stats/StatsDoughnut.vue"
  import { useTruncate } from '@/Composables/useTruncate'
  import { useRangeFromNow } from '@/Composables/useFormatTime'
  import { useLocaleStore } from "@/Stores/locale"
  import { trans } from 'laravel-vue-i18n'
  import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from 'chart.js'
  import Image from '@/Components/Image.vue'
  import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
  import { PageHeadingTypes } from '@/types/PageHeading'
  import FlatTreeMap from '@/Components/Navigation/FlatTreeMap.vue'
  
  const props = defineProps<{
      title: string
      pageHead: PageHeadingTypes
      flatTreeMaps: {}
  }>()
  
  library.add(faTruckCouch)
  ChartJS.register(ArcElement, Tooltip, Legend, Colors)
  
  const dataStats = [
      {
          label: 'Out',
          count: 12,
          sticker: {
              icon: 'fal fa-truck-couch',
              class: 'bg-indigo-600 -scale-x-[1]'
          },
          cases: {
              pertamina: {
                  label: 'Pertamina',
                  count: 12,
                  icon: {
                      icon: 'fa-gas-pump',
                      class: 'text-slate-400',
                      tooltip: 'Pertamina'
                  }
              },
              indihome: {
                  label: 'Indihome',
                  count: 31,
                  icon: {
                      icon: 'fa-wifi',
                      class: 'text-slate-400',
                      tooltip: 'Indihome'
                  }
              },
          }
      },
      {
          label: 'Blue',
          count: 19,
          sticker: {
              icon: 'fal fa-truck-couch',
              class: 'bg-amber-500'
          },
          cases: {
              biznet: {
                  label: 'Biznet',
                  count: 19,
                  icon: {
                      icon: 'fa-wifi',
                      class: 'text-slate-400',
                      tooltip: 'Biznet'
                  }
              },
              demokrat: {
                  label: 'Demokrat',
                  count: 31,
                  icon: {
                      icon: 'fa-flag-usa',
                      class: 'text-slate-400',
                      tooltip: 'Demokrat'
                  }
              },
          }
      },
      {
          label: 'Yellow',
          count: 12,
          sticker: {
              icon: 'fal fa-truck-couch',
              class: 'bg-sky-500'
          },
          cases: {
              pertamina: {
                  label: 'Pertamina',
                  count: 12,
                  icon: {
                      icon: 'fa-gas-pump',
                      class: 'text-slate-400',
                      tooltip: 'Pertamina'
                  }
              },
              indihome: {
                  label: 'Indihome',
                  count: 31,
                  icon: {
                      icon: 'fa-wifi',
                      class: 'text-slate-400',
                      tooltip: 'Indihome'
                  }
              },
          }
      },
      {
          label: 'Green',
          count: 19,
          sticker: {
              icon: 'fal fa-truck-couch',
              class: 'bg-pink-500'
          },
          cases: {
              biznet: {
                  label: 'Biznet',
                  count: 19,
                  icon: {
                      icon: 'fa-wifi',
                      class: 'text-slate-400',
                      tooltip: 'Biznet'
                  }
              },
              demokrat: {
                  label: 'Demokrat',
                  count: 31,
                  icon: {
                      icon: 'fa-flag-usa',
                      class: 'text-slate-400',
                      tooltip: 'Demokrat'
                  }
              },
          }
      },
  ]
  
  const banner = [
      {
          id: 1,
          name: 'Banner 1',
          image: 'https://picsum.photos/200/50',
          views: 12,
          updated_at: '2021-08-01 12:00:00',
          state_icon: {
              icon: 'fa-check-circle',
              class: 'text-green-500',
              tooltip: 'Active'
          },
          route: {
              name: 'customer.banners.banners.show',
              parameters: {
                  banner: 1
              }
          }
      }
  ]
  
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
  </script>
  
  
  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead" />
  
      <div class="p-6">No data to be shown</div>
  
      <div v-if="flatTreeMaps" class="mt-2">
          <FlatTreeMap class="mx-4" v-for="(treeMap, idx) in flatTreeMaps" :key="idx" :nodes="treeMap" mode="compact" />
      </div>
  
      <div v-if="false" class="px-4 py-4 space-y-8">
          <div class="px-10 space-y-1">
              <div class="text-xl text-state-700 font-bold">
                  Fulfilment Stats
              </div>
  
              <div class="flex items-center gap-x-16">
  
                  <!-- Section: Stats -->
                  <div class="grid grid-cols-2 gap-2">
                      <StatsDoughnut v-for="stat in dataStats" :stat="stat" ></StatsDoughnut>
                  </div>
              </div>
          </div>
  
          <!-- Sections: Recently added Banner -->
          <div class="max-w-2xl lg:mx-0 lg:max-w-none">
              <div class="flex items-center justify-between">
                  <h2 class="text-base font-semibold text-gray-700 leading-none">{{ trans('Recently edited banner') }}</h2>
                  <Link :href="'#'" class="text-sm text-gray-500 hover:text-gray-700">
                      View all<span class="sr-only">, banner</span>
                  </Link>
              </div>
              <!-- Looping: Last Edited Banners -->
              <ul role="list" class="mt-3 grid grid-cols-1 gap-x-6 gap-y-8 lg:grid-cols-3 xl:gap-x-8">
                  <Link :href="`#`" v-for="lastEditedBanner in banner" :key="lastEditedBanner.id"
                      class="overflow-hidden rounded-md h-fit border border-indigo-300 hover:ring-2 hover:ring-indigo-400">
                      <div class="w-full h-auto aspect-[4/1] flex items-center justify-start gap-x-4 border-b border-gray-700/5 bg-gray-200 overflow-hidden">
                          <Image :src="{original: lastEditedBanner.image}" :alt="lastEditedBanner?.name" />
                      </div>
  
                      <dl class="divide-y divide-transparent px-4 pt-1 pb-3 text-sm">
                          <!-- Title Banner -->
                          <div class="flex justify-between items-center gap-x-4">
                              <!-- <dt class="text-gray-500 text-sm">{{ trans('Name') }}</dt> -->
                              <dd class="flex items-start gap-x-2">
                                  <div class="text-lg font-semibold text-gray-600">{{ useTruncate(lastEditedBanner?.name, 28, 4) }}
                                  </div>
                              </dd>
                          </div>
                          <!-- Last Eedited -->
                          <div class="flex justify-between items-center gap-x-4">
                              <!-- <dt class="text-gray-500 text-sm">{{ trans('Last edit') }}</dt> -->
                              <dd class="text-gray-600 text-xs italic tracking-wide space-x-1">
                                  <FontAwesomeIcon fixed-width icon='fal fa-history' class='text-gray-400' aria-hidden='true' />
                                  <span class="text-gray-500">{{ trans('Last edited on') }}</span>
                                  <time :datetime="lastEditedBanner.updated_at">{{ useRangeFromNow(lastEditedBanner.updated_at, {
                                      localeCode: useLocaleStore().language.code
                                  }) }}</time>
                              </dd>
                              <div>
                                  <FontAwesomeIcon fixed-width :icon='lastEditedBanner.state_icon?.icon'
                                      :class='lastEditedBanner.state_icon?.class' class="" aria-hidden='true'
                                      :alt="lastEditedBanner.state_icon?.tooltip" />
                              </div>
                          </div>
                          <!-- Views -->
                          <div class="text-gray-500 text-xs italic space-x-1">
                              <FontAwesomeIcon fixed-width icon='far fa-eye' class='text-gray-400' aria-hidden='true' />
                              {{ lastEditedBanner.views ?? 0 }} views
                          </div>
                      </dl>
                  </Link>
              </ul>
          </div>
      </div>
  </template>
  
  