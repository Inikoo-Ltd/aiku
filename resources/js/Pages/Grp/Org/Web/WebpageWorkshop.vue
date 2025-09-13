<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {
  ref, onMounted, provide, watch, computed, inject,
  IframeHTMLAttributes ,onUnmounted, onBeforeUnmount 
} from "vue";
import { Head, router } from "@inertiajs/vue3";
import * as Sentry from "@sentry/vue"
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
  faEye,
  faWindWarning,
  faUndo,
  faRedo
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
  url : string
  luigi_tracker_id: string
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
const isLoadingBlock = ref<string | null>(null);
const isSavingBlock = ref(false);
const _WebpageSideEditor = ref(null);
const cancelTokens = ref<Record<string, Function>>({});
const debounceTimers = ref({});
const addBlockCancelToken = ref<Function | null>(null);
const orderBlockCancelToken = ref<Function | null>(null);
const deleteBlockCancelToken = ref<Function | null>(null);
const addBlockParentIndex = ref({ parentIndex: data.value.layout.web_blocks.length ,type: "current" });
const isLoadingDeleteBlock = ref<number | null>(null);
const comment = ref("");
const isLoadingPublish = ref(false);
const fullScreen = ref(false);
const filterBlock = ref('all');
const MAX_HISTORY = 5;
const undoStack = ref<any[]>(JSON.parse(localStorage.getItem('undoStack') || '[]'));
const redoStack = ref<any[]>(JSON.parse(localStorage.getItem('redoStack') || '[]'));

