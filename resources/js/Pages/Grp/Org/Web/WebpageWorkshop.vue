<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {
  ref, onMounted, provide, watch, computed, inject,
  IframeHTMLAttributes, onUnmounted,
} from "vue";
import { Head, router } from "@inertiajs/vue3";
import * as Sentry from "@sentry/vue"
import { capitalize } from "@/Composables/capitalize";
import axios from "axios";
import { debounce, get, set } from 'lodash-es';
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
import Dialog from 'primevue/dialog';
import ImageUploadWithCroppedFunction from '@/Components/ImageUploadWithCroppedFunction.vue'
import CreateTemplateDialog from '@/Components/Workshop/CreateTemplateDialog.vue'

import { Root, Daum } from "@/types/webBlockTypes";
import { Root as RootWebpage } from "@/types/webpageTypes";
import { PageHeadingTypes } from "@/types/PageHeading";
import { routeType } from "@/types/route";
import { WebLayoutTemplate } from "@/types/WebLayoutTemplate";

import {
  faExclamationTriangle, faBrowser, faDraftingCompass, faRectangleWide,
  faStars, faTimes, faBars, faExternalLink, faExpandWide, faCompressWide,
  faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower, faSkull,
  faEye,
  faUndo,
  faRedo,
  faChevronRight,
  faSync,
  faLayerPlus
} from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faChevronLeft } from "@far";
import { faLayerGroup } from "@fas";

library.add(
  faBrowser, faDraftingCompass, faRectangleWide, faTimes, faStars,
  faBars, faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower,
  faSkull, faEye, faExpandWide, faCompressWide,
);

const props = defineProps<{
  title: string,
  pageHead: PageHeadingTypes,
  webpage: RootWebpage,
  webBlockTypes: Root
  url: string
  luigi_tracker_id: string
  editable : boolean
}>();

console.log('props',props)
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
const addBlockParentIndex = ref({ parentIndex: data.value.layout.web_blocks.length, type: "current" });
const isLoadingDeleteBlock = ref<number | null>(null);
const comment = ref("");
const isLoadingPublish = ref(false);
const isSidebarCollapsed = ref(false);
const isFullScreen = ref(false);
const filterBlock = ref('all');
const history = ref<any[]>([]);
const future = ref<any[]>([]);
const selectedTab = ref(1)
const dialogUploadImageVisible = ref(false)
const imageUploadSetting = ref(null)
const activeChildBlockArray = ref<number | null>(null);
const activeChildBlockArrayBlock = ref<number | null>(null);
const sideKey = ref(1);
const isCreateTemplateDialogVisible = ref(false);
const isCreatingTemplate = ref(false);
const TEMPLATES_INDEX_ROUTE = "grp.json.template_layouts.index";
const TEMPLATE_DETAIL_ROUTE = "grp.json.template_layouts.detail";
const TEMPLATE_APPLY_ROUTE = "grp.models.webpage.apply_template";
const templates = ref<WebLayoutTemplate[]>([]);
const isLoadingTemplates = ref(false);
const templatesErrorMessage = ref<string | null>(null);
const applyingTemplateId = ref<number | null>(null);

const canUndo = computed(() => history.value.length > 1);
const canRedo = computed(() => future.value.length > 0);

console.log('layout',layout)

provide('webpage_luigi_tracker_id', props.luigi_tracker_id)
provide('currentView', currentView);
provide('openedBlockSideEditor', openedBlockSideEditor);
provide('openedChildSideEditor', openedChildSideEditor);
provide('isAddBlockLoading', isAddBlockLoading);
provide('isLoadingBlock', isLoadingBlock);
provide('isLoadingDeleteBlock', isLoadingDeleteBlock);
provide('filterBlock', filterBlock);
provide('activeChildBlockArray', activeChildBlockArray);
provide('activeChildBlockArrayBlock', activeChildBlockArrayBlock);
provide('sideKey', sideKey);

// Utility
const sendToIframe = (data: any) => {
  _iframe.value?.contentWindow.postMessage(data, '*');
};

