<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 24 Nov 2022 10:39:51 Central Indonesia Time, Ubud, Bali, Indonesia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { Head } from '@inertiajs/vue3'
  import PageHeading from '@/Components/Headings/PageHeading.vue'
  import { library } from '@fortawesome/fontawesome-svg-core'
  import { faGlobe } from '@fal'
  import { capitalize } from "@/Composables/capitalize"
  import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
  import { useFormatTime } from '@/Composables/useFormatTime'
  import { trans } from 'laravel-vue-i18n'
  import Tag from '@/Components/Tag.vue'
  
  
  library.add(faGlobe)
  
  const props = defineProps<{
      title: string
      pageHead: PageHeadingTypes
      data: {}
  }>()
  
  const dataCompany = [
      {
          label: trans('Contact name'),
          key: 'contact',
          value: props.data.contact_name ?? '-'
      },
      {
          label: trans('Username'),
          key: 'username',
          value: props.data.username
      },
      {
          label: trans('Email'),
          key: 'email',
          value: props.data.customer?.email
      },
      {
          label: trans('Last login'),
          key: 'last_login',
          value: props.data.last_login ?? '-'
      },
      {
          label: trans('Created at'),
          key: 'created_At',
          value: useFormatTime(props.data.customer?.created_at)
      },
      {
          key: 'status',
          value: props.data.status
      },
    //   {
    //       label: 'Location',
    //       key: 'location',
    //       value: props.data.customer?.location
    //   },
  ]
  console.log(props)
  </script>
  
  
  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead"></PageHeading>
      <div class="grid grid-cols-2 py-4 px-6">
  
          <!-- Section: field data -->
          <div>
              <div class="text-xl font-bold mb-2">{{ trans('User details') }}</div>
              <div class="h-fit w-80 relative grid grid-cols-1 divide-y divide-gray-300 border border-gray-300 rounded-md">
                  <div v-for="(print, index) in dataCompany" class="py-2.5 px-4">
                      <div class="text-gray-400 text-xs">
                          {{ print.label }}
                      </div>
                      <div class="font-medium text-sm">
                          <Tag v-if="print.key === 'status'" :theme="print.value ? 3 : undefined" :label="print.value ? 'Active' : 'Inactive'" />
                          <span v-else>{{print.value}}</span>
                      </div>
                  </div>
              </div>
          </div>
  


      </div>
          <!-- <pre>{{ data }}</pre> -->
  </template>
  