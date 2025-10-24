<script setup lang="ts">
import { getIrisComponent } from "@/Composables/getIrisComponents";
import { routeType } from "@/types/route";
import { inject, provide, computed, ref } from "vue";
import { notify } from "@kyvg/vue3-notification";
import { trans } from "laravel-vue-i18n";
import axios from "axios";
import MobileHeader from "@/Components/CMS/Website/Headers/MobileHeader.vue";
import { getStyles } from "@/Composables/styles";
import { set } from "lodash"
import { router } from "@inertiajs/vue3"

const props = defineProps<{
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
  screenType?: "mobile" | "tablet" | "desktop"
}>();


const layout = inject("layout", {});
const isLoggedIn = computed(() => {
  return layout.iris?.is_logged_in;
})
provide("isPreviewLoggedIn", isLoggedIn);

const isLoadingLogout = ref(false)
const onClickLogout = () => {
    router.post(
        '/app/logout',
        {
            
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingLogout.value = true
            },
            onSuccess: () => {
                set(layout, ['iris', 'is_logged_in'], false)
                if (typeof window !== "undefined") {
                    let storageIris = JSON.parse(localStorage.getItem('iris') || '{}')  // Get layout from localStorage
                    localStorage.setItem('iris', JSON.stringify({
                        ...storageIris,
                        is_logged_in: false
                    }))
                }
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to logout"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingLogout.value = false
            },
        }
    )
}


provide("onLogout", onClickLogout);

</script>

<template>
  <!-- Section: Topbar (login, logout) -->
  <component v-if="data?.topBar?.data.fieldValue" :is="getIrisComponent(data?.topBar.code)"
             :fieldValue="data.topBar.data.fieldValue" v-model="data.topBar.data.fieldValue" />

  <!-- Section: Header (logo, search, Title) -->
  <component v-if="data?.header" :is="getIrisComponent(data?.header?.code)" :fieldValue="data.header.data.fieldValue"
             class="hidden md:block" />

  <!-- Section: Menu desktop -->
  <component v-if="menu?.code" :is="getIrisComponent(menu?.code)" :fieldValue="menu?.data?.fieldValue"
             :colorThemed="colorThemed" class="hidden md:block" />

  <!-- Section: Mobile Header -->
  <div :style="getStyles(data.header.data.fieldValue.container.properties, screenType)">
      <MobileHeader :header-data="data.header.data.fieldValue" :menu-data="menu?.data?.fieldValue" :productCategories="menu.product_categories" :screenType="screenType" />
  </div>

</template>