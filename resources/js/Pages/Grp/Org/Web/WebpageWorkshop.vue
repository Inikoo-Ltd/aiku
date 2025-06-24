<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {
  ref, onMounted, provide, watch, computed, inject,
  IframeHTMLAttributes, toRaw
} from "vue";
import { Head, router } from "@inertiajs/vue3";
import { capitalize } from "@/Composables/capitalize";
import axios from "axios";
import { debounce } from 'lodash-es';
import { notify } from "@kyvg/vue3-notification";
import { trans } from "laravel-vue-i18n";
import { useConfirm } from "primevue/useconfirm";
import { useLiveUsers } from "@/Stores/active-users";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import { setIframeView } from "@/Composables/Workshop";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import Publish from "@/Components/Publish.vue";
import ScreenView from "@/Components/ScreenView.vue";
import WebpageSideEditor from "@/Components/Workshop/WebpageSideEditor.vue";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import ConfirmDialog from 'primevue/confirmdialog';
import ToggleSwitch from 'primevue/toggleswitch';

import { Root, Daum } from "@/types/webBlockTypes";
import { Root as RootWebpage } from "@/types/webpageTypes";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { routeType } from "@/types/route";

import {
  faExclamationTriangle, faBrowser, faDraftingCompass, faRectangleWide,
  faStars, faTimes, faBars, faExternalLink, faExpandWide, faCompressWide,
  faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower, faSkull,
  faEye
} from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(
  faBrowser, faDraftingCompass, faRectangleWide, faTimes, faStars,
  faBars, faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower,
  faSkull, faEye
);

const props = defineProps<{
  title: string,
  pageHead: PageHeadingTypes,
  webpage: RootWebpage,
  webBlockTypes: Root
}>();
console.log('ss', props.webpage)
provide('isInWorkshop', true);
const layout = inject('layout', layoutStructure);
const confirm = useConfirm();

const data = ref(props.webpage);
const iframeClass = ref("w-full h-full");
const isIframeLoading = ref(true);
const isModalBlockList = ref(false);
const _iframe = ref<IframeHTMLAttributes | null>(null);
const currentView = ref('desktop');
const openedBlockSideEditor = ref<number | null>(null);
const openedChildSideEditor = ref<number | null>(null);
const isAddBlockLoading = ref<string | null>(null);
const isLoadingblock = ref<string | null>(null);
const isSavingBlock = ref(false);
const _WebpageSideEditor = ref(null);
const cancelTokens = ref<Record<string, Function>>({});
const debounceTimers = ref({});
const addBlockCancelToken = ref<Function | null>(null);
const orderBlockCancelToken = ref<Function | null>(null);
const deleteBlockCancelToken = ref<Function | null>(null);
const addBlockParentIndex = ref(0);
const isLoadingDeleteBlock = ref<number | null>(null);
const comment = ref("");
const isLoadingPublish = ref(false);
const fullScreeen = ref(false);
const filterBlock = ref('all');

provide('currentView', currentView);
provide('openedBlockSideEditor', openedBlockSideEditor);
provide('openedChildSideEditor', openedChildSideEditor);
provide('isAddBlockLoading', isAddBlockLoading);
provide('isLoadingblock', isLoadingblock);
provide('isLoadingDeleteBlock', isLoadingDeleteBlock);
provide('filterBlock', filterBlock);

// Utility
const sendToIframe = (data: any) => {
  _iframe.value?.contentWindow.postMessage(data, '*');
};

// Block Handlers
const addNewBlock = async ({ block, type }) => {
  if (addBlockCancelToken.value) addBlockCancelToken.value();
  let position  = data.value.layout.web_blocks.length
  if(type) {
    position = type === 'before' ? addBlockParentIndex.value : addBlockParentIndex.value + 1;
  }

  router.post(
    route(props.webpage.add_web_block_route.name, props.webpage.add_web_block_route.parameters),
    { web_block_type_id: block.id, position  : position },
    {
      onStart: () => isAddBlockLoading.value = "addBlock" + block.id,
      onFinish: () => {
        addBlockCancelToken.value = null;
        isAddBlockLoading.value = null;
        addBlockParentIndex.value = 0;
      },
      onCancelToken: token => addBlockCancelToken.value = token.cancel,
      onSuccess: e => {
        data.value = e.props.webpage;
        sendToIframe({ key: 'reload', value: {} });
      },
      onError: error => {
        console.log('sss',error)
        notify({
        title: trans("Something went wrong"),
        text: error.message,
        type: "error"
      })}
    }
  );
};

const duplicateBlock = async (modelHasWebBlock = Number) => {
  router.post(
    route('grp.models.webpage.web_block.duplicate', {
      webpage: data.value.id,
      modelHasWebBlock: modelHasWebBlock
    }),
    {},
    {
      onStart: () => isAddBlockLoading.value = "addBlock" + modelHasWebBlock,
      onFinish: () => {
        addBlockCancelToken.value = null;
        isAddBlockLoading.value = null;
        addBlockParentIndex.value = 0;
      },
      onCancelToken: token => addBlockCancelToken.value = token.cancel,
      onSuccess: e => {
        data.value = e.props.webpage;
        sendToIframe({ key: 'reload', value: {} });
      },
      onError: error => notify({
        title: trans("Something went wrong"),
        text: error.message,
        type: "error"
      })
    }
  );
};


