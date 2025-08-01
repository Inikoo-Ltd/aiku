<!--
  Author: Raul Perusquia <raul@inikoo.com>
  Created: Wed, 07 Jun 2023
-->

<script setup lang="ts">
import {
  ref, onMounted, provide, watch, computed, inject,
  IframeHTMLAttributes
} from "vue";
import axios from "axios";
import { Head } from "@inertiajs/vue3";

// Utils
import { capitalize } from "@/Composables/capitalize";
import { notify } from "@kyvg/vue3-notification";
import { trans } from "laravel-vue-i18n";
import { useConfirm } from "primevue/useconfirm";
import { useLiveUsers } from "@/Stores/active-users";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import { setIframeView } from "@/Composables/Workshop";

// Components
import PageHeading from "@/Components/Headings/PageHeading.vue";
import Publish from "@/Components/Publish.vue";
import ScreenView from "@/Components/ScreenView.vue";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import ConfirmDialog from 'primevue/confirmdialog';
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue";
import Blueprint from "@/Components/CMS/Webpage/Blog/Blueprint";

// FontAwesome
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
  faExclamationTriangle, faBrowser, faDraftingCompass, faRectangleWide,
  faStars, faTimes, faBars, faExternalLink, faExpandWide, faCompressWide,
  faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower, faSkull,
  faEye,
} from "@fal";

library.add(
  faBrowser, faDraftingCompass, faRectangleWide, faTimes, faStars,
  faBars, faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower,
  faSkull, faEye
);

// Types
import { Root as WebBlockTypes } from "@/types/webBlockTypes";
import { Root as WebpageType } from "@/types/webpageTypes";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { routeType } from "@/types/route";

// Props
const props = defineProps<{
  title: string,
  pageHead: PageHeadingTypes,
  webpage: WebpageType,
  webBlockTypes: WebBlockTypes,
  url: string
}>();

// Provide / Inject
const layout = inject('layout', layoutStructure);
provide('isInWorkshop', true);

// State
const data = ref(props.webpage);
const iframeClass = ref("w-full h-full");
const isIframeLoading = ref(true);
const _iframe = ref<IframeHTMLAttributes | null>(null);
const currentView = ref('desktop');
const fullScreen = ref(false);
const comment = ref("");
const isSavingBlock = ref(false);
const isLoadingPublish = ref(false);
const openedBlockSideEditor = ref<number | null>(null);
const openedChildSideEditor = ref<number | null>(null);
const isAddBlockLoading = ref<string | null>(null);
const isLoadingBlock = ref<string | null>(null);
const isLoadingDeleteBlock = ref<number | null>(null);
const cancelTokens = ref<Record<string, Function>>({});
const debounceTimers = ref({});
const filterBlock = ref('all');

// Provide global state
provide('currentView', currentView);
provide('openedBlockSideEditor', openedBlockSideEditor);
provide('openedChildSideEditor', openedChildSideEditor);
provide('isAddBlockLoading', isAddBlockLoading);
provide('isLoadingBlock', isLoadingBlock);
provide('isLoadingDeleteBlock', isLoadingDeleteBlock);
provide('filterBlock', filterBlock);

// Utils
const sendToIframe = (data: any) => {
  _iframe.value?.contentWindow.postMessage(data, '*');
};

const debounceSaveWorkshop = (block) => {
  if (debounceTimers.value[block.id]) clearTimeout(debounceTimers.value[block.id]);

  debounceTimers.value[block.id] = setTimeout(async () => {
    const url = route(props.webpage.update_model_has_web_blocks_route.name, { modelHasWebBlocks: block.id });
    isLoadingBlock.value = block.id;
    isSavingBlock.value = true;
    const source = axios.CancelToken.source();
    cancelTokens.value[block.id] = source.cancel;

    try {
      await axios.patch(
        url,
        {
          layout: block.web_block.layout,
          show_logged_in: block.visibility.in,
          show_logged_out: block.visibility.out,
          show: block.show,
        },
        {
          cancelToken: source.token,
          headers: { "X-Requested-With": "XMLHttpRequest" },
        }
      );
      sendToIframe({ key: "reload", value: {} });
    } catch (error) {
      if (!axios.isCancel(error)) {
        notify({
          title: trans("Something went wrong"),
          text: error?.response?.data?.message || error.message,
          type: "error",
        });
      }
    } finally {
      isLoadingBlock.value = null;
      isSavingBlock.value = false;
      delete cancelTokens.value[block.id];
    }
  }, 1500);
};

const onSaveWorkshop = (block, sendValue = true) => {
  if (cancelTokens.value[block.id]) cancelTokens.value[block.id]();
  if (sendValue) {
    sendToIframe({
      key: 'setWebpage',
      value: JSON.parse(JSON.stringify(data.value))
    });
  }
  debounceSaveWorkshop(block);
};

const onSaveWorkshopFromId = (blockId, from?) => {
  if (!blockId) return;
  if (cancelTokens.value[blockId]) cancelTokens.value[blockId]();
  const block = data.value.layout.web_blocks.find(b => b.id === blockId);
  sendToIframe({
    key: 'setWebpage',
    value: JSON.parse(JSON.stringify(data.value))
  });
  if (block) debounceSaveWorkshop(block);
};

provide('onSaveWorkshop', onSaveWorkshop);
provide('onSaveWorkshopFromId', onSaveWorkshopFromId);

// Publish Actions
const onPublish = async (action: routeType, popover) => {
  try {
    if (!action?.method || !action?.name || !action?.parameters)
      throw new Error("Invalid action parameters");

    isLoadingPublish.value = true;

    const response = await axios[action.method](
      route(action.name, action.parameters),
      {
        comment: comment.value,
        publishLayout: { blocks: data.value.layout }
      }
    );

    if (response.status === 200) {
      comment.value = "";
      notify({
        title: trans("Published!"),
        text: trans("Webpage data has been published successfully"),
        type: "success"
      });
    }
    popover.close();
  } catch (error) {
    notify({
      title: trans("Something went wrong"),
      text: error?.response?.data?.message || error.message || "Unknown error occurred",
      type: "error"
    });
  } finally {
    isLoadingPublish.value = false;
  }
};

const beforePublish = (route, popover) => {
  onPublish(route, popover);
};


const iframeSrc = route("grp.websites.webpage.preview", [
  route().params["website"],
  route().params["webpage"],
  {
    organisation: route().params["organisation"],
    shop: route().params["shop"],
    fulfilment: route().params["fulfilment"]
  }
]);

const previewSrc = route("grp.websites.preview", [
  route().params["website"],
  route().params["webpage"],
  {
    organisation: route().params["organisation"],
    shop: route().params["shop"],
    fulfilment: route().params["fulfilment"]
  }
]);

const openFullScreenPreview = () => {
  const url = new URL(previewSrc, window.location.origin);
  url.searchParams.set('isInWorkshop', 'true');
  url.searchParams.set('mode', 'iris');
  window.open(url.toString(), '_blank');
};

const openWebsite = () => window.open(props.url, '_blank');

const compUsersEditThisPage = computed(() => {
  return useLiveUsers().liveUsersArray.filter(user =>
    user.current_page?.route_name === layout.currentRoute &&
    user.current_page?.route_params?.webpage === layout.currentParams?.webpage
  ).map(user => user.name ?? user.username);
});

// Events
onMounted(() => {
  window.addEventListener("message", (event) => {
    if (event.origin !== window.location.origin) return;
    const { key, value } = event.data;
    if (key === 'autosave') onSaveWorkshop(value, false);
  });
});

watch(currentView, (newVal) => {
  iframeClass.value = setIframeView(newVal);
});



</script>

