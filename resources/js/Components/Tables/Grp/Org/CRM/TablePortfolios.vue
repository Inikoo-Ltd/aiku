<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue";
import { useFormatTime } from "@/Composables/useFormatTime";
import { useLocaleStore } from "@/Stores/locale";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { ref } from "vue";
import { routeType } from "@/types/route";
import { notify } from "@kyvg/vue3-notification";
import { faTrashAlt } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Portfolio } from "@/types/portfolio";

library.add(faTrashAlt);

defineProps<{
  data: {}
  tab?: string
}>();

const locale = useLocaleStore();

function itemRoute(portfolio: Portfolio) {
  return route(
    "grp.helpers.redirect_portfolio_item",
    [portfolio.item_id]);


}

const isDeleteLoading = ref<boolean | string>(false);
const onDeletePortfolio = async (routeDelete: routeType, portfolioReference: string) => {
  isDeleteLoading.value = portfolioReference;
  try {
    router[routeDelete.method || "get"](route(routeDelete.name, routeDelete.parameters));
    notify({
      title: "Success",
      text: `Portfolio ${portfolioReference} has been deleted`,
      type: "success"
    });
  } catch {
    notify({
      title: "Something went wrong.",
      type: "error"
    });
  }
};

</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(product_code)="{ item: portfolio }">
      <Link :href="itemRoute(portfolio)" class="primaryLink">
        {{ portfolio["product_code"] }}
      </Link>
    </template>


    <template #cell(location)="{ item: portfolio }">
      <AddressLocation :data="portfolio['location']" />
    </template>

    <template #cell(created_at)="{ item: portfolio }">
      <div class="text-gray-500">{{ useFormatTime(portfolio["created_at"], {
        localeCode: locale.language.code,
        formatTime: "Ymd"
      }) }}
      </div>
    </template>

    <template #cell(action)="{ item: portfolio }">
      <Button @click="() => onDeletePortfolio(portfolio.routes.delete_route, portfolio.slug)" :key="portfolio.slug"
              icon="fal fa-trash-alt" type="negative" :disabled="isDeleteLoading === portfolio.slug"
              :loading="isDeleteLoading === portfolio.slug" />
    </template>
  </Table>
</template>