const debounceSaveWorkshop = block => {
  if (debounceTimers.value[block.id]) clearTimeout(debounceTimers.value[block.id]);

  debounceTimers.value[block.id] = setTimeout(() => {
    router.patch(
      route(props.webpage.update_model_has_web_blocks_route.name, { modelHasWebBlocks: block.id }),
      {
        layout: block.web_block.layout,
        show_logged_in: block.visibility.in,
        show_logged_out: block.visibility.out,
        show: block.show
      },
      {
        onStart: () => {
          isLoadingblock.value = block.id;
          isSavingBlock.value = true;
        },
        onCancelToken: token => cancelTokens.value[block.id] = token.cancel,
        onFinish: () => {
          isLoadingblock.value = null;
          isSavingBlock.value = false;
          delete cancelTokens.value[block.id];
        },
        onSuccess: e => {
          data.value = e.props.webpage;
          sendToIframe({ key: 'reload', value: {} });
        },
        onError: error => notify({
          title: trans("Something went wrong"),
          text: error.message,
          type: "error"
        }),
        preserveScroll: true
      }
    );
  }, 1500);
};

const debouncedSaveSiteSettings = debounce(block => {
  router.patch(
    route('grp.models.model_has_web_block.bulk.update'),
    { web_blocks: block },
    {
      onStart: () => isSavingBlock.value = true,
      onFinish: () => isSavingBlock.value = false,
      onSuccess: () => sendToIframe({ key: 'reload', value: {} }),
      onError: error => notify({
        title: trans("Something went wrong"),
        text: error.message,
        type: "error"
      }),
      preserveScroll: true
    }
  );
}, 1500);

const onSaveSiteSettings = block => debouncedSaveSiteSettings(block);

const onSaveWorkshop = block => {
  if (cancelTokens.value[block.id]) cancelTokens.value[block.id]();
  sendToIframe({
    key: 'setWebpage',
    value: JSON.parse(JSON.stringify(data.value))
  });
  debounceSaveWorkshop(block);
};

const onSaveWorkshopFromId = (blockId, from) => {
  if (from) console.log('onSaveWorkshopFromId from:', from);
  if (!blockId) return;
  if (cancelTokens.value[blockId]) cancelTokens.value[blockId]();

  const block = data.value.layout.web_blocks.find(block => block.id === blockId);
  sendToIframe({
    key: 'setWebpage',
    value: JSON.parse(JSON.stringify(data.value))
  });
    if (block) debounceSaveWorkshop(block);
};

provide('onSaveWorkshopFromId', onSaveWorkshopFromId);
provide('onSaveWorkshop', onSaveWorkshop);

const sendOrderBlock = async block => {
  if (orderBlockCancelToken.value) orderBlockCancelToken.value();
  router.post(
    route(props.webpage.reorder_web_blocks_route.name, props.webpage.reorder_web_blocks_route.parameters),
    { positions: block },
    {
      onFinish: () => {
        isLoadingblock.value = null;
        orderBlockCancelToken.value = null;
      },
      onCancelToken: token => orderBlockCancelToken.value = token.cancel,
      onSuccess: e => {
        data.value = e.props.webpage;
        sendToIframe({ key: 'reload', value: {} });
      },
      onError: error => notify({
        title: trans("Something went wrong"),
        text: error.message,
        type: "error"
      })
    }
  );
};

const sendDeleteBlock = async (block: Daum) => {
  if (deleteBlockCancelToken.value) deleteBlockCancelToken.value();
  router.delete(
    route(props.webpage.delete_model_has_web_blocks_route.name, { modelHasWebBlocks: block.id }),
    {
      onStart: () => isLoadingDeleteBlock.value = block.id,
      onFinish: () => {
        isLoadingDeleteBlock.value = null;
        orderBlockCancelToken.value = null;
      },
      onCancelToken: token => deleteBlockCancelToken.value = token.cancel,
      onSuccess: e => {
        data.value = e.props.webpage;
        sendToIframe({ key: 'reload', value: {} });
      },
      onError: error => notify({
        title: trans("Something went wrong"),
        text: error.message,
        type: "error"
      })
    }
  );
};

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
  const validation = JSON.stringify(data.value.layout);
  validation.includes('<h1') || validation.includes('<H1')
    ? onPublish(route, popover)
    : confirmPublish(route, popover);
};

