<!--
  Author: Raul Perusquia <raul@inikoo.com>
  Created: Wed, 07 Jun 2023
-->

<script setup lang="ts">
import { ref, provide } from "vue";
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import { useFreshAfterHistoryRestore } from "@/Composables/useFreshAfterHistoryRestore"

// Utils
import { capitalize } from "@/Composables/capitalize";
import { ctrans } from "@/Composables/useTrans";

// Components
import PageHeading from "@/Components/Headings/PageHeading.vue";
import Publish from "@/Components/Publish.vue";
import PublishScheduleButton from "@/Components/Workshop/Blog/PublishScheduleButton.vue";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import ConfirmDialog from 'primevue/confirmdialog';
import EmailWorkshop from '@/Components/CMS/Website/Outboxes/EmailWorkshop/EmailWorkshop.vue'

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
import { PageHeadingTypes } from "@/types/PageHeading";
import { routeType } from "@/types/route";
import EditorBlogWorkshop from "@/Components/Workshop/Blog/EditorBlogWorkshop.vue";

// Props
const props = defineProps<{
  title: string,
  pageHead: PageHeadingTypes,
  webpage: WebpageType,
  webBlockTypes: WebBlockTypes,
  url: string
  webpage_sub_type : string
  updateRoute?: routeType
  mergeTags?: Array<any>
  publish_schedule?: {
    scheduled_at: string | null
    scheduled_by: string | null
    store_route: routeType
    delete_route: routeType
  } | null
}>();

provide('isInWorkshop', true);

// State
const data = ref(props.webpage);

const isRefreshingLayout = useFreshAfterHistoryRestore(['webpage', 'pageHead'], () => {
  data.value = props.webpage;
});
const comment = ref("");
const isSavingBlock = ref(false);
const isLoadingPublish = ref(false);
const _emailWorkshop = ref<InstanceType<typeof EmailWorkshop> | null>(null);
const isPublishingBeefree = ref(false);
const _editorBlogWorkshop = ref<InstanceType<typeof EditorBlogWorkshop> | null>(null);
const _publishScheduleButton = ref<InstanceType<typeof PublishScheduleButton> | null>(null);
const isSavingBlog = ref(false);
const workshopError = ref<string | null>(null);
const isJustPublished = ref(false);

const messageFromError = (error: any): string =>
  error?.response?.data?.message || error?.message || ctrans("Unknown error occurred");

const saveDraftBeforeSchedule = async (): Promise<boolean> =>
  await _editorBlogWorkshop.value?.saveNow() ?? true;

// Beefree auto save
const persistBeefreeLayout = async (Jsonlayout: any, compiledLayout: string | null = null) => {
  if (!props.webpage.updateRoute?.name) {
    workshopError.value = ctrans("No update route is configured for this page");
    return;
  }

  workshopError.value = null;
  isSavingBlock.value = true;
  try {
    await axios[props.webpage.updateRoute.method ?? "patch"](
      route(props.webpage.updateRoute.name, props.webpage.updateRoute.parameters),
      {
        layout: {
          data: {
            fieldValue: {
              builderType: "beefree",
              beefree: {
                json: {
                  layout: Jsonlayout
                },
                html: compiledLayout ?? ""
              }
            }
          }
        }
      }
    );
  } catch (error) {
    workshopError.value = ctrans("Failed to save: :message", { message: messageFromError(error) });
  } finally {
    isSavingBlock.value = false;
  }
};


const onBeefreeAutoSave = (jsonFile: any) => {
  persistBeefreeLayout(JSON.parse(jsonFile));
};

const onBeefreeSave = (payload: any) => {
  if (isPublishingBeefree.value) {
    isPublishingBeefree.value = false;
    persistPublishBeefreeLayout(JSON.parse(payload.jsonFile), payload.htmlFile);
    return;
  }

  persistBeefreeLayout(JSON.parse(payload.jsonFile), payload.htmlFile);
};


const persistPublishBeefreeLayout = async (Jsonlayout: any, compiledLayout: string | null = null) => {
  if (!props.webpage.publishRoute?.name) {
    workshopError.value = ctrans("No publish route is configured for this page");
    return;
  }

  workshopError.value = null;
  isSavingBlock.value = true;

  try {
    await axios[props.webpage.publishRoute.method ?? "post"](
      route(props.webpage.publishRoute.name, props.webpage.publishRoute.parameters),
      {
        layout: {
          data: {
            fieldValue: {
              builderType: "beefree",
              beefree: {
                json: {
                  layout: Jsonlayout
                },
                html: compiledLayout ?? ""
              }
            }
          }
        }
      }
    );
    showPublished();
  } catch (error) {
    workshopError.value = ctrans("Failed to publish: :message", { message: messageFromError(error) });
  } finally {
    isSavingBlock.value = false;
  }
};

