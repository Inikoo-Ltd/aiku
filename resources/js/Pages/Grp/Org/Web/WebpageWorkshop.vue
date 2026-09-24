<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import WebpageLockBanner from '@/Components/CMS/Webpage/WebpageLockBanner.vue'
import WebpageLockButton from '@/Components/CMS/Webpage/WebpageLockButton.vue'
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
import { ctrans } from "@/Composables/useTrans";
import {
  useWorkshopShortcuts, formatShortcutCombo,
  WorkshopShortcut, CopiedWebBlock,
} from "@/Composables/useWorkshopShortcuts";
import { getCopyPermissions, getDeletePermissions, getHiddenPermissions } from "@/Composables/getBlueprintWorkshop";
import { useConfirm } from "primevue/useconfirm";
import { useLiveUsers } from "@/Stores/active-users";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import { getRevealSetting, setIframeView } from "@/Composables/Workshop";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import Publish from "@/Components/Publish.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import ScreenView from "@/Components/ScreenView.vue";
import WebpageSideEditor from "@/Components/Workshop/WebpageSideEditor.vue";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import ConfirmDialog from 'primevue/confirmdialog';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import ImageUploadWithCroppedFunction from '@/Components/ImageUploadWithCroppedFunction.vue'
import CreateTemplateDialog from '@/Components/Workshop/CreateTemplateDialog.vue'
import ApplyTemplateDialog from '@/Components/Workshop/ApplyTemplateDialog.vue'
import WorkshopShortcutsDialog from '@/Components/Workshop/WorkshopShortcutsDialog.vue'

import { Root, Daum } from "@/types/webBlockTypes";
import { Root as RootWebpage } from "@/types/webpageTypes";
import { PageHeadingTypes } from "@/types/PageHeading";
import { routeType } from "@/types/route";
import {
  WebLayoutTemplate,
  WebLayoutTemplateFilter,
  WebLayoutTemplateList
} from "@/types/WebLayoutTemplate";

import {
  faExclamationTriangle, faBrowser, faDraftingCompass, faRectangleWide,
  faStars, faTimes, faBars, faExternalLink, faExpandWide, faCompressWide,
  faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower, faSkull,
  faEye,
  faUndo,
  faRedo,
  faChevronRight,
  faSync,
  faLayerPlus,
  faKeyboard,
  faPaste
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
  editable : boolean
  lock: any
}>();


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
const TEMPLATE_DELETE_ROUTE = "grp.models.web_layout_template.delete";
const TEMPLATES_PER_PAGE = 10;
const templates = ref<WebLayoutTemplateList>({ data: [] });
const templatesSearch = ref("");
const templatesFilter = ref<WebLayoutTemplateFilter>("all");
const isLoadingTemplates = ref(false);
const templatesErrorMessage = ref<string | null>(null);
const applyingTemplateId = ref<number | null>(null);
const deletingTemplateId = ref<number | null>(null);
const isApplyTemplateDialogVisible = ref(false);
const isApplyingTemplate = ref(false);
const selectedTemplate = ref<WebLayoutTemplate | null>(null);
const templateMerge = ref<{ current: any[], incoming: any[] }>({ current: [], incoming: [] });
const isShortcutsDialogVisible = ref(false);
const COPIED_BLOCK_STORAGE_KEY = "webpageWorkshop.copiedBlock";

const canUndo = computed(() => history.value.length > 1);
const canRedo = computed(() => future.value.length > 0);

const WEBPAGE_TYPES_WITHOUT_TEMPLATE = ['storefront', 'blog', 'system_page'];
const canUseTemplate = computed(() => !WEBPAGE_TYPES_WITHOUT_TEMPLATE.includes(props.webpage.type));

console.log('layout',layout)

const revealBlockOptions = computed(() =>
  (data.value?.layout?.web_blocks ?? [])
    .filter(block => getRevealSetting(block))
    .map(block => ({
      key: getRevealSetting(block).key,
      label: block.web_block.layout.data?.fieldValue?.blocks?.name || block.type,
    }))
);

provide('revealBlockOptions', revealBlockOptions);

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
  if (!props.editable) return;
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
          title: ctrans("Something went wrong"),
          text: error.message,
          type: "error"
        })
      }
    }
  );
};