const confirmPublish = (route, popover) => {
  confirm.require({
    message: 'You Dont have title/ h1 in code, are you sure to publish ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    group: "alert-publish",
    rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Publish' },
    accept: () => onPublish(route, popover)
  });
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

const setHideBlock = (block: Daum) => {
  block.show = !block.show;
  onSaveWorkshop(block);
};

const SyncAurora = () => {
  router.patch(
    route(props.webpage.route_webpage_edit.name, props.webpage.route_webpage_edit.parameters),
    { allow_fetch: props.webpage.allow_fetch },
    {
      onStart: () => isSavingBlock.value = true,
      onFinish: () => isSavingBlock.value = false,
      onSuccess: () => sendToIframe({ key: 'reload', value: {} }),
      onError: error => notify({
        title: trans("Something went wrong"),
        text: error.message,
        type: "error"
      }),
      preserveScroll: true
    }
  );
};

onMounted(() => {
  window.addEventListener("message", (event) => {
    if (event.origin !== window.location.origin) return;
    const { key, value } = event.data;

    switch (key) {
      case 'autosave': return onSaveWorkshop(value);
      case 'activeBlock': return openedBlockSideEditor.value = value;
      case 'activeChildBlock': return openedChildSideEditor.value = value;
      case 'addBlock':
        if (_WebpageSideEditor.value) {
          isModalBlockList.value = true;
          addBlockParentIndex.value = value.parentIndex;
          _WebpageSideEditor.value.addType = value.type;
        }
        break;
      case 'deleteBlock': return sendDeleteBlock(value);
    }
  });
});

watch(openedBlockSideEditor, (newValue) => sendToIframe({ key: 'activeBlock', value: newValue }));
watch(currentView, (newValue) => iframeClass.value = setIframeView(newValue));
watch(filterBlock, (newValue) => sendToIframe({ key: 'isPreviewLoggedIn', value: newValue }));

const compUsersEditThisPage = computed(() => {

	return useLiveUsers().liveUsersArray.filter(user => (user.current_page?.route_name === layout.currentRoute && user.current_page?.route_params?.webpage === layout.currentParams?.webpage)).map(user => user.name ?? user.username)
})



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
      <div class="px-2 cursor-pointer" v-tooltip="'go to website'" @click="openWebsite">
        <FontAwesomeIcon :icon="faExternalLink" size="xl" aria-hidden="true" />
      </div>
    </template>
  </PageHeading>

  <ConfirmDialog group="alert-publish" />

  <div class="flex">
    <div v-if="!fullScreeen" class="hidden lg:flex lg:flex-col border-2 bg-gray-200 pl-3 py-1">
      <WebpageSideEditor ref="_WebpageSideEditor" v-model="isModalBlockList" :webpage="data"
        :webBlockTypes="webBlockTypes" @update="onSaveWorkshop" @delete="sendDeleteBlock" @add="addNewBlock"
        @order="sendOrderBlock" @setVisible="setHideBlock" @onSaveSiteSettings="onSaveSiteSettings"
        @onDuplicateBlock="duplicateBlock" />
    </div>

    <!-- Preview Section -->
    <div class="h-[calc(100vh-16vh)] w-full flex flex-col bg-gray-200 overflow-x-auto">
      <div class="flex justify-between items-center px-2 py-1">
        <div class="flex items-center gap-2 text-gray-500">
          <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
          <div v-tooltip="trans('Open preview in new tab')" @click="openFullScreenPreview"
            class="cursor-pointer hover:text-amber-600">
            <FontAwesomeIcon :icon="faEye" fixed-width />
          </div>
          <div v-tooltip="'Full screen'" @click="fullScreeen = !fullScreeen" class="cursor-pointer">
            <FontAwesomeIcon :icon="!fullScreeen ? faExpandWide : faCompressWide" fixed-width />
          </div>
        </div>

        <div v-if="compUsersEditThisPage?.length > 1"
          class="flex items-center gap-2 px-2 bg-yellow-300 text-yellow-700 rounded">
          <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width />
          <span>
            {{ compUsersEditThisPage.length }} {{ trans("users edit this page.") }}
          </span>
          <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width />
        </div>

        <div class="flex items-center gap-2 text-sm text-gray-700">
          <label for="sync-toggle">Sync with aurora</label>
          <ToggleSwitch id="sync-toggle" v-model="props.webpage.allow_fetch"
            @update:modelValue="(e) => SyncAurora(e)" />
        </div>
      </div>

      <div class="relative border-2 h-full w-full bg-white overflow-auto">
        <div v-if="isIframeLoading" class="absolute inset-0 flex items-center justify-center bg-white">
          <LoadingIcon class="w-24 h-24 text-6xl" />
        </div>
        <iframe ref="_iframe" :src="iframeSrc" :title="props.title"
          :class="[iframeClass, isIframeLoading ? 'hidden' : '']" @load="isIframeLoading = false" allowfullscreen />
      </div>
    </div>
  </div>
</template>


<style lang="scss" scoped>
:deep(.component-iseditable) {
  @apply border border-transparent border-dashed cursor-pointer;
}

iframe {
  height: 100%;
  transition: width 0.3s ease;
}

:deep(.loading-overlay) {
  position: block;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.8);
  z-index: 1000;
}

:deep(.spinner) {
  border: 4px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top: 4px solid #3498db;
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
