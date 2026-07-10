<!--
  Author: Raul Perusquia <raul@inikoo.com>
  Created: Wed, 07 Jun 2023
-->

<script setup lang="ts">
import { ref, provide } from "vue";
import axios from "axios";
import { Head } from "@inertiajs/vue3";

// Utils
import { capitalize } from "@/Composables/capitalize";
import { notify } from "@kyvg/vue3-notification";
import { trans } from "laravel-vue-i18n";

// Components
import PageHeading from "@/Components/Headings/PageHeading.vue";
import Publish from "@/Components/Publish.vue";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import ConfirmDialog from 'primevue/confirmdialog';
import Beefree from '@/Components/CMS/Website/Outboxes/Beefree.vue'

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
}>();

provide('isInWorkshop', true);

// State
const data = ref(props.webpage);
const comment = ref("");
const isSavingBlock = ref(false);
const isLoadingPublish = ref(false);
const isBeefreeReady = ref(false);

// Beefree auto save
const persistBeefreeLayout = async (layout: any, compiledLayout: string | null = null) => {
  if (!props.webpage.updateRoute?.name) {
    notify({
      title: trans("Something went wrong"),
      text: trans("No update route is configured for this page"),
      type: "error"
    });
    return;
  }

  isSavingBlock.value = true;

  try {
    await axios[props.webpage.updateRoute.method ?? "patch"](
      route(props.webpage.updateRoute.name, props.webpage.updateRoute.parameters),
      { layout, compiled_layout: compiledLayout }
    );
  } catch (error) {
    notify({
      title: trans("Failed to save"),
      text: error?.response?.data?.message || error.message || "Unknown error occurred",
      type: "error"
    });
  } finally {
    isSavingBlock.value = false;
  }
};

const onBeefreeAutoSave = (jsonFile: any) => {
  persistBeefreeLayout(JSON.parse(jsonFile));
};

const onBeefreeSave = (payload: any) => {
  persistBeefreeLayout(JSON.parse(payload.jsonFile), payload.htmlFile);
};

// Publish Actions
const onPublish = async (action: routeType, popover) => {
  try {
    if (!action?.method || !action?.name || !action?.parameters)
      throw new Error("Invalid action parameters");

    isLoadingPublish.value = true;

    const response = await axios[action.method](
      route(action.name, action.parameters),
      props.webpage_sub_type === 'mailshot'
        ? { comment: comment.value, layout: data.value.layout }
        : { comment: comment.value, publishLayout: { blocks: data.value.layout } }
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

const openWebsite = () => window.open(props.url, '_blank');

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

  <EditorBlogWorkshop v-if="webpage_sub_type === 'blog'" v-bind="props"/>

  <Beefree
    v-if="webpage_sub_type === 'mailshot'"
    :snapshot="webpage"
    :mergeTags="webpage.mergeTags ?? []"
    :updateRoute="webpage.updateRoute"
    :organisationSlug="route().params?.organisation"
    :shopSlug="route().params?.shop"
    ref="_beefree"
    :builderType="'page'"
    @auto-save="onBeefreeAutoSave"
    @onSave="onBeefreeSave"
    @ready="isBeefreeReady = $event"
  />
</template>

<style lang="scss" scoped>
</style>