const renameDuplicatedRevealKeys = () => {
  const blocks = data.value.layout.web_blocks;
  const usedKeys = new Set(blocks.map(block => block?.web_block?.layout?.reveal?.key).filter(Boolean));
  const seenKeys = new Set();

  blocks.forEach(block => {
    const reveal = block?.web_block?.layout?.reveal;
    if (!reveal?.key) return;

    if (!seenKeys.has(reveal.key)) {
      seenKeys.add(reveal.key);
      return;
    }

    let suffix = 2;
    while (usedKeys.has(`${reveal.key}-${suffix}`)) suffix++;

    reveal.key = `${reveal.key}-${suffix}`;
    usedKeys.add(reveal.key);
    seenKeys.add(reveal.key);
    onSaveWorkshop(block, false);
  });
};

const duplicateBlock = (modelHasWebBlockId: number, position?: number) => {
  if (!props.editable) return;
  const blocks = data.value.layout.web_blocks;
  const sourceIndex = blocks.findIndex(block => block.id === modelHasWebBlockId);
  const targetPosition = position ?? (sourceIndex === -1 ? blocks.length : sourceIndex + 1);

  router.post(
    route('grp.models.webpage.web_block.duplicate', {
      webpage: data.value.id,
      modelHasWebBlock: modelHasWebBlockId
    }),
    { position: targetPosition },
    {
      onStart: () => isAddBlockLoading.value = "addBlock" + modelHasWebBlockId,
      onFinish: () => {
        addBlockCancelToken.value = null;
        isAddBlockLoading.value = null;
        addBlockParentIndex.value = { parentIndex: data.value.layout.web_blocks.length, type: "current" }
        saveState()
      },
      onCancelToken: token => addBlockCancelToken.value = token.cancel,
      onSuccess: e => {
        data.value = e.props.webpage;
        renameDuplicatedRevealKeys();
        openedBlockSideEditor.value = Math.min(targetPosition, data.value.layout.web_blocks.length - 1);
        sendToIframe({ key: 'reload', value: {} });
      },
      onError: error => notify({
        title: ctrans("Something went wrong"),
        text: error.message,
        type: "error"
      })
    }
  );
};

const debounceSaveWorkshop = (block, reload = false, reloadIframe = false) => {
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
      if (reloadIframe) {
        sendToIframe({ key: "reload", value: {} });
      }
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
        title: ctrans("Something went wrong"),
        text: error.message,
        type: "error"
      }),
    }
  );
}, 1500);

const onSaveSiteSettings = block => {
  if (!props.editable) return;
  debouncedSaveSiteSettings(block);
};

const onSaveWorkshop = (block, isFromSideEditor = true, reload = false) => {
  if (!props.editable) return;
  if (cancelTokens.value[block.id]) cancelTokens.value[block.id]();
  if (isFromSideEditor) {
    sendToIframe({
      key: 'setWebpage',
      value: JSON.parse(JSON.stringify(data.value))
    });
  }
  debounceSaveWorkshop(block, reload, isFromSideEditor);
};

const onSaveWorkshopFromId = (blockId, from) => {
  if (from) console.log('onSaveWorkshopFromId from:', from);
  if (!blockId || !props.editable) return;
  if (cancelTokens.value[blockId]) cancelTokens.value[blockId]();

  const block = data.value.layout.web_blocks.find(block => block.id === blockId);
  sendToIframe({
    key: 'setWebpage',
    value: JSON.parse(JSON.stringify(data.value))
  });
  if (block) debounceSaveWorkshop(block, false, true);
};

provide('onSaveWorkshopFromId', onSaveWorkshopFromId);
provide('onSaveWorkshop', onSaveWorkshop);


const sendOrderBlock = async block => {
  if (!props.editable) return;
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
        title: ctrans("Something went wrong"),
        text: error.message,
        type: "error"
      })
    }
  );
};

const sendDeleteBlock = async (block: Daum) => {
  if (!props.editable) return;
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
        title: ctrans("Something went wrong"),
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
        title: ctrans("Published!"),
        text: ctrans("Webpage data has been published successfully"),
        type: "success"
      });
    }
    popover.close();
  } catch (error) {
    notify({
      title: ctrans("Something went wrong"),
      text: error?.response?.data?.message || error.message || "Unknown error occurred",
      type: "error"
    });
  } finally {
    isLoadingPublish.value = false;
  }
};

