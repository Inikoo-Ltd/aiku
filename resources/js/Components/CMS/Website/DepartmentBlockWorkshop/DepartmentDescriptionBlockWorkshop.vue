<script setup lang="ts">
import { faCube, faLink } from "@fal";
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop, faInfoCircle, faDotCircle } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { ref, provide, toRaw, watch, onMounted } from "vue";
import SideMenuWebsiteWorkshop from "@/Components/SideMenuWebsiteWorkshop.vue";
import { getComponent } from "@/Composables/getWorkshopComponents";
import { router } from "@inertiajs/vue3";
import { notify } from "@kyvg/vue3-notification";
import { routeType } from "@/types/route";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Drawer from 'primevue/drawer';
import Button from "@/Components/Elements/Buttons/Button.vue";
import ScreenView from "@/Components/ScreenView.vue";
import { setColorStyleRootByEl } from "@/Composables/useApp"
import { trans } from "laravel-vue-i18n";
import axios from "axios";
import { set } from "lodash";

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop);

const props = defineProps<{
  tab: string
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    department: any[];
    update_department_route: routeType;
  };
}>();


interface LayoutTheme {
  color: string[]
  layout: string
  fontFamily: string
}


const emit = defineEmits<{
  'update:layout': [layout: LayoutTheme]
}>()

const rootRef = ref<HTMLElement | null>(null)
const layoutState = ref(JSON.parse(JSON.stringify(props.data.layout)));
const isLoadingSave = ref(false);
const visibleDrawer = ref(false);
const currentView = ref("desktop");
const iframeClass = ref("w-full h-full");
const loading = ref(false);

const dataPicked = ref({
  sub_departments: null,
  department: []
});

provide("currentView", currentView);

const createSnapshot = () => {
  const raw = toRaw(layoutState.value);
  const snapshot = JSON.parse(JSON.stringify(raw));

  if (snapshot.data?.fieldValue) {
    delete snapshot.data.fieldValue.department;
    delete snapshot.data.fieldValue.sub_departments;
  }

  return { [raw.name] : snapshot};
};

const autosave = () => {
  const payload = createSnapshot();
  router.patch(
    route(
      props.data.autosaveRoute.name,
      props.data.autosaveRoute.parameters
    ),
    { layout: payload },
    {
      onStart: () => { isLoadingSave.value = true },
      onFinish: () => { isLoadingSave.value = false },
      onError: (errors) => {
        notify({
          title: "Autosave Failed",
          text: errors?.message || "Unknown error occurred.",
          type: "error",
        });
      },
    }
  );
};


