<script setup lang="ts">
import { getIrisComponent } from "@/Composables/getIrisComponents";
import { routeType } from "@/types/route";
import { ref, inject, provide, computed } from "vue";
import { notify } from "@kyvg/vue3-notification";
import { trans } from "laravel-vue-i18n";
import axios from "axios";
import MobileHeader from "@/Components/CMS/Website/Headers/MobileHeader.vue";
import { getStyles } from "@/Composables/styles";

defineProps<{
  data: {
    key: string,
    data: object,
    blueprint: object
    loginRoute?: routeType
  }
  menu: {
    key: string,
    data: object,
    blueprint: object
  }
  colorThemed: object
  screenType: "mobile" | "tablet" | "desktop"
}>();


const layout = inject("layout", {});
const isLoggedIn = computed(() => {
  return layout.iris?.user_auth ? true : false;
})
provide("isPreviewLoggedIn", isLoggedIn);

const onLogoutAuth = async () => {
  try {
    await axios.post(route("iris.logout"));
    window.location.reload();
  } catch {
    console.error("error onLogoutAuth");
    notify({
      title: trans("Something went wrong"),
      text: trans("Failed to logout"),
      type: "error"
    });
  }
};


provide("onLogout", onLogoutAuth);

</script>

<template>
  <!-- Section: Topbar -->
  <component v-if="data?.topBar?.data.fieldValue" :is="getIrisComponent(data?.topBar.code)"
             :fieldValue="data.topBar.data.fieldValue" v-model="data.topBar.data.fieldValue" class="hidden md:block" />

  <!-- Section: Header -->
  <component :is="getIrisComponent(data?.header?.code)" :fieldValue="data.header.data.fieldValue"
             class="hidden md:block" />

  <!-- Section: Menu desktop -->
  <component v-if="menu?.code" :is="getIrisComponent(menu?.code)" :navigations="menu.data.fieldValue.navigation"
             :colorThemed="colorThemed" class="hidden md:block" />

  <div :style="getStyles(data.header.data.fieldValue.container.properties, screenType)">
      <MobileHeader :header-data="data.header.data.fieldValue" :menu-data="menu?.data?.fieldValue?.navigation" :screenType="screenType" />
  </div>

</template>