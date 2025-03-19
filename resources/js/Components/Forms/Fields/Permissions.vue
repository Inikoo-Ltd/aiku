<script setup lang="ts">
import { ref } from "vue";
import { Collapse } from "vue-collapsed";
import { set } from "lodash-es";
import EmployeePosition from "@/Components/Forms/Fields/EmployeePosition.vue";
import { trans } from "laravel-vue-i18n";
import Fieldset from "primevue/fieldset";

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faHelmetBattle, faStar, faGlobe } from "@fas";
import { faCircle } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { routeType } from "@/types/route";

library.add(faHelmetBattle, faStar, faCircle, faGlobe);

const props = defineProps<{
  form: {
    [key: string]: {
      position_name: string  // Administrator
      organisations: {
        [key: string]: string[]  // aw: [uk, de, fr]
      }
      shops: string[]
      warehouses: string[]
      fulfilments: string[]
      group: string[]
    }
  }
  fieldName: string
  options?: any
  fieldData: {
    list_authorised: {
      [key: string]: {
        authorised_shops: number
        authorised_fulfilments: number
        authorised_warehouses: number
        authorised_productions: number
      }
    }
    current_organisation: {
      slug: string
      name: string
    }
    updatePseudoJobPositionsRoute: routeType
    updateOrganisationPermissionsRoute: routeType
  }
  updateRoute: routeType
}>();

console.log("mmmmm", props.options);

const handleBox = (shopsSelected: string[], shopSlug: string) => {
  // console.log('ffff', shopsSelected)
  // if (shopsSelected.includes(shopSlug)) {

  //     const indexShopSlug = shopsSelected.indexOf(shopSlug)
  //     if (indexShopSlug !== -1) {
  //         shopsSelected.splice(indexShopSlug, 1)
  //     } else {
  //         shopsSelected.push(shopSlug)
  //     }
  // }
};

const groupPositionList = {
  group_admin: {
    department: trans("group admin"),
    key: "group_admin",
    level: "group_admin",
    icon: "fas fa-helmet-battle",
    subDepartment: [
      {
        slug: "group-admin", // Note, this is not slug is job position code
        label: trans("Group Administrator")
        // number_employees: props.options.positions?.data?.find(position => position.slug == 'group_admin')?.number_employees || 0,
      }
    ]
  },
  system_admin: {
    key: "group_sysadmin",
    department: trans("Group sysadmin"),
    level: "group_sysadmin",
    icon: "fas fa-computer-classic",
    subDepartment: [
      {
        slug: "sys-admin", // Note, this is not slug is job position code
        label: trans("System Administrator")
        // number_employees: props.options.positions?.data?.find(position => position.slug == 'system_admin')?.number_employees || 0,
      }
    ]
  },
  group_webmaster: {
    key: "group_webmaster",
    department: trans("Group webmaster"),
    level: "group_sysadmin",
    icon: "fas fa-globe",
    subDepartment: [
      {
        slug: "gp-wm", // Note, this is not slug is job position code
        label: trans("Group webmaster")
      }
    ]
  },
  group_supply_chain: {
    key: "group_supply_chain",
    department: trans("Supply Chain"),
    icon: "fal fa-box-usd",
    level: "group_supply_chain",
    subDepartment: [
      {
        slug: "gp-sc", // Note, this is not slug is job position code
        grade: "manager",
        label: trans("Manager")
        // number_employees: props.options.positions?.data?.find(position => position.slug == 'gp-sc')?.number_employees || 0,
      }
    ]
    // value: null
  },
  group_goods: {
    key: "group_goods",
    department: trans("Goods"),
    icon: "fal fa-box-usd",
    level: "group_goods",
    subDepartment: [
      {
        slug: "gp-g", // Note, this is not slug is job position code
        grade: "manager",
        label: trans("Manager")
        // number_employees: props.options.positions?.data?.find(position => position.slug == 'gp-g')?.number_employees || 0,
      }
    ]
    // value: null
  }
};

