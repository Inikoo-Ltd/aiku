<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 19:09:33 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import {Head, useForm} from '@inertiajs/vue3'
  import { jumpToElement } from "@/Composables/jumpToElement"

  import PageHeading from '@/Components/Headings/PageHeading.vue'
  import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
  import { faExclamationCircle, faCheckCircle, faAsterisk } from '@fas'
  import { library } from "@fortawesome/fontawesome-svg-core"
  library.add(faExclamationCircle, faAsterisk, faCheckCircle)

  const props = defineProps<{
      title: string,
      pageHead: object,
      formData: {
          blueprint: object;
          route: {
              name: string,
              parameters?: Array<string>
          };
      }
  }>()


  import Input from '@/Components/Forms/Fields/Input.vue'
  import Select from '@/Components/Forms/Fields/Select.vue'
  import Phone from '@/Components/Forms/Fields/Phone.vue'
  import Date from '@/Components/Forms/Fields/Date.vue'
  import {trans} from "laravel-vue-i18n"
  import Address from "@/Components/Forms/Fields/Address.vue"
  import Radio from '@/Components/Forms/Fields/Radio.vue'
  import Country from "@/Components/Forms/Fields/Country.vue"
  import Currency from "@/Components/Forms/Fields/Currency.vue"
  import { capitalize } from "@/Composables/capitalize"
  import InputWithAddOn from '@/Components/Forms/Fields/InputWithAddOn.vue'


  const getComponent = (componentName: string) => {
      const components = {
          'input': Input,
          'inputWithAddOn': InputWithAddOn,
          'phone': Phone,
          'date': Date,
          'select': Select,
          'address':Address,
          'radio': Radio,
          'country': Country,
          'currency': Currency,
      };
      return components[componentName] ?? null;

  };

  let fields = {};
  Object.entries(props.formData.blueprint).forEach(([, val]) => {
      Object.entries(val['fields']).forEach(([fieldName, fieldData]) => {
          fields[fieldName] = fieldData['value'];
      });
  });

  const form = useForm(fields);

  const handleFormSubmit = () => {
      form.post(route(
          props.formData.route.name,
          props.formData.route.arguments
  ));
  };

  const current = null
  </script>

  <template layout="CustomerApp">
      <Head :title="capitalize(title)"/>
      <PageHeading :data="pageHead"></PageHeading>
      <div class="rounded-lg bg-white shadow">
          <div class="divide-y divide-gray-200 lg:grid grid-flow-col lg:grid-cols-12 lg:divide-y-0 lg:divide-x">

              <!-- Left Tab: Navigation -->
              <aside class="py-0 lg:col-span-3 lg:h-full">
                  <div class="sticky top-16">
                      <div v-for="(item, key) in formData['blueprint']" @click="jumpToElement(`field${key}`)"
                          :class="[
                              key == current
                                  ? 'bg-orange-200 border-orange-500 text-orange-700 hover:bg-orange-50 hover:text-orange-700'
                                  : 'border-transparent text-gray-600 hover:bg-orange-100 hover:text-orange-700',
                              'cursor-pointer group border-l-4 px-3 py-2 flex items-center text-sm font-medium',
                          ]"
                          :aria-current="key === current ? 'page' : undefined"
                      >
                          <FontAwesomeIcon v-if="item.icon" aria-hidden="true"
                          :class="[
                              key === current
                                  ? 'text-orange-500 group-hover:text-orange-500'
                                  : 'text-gray-400 group-hover:text-gray-500',
                              'shrink-0 -ml-1 mr-3 h-6 w-6',
                          ]"
                          :icon="item.icon" />
                          <span class="capitalize truncate">{{ item.title }}</span>
                      </div>
                  </div>
              </aside>

              <!-- Main form -->
              <form class="px-4 sm:px-6 md:px-10 col-span-9 gap-y-8 pb-8 divide-y divide-blue-200 " @submit.prevent="handleFormSubmit">
                  <div v-for="(sectionData, sectionIdx ) in formData['blueprint']" :key="sectionIdx" class="relative py-4">
                      <!-- Helper: Section click -->
                      <div class="sr-only absolute -top-16" :id="`field${sectionIdx}`" />
                      <div v-if="sectionData.title || sectionData.subtitle" class="space-y-1">
                          <h3 class="text-lg leading-6 font-medium text-gray-900">
                              {{ sectionData.title }}
                          </h3>
                          <p v-show="sectionData['subtitle']" class="max-w-2xl text-sm text-gray-500">
                              {{ sectionData.subtitle }}
                          </p>
                      </div>

                      <div class="mt-2 pt-4 sm:pt-5">
                          <div v-for="(fieldData, fieldName, index ) in sectionData.fields" :key="index" class="mt-1 ">
                              <dl class="divide-y divide-green-200  ">
                                  <div class="pb-4 sm:pb-5 sm:grid sm:grid-cols-3 sm:gap-4 max-w-2xl">
                                      <dt class="text-sm font-medium text-gray-500">
                                          <div class="inline-flex items-start leading-none">
                                              <!-- Icon: Required -->
                                              <FontAwesomeIcon v-if="fieldData.required" :icon="['fas', 'asterisk']" class="font-light text-[12px] text-red-400 mr-1"/>
                                              <span>{{ fieldData.label }}</span>
                                          </div>
                                      </dt>
                                      <dd class="sm:col-span-2">
                                          <div class="mt-1 flex text-sm text-gray-700 sm:mt-0">
                                              <div class="relative flex-grow">
                                                  <!-- Dynamic component -->
                                                  <component
                                                      :is="getComponent(fieldData['type'])"
                                                      :form="form"
                                                      :fieldName="fieldName"
                                                      :options="fieldData['options']"
                                                      :fieldData="fieldData"
                                                      :key="index"
                                                  >
                                                  </component>
                                              </div>
                                          </div>
                                      </dd>
                                  </div>
                              </dl>
                          </div>
                      </div>
                  </div>

                  <div class="pt-5 border-t-2 border-orange-500">
                      <div class="flex justify-end">

                          <button type="submit" :disabled="form.processing"
                                  class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                              {{ trans('Save') }}
                          </button>
                      </div>
                  </div>

              </form>
  </div></div>
  </template>