const beforePublish = (route, popover) => {
  if (!props.editable) return;
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
  if (!props.editable) return;
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
  if (!props.editable) return;
  isCreatingTemplate.value = true;

  axios.post(
    route('grp.models.webpage.store_as_template', { webpage: data.value.id }),
    payload
  ).then(() => {
    isCreateTemplateDialogVisible.value = false;
    notify({
      title: ctrans("Success"),
      text: ctrans("Template has been created"),
      type: "success"
    });
    fetchTemplates();
  }).catch(error => {
    notify({
      title: ctrans("Something went wrong"),
      text: error?.response?.data?.message || error.message,
      type: "error"
    });
  }).finally(() => {
    isCreatingTemplate.value = false;
  });
};

const buildTemplatesUrl = (url?: string) => {
  const target = new URL(
    url || route(TEMPLATES_INDEX_ROUTE, { webpage: data.value.id }),
    window.location.origin
  );

  target.searchParams.set("per_page", String(TEMPLATES_PER_PAGE));

  if (templatesSearch.value) {
    target.searchParams.set("filter[global]", templatesSearch.value);
  } else {
    target.searchParams.delete("filter[global]");
  }

  target.searchParams.set("filter[show]", templatesFilter.value);

  return target.toString();
};

const fetchTemplates = async (url?: string) => {
  isLoadingTemplates.value = true;
  templatesErrorMessage.value = null;

  try {
    const response = await axios.get(buildTemplatesUrl(url));

    templates.value = {
      data: response.data?.data ?? [],
      meta: response.data?.meta,
      links: response.data?.links,
    };
  } catch (error: any) {
    templatesErrorMessage.value = error?.response?.data?.message || error.message;
  } finally {
    isLoadingTemplates.value = false;
  }
};

const onSearchTemplates = (value: string) => {
  templatesSearch.value = value;
  fetchTemplates();
};

const onFilterTemplates = (value: WebLayoutTemplateFilter) => {
  templatesFilter.value = value;
  fetchTemplates();
};

const applyTemplate = async (template: WebLayoutTemplate) => {
  if (!props.editable) return;
  applyingTemplateId.value = template.id;

  try {
    const response = await axios.get(
      route(TEMPLATE_DETAIL_ROUTE, { webpage: data.value.id, layoutTemplate: template.id })
    );

    selectedTemplate.value = template;
    templateMerge.value = {
      current: response.data?.current ?? [],
      incoming: response.data?.incoming ?? [],
    };
    isApplyTemplateDialogVisible.value = true;
  } catch (error: any) {
    notify({
      title: ctrans("Something went wrong"),
      text: error?.response?.data?.message || error.message,
      type: "error"
    });
  } finally {
    applyingTemplateId.value = null;
  }
};

const deleteTemplate = async (template: WebLayoutTemplate) => {
  deletingTemplateId.value = template.id;

  try {
    await axios.delete(route(TEMPLATE_DELETE_ROUTE, { template: template.id }));

    notify({
      title: ctrans("Success"),
      text: ctrans("Template has been deleted"),
      type: "success"
    });

    await fetchTemplates();
  } catch (error: any) {
    notify({
      title: ctrans("Something went wrong"),
      text: error?.response?.data?.message || error.message,
      type: "error"
    });
  } finally {
    deletingTemplateId.value = null;
  }
};