Object.keys(props.form[props.fieldName].organisations).forEach(key => {
  console.log("key", key);
  if (Array.isArray(props.form[props.fieldName].organisations[key]) && props.form[props.fieldName].organisations[key].length === 0) {
    props.form[props.fieldName].organisations[key] = {};
  }
});

const isRadioChecked = (subDepartmentSlug: string) => {
  return props.form[props.fieldName]?.group?.includes(subDepartmentSlug);
};
const onClickButtonGroup = (department: string, subDepartmentSlug: string) => {
  // ('mrk', 'mrk-c', ['shops', 'fulfilment'])
  const index = props.form[props.fieldName].group.indexOf(department);
  if (index !== -1) {
    props.form[props.fieldName].group.splice(index, 1);
  }

  // If click on the active subDepartment, then unselect it
  if (props.form?.[props.fieldName]?.group?.includes(subDepartmentSlug)) {
    // delete props.form[props.fieldName].group[subDepartmentSlug]
    const index = props.form[props.fieldName].group.indexOf(subDepartmentSlug);
    if (index !== -1) {
      props.form[props.fieldName].group.splice(index, 1);
    }
  } else {
    // for (const key in props.form[props.fieldName].group) {
    //     // key == wah-m || mrk-c || hr-c
    //     // Check if the 'wah-m' contain the substring 'wah'
    //     if (key.includes(department)) {
    //         // If the selected radio is not same group ('manager' group or 'clerk' group)
    //         if (optionsJob[department].subDepartment.find(sub => sub.slug == key)?.grade != optionsJob[department].subDepartment.find(sub => sub.slug == subDepartmentSlug)?.grade) {
    //             // Delete mrk-c
    //             delete props.form[props.fieldName].group[key]
    //         }
    //     }
    // }
    props.form[props.fieldName].group.push(subDepartmentSlug);
    // set(props.form, [props.fieldName, 'group', subDepartmentSlug], true)
  }

  if (props.form?.errors?.[props.fieldName]) {
    props.form.errors[props.fieldName] = "";
  }
};
const submitGroupPermissions = () => {
  console.log("Submit Group:", route(props.fieldData.updatePseudoJobPositionsRoute.name, props.fieldData.updatePseudoJobPositionsRoute.parameters));
  props.form
    .transform((data) => ({
      permissions: data[props.fieldName].group
    }))
    [props.fieldData.updatePseudoJobPositionsRoute.method](route(props.fieldData.updatePseudoJobPositionsRoute.name, props.fieldData.updatePseudoJobPositionsRoute.parameters), { preserveScroll: true });
//     .submit(
//         props.fieldData.updatePseudoJobPositionsRoute.method || 'patch',
// ,
//         // {
//         //     preserveScroll: true,
//         //     onSuccess: () => notify({
//         //         title: 'Success',
//         //         text: trans('Successfully update the permissions'),
//         //         type: 'success',
//         //     }),
//         //     onError: () => notify({
//         //         title: 'Something went wrong',
//         //         text: trans('Failed to update the permissions'),
//         //         type: 'error',
//         //     })
//         // }
//     )

  // console.log('onStart')
  // router[props.fieldData.updatePseudoJobPositionsRoute.method || 'patch'](
  //     route(props.fieldData.updatePseudoJobPositionsRoute.name, props.fieldData.updatePseudoJobPositionsRoute.parameters),
  //     {
  //         permissions: props.form[props.fieldName].group
  //     },
  //     {
  //         preserveScroll: true,
  //         onStart: () => {
  //             console.log('onStart')
  //         },
  //         onSuccess: () => notify({
  //             title: 'Success',
  //             text: 'cccc',
  //             type: 'success',
  //         }),
  //     }
  // )

  // [props.fieldData.updatePseudoJobPositionsRoute.method]()
};


const organisation = [
  {
    label: "Ancient Wisdom",
    slug: "aw"
  },
  {
    label: "Ancient Wisdom SRO",
    slug: "sk"
  },
  {
    label: "Aromatics",
    slug: "aroma"
  },
  {
    label: "AW Spain",
    slug: "es"
  }
];
// const selectedOrganisation = ref<typeof organisation[number] | null>(organisation[0])
const selectedOrganisation = ref<typeof organisation[number] | null>(null);

