<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 14:00:48 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
-->

<script setup lang="ts">
import AppLogin from "@/Components/Forms/Fields/AppLogin.vue";
import { ref } from 'vue'
import Image from '@/Components/Image.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import Button from '@/Components/Elements/Buttons/Button.vue';
import { faEye, faEyeSlash } from "@fal";
import PermissionsPictogram from '@/Components/DataDisplay/PermissionsPictogram.vue'
import { trans } from "laravel-vue-i18n"
import { Link } from "@inertiajs/vue3"
import Tag from "@/Components/Tag.vue"

const props = defineProps<{
  data: {
    employee : any,
    pin : any
  };

}>();
console.log(props)


const showPins = ref(false);

function toggleShowPins() {
  showPins.value = !showPins.value;
}

const isVisitClockingMachine = ref(false)
</script>

<template>
<!--   <div class="h-fit grid grid-cols-3 gap-4 p-6">
    <div class="col-span-1">
      <div class="h-fit grid col-span-3 ring-1 ring-gray-300 shadow rounded-2xl p-6 gap-y-6">
        <AppLogin :route="{ name: 'grp.models.profile.app-login-qrcode' }" />
        <div class="mt-8 flex flex-col items-center gap-y-1">
          <div class="text-gray-400 italic">Don't have the app?</div>
          <a
            href=""
            target="_blank"
            class="text-blue-700 hover:underline flex items-center gap-x-2"
          >
            <FontAwesomeIcon
              icon="fab fa-android"
              class=""
              size="xl"
              fixed-width
              aria-hidden="true"
            />
            <div class="text-lg font-semibold leading-5">
              Download Android App
            </div>
          </a>
        </div>
      </div>
    </div>

    <div class="col-span-2">
      <div class="h-fit w-fit grid col-span-3 ring-1 ring-gray-300 shadow rounded-2xl p-6 gap-y-6">
        <div class="flex flex-col items-center gap-y-4">
          <div class="flex flex-wrap justify-center gap-2">
            <div
              v-for="(value, index) in Array.from(data.pin)"
              :key="index"
              class="w-12 h-12 flex items-center justify-center text-lg font-semibold border border-gray-300 rounded-md shadow-sm bg-gray-50"
            >
            
              {{ showPins ? value : 'X' }}
            </div>
          </div>
        </div>

        <div class=" flex flex-col items-center gap-y-4">
          <div class="text-gray-400 italic">Show Clocking Machine Pin</div>

          <Button  @click="toggleShowPins" :label="showPins ? 'Hide Pins' : 'Show Pins'" :icon="showPins ? faEyeSlash : faEye" />
        </div>
      </div>
    </div>
  </div> -->

  <div class="px-6 py-6 grid lg:grid-cols-9 gap-x-8">
        <div class="lg:col-span-6 ring-1 ring-gray-300 shadow rounded-2xl py-6 grid lg:grid-cols-2 gap-y-4">
            <div class="flex flex-col gap-y-4 px-8">
                <div class="mx-auto w-fit aspect-square rounded-full overflow-hidden md:h-56" :src="'person.imageUrl'" alt="">
                    <Image :src="data?.employee?.data.avatar" />
                </div>
                
                <div class="col-span-4">
                    <div class="flex items-end gap-x-2">
                        <div class="font-semibold text-2xl">{{ data?.employee?.data.contact_name }}</div>
                        <div class="text-gray-400">
                            #{{ data?.employee?.data.id }} {{ data?.employee?.data.username }}
                        </div>
                    </div>

                    <div class="mt-2">
                        <div class="text-gray-500 text-sm mb-1">
                            {{ trans("Job position") }}
                        </div>
                        <div v-if="data.employee?.data?.job_positions?.length" class="flex gap-x-2 gap-y-1">
                            <Tag v-for="job in data.employee?.data?.job_positions" :key="job.slug"
                                :label="job.name"
                                noHoverColor
                                stringToColor
                            />
                        </div>
                        <!-- <div v-if="data?.employee?.data.about" class="text-gray-500">
                            {{ data?.employee?.data.about }}
                        </div> -->
                        <div v-else class="text-gray-400 italic text-sm">
                            {{ trans('Have no job position') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Contact Information -->
            <div class="mt-6 border-l border-gray-300 px-6 space-y-3">
                <div class="font-semibold">{{ trans("Contact Information") }}</div>

                <div class="space-y-2">
                    <div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                            {{ trans("Contact Name") }}
                        </div>
                        <div class="xl:col-span-2 font-medium text-right lg:text-left">
                            {{ data?.employee?.data.contact_name }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                          {{ trans("Emergency Contact") }}
                        </div>
                        <div class="xl:col-span-2 font-medium text-right lg:text-left">
                            {{ data?.employee?.data.emergency_contact || '-' }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                            {{ trans("Start Working") }}
                        </div>
                        <div class="xl:col-span-2 font-medium text-right lg:text-left">
                            {{ useFormatTime(data?.employee?.data.employment_start_at) }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                          {{ trans("State") }}
                        </div>
                        <div class="xl:col-span-2 font-medium text-right lg:text-left">
                          <Tag  :label="data?.employee?.data.state"></Tag>
                        </div>
                    </div>

                    <!-- <div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                            {{ trans("Job Position") }}
                        </div>
                        <div class="xl:col-span-2 font-medium text-right lg:text-left">
                            {{ data?.employee?.data.job_positions?.length }} 
                        </div>
                    </div> -->
                </div>
            </div>
        </div>

        <div class="mt-4 lg:mt-0 w-full h-fit lg:max-w-lg grid lg:col-span-3 ring-1 ring-gray-300 shadow rounded-2xl py-6 px-4 gap-y-6">
            <template v-if="data?.pin">
                <div class="flex flex-nowrap justify-center gap-2">
                  <div
                    v-for="(value, index) in Array.from(data?.pin)"
                    :key="index"
                    class="h-6 xl:h-8 aspect-square flex items-center justify-center text-sm xl:text-lg font-semibold border border-gray-300 rounded xl:rounded-md shadow-sm bg-gray-50"
                  >
                
                    <Transition name="spin-to-right">
                      <span :key="showPins ? value : 'X'">{{ showPins ? value : 'X' }}</span>
                    </Transition>
                  </div>
                </div>
                
                <div class=" flex flex-col items-center gap-y-4">
                    <Button  @click="toggleShowPins" type="tertiary" :label="showPins ? trans('hide Clocking machine PIN') : trans('Show Clocking machine PIN')" :icon="showPins ? faEyeSlash : faEye" />
                </div>
            </template>

            <template v-else>
                <div class="text-center text-gray-400 italic">
                    {{ trans("No Clocking machine PIN yet") }}
                </div>
                <Link :href="route('grp.org.hr.employees.edit', {...route().params, section: 'pin'})" @start="() => isVisitClockingMachine = true" @finish="() => isVisitClockingMachine = false" class="mx-auto">
                    <Button type="secondary" :loading="isVisitClockingMachine" :label="trans('Add Clocking machine PIN')" icon="fal fa-plus" />
                </Link>
            </template>
        </div>

    </div>
    <div class="flex py-4 px-8 gap-x-8">
      <div v-if="data?.permissions_pictogram" class="sm:col-span-2">
          <PermissionsPictogram
              :data_pictogram="data?.permissions_pictogram"
          />
      </div>
    </div>
</template>