const showPublished = () => {
  data.value.is_dirty = false;
  _publishScheduleButton.value?.clearSchedule();
  isJustPublished.value = true;
  setTimeout(() => isJustPublished.value = false, 4000);
};

// Publish Actions
const onPublish = async (action: routeType, popover) => {
  try {
    if (!action?.method || !action?.name || !action?.parameters)
      throw new Error("Invalid action parameters");

    isLoadingPublish.value = true;
    workshopError.value = null;

    const response = await axios[action.method](
      route(action.name, action.parameters),
      props.webpage_sub_type === 'mailshot'
        ? { comment: comment.value, layout: data.value.layout }
        : { comment: comment.value, publishLayout: { blocks: data.value.layout } }
    );

    if (response.status === 200) {
      comment.value = "";
      showPublished();
    }
    popover.close();
  } catch (error) {
    workshopError.value = ctrans("Failed to publish: :message", { message: messageFromError(error) });
  } finally {
    isLoadingPublish.value = false;
  }
};

const beforePublish = async (route, popover) => {
  if (props.webpage_sub_type === 'mailshot') {
    isPublishingBeefree.value = true;
    _emailWorkshop.value?.save();
    popover.close();
    return;
  }

  isLoadingPublish.value = true;
  const isSaved = await _editorBlogWorkshop.value?.saveNow() ?? true;
  if (!isSaved) {
    isLoadingPublish.value = false;
    return;
  }

  onPublish(route, popover);
};

const openWebsite = () => window.open(props.url, '_blank');

</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #button-schedule-publish>
      <PublishScheduleButton v-if="publish_schedule" ref="_publishScheduleButton"
        :publishSchedule="publish_schedule" :saveDraft="saveDraftBeforeSchedule" />
    </template>
    <template #button-publish="{ action }">
      <Publish :isLoading="isLoadingPublish" :is_dirty="data.is_dirty" v-model="comment"
        @onPublish="(popover) => beforePublish(action.route, popover)" />
    </template>
    <template #afterTitle v-if="isSavingBlock || isSavingBlog || workshopError || isJustPublished">
      <LoadingIcon v-if="isSavingBlock || isSavingBlog" v-tooltip="ctrans('Saving..')" />
      <span v-else-if="workshopError" class="text-xs font-normal text-red-600">{{ workshopError }}</span>
      <span v-else class="text-xs font-normal text-green-600">{{ ctrans("Published") }}</span>
    </template>
    <template #other>
      <button class="flex items-center gap-1 px-3 py-1 text-sm text-[var(--app-accent)] hover:underline"
        v-tooltip="ctrans('Go to website')" @click="openWebsite">
        <FontAwesomeIcon :icon="faExternalLink" size="lg" fixed-width />
        <span>{{ ctrans('Open Site') }}</span>
      </button>
    </template>
  </PageHeading>
  <ConfirmDialog group="alert-publish">
    <template #icon>
      <FontAwesomeIcon :icon="faExclamationTriangle" class="text-orange-500" fixed-width />
    </template>
  </ConfirmDialog>

  <div v-if="isRefreshingLayout" />
  <EmailWorkshop
    v-else-if="webpage_sub_type === 'mailshot'"
    :snapshot="webpage.layout.web_blocks[0].web_block.layout.data.fieldValue.beefree.json"
    :mergeTags="webpage.mergeTags ?? []"
    :imagesUploadRoute="{ name: 'grp.models.model_has_web_block.images.store', parameters: { modelHasWebBlocks: webpage.layout.web_blocks[0].id } }"
    :organisationSlug="route().params?.organisation"
    :shopSlug="route().params?.shop"
    ref="_emailWorkshop"
    builderType="page"
    @auto-save="onBeefreeAutoSave"
    @onSave="onBeefreeSave"
  />

  <EditorBlogWorkshop v-else ref="_editorBlogWorkshop" v-bind="props" v-model:isSaving="isSavingBlog" />

 
</template>

<style lang="scss" scoped>
</style>