provide('webpage_luigi_tracker_id', props.luigi_tracker_id)
provide('currentView', currentView);
provide('openedBlockSideEditor', openedBlockSideEditor);
provide('openedChildSideEditor', openedChildSideEditor);
provide('isAddBlockLoading', isAddBlockLoading);
provide('isLoadingBlock', isLoadingBlock);
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
  if(type == 'before' ) {
    position =  addBlockParentIndex.value.parentIndex
  }else if(type == 'after'){
    position =  addBlockParentIndex.value.parentIndex + 1;
  }

  //pushToHistory();
  router.post(
    route(props.webpage.add_web_block_route.name, props.webpage.add_web_block_route.parameters),
    { web_block_type_id: block.id, position  : position },
    {
      onStart: () => isAddBlockLoading.value = "addBlock" + block.id,
      onFinish: () => {
        addBlockCancelToken.value = null;
        isAddBlockLoading.value = null;
        addBlockParentIndex.value = { parentIndex: data.value.layout.web_blocks.length ,type: "current" };
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
  //pushToHistory();
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
        addBlockParentIndex.value = { parentIndex: data.value.layout.web_blocks.length ,type: "current" }
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

const debounceSaveWorkshop = (block) => {
  console.log('debounceSaveWorkshop', block);
  // Clear any pending debounce timers for this block
  if (debounceTimers.value[block.id]) {
    clearTimeout(debounceTimers.value[block.id]);
  }

  debounceTimers.value[block.id] = setTimeout(async () => {
    const url = route(props.webpage.update_model_has_web_blocks_route.name, {
      modelHasWebBlocks: block.id,
    });

    // Cancel any previous request for this block
    if (cancelTokens.value[block.id]) {
      cancelTokens.value[block.id](); // call previous cancel function
    }

    // Create a new cancel token
    const source = axios.CancelToken.source();
    cancelTokens.value[block.id] = source.cancel;

    isLoadingBlock.value = block.id;  // This made the state inside in the field will changes (like opened Select will closed)
    isSavingBlock.value = true;  // This made the state inside in the field will changes (like opened Select will closed)
    //pushToHistory();
    try {
    const response =  await axios.patch(
        url,
        {
          layout: block.web_block.layout,
          show_logged_in: block.visibility.in,
          show_logged_out: block.visibility.out,
          show: block.show,
        },
        {
          cancelToken: source.token,
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
        }
      );

      // Reload the preview
      data.value.layout = response.data.data.layout;
      sendToIframe({ key: "reload", value: {} });
    } catch (error) {
      if (axios.isCancel?.(error) || error?.code === "ERR_CANCELED") {
        console.log(error)
        return;
      }

      Sentry.captureException(error);

      if (error?.response?.data?.message) {
        notify({
          title: "Failed to auto save",
          text: error.response.data.message,
          type: "error",
        });
      } else {
        notify({
          title: "Failed to auto save",
          text: error.message,
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


const debouncedSaveSiteSettings = debounce(block => {
  router.patch(
    route('grp.models.model_has_web_block.bulk.update'),
    { web_blocks: block },
    {
      preserveScroll: true,
      preserveState: true,
      onStart: () => isSavingBlock.value = true,
      onFinish: () => isSavingBlock.value = false,
      onSuccess: () => {
        sendToIframe({ key: 'reload', value: {} })
      },
      onError: error => notify({
        title: trans("Something went wrong"),
        text: error.message,
        type: "error"
      }),
    }
  );
}, 1500);

const onSaveSiteSettings = block => debouncedSaveSiteSettings(block);

const onSaveWorkshop = (block, snedChangeValue = true) => {
  if (cancelTokens.value[block.id]) cancelTokens.value[block.id]();
  if (snedChangeValue) {
    sendToIframe({
      key: 'setWebpage',
      value: JSON.parse(JSON.stringify(data.value))
    });
  }
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
  //pushToHistory(); 
  router.post(
    route(props.webpage.reorder_web_blocks_route.name, props.webpage.reorder_web_blocks_route.parameters),
    { positions: block },
    {
      onFinish: () => {
        isLoadingBlock.value = null;
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
  //pushToHistory(); 
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
  if(props.webpage.type == "catalogue") onPublish(route, popover)
  else {
    console.log('validation', validation)
     validation.includes('<h1') || validation.includes('<H1')
    ? onPublish(route, popover)
    : confirmPublish(route, popover);
  }
 
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


/* const saveHistoryToLocalStorage = () => {
  localStorage.setItem('undoStack', JSON.stringify(undoStack.value));
  localStorage.setItem('redoStack', JSON.stringify(redoStack.value));
};

// Push current state to undoStack
const //pushToHistory = () => {
  // Clone current layout state
  const currentState = JSON.parse(JSON.stringify(data.value.layout));

  // Push to undo stack
  undoStack.value.push(currentState);

  // Keep only last MAX_HISTORY
  if (undoStack.value.length > MAX_HISTORY) {
    undoStack.value.shift();
  }

  // Clear redo stack because new change
  redoStack.value = [];

  saveHistoryToLocalStorage();
}; */

/* // Undo
const undo = async () => {
  if (undoStack.value.length === 0) return;

  // Move current state to redo
  redoStack.value.push(JSON.parse(JSON.stringify(data.value.layout)));
  if (redoStack.value.length > MAX_HISTORY) redoStack.value.shift();

  // Get last undo state
  const prevState = undoStack.value.pop();
  if (prevState) {
    data.value.layout = JSON.parse(JSON.stringify(prevState));
    sendToIframe({ key: 'setWebpage', value: JSON.parse(JSON.stringify(data.value)) });
  }

  saveHistoryToLocalStorage();
  console.log('Redo stack:', redoStack.value);
  try {
		const response = await axios.get(
			route('grp.json.web-block.web_block_histories.index', {webBlock : 554988, webpage : props.webpage.id }),
		)
		console.log('Undo stack:', response);
	} catch (error: any) {
		console.log(error)
	}
};

// Redo
const redo = async () => {
  if (redoStack.value.length === 0) return;

  // Move current state to undo
  undoStack.value.push(JSON.parse(JSON.stringify(data.value.layout)));
  if (undoStack.value.length > MAX_HISTORY) undoStack.value.shift();

  // Get next redo state
  const nextState = redoStack.value.pop();
  if (nextState) {
    data.value.layout = JSON.parse(JSON.stringify(nextState));
    sendToIframe({ key: 'setWebpage', value: JSON.parse(JSON.stringify(data.value)) });
  }

  saveHistoryToLocalStorage();

  console.log('Redo stack:', undoStack.value);
  try {
		const response = await axios.get(
			route('grp.json.web-block.web_block_histories.index', {webBlock : 554988, webpage : props.webpage.id }),
		)
		console.log('Undo stack:', response);
	} catch (error: any) {
		console.log(error)
	}
};

// Clear all history
const clearHistory = () => {
  undoStack.value = [];
  redoStack.value = [];
  localStorage.removeItem('undoStack');
  localStorage.removeItem('redoStack');
};

// When component is unmounted
onUnmounted(() => {
  clearHistory();
});

// Also clear when navigating away or closing tab
window.addEventListener('beforeunload', () => {
  clearHistory();
});
*/

onMounted(() => {
  window.addEventListener("message", (event) => {
    if (event.origin !== window.location.origin) return;
    const { key, value } = event.data;
    switch (key) {
      case 'autosave': return onSaveWorkshop(value,false);
      case 'activeBlock': return openedBlockSideEditor.value = value;
      case 'activeChildBlock': return openedChildSideEditor.value = value;
      case 'addBlock':
        if (_WebpageSideEditor.value) {
          isModalBlockList.value = true;
          addBlockParentIndex.value = value;
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

const openWebsite = () => {
  window.open(props.url, '_blank')
}
console.log('props',props)
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
      <div class="px-2 cursor-pointer"  v-tooltip="trans('Go to website')" @click="openWebsite">
        <FontAwesomeIcon :icon="faExternalLink" size="xl" aria-hidden="true" />
      </div>
    </template>
  </PageHeading>


  <ConfirmDialog group="alert-publish" >
    <template #icon>
      <FontAwesomeIcon :icon="faExclamationTriangle"  class="text-orange-500"/>
    </template>
  </ConfirmDialog>

  <div class="flex">
    <div v-if="!fullScreen" class="hidden lg:flex lg:flex-col border-2 bg-gray-200 pl-3 py-1">
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
          <div v-tooltip="'Full screen'" @click="fullScreen = !fullScreen" class="cursor-pointer">
            <FontAwesomeIcon :icon="!fullScreen ? faExpandWide : faCompressWide" fixed-width />
          </div>
           <!-- <div v-tooltip="'Undo'" class="cursor-pointer">
            <FontAwesomeIcon  @click="undo" :icon="faUndo" fixed-width />
          </div> -->
          <!--  <div v-tooltip="'Redo'" class="cursor-pointer">
            <FontAwesomeIcon  @click="redo" :icon="faRedo" fixed-width />
          </div> -->
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
          <label v-if="props.webpage.allow_fetch" for="sync-toggle">Connected with aurora</label>
            <label v-else for="sync-toggle">Disconnected from aurora</label>
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