const onApplyTemplate = (payload: {
  template_id: number | null,
  blocks: Array<{
    source: 'current' | 'incoming',
    id: number,
    ulid : ulid,
    type: string,
    show: boolean,
    visibility: any,
    position: number
  }>
}) => {
  if (!props.editable) return;
  isApplyingTemplate.value = true;

  console.log(payload)
  axios.post(
    route(TEMPLATE_APPLY_ROUTE, { webpage: data.value.id }),
    payload
  ).then(response => {
    isApplyTemplateDialogVisible.value = false;
    data.value = { ...data.value, layout: response.data };

    sendToIframe({
      key: "setWebpage",
      value: JSON.parse(JSON.stringify(data.value)),
    });

    saveState();
    router.reload({
      only: ['webpage'],
      onSuccess: (newValue) => {
        data.value = newValue.props.webpage;
        sideKey.value++;
        sendToIframe({ key: 'reload', value: {} });
      },
    });

    notify({
      title: ctrans("Success"),
      text: ctrans("Template has been applied"),
      type: "success"
    });
  }).catch(error => {
    notify({
      title: ctrans("Something went wrong"),
      text: error?.response?.data?.message || error.message,
      type: "error"
    });
  }).finally(() => {
    isApplyingTemplate.value = false;
  });
};



const saveState = () => {
  history.value.push(JSON.parse(JSON.stringify(data.value.layout)));
  localStorage.setItem(data.value.code, JSON.stringify(data.value.layout));
  future.value = [];
};

const undo = async () => {
  if (props.editable && history.value.length > 1) {
    const prevState = history.value[history.value.length - 2]; // the one before last
    const current = history.value.pop()!; // remove current
    future.value.unshift(current);

    await afterUndoRedo(JSON.parse(JSON.stringify(prevState)));

    // update localStorage AFTER server confirms
    localStorage.setItem(data.value.code, JSON.stringify(data.value.layout));
  }
};