<template>

  <Head :title="capitalize(title)" />

  <PageHeading :data="pageHead">
    <template #button-publish="{ action }">
      <Publish :isLoading="isLoadingPublish" :is_dirty="data.is_dirty" v-model="comment"
        @onPublish="(popover) => beforePublish(action.route, popover)" />
    </template>
    <template #afterTitle v-if="isSavingBlock">
      <LoadingIcon v-tooltip="trans('Saving..')" />
    </template>
    <template #other>
      <button class="flex items-center gap-1 px-3 py-1 text-sm text-blue-600 hover:underline"
        v-tooltip="trans('Go to website')" @click="openWebsite">
        <FontAwesomeIcon :icon="faExternalLink" size="lg" />
        <span>{{ trans('Open Site') }}</span>
      </button>
    </template>
  </PageHeading>

  <ConfirmDialog group="alert-publish">
    <template #icon>
      <FontAwesomeIcon :icon="faExclamationTriangle" class="text-orange-500" />
    </template>
  </ConfirmDialog>

  <div class="flex h-[calc(100vh-5rem)] bg-gray-100">
    <!-- Sidebar -->
    <aside v-if="!fullScreen" class="hidden lg:flex lg:flex-col w-[380px] bg-white border-r p-4 shadow-sm space-y-4">
      <h2 class="text-lg font-bold text-gray-700 border-b pb-2">{{ trans('Blog Settings') }}</h2>
      <div class="h-[calc(100vh-4rem)] bg-gray-100 overflow-auto">
        <SideEditor v-model="data.layout.web_blocks[0].web_block.layout.data.fieldValue" :panelOpen="openedChildSideEditor"
          :blueprint="Blueprint.blueprint"
          :uploadImageRoute="{ ...webpage.images_upload_route, parameters: { modelHasWebBlocks: webpage.layout.web_blocks[0].id } }"
          @update:modelValue="() => onSaveWorkshop(webpage.layout.web_blocks[0])" />

      </div>

    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
      <div class="flex items-center justify-between bg-white border-b px-4 py-2 shadow-sm">
        <div class="flex items-center gap-3 text-gray-600">
          <ScreenView v-model="currentView" @screenView="(e) => (currentView = e)" />
          <FontAwesomeIcon :icon="faEye" fixed-width class="cursor-pointer hover:text-blue-600"
            v-tooltip="trans('Open preview in new tab')" @click="openFullScreenPreview" />
          <FontAwesomeIcon :icon="!fullScreen ? faExpandWide : faCompressWide" fixed-width
            class="cursor-pointer hover:text-blue-600" v-tooltip="'Full screen'" @click="fullScreen = !fullScreen" />
        </div>
        <div v-if="compUsersEditThisPage.length > 1"
          class="flex items-center gap-2 px-3 py-1 rounded bg-yellow-100 text-yellow-800 border border-yellow-300">
          <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width />
          <span>{{ compUsersEditThisPage.length }} {{ trans("users edit this page.") }}</span>
          <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width />
        </div>
      </div>

      <div class="relative border-2 h-full w-full bg-white overflow-auto">
        <div v-if="isIframeLoading" class="absolute inset-0 flex items-center justify-center bg-white">
          <LoadingIcon class="w-24 h-24 text-6xl" />
        </div>
        <iframe ref="_iframe" :src="iframeSrc" :title="props.title"
          :class="[iframeClass, isIframeLoading ? 'hidden' : '']" @load="isIframeLoading = false" allowfullscreen />
      </div>
    </main>
  </div>
</template>

<style lang="scss" scoped>
:deep(.component-iseditable) {
  @apply border border-transparent border-dashed cursor-pointer;
}

:deep(.loading-overlay) {
  position: absolute;
  inset: 0;
  @apply flex items-center justify-center bg-white bg-opacity-80 z-[999];
}

:deep(.spinner) {
  border: 4px solid rgba(255, 255, 255, 0.3);
  border-top: 4px solid #3498db;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }

  100% {
    transform: rotate(360deg);
  }
}
</style>