const organisationPositionCounts = ref({});
// watch(props.form, (newValue) => {
//     console.log('vcxvcxvcxvcxvcxvcxvcx')
//     organisationPositionCounts.value = {...newValue}.map((org) => {
//         return {
//             [org]: Object.keys(org).length
//         }
//     })
// }, { deep: true })
</script>

<template>
  <div class="flex flex-col gap-y-6">
    <div class="flex gap-x-2">
      <Fieldset legend="Group permissions" class="w-full max-w-4xl">
        <div>
          <template v-for="(jobGroup, departmentName, idxJobGroup) in groupPositionList" :key="departmentName + idxJobGroup">
            <div class="grid grid-cols-3 gap-x-1.5 px-2 items-center even:bg-gray-100 transition-all duration-200 ease-in-out">
              <!-- Section: Department label -->
              <div class="flex items-center capitalize gap-x-1.5">
                <FontAwesomeIcon v-if="jobGroup.icon" :icon="jobGroup.icon" class="text-gray-400 fixed-width" aria-hidden="true" />
                {{ jobGroup.department }}
              </div>
              <!-- Section: Radio (the clickable area) -->
              <div class="h-full col-span-2 flex-col transition-all duration-200 ease-in-out">
                <div class="flex items-center divide-x divide-slate-300">
                  <!-- Button: Radio position -->
                  <div class="pl-2 flex items-center gap-x-4">
                    <template v-for="subDepartment, idxSubDepartment in jobGroup.subDepartment">
                      <!-- If subDepartment is have atleast 1 Fulfilment, or have atleast 1 Shop, or have atleast 1 Warehouse, or have atleast 1 Production, or is a simple sub department (i.e buyer, administrator, etc) -->
                      <button
                        @click.prevent="onClickButtonGroup(departmentName, subDepartment.slug)"
                        class="group h-full cursor-pointer flex items-center justify-start rounded-md py-3 px-3 font-medium capitalize disabled:text-gray-400 disabled:cursor-not-allowed disabled:ring-0 disabled:active:active:ring-offset-0"
                        :class="(isRadioChecked('org-admin') && subDepartment.slug != 'org-admin') || (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin') ? 'text-green-500' : ''"
                        :disabled="(isRadioChecked('group-admin') && subDepartment.slug != 'group-admin')
                                                    ? true
                                                    : false"
                      >
                        <!-- {{ (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin') }} -->
                        <div class="relative text-left">
                          <div class="absolute -left-1 -translate-x-full top-1/2 -translate-y-1/2">
                            <template
                              v-if="(isRadioChecked('org-admin') && subDepartment.slug != 'org-admin') || (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin') || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDepartment.slug !== 'shop-admin')">
                              <FontAwesomeIcon v-if="idxSubDepartment === 0" icon="fas fa-check-circle" class="" fixed-width aria-hidden="true" />
                              <FontAwesomeIcon v-else icon="fal fa-circle" class="" fixed-width aria-hidden="true" />
                            </template>
                            <template v-else-if="form[fieldName].group.includes(subDepartment.slug)">
                              <FontAwesomeIcon icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                            </template>
                            <FontAwesomeIcon v-else icon="fal fa-circle" fixed-width aria-hidden="true" class="text-gray-400 hover:text-gray-700" />
                          </div>
                          <span :class="[
                                                        (isRadioChecked('org-admin') && subDepartment.slug != 'org-admin') || (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin') || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDepartment.slug !== 'shop-admin') ? 'text-gray-400' : 'text-gray-600 group-hover:text-gray-700'
                                                    ]">
                                                        {{ subDepartment.label }}
                                                    </span>
                        </div>
                      </button>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </Fieldset>

      <div class="mt-4">
        <div @click="submitGroupPermissions" class="h-9 align-bottom text-center cursor-pointer" :disabled="form.processing || !form.isDirty">
          <template v-if="form.isDirty">
            <FontAwesomeIcon v-if="form.processing" icon="fad fa-spinner-third" class="text-2xl animate-spin" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
          </template>
          <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
        </div>
      </div>

      <!-- <Button full label="Save group permissions" icon="fal fa-save" class="mt-4" :disabled="!form.isDirty || form.processing" :loading="form.processing" /> -->
    </div>

    <!-- Section: Organisations position -->
    <div class="grid max-w-4xl">
      <div class="flex justify-between px-2 border-b border-gray-300 py-2 mb-2">
        <div>
          {{ trans("Organisations") }}
        </div>
        <div>
          {{ trans("Access") }}
        </div>
      </div>

      <div v-for="(organisation, idxOrganisation) in props.fieldData.organisation_list.data"
           class="border-l-[3px] pl-2 flex flex-col mb-1 gap-y-1"
           :class="selectedOrganisation?.slug == organisation.slug ? 'border-indigo-500' : 'border-gray-300'"
      >
        <div
          @click="selectedOrganisation?.slug == organisation.slug ? selectedOrganisation = null : selectedOrganisation = organisation"
          class="rounded cursor-pointer py-1 px-2 flex justify-between items-center"
          :class="organisation.slug === selectedOrganisation?.slug ? 'bg-indigo-100 text-indigo-500' : 'hover:bg-gray-200/70 '"
        >
          <div class="">{{ organisation.name }}
            <FontAwesomeIcon v-if="fieldData.current_organisation?.slug === organisation.slug" v-tooltip="trans('Employee in this company')" icon="fas fa-star" class="opacity-50 text-xxs" fixed-width
                             aria-hidden="true" />
          </div>
          <div v-tooltip="trans('Number job positions')" class="pl-3 pr-2 tabular-nums">
            <transition name="spin-to-right"><span :key="organisationPositionCounts[organisation.slug]">{{ organisationPositionCounts[organisation.slug] }}</span></transition>
            /{{ organisation.number_job_positions }}
          </div>
        </div>

        <Collapse as="section" :when="organisation.slug == selectedOrganisation?.slug">
          <!-- {{ form[fieldName] }} -->
          <div v-if="options?.[organisation.slug]" class="rounded-md mb-2">
            <EmployeePosition
              :key="'employeePosition' + organisation.slug "
              :form="form[fieldName]"
              :fieldData
              :fieldName="organisation.slug"
              :options="options?.[organisation.slug]"
              saveButton
              :isGroupAdminSelected="isRadioChecked('group-admin')"
              :organisationId="organisation.id"
              @countPosition="(count: number) => set(organisationPositionCounts, organisation.slug, count)"
            />
          </div>
          <div v-else class="text-center border border-gray-300 rounded-md mb-2">
            No data positions
          </div>
        </Collapse>
      </div>
    </div>


    <!-- <pre>{{ form }}</pre> -->
    <!-- <div class="">
        <nav class="isolate flex divide-x divide-gray-200 rounded-lg shadow" aria-label="Tabs">
            <div v-for="(org, idxOrg) in organisation" :key="idxOrg" @click="selectedOrganisation = org" :class="[
                    selectedOrganisation?.slug === org.slug ? 'text-gray-900' : 'text-gray-500 hover:text-gray-700',
                    idxOrg === 0 ? 'rounded-l-lg' : '',
                    idxOrg === idxOrg - 1 ? 'rounded-r-lg' : ''
                ]"
                class="cursor-pointer relative min-w-0 flex-1 overflow-hidden bg-white px-4 py-4 text-center text-sm font-medium hover:bg-gray-50 focus:z-10">
                <span>{{ org.label }}</span>
                <span aria-hidden="true"
                    :class="[selectedOrganisation?.slug === org.slug ? 'bg-indigo-500' : 'bg-transparent', 'absolute inset-x-0 bottom-0 h-0.5']" />
            </div>
        </nav>
    </div> -->
    <!-- {{ selectedOrganisation?.slug }} --- {{ form[fieldName][selectedOrganisation?.slug] }} -->


  </div>
</template>