// Block Handlers
const addNewBlock = async ({ block, type }) => {
  if (addBlockCancelToken.value) addBlockCancelToken.value();
  let position = data.value.layout.web_blocks.length
  if (type == 'before') {
    position = addBlockParentIndex.value.parentIndex
  } else if (type == 'after') {
    position = addBlockParentIndex.value.parentIndex + 1;
  }


  router.post(
    route(props.webpage.add_web_block_route.name, props.webpage.add_web_block_route.parameters),
    { web_block_type_id: block.id, position: position },
    {
      onStart: () => isAddBlockLoading.value = "addBlock" + block.id,
      onFinish: () => {
        addBlockCancelToken.value = null;
        isAddBlockLoading.value = null;
        addBlockParentIndex.value = { parentIndex: data?.value?.layout?.web_blocks?.length, type: "current" };
      },
      onCancelToken: token => addBlockCancelToken.value = token.cancel,
      onSuccess: e => {
        data.value = e.props.webpage
        saveState()
        sendToIframe({ key: 'reload', value: {} });
      },
      onError: error => {
        console.log('sss', error)
        notify({
          title: trans("Something went wrong"),
          text: error.message,
          type: "error"
        })
      }
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
        addBlockParentIndex.value = { parentIndex: data.value.layout.web_blocks.length, type: "current" }
        saveState()
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

const debounceSaveWorkshop = (block, reload = false) => {
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

    isLoadingBlock.value = block.id;
    isSavingBlock.value = true;
    //pushToHistory();
    try {
      const response = await axios.patch(
        url,
        {
          layout: block?.web_block?.layout,
          show_logged_in: block?.visibility?.in,
          show_logged_out: block?.visibility?.out,
          show: block?.show,
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
      if (reload) {
        router.reload({
          only: ['webpage'],
          onSuccess: (newValue) => {
           /*  console.log('sss', newValue.props.webpage , data.value) */
            data.value = newValue.props.webpage
            sideKey.value++
          },
        })
        /* router.reload({ only: ["webpage"] }) */
      }
      /* sendToIframe({ key: "reload", value: {} }); */
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
      saveState()
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
      onFinish: () => {
         saveState()
         isSavingBlock.value = false
      },
      onSuccess: (e) => {
        data.value = e.props.webpage;
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

const onSaveWorkshop = (block, sendChangeValue = true, reload = false) => {
  if (cancelTokens.value[block.id]) cancelTokens.value[block.id]();
  if (sendChangeValue) {
    sendToIframe({
      key: 'setWebpage',
      value: JSON.parse(JSON.stringify(data.value))
    });
  }
  debounceSaveWorkshop(block, reload);
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
        isLoadingBlock.value = null;
        orderBlockCancelToken.value = null;
        saveState()
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
        saveState()
      },
      onCancelToken: token => deleteBlockCancelToken.value = token.cancel,
      onSuccess: e => {
        data.value = e.props.webpage;
        openedBlockSideEditor.value = null
        openedChildSideEditor.value = null
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
  if (props.webpage.type == "catalogue") onPublish(route, popover)
  else {
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

const enterFullScreen = async () => {
  isFullScreen.value = true;
  layout.leftSidebar.show = false;
  try {
    if (!document.fullscreenElement) {
      await document.documentElement.requestFullscreen();
    }
  } catch (error) {
    console.warn('Native fullscreen unavailable', error);
  }
};

const exitFullScreen = async () => {
  isFullScreen.value = false;
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen();
    }
  } catch (error) {
    console.warn('Unable to exit native fullscreen', error);
  }
};

const toggleFullScreen = () => isFullScreen.value ? exitFullScreen() : enterFullScreen();

const setHideBlock = (block: Daum) => {
  block.show = !block.show;
  onSaveWorkshop(block);
};

const onCreateTemplate = (payload: {
  name: string,
  scope: 'shown' | 'all',
  blocks: Array<{
    id: number,
    type: string,
    position: number,
    show: boolean,
    visibility: any,
    web_block_id: number | undefined,
    web_block_type_id: number | undefined,
    fieldValue: any
  }>
}) => {
  isCreatingTemplate.value = true;

  axios.post(
    route('grp.models.webpage.store_as_template', { webpage: data.value.id }),
    payload
  ).then(() => {
    isCreateTemplateDialogVisible.value = false;
    notify({
      title: trans("Success"),
      text: trans("Template has been created"),
      type: "success"
    });
    fetchTemplates();
  }).catch(error => {
    notify({
      title: trans("Something went wrong"),
      text: error?.response?.data?.message || error.message,
      type: "error"
    });
  }).finally(() => {
    isCreatingTemplate.value = false;
  });
};

const fetchTemplates = async () => {
  isLoadingTemplates.value = true;
  templatesErrorMessage.value = null;

  try {
    const response = await axios.get(
      route(TEMPLATES_INDEX_ROUTE, { webpage: data.value.id })
    );

    templates.value = response.data?.data ?? response.data ?? [];
  } catch (error: any) {
    templatesErrorMessage.value = error?.response?.data?.message || error.message;
  } finally {
    isLoadingTemplates.value = false;
  }
};

const applyTemplate = async (template: WebLayoutTemplate) => {
  applyingTemplateId.value = template.id;

  console.log("SABAR"); 
  const res = await axios.get(route(TEMPLATE_DETAIL_ROUTE, {webpage: data.value.id, layoutTemplate: applyingTemplateId.value}))
  console.log('KONTOL', res); return;

  try {
    const response = await axios.post(
      route(TEMPLATE_APPLY_ROUTE, {
        webpage: data.value.id,
        webLayoutTemplate: template.id,
      })
    );

    const layout = response.data?.layout ?? response.data;

    data.value = { ...data.value, layout };
    openedBlockSideEditor.value = null;
    saveState();

    sendToIframe({
      key: "setWebpage",
      value: JSON.parse(JSON.stringify(data.value)),
    });

    notify({
      title: trans("Success"),
      text: trans("Template has been applied"),
      type: "success"
    });
  } catch (error: any) {
    notify({
      title: trans("Something went wrong"),
      text: error?.response?.data?.message || error.message,
      type: "error"
    });
  } finally {
    applyingTemplateId.value = null;
  }
};



const saveState = () => {
  history.value.push(JSON.parse(JSON.stringify(data.value.layout)));
  localStorage.setItem(data.value.code, JSON.stringify(data.value.layout));
  future.value = [];
};

const undo = async () => {
  if (history.value.length > 1) {
    const prevState = history.value[history.value.length - 2]; // the one before last
    const current = history.value.pop()!; // remove current
    future.value.unshift(current);

    await afterUndoRedo(JSON.parse(JSON.stringify(prevState)));

    // update localStorage AFTER server confirms
    localStorage.setItem(data.value.code, JSON.stringify(data.value.layout));
  }
};

const redo = async () => {
  if (future.value.length > 0) {
    const nextState = future.value.shift()!;
    history.value.push(nextState);

    await afterUndoRedo(JSON.parse(JSON.stringify(nextState)));

    // update localStorage AFTER server confirms
    localStorage.setItem(data.value.code, JSON.stringify(data.value.layout));
  }
};

const afterUndoRedo = async (value) => {
  try {
    const payload = { layout: value };

    const response = await axios.patch(
      route('grp.models.webpage.web_block_check', {
        webpage: props.webpage.id,
      }),
      payload
    );

    data.value = { ...data.value, layout: response.data };
    console.log('sss', response.data)

    sendToIframe({
      key: "setWebpage",
      value: JSON.parse(JSON.stringify(data.value)),
    });
  } catch (error: any) {
    if (axios.isAxiosError(error)) {
      console.error("Axios error:", error.response?.data || error.message);
    } else {
      console.error("Unexpected error:", error);
    }
  }
};


// Clear all history
const clearHistory = () => {
  history.value = [];
  future.value = [];
  localStorage.removeItem(data?.value?.code);
};

const closeUploadImage = (visible) => {
  dialogUploadImageVisible.value = visible,
    imageUploadSetting.value = null
}

watch(openedBlockSideEditor, (newValue) => sendToIframe({ key: 'activeBlock', value: newValue }));
watch(currentView, (newValue) => iframeClass.value = setIframeView(newValue));
watch(filterBlock, (newValue) => sendToIframe({ key: 'isPreviewLoggedIn', value: newValue }));

onUnmounted(() => {
  clearHistory();
});

// Also clear when navigating away or closing tab
window.addEventListener('beforeunload', () => {
  clearHistory();
});


onMounted(() => {
  layout.leftSidebar.show = false
  const handleMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return;
    if (isCreateTemplateDialogVisible.value) return;
    const { key, value } = event.data;
    switch (key) {
      case 'autosave':
        return onSaveWorkshop(value, false, true);
      case 'activeBlock':
        openedBlockSideEditor.value = value;
        return;
      case 'activeChildBlock':
        selectedTab.value = 2;
        openedChildSideEditor.value = value;
        return;
      case 'activeChildBlockArray':
        selectedTab.value = 2;
        activeChildBlockArray.value = value;
        return;
      case 'activeChildBlockArrayBlock':
        selectedTab.value = 2;
        activeChildBlockArrayBlock.value = value;
        return;
      case 'addBlock':
        if (_WebpageSideEditor.value) {
          isModalBlockList.value = true;
          addBlockParentIndex.value = value;
          _WebpageSideEditor.value.addType = value.type;
        }
        return;
      case 'uploadImage':
        if (value) {
          dialogUploadImageVisible.value = true;
          imageUploadSetting.value = value
        }
        return;
      case 'deleteBlock':
        return sendDeleteBlock(value);
    }
  };

  const handleFullScreenChange = () => {
    if (!document.fullscreenElement) isFullScreen.value = false;
  };

  const handleFullScreenShortcut = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isFullScreen.value && !document.querySelector('.p-dialog-mask')) {
      exitFullScreen();
      return;
    }
    if (event.key === 'F11') {
      event.preventDefault();
      toggleFullScreen();
    }
  };

  window.addEventListener("message", handleMessage);
  document.addEventListener("fullscreenchange", handleFullScreenChange);
  window.addEventListener("keydown", handleFullScreenShortcut);

  onUnmounted(() => {
    window.removeEventListener("message", handleMessage);
    document.removeEventListener("fullscreenchange", handleFullScreenChange);
    window.removeEventListener("keydown", handleFullScreenShortcut);
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
  });
});



const compUsersEditThisPage = computed(() => {
  return useLiveUsers().liveUsersArray.filter(user => (user?.current_page?.route_name === layout.currentRoute && user?.current_page?.route_params?.webpage === layout.currentParams?.webpage)).map(user => user.name ?? user.username)
})

const openWebsite = () => {
  window.open(props.url, '_blank')
}
console.log('props_workshop',props)
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading v-show="!isFullScreen" :data="pageHead" ignoreIsolate>
    <template #button-publish="{ action }">
      <Publish 
        :isLoading="isLoadingPublish" 
        :is_dirty="data.is_dirty" 
        v-model="comment"
        @onPublish="(popover) => beforePublish(action.route, popover)" 
      />
    </template>

    <template #afterTitle v-if="isSavingBlock">
      <LoadingIcon v-tooltip="trans('Saving..')" />
    </template>

    <template #other>
      <div class="px-2 cursor-pointer" v-tooltip="trans('Go to website')" @click="openWebsite">
        <FontAwesomeIcon :icon="faExternalLink" size="xl" aria-hidden="true" />
      </div>
    </template>
  </PageHeading>

  <ConfirmDialog group="alert-publish">
    <template #icon>
      <FontAwesomeIcon :icon="faExclamationTriangle" class="text-orange-500" />
    </template>
  </ConfirmDialog>

  <div class="flex bg-slate-100" :class="isFullScreen ? 'fixed inset-0 z-[45]' : ''">
    <div class="hidden lg:flex lg:flex-col relative z-[20] bg-white border-r border-slate-200 shadow-sm"
      :class="isFullScreen ? 'h-screen' : 'h-[calc(100vh-16vh)]'">
      <!-- Sidebar Content -->
      <div v-show="!isSidebarCollapsed" class="flex-1 min-h-0 flex flex-col">
        <WebpageSideEditor
          ref="_WebpageSideEditor"
          v-model="isModalBlockList"
          :webpage="data"
          :webBlockTypes="webBlockTypes"
          v-model:selectedTab="selectedTab"
          :editable="editable"
          :templates="templates"
          :isLoadingTemplates="isLoadingTemplates"
          :templatesErrorMessage="templatesErrorMessage"
          :applyingTemplateId="applyingTemplateId"
          @fetchTemplates="fetchTemplates"
          @useTemplate="applyTemplate"
          @update="onSaveWorkshop"
          @delete="sendDeleteBlock"
          @add="addNewBlock"
          @order="sendOrderBlock"
          @setVisible="setHideBlock"
          @onSaveSiteSettings="onSaveSiteSettings"
          @onDuplicateBlock="duplicateBlock"
          @update:selected-tab="(e) => selectedTab = e" />
      </div>

      <!-- Collapsed rail: keeps the panel reachable when hidden -->
      <button type="button" v-show="isSidebarCollapsed" @click="isSidebarCollapsed = false"
        v-tooltip.right="trans('Show editor panel')"
        class="flex-1 w-8 flex flex-col items-center justify-center gap-2 text-slate-400 hover:text-slate-700 hover:bg-slate-50 transition-colors">
        <FontAwesomeIcon :icon="faLayerGroup" fixed-width />
      </button>

      <!-- Toggle Button -->
      <button type="button" @click="isSidebarCollapsed = !isSidebarCollapsed"
        v-tooltip.right="isSidebarCollapsed ? trans('Show editor panel') : trans('Hide editor panel')"
        class="absolute top-1/2 -translate-y-1/2 right-[-12px] z-10 h-7 w-6 flex items-center justify-center
               bg-white text-slate-500 hover:text-slate-900 rounded-r-md
               shadow-md hover:shadow-lg transition-all duration-200 ease-in-out
               border border-l-0 border-slate-200 hover:border-slate-300">
        <FontAwesomeIcon :icon="!isSidebarCollapsed ? faChevronLeft : faChevronRight" class="text-xs" />
      </button>
    </div>

    <!-- Preview Section -->
    <div class="w-full flex flex-col bg-slate-100 overflow-x-auto"
      :class="isFullScreen ? 'h-screen' : 'h-[calc(100vh-16vh)]'">
      <div class="flex shrink-0 flex-wrap justify-between items-center gap-1.5 px-2 py-1 bg-white border-b border-slate-200">
        <!-- Group: view + history -->
        <div class="flex items-center gap-0.5 text-xs">
          <!-- Page title: the PageHeading is hidden in full screen -->
          <span v-if="isFullScreen"
            class="max-w-[220px] truncate mr-1.5 text-xs font-semibold text-slate-700">
            {{ capitalize(title) }}
          </span>

          <div class="flex items-center rounded border border-slate-200 overflow-hidden"
            v-tooltip.bottom="trans('Preview device size')">
            <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
          </div>

          <span class="mx-0.5 h-4 w-px bg-slate-200" aria-hidden="true" />

          <!-- Undo -->
          <button type="button" v-tooltip.bottom="trans('Undo')" :disabled="!canUndo" @click="undo"
            class="h-7 w-7 flex items-center justify-center rounded text-slate-500 transition-colors
                   enabled:hover:bg-slate-100 enabled:hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed">
            <FontAwesomeIcon :icon="faUndo" fixed-width aria-hidden="true" />
          </button>

          <!-- Redo -->
          <button type="button" v-tooltip.bottom="trans('Redo')" :disabled="!canRedo" @click="redo"
            class="h-7 w-7 flex items-center justify-center rounded text-slate-500 transition-colors
                   enabled:hover:bg-slate-100 enabled:hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed">
            <FontAwesomeIcon :icon="faRedo" fixed-width aria-hidden="true" />
          </button>
        </div>

        <!-- Group: collaboration warning -->
        <div v-if="compUsersEditThisPage?.length > 1"
          v-tooltip.bottom="compUsersEditThisPage.join(', ')"
          class="flex items-center gap-1.5 px-2 py-0.5 rounded border border-amber-300 bg-amber-50 text-amber-800 text-[11px] font-medium">
          <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width />
          <span>
            {{ compUsersEditThisPage.length }} {{ trans("users edit this page.") }}
          </span>
        </div>

     
        <div class="flex items-center gap-0.5 text-xs">
          <!-- Saving indicator: the PageHeading one is hidden in full screen -->
          <span v-if="isFullScreen && isSavingBlock"
            class="flex items-center gap-1.5 mr-1 text-xs text-slate-400">
            <LoadingIcon />
            {{ trans('Saving..') }}
          </span>

          <!-- Create as template -->
          <button type="button" v-tooltip.bottom="trans('Pick the blocks to keep and save this page as a template')"
            @click="isCreateTemplateDialogVisible = true"
            class="h-7 flex items-center gap-1.5 px-2 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
            <FontAwesomeIcon :icon="faLayerPlus" fixed-width />
            <span class="text-xs font-medium">{{ trans('Create as template') }}</span>
          </button>

          <span class="mx-0.5 h-4 w-px bg-slate-200" aria-hidden="true" />

          <!-- Reload preview -->
          <button type="button" v-tooltip.bottom="trans('Reload preview')"
            @click="sendToIframe({ key: 'reload', value: {} })"
            class="h-7 w-7 flex items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition-colors">
            <FontAwesomeIcon :icon="faSync" fixed-width />
          </button>

          <!-- Open preview in new tab -->
          <button type="button" v-tooltip.bottom="trans('Open preview in new tab')" @click="openFullScreenPreview"
            class="h-7 w-7 flex items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition-colors">
            <FontAwesomeIcon :icon="faEye" fixed-width />
          </button>

          <!-- Full screen: expands to a labelled exit button while active -->
          <button type="button" @click="toggleFullScreen"
            v-tooltip.bottom="isFullScreen ? '' : trans('Full screen (F11)')"
            class="h-7 flex items-center gap-1.5 rounded transition-colors"
            :class="isFullScreen
              ? 'px-2 bg-slate-900 text-white hover:bg-slate-700'
              : 'w-7 justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-900'">
            <FontAwesomeIcon :icon="isFullScreen ? faCompressWide : faExpandWide" fixed-width />
            <template v-if="isFullScreen">
              <span class="text-xs font-medium">{{ trans('Exit full screen') }}</span>
              <kbd class="px-1 py-px rounded bg-white/20 text-[10px] font-sans leading-none">Esc</kbd>
            </template>
          </button>
        </div>
      </div>

      <div class="relative flex-1 min-h-0 w-full overflow-auto">
        <div v-if="isIframeLoading"
          class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white">
          <LoadingIcon class="w-16 h-16 text-5xl text-slate-400" />
          <span class="text-sm text-slate-400">{{ trans("Loading preview…") }}</span>
        </div>
        <iframe ref="_iframe" :src="iframeSrc" :title="props.title"
          :class="[iframeClass, isIframeLoading ? 'invisible' : '', 'border-0 bg-white']"
          @load="isIframeLoading = false" allowfullscreen />
      </div>
    </div>
  </div>

  <CreateTemplateDialog
    v-model:visible="isCreateTemplateDialogVisible"
    :webpage="data"
    :previewSrc="iframeSrc"
    :isLoading="isCreatingTemplate"
    @create="onCreateTemplate" />

  <Dialog v-model:visible="dialogUploadImageVisible" modal header="Upload Image" :style="{ width: '80rem' }"
    @hide="() => closeUploadImage(false)">
    <ImageUploadWithCroppedFunction @dialog="(visible) => closeUploadImage(visible)"
      :model-value="get(data.layout.web_blocks[openedBlockSideEditor].web_block.layout.data.fieldValue, imageUploadSetting.key)"
      @update:modelValue="(val) => {
        set(
          data.layout.web_blocks[openedBlockSideEditor].web_block.layout.data.fieldValue,
          imageUploadSetting.key,
          val
        )
        onSaveWorkshop(data.layout.web_blocks[openedBlockSideEditor])
      }" :stencilProps="imageUploadSetting.stencilProps" :upload-routes="{
          ...data.images_upload_route,
          parameters: {
            modelHasWebBlocks: data.layout.web_blocks[openedBlockSideEditor].id
          }
        }" />
  </Dialog>

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