function debounce(fn: Function, delay = 800) {
  let timer: any;
  return (...args: any[]) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

const debouncedAutosave = debounce(autosave);


const onPickTemplate = (template: any) => {
  layoutState.value = JSON.parse(JSON.stringify({
    ...template,
    data: {
      ...template.data,
      fieldValue: {
        container: { properties: null },
        ...(template.data?.fieldValue || {})
      }
    }
  }));
  autosave();
};


async function selectProductCategory(subDepartment: any) {
  loading.value = true;

  const resetDepartmentState = () => {
    set(dataPicked.value, 'department', null);
    set(dataPicked.value, 'sub_departments', []);
  };

  try {
    const { data } = await axios.get(
      route(
        props.data.route_get_list?.name,
        {
          ...props.data.route_get_list?.parameters,
          department: subDepartment.slug
        }
      )
    );

    Object.assign(dataPicked.value, {
      department: subDepartment,
      sub_departments: data?.data ?? [],
    });

    visibleDrawer.value = false;

  } catch (error) {
    console.error('Error fetching sub-parent_product_category:', error);

    resetDepartmentState();

    notify({
      title: 'Error',
      text: 'Failed to fetch sub-parent_product_category. Please try again.',
      type: 'error',
    });
  } finally {
    loading.value = false;
  }
}



watch(currentView, (view) => {
  switch (view) {
    case "mobile":
      iframeClass.value = "w-[375px] h-[667px] mx-auto";
      break;
    case "tablet":
      iframeClass.value = "w-[768px] h-[1024px] mx-auto";
      break;
    default:
      iframeClass.value = "w-full h-full";
  }
});


onMounted(() => {
  if (rootRef.value && props.layout_theme?.color) {
    setColorStyleRootByEl(rootRef.value, props.layout_theme.color)
  }
  if (props?.data?.department?.data.length) {
    const initialDept = props.data.department.data[0];
    selectProductCategory(initialDept);
  }
})


</script>


<template>
  <div class="pt-4">
    <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
      <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
        <SideMenuWebsiteWorkshop :data="layoutState" :webBlockTypes="props.data.web_block_types"
          :dataList="props.data.department" @auto-save="debouncedAutosave" @set-up-template="onPickTemplate" />
      </div>

      <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
        <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
          <div class="py-1 px-2 cursor-pointer lg:block hidden" v-tooltip="'Desktop view'">
            <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
          </div>
          <div class="text-sm text-gray-600 italic mr-3 cursor-pointer" @click="visibleDrawer = true">
            <span v-if="layoutState?.data?.fieldValue?.department?.name">
              {{ trans('Preview') }}: <strong>{{ layoutState?.data?.fieldValue?.department?.name }}</strong>
            </span>
            <span v-else>{{ trans('Pick Catalouge') }}</span>
          </div>
        </div>
        <div v-if="props.data.layout?.code" ref="rootRef" :class="['p-4', iframeClass]">
          <component class="flex-1 overflow-auto active-block"
            :is="getComponent(props.data.layout.code, { shop_type: layout?.shopState?.type })" :screenType="currentView"
            :routeEditSubDepartment="props.data.update_route" :modelValue="{
              ...layoutState?.data?.fieldValue,
              sub_departments: dataPicked.sub_departments,
              department: dataPicked.department,
            }" />
        </div>
        <div v-else
          class="flex flex-col items-center justify-center gap-3 text-center text-gray-500 flex-1 min-h-[300px]"
          style="height: 100%;">
          <div class="flex flex-col items-center gap-2">
            <FontAwesomeIcon :icon="faInfoCircle" class="text-4xl" />
            <h3 class="text-lg font-semibold">{{ trans('No department selected') }}</h3>
            <p class="text-sm max-w-xs">
              {{ trans('Please pick a department to preview its data here.') }}
            </p>
          </div>
          <Button :label="trans('Pick a catalouge as a data preview')" @click="visibleDrawer = true" />
        </div>
      </div>

    </div>
  </div>

  <Drawer v-model:visible="visibleDrawer" position="right" :pt="{ root: { style: 'width: 30vw' } }">
    <template #header>
      <div>
        <h2 class="text-base font-semibold">{{ trans('Department Overview') }}</h2>
        <p class="text-xs text-gray-500">{{ trans('Choose a department to preview') }}</p>
      </div>
    </template>

    <div class="mx-auto">
      <ul class="space-y-3">
        <li v-for="(dept, index) in props.data.department.data" :key="dept.slug"
          @click="() => selectProductCategory(dept)"
          class="border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow" :class="[
            'rounded-lg shadow-sm transition-shadow',
            dept.slug == dataPicked.department?.slug
              ? 'border border-blue-500 ring-2 ring-blue-300 shadow-md'
              : 'border border-gray-200 hover:shadow-md hover:border-gray-300'
          ]">
          <div class="flex items-center justify-between px-4 py-3 cursor-pointer group hover:bg-gray-50 rounded-t-lg">
            <div class="flex items-center gap-3 text-gray-800 font-medium">
              <FontAwesomeIcon :icon="faDotCircle" class="w-4 h-4" :class="dept?.slug == dataPicked?.department?.slug
                ? 'text-blue-500'
                : 'text-gray-400'
                " />

              <span class="group-hover:underline">
                {{ dept.name }}
              </span>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </Drawer>

</template>

<style scoped></style>