const redo = async () => {
  if (props.editable && future.value.length > 0) {
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

const selectedBlock = computed<Daum | null>(() =>
  openedBlockSideEditor.value === null
    ? null
    : data.value.layout.web_blocks[openedBlockSideEditor.value] ?? null
);

const getBlockName = (block: Daum) => block.web_block?.layout?.data?.fieldValue?.blocks?.name || block.type;

const readCopiedBlock = (): CopiedWebBlock | null => {
  try {
    return JSON.parse(localStorage.getItem(COPIED_BLOCK_STORAGE_KEY) ?? "null");
  } catch {
    return null;
  }
};

const copiedBlock = ref<CopiedWebBlock | null>(readCopiedBlock());

const copyBlock = (block: Daum) => {
  if (!getCopyPermissions(block)) {
    notify({ title: ctrans("This block can't be copied"), type: "warn" });
    return;
  }

  copiedBlock.value = { id: block.id, name: getBlockName(block), webpageId: data.value.id };
  try {
    localStorage.setItem(COPIED_BLOCK_STORAGE_KEY, JSON.stringify(copiedBlock.value));
  } catch (error) {
    console.warn("Unable to share the copied block with other tabs", error);
  }

  notify({
    title: ctrans("Block copied"),
    text: ctrans("Paste :block on this or any other webpage with :keys", {
      block: copiedBlock.value.name,
      keys: formatShortcutCombo(["Mod", "V"]),
    }),
    type: "success",
  });
};

const clearCopiedBlock = () => {
  copiedBlock.value = null;
  try {
    localStorage.removeItem(COPIED_BLOCK_STORAGE_KEY);
  } catch (error) {
    console.warn("Unable to clear the copied block", error);
  }
};

const pasteBlock = (position?: number) => {
  if (!props.editable || !copiedBlock.value) return;
  const insertAfterSelected = openedBlockSideEditor.value === null
    ? data.value.layout.web_blocks.length
    : openedBlockSideEditor.value + 1;
  duplicateBlock(copiedBlock.value.id, position ?? insertAfterSelected);
};

const onCopiedBlockChangedInOtherTab = (event: StorageEvent) => {
  if (event.key === COPIED_BLOCK_STORAGE_KEY) copiedBlock.value = readCopiedBlock();
};

provide('copiedWebBlock', copiedBlock);
provide('copyWebBlock', copyBlock);
provide('pasteWebBlock', pasteBlock);

const confirmDeleteSelectedBlock = () => {
  const block = selectedBlock.value;
  if (!block) return;
  confirm.require({
    group: "workshop-shortcut",
    header: ctrans("Delete block"),
    message: ctrans("Delete :block from this webpage?", { block: getBlockName(block) }),
    rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
    acceptProps: { label: ctrans("Yes, delete"), severity: "danger" },
    accept: () => sendDeleteBlock(block),
  });
};

const moveSelectedBlock = (offset: -1 | 1) => {
  const blocks = data.value.layout.web_blocks;
  const from = openedBlockSideEditor.value;
  if (from === null) return;
  const to = from + offset;
  if (to < 0 || to >= blocks.length) return;

  [blocks[from], blocks[to]] = [blocks[to], blocks[from]];
  openedBlockSideEditor.value = to;
  sendOrderBlock(Object.fromEntries(blocks.map((block, index) => [block.web_block.id, { position: index }])));
};

const selectAdjacentBlock = (offset: -1 | 1) => {
  const lastIndex = data.value.layout.web_blocks.length - 1;
  openedBlockSideEditor.value = Math.min(Math.max((openedBlockSideEditor.value ?? 0) + offset, 0), lastIndex);
};

const deselectOrExitFullScreen = () => {
  if (isFullScreen.value) {
    exitFullScreen();
    return;
  }
  openedChildSideEditor.value = null;
  openedBlockSideEditor.value = null;
};

const hasSelectedBlock = () => selectedBlock.value !== null;
const canEditSelectedBlock = () => props.editable && hasSelectedBlock();

const shortcuts: WorkshopShortcut[] = [
  {
    id: "undo", group: "History", label: "Undo", combos: [["Mod", "Z"]],
    run: undo, isAvailable: () => props.editable && canUndo.value,
  },
  {
    id: "redo", group: "History", label: "Redo", combos: [["Mod", "Shift", "Z"], ["Mod", "Y"]],
    run: redo, isAvailable: () => props.editable && canRedo.value,
  },
  {
    id: "copy", group: "Blocks", label: "Copy selected block", combos: [["Mod", "C"]],
    run: () => copyBlock(selectedBlock.value!), isAvailable: hasSelectedBlock, skipWhenTextSelected: true,
  },
  {
    id: "paste", group: "Blocks", label: "Paste block below the selected one", combos: [["Mod", "V"]],
    run: () => pasteBlock(), isAvailable: () => props.editable && !!copiedBlock.value,
  },
  {
    id: "duplicate", group: "Blocks", label: "Duplicate selected block", combos: [["Mod", "D"]],
    run: () => duplicateBlock(selectedBlock.value!.id),
    isAvailable: () => canEditSelectedBlock() && getCopyPermissions(selectedBlock.value!),
  },
  {
    id: "delete", group: "Blocks", label: "Delete selected block", combos: [["Delete"], ["Backspace"]],
    run: confirmDeleteSelectedBlock,
    isAvailable: () => canEditSelectedBlock() && getDeletePermissions(selectedBlock.value!.web_block.layout.data),
  },
  {
    id: "toggle-visibility", group: "Blocks", label: "Hide or show selected block", combos: [["Mod", "Shift", "H"]],
    run: () => setHideBlock(selectedBlock.value!),
    isAvailable: () => canEditSelectedBlock() && getHiddenPermissions(selectedBlock.value!.web_block.layout.data),
  },
  {
    id: "move-up", group: "Blocks", label: "Move selected block up", combos: [["Alt", "ArrowUp"]],
    run: () => moveSelectedBlock(-1), isAvailable: canEditSelectedBlock,
  },
  {
    id: "move-down", group: "Blocks", label: "Move selected block down", combos: [["Alt", "ArrowDown"]],
    run: () => moveSelectedBlock(1), isAvailable: canEditSelectedBlock,
  },
  {
    id: "select-previous", group: "Selection", label: "Select previous block", combos: [["ArrowUp"]],
    run: () => selectAdjacentBlock(-1), isAvailable: hasSelectedBlock, allowRepeat: true,
  },
  {
    id: "select-next", group: "Selection", label: "Select next block", combos: [["ArrowDown"]],
    run: () => selectAdjacentBlock(1), isAvailable: hasSelectedBlock, allowRepeat: true,
  },
  {
    id: "deselect", group: "Selection", label: "Deselect block or exit full screen", combos: [["Escape"]],
    run: deselectOrExitFullScreen, isAvailable: () => isFullScreen.value || hasSelectedBlock(),
  },
  {
    id: "save", group: "Editor", label: "Save (changes already save automatically)", combos: [["Mod", "S"]],
    allowWhileTyping: true,
    run: () => notify({
      title: ctrans("Changes save automatically"),
      text: ctrans("Publish when you are ready to put them live."),
      type: "info",
    }),
  },
  {
    id: "toggle-panel", group: "Editor", label: "Show or hide editor panel", combos: [["Mod", "\\"]],
    run: () => isSidebarCollapsed.value = !isSidebarCollapsed.value,
  },
  {
    id: "full-screen", group: "Editor", label: "Full screen", combos: [["F11"]],
    run: toggleFullScreen, allowWhileTyping: true,
  },
  {
    id: "shortcuts", group: "Editor", label: "Show keyboard shortcuts", combos: [["?"], ["Mod", "/"]],
    run: () => isShortcutsDialogVisible.value = true,
  },
];

const isShortcutBlocked = () =>
  isModalBlockList.value
  || isCreateTemplateDialogVisible.value
  || isApplyTemplateDialogVisible.value
  || dialogUploadImageVisible.value
  || isShortcutsDialogVisible.value
  || !!document.querySelector(".p-dialog-mask, .p-confirmpopup");

const { listenTo: listenForShortcuts } = useWorkshopShortcuts(shortcuts, isShortcutBlocked);

const onIframeLoad = () => {
  isIframeLoading.value = false;
  listenForShortcuts(_iframe.value?.contentWindow);
};

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
        if (props.editable) selectedTab.value = 2;
        openedChildSideEditor.value = value;
        return;
      case 'activeChildBlockArray':
        if (props.editable) selectedTab.value = 2;
        activeChildBlockArray.value = value;
        return;
      case 'activeChildBlockArrayBlock':
        if (props.editable) selectedTab.value = 2;
        activeChildBlockArrayBlock.value = value;
        return;
      case 'addBlock':
        if (_WebpageSideEditor.value && props.editable) {
          isModalBlockList.value = true;
          addBlockParentIndex.value = value;
          _WebpageSideEditor.value.addType = value.type;
        }
        return;
      case 'uploadImage':
        if (value && props.editable) {
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

  window.addEventListener("message", handleMessage);
  document.addEventListener("fullscreenchange", handleFullScreenChange);
  window.addEventListener("storage", onCopiedBlockChangedInOtherTab);
  listenForShortcuts(window);

  onUnmounted(() => {
    window.removeEventListener("message", handleMessage);
    document.removeEventListener("fullscreenchange", handleFullScreenChange);
    window.removeEventListener("storage", onCopiedBlockChangedInOtherTab);
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
      <Button
        v-if="!editable"
        :label="ctrans('Publish')"
        icon="far fa-rocket-launch"
        type="tertiary"
        disabled
        v-tooltip="lock?.message"
      />
      <Publish
        v-else
        :isLoading="isLoadingPublish"
        :is_dirty="data.is_dirty" 
        v-model="comment"
        @onPublish="(popover) => beforePublish(action.route, popover)" 
      />
    </template>

    <template #afterTitle v-if="isSavingBlock">
      <LoadingIcon v-tooltip="ctrans('Saving..')" />
    </template>

    <template #otherBefore>
      <WebpageLockButton :lock="lock" />
    </template>

    <template #other>
      <div class="px-2 cursor-pointer" v-tooltip="ctrans('Go to website')" @click="openWebsite">
        <FontAwesomeIcon :icon="faExternalLink" size="xl" fixed-width aria-hidden="true" />
      </div>
    </template>
  </PageHeading>
  <WebpageLockBanner v-show="!isFullScreen" :lock="lock" />

  <ConfirmDialog group="alert-publish">
    <template #icon>
      <FontAwesomeIcon :icon="faExclamationTriangle" class="text-orange-500" fixed-width />
    </template>
  </ConfirmDialog>

  <ConfirmDialog group="workshop-shortcut">
    <template #icon>
      <FontAwesomeIcon :icon="faExclamationTriangle" class="text-red-500" fixed-width />
    </template>
  </ConfirmDialog>

  <WorkshopShortcutsDialog v-model:visible="isShortcutsDialogVisible" :shortcuts="shortcuts" />

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
          :canUseTemplate="canUseTemplate"
          :templates="templates"
          :isLoadingTemplates="isLoadingTemplates"
          :templatesErrorMessage="templatesErrorMessage"
          :applyingTemplateId="applyingTemplateId"
          :deletingTemplateId="deletingTemplateId"
          :templatesFilter="templatesFilter"
          @fetchTemplates="fetchTemplates()"
          @searchTemplates="onSearchTemplates"
          @filterTemplates="onFilterTemplates"
          @navigateTemplates="fetchTemplates"
          @useTemplate="applyTemplate"
          @deleteTemplate="deleteTemplate"
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
        v-tooltip.right="ctrans('Show editor panel')"
        class="flex-1 w-8 flex flex-col items-center justify-center gap-2 text-slate-400 hover:text-slate-700 hover:bg-slate-50 transition-colors">
        <FontAwesomeIcon :icon="faLayerGroup" fixed-width />
      </button>

      <!-- Toggle Button -->
      <button type="button" @click="isSidebarCollapsed = !isSidebarCollapsed"
        v-tooltip.right="isSidebarCollapsed ? ctrans('Show editor panel') : ctrans('Hide editor panel')"
        class="absolute top-1/2 -translate-y-1/2 right-[-12px] z-10 h-7 w-6 flex items-center justify-center
               bg-white text-slate-500 hover:text-slate-900 rounded-r-md
               shadow-md hover:shadow-lg transition-all duration-200 ease-in-out
               border border-l-0 border-slate-200 hover:border-slate-300">
        <FontAwesomeIcon :icon="!isSidebarCollapsed ? faChevronLeft : faChevronRight" class="text-xs" fixed-width />
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
            v-tooltip.bottom="ctrans('Preview device size')">
            <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
          </div>

          <span class="mx-0.5 h-4 w-px bg-slate-200" aria-hidden="true" />

          <!-- Undo -->
          <button type="button" v-tooltip.bottom="`${ctrans('Undo')} (${formatShortcutCombo(['Mod', 'Z'])})`" :disabled="!editable || !canUndo" @click="undo"
            class="h-7 w-7 flex items-center justify-center rounded text-slate-500 transition-colors
                   enabled:hover:bg-slate-100 enabled:hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed">
            <FontAwesomeIcon :icon="faUndo" fixed-width aria-hidden="true" />
          </button>

          <!-- Redo -->
          <button type="button" v-tooltip.bottom="`${ctrans('Redo')} (${formatShortcutCombo(['Mod', 'Shift', 'Z'])})`" :disabled="!editable || !canRedo" @click="redo"
            class="h-7 w-7 flex items-center justify-center rounded text-slate-500 transition-colors
                   enabled:hover:bg-slate-100 enabled:hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed">
            <FontAwesomeIcon :icon="faRedo" fixed-width aria-hidden="true" />
          </button>

          <span class="mx-0.5 h-4 w-px bg-slate-200" aria-hidden="true" />

          <!-- Keyboard shortcuts -->
          <button type="button" v-tooltip.bottom="`${ctrans('Keyboard shortcuts')} (?)`"
            @click="isShortcutsDialogVisible = true"
            class="h-7 w-7 flex items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition-colors">
            <FontAwesomeIcon :icon="faKeyboard" fixed-width aria-hidden="true" />
          </button>

          <!-- Copied block: paste it, or clear it -->
          <div v-if="copiedBlock && editable"
            class="ml-1 flex h-7 items-center rounded border border-dashed border-slate-300 text-slate-600">
            <button type="button" @click="pasteBlock()"
              v-tooltip.bottom="`${ctrans('Paste below the selected block')} (${formatShortcutCombo(['Mod', 'V'])})`"
              class="flex h-full items-center gap-1.5 pl-2 pr-1 hover:text-slate-900">
              <FontAwesomeIcon :icon="faPaste" fixed-width aria-hidden="true" />
              <span class="max-w-[140px] truncate text-xs">{{ copiedBlock.name }}</span>
            </button>
            <button type="button" @click="clearCopiedBlock" v-tooltip.bottom="ctrans('Clear copied block')"
              class="flex h-full items-center px-1.5 text-slate-400 hover:text-slate-900">
              <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
            </button>
          </div>
        </div>

        <!-- Group: collaboration warning -->
        <div v-if="compUsersEditThisPage?.length > 1"
          v-tooltip.bottom="compUsersEditThisPage.join(', ')"
          class="flex items-center gap-1.5 px-2 py-0.5 rounded border border-amber-300 bg-amber-50 text-amber-800 text-[11px] font-medium">
          <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width />
          <span>
            {{ compUsersEditThisPage.length }} {{ ctrans("users edit this page.") }}
          </span>
        </div>

     
        <div class="flex items-center gap-0.5 text-xs">
          <!-- Saving indicator: the PageHeading one is hidden in full screen -->
          <span v-if="isFullScreen && isSavingBlock"
            class="flex items-center gap-1.5 mr-1 text-xs text-slate-400">
            <LoadingIcon />
            {{ ctrans('Saving..') }}
          </span>

          <!-- Create as template -->
          <template v-if="canUseTemplate && editable">
            <button type="button" v-tooltip.bottom="ctrans('Pick the blocks to keep and save this page as a template')"
              @click="isCreateTemplateDialogVisible = true"
              class="h-7 flex items-center gap-1.5 px-2 rounded border border-slate-200 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
              <FontAwesomeIcon :icon="faLayerPlus" fixed-width />
              <span class="text-xs font-medium">{{ ctrans('Create as template') }}</span>
            </button>

            <span class="mx-0.5 h-4 w-px bg-slate-200" aria-hidden="true" />
          </template>

          <!-- Reload preview -->
          <button type="button" v-tooltip.bottom="ctrans('Reload preview')"
            @click="sendToIframe({ key: 'reload', value: {} })"
            class="h-7 w-7 flex items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition-colors">
            <FontAwesomeIcon :icon="faSync" fixed-width />
          </button>

          <!-- Open preview in new tab -->
          <button type="button" v-tooltip.bottom="ctrans('Open preview in new tab')" @click="openFullScreenPreview"
            class="h-7 w-7 flex items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition-colors">
            <FontAwesomeIcon :icon="faEye" fixed-width />
          </button>

          <!-- Full screen: expands to a labelled exit button while active -->
          <button type="button" @click="toggleFullScreen"
            v-tooltip.bottom="isFullScreen ? '' : ctrans('Full screen (F11)')"
            class="h-7 flex items-center gap-1.5 rounded transition-colors"
            :class="isFullScreen
              ? 'px-2 bg-slate-900 text-white hover:bg-slate-700'
              : 'w-7 justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-900'">
            <FontAwesomeIcon :icon="isFullScreen ? faCompressWide : faExpandWide" fixed-width />
            <template v-if="isFullScreen">
              <span class="text-xs font-medium">{{ ctrans('Exit full screen') }}</span>
              <kbd class="px-1 py-px rounded bg-white/20 text-[10px] font-sans leading-none">Esc</kbd>
            </template>
          </button>
        </div>
      </div>

      <div class="relative flex-1 min-h-0 w-full overflow-auto">
        <div v-if="isIframeLoading"
          class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white">
          <LoadingIcon class="w-16 h-16 text-5xl text-slate-400" />
          <span class="text-sm text-slate-400">{{ ctrans("Loading preview…") }}</span>
        </div>
        <iframe ref="_iframe" :src="iframeSrc" :title="props.title"
          :class="[iframeClass, isIframeLoading ? 'invisible' : '', 'border-0 bg-white']"
          @load="onIframeLoad" allowfullscreen />
      </div>
    </div>
  </div>

  <CreateTemplateDialog
    v-model:visible="isCreateTemplateDialogVisible"
    :webpage="data"
    :previewSrc="iframeSrc"
    :isLoading="isCreatingTemplate"
    @create="onCreateTemplate" />

  <ApplyTemplateDialog
    v-model:visible="isApplyTemplateDialogVisible"
    :template="selectedTemplate"
    :current="templateMerge.current"
    :incoming="templateMerge.incoming"
    :webBlocks="data.layout.web_blocks"
    :isLoading="isApplyingTemplate"
    :currentPageDetail="{
      type: data.type,
      sub_type: data.sub_type,
    }"
    @apply="onApplyTemplate" />

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
