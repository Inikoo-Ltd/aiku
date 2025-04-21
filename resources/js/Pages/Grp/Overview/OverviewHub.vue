<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faExclamationCircle } from "@fortawesome/free-solid-svg-icons";
import { capitalize } from "@/Composables/capitalize";
import { ref, onMounted, onUnmounted } from "vue";
import {
  faBoothCurtain,
  faBoxes,
  faBoxOpen,
  faBrowser,
  faCoin,
  faDolly,
  faEnvelope,
  faEnvelopeOpenText,
  faExchangeAlt,
  faFilter,
  faForklift,
  faInboxOut,
  faIndustryAlt,
  faInventory,
  faLocationArrow,
  faMailBulk,
  faRoad,
  faTrashAlt,
  faTruckLoading,
  faUser,
  faUserAlien,
  faUserCircle,
  faUserHeadset,
  faUsers,
  faWarehouseAlt,
  faPaperPlane,
  faScrollOld,
  faPhoneVolume,
  faRaygun,
  faFileMinus,
  faEraser
} from "@fal";
import { faAngleDown, faAngleUp } from "@far";
import Dashboard from "@/Components/DataDisplay/Dashboard/Dashboard.vue";

defineProps<{
  title: string
  pageHead: any
  dashboard_stats: {}
}>();


library.add(
  faExclamationCircle,
  faInboxOut,
  faUser,
  faFilter,
  faBoxes,
  faAngleDown,
  faAngleUp,
  faBrowser,
  faTrashAlt,
  faCoin,
  faBoothCurtain,
  faRoad,
  faUserAlien,
  faEnvelopeOpenText,
  faEnvelope,
  faUserCircle,
  faExchangeAlt,
  faDolly,
  faBoxOpen,
  faTruckLoading,
  faForklift,
  faUsers,
  faUserHeadset,
  faWarehouseAlt,
  faInventory,
  faLocationArrow,
  faIndustryAlt,
  faMailBulk,
  faPaperPlane,
  faPhoneVolume,
  faRaygun,
  faScrollOld,
  faUserCircle,
  faFileMinus,
  faEraser
);


const isFilterVisible = ref(false);

const handleClickOutside = (event: Event) => {
  const target = event.target as HTMLElement;
  if (!target.closest(".search-container")) {
    isFilterVisible.value = false;
  }
};

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
});


</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />

  <div class="grid grid-cols-12 gap-4 p-4">
    <div class="col-span-12">
      <Dashboard :dashboard="dashboard_stats" />
    </div>

  </div>
</template>


