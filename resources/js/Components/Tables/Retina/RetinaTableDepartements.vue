<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import Icon from "@/Components/Icon.vue";
import { ref } from "vue";
import { faSeedling } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(faSeedling);

defineProps<{
    data: {}
    tab?: string
}>();


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">

        <template #cell(organisation_code)="{ item: department }">
           <!--  <Link v-tooltip='department["organisation_name"]' :href="organisationRoute(department)" class="secondaryLink"> -->
                {{ department["organisation_code"] }}
           <!--  </Link> -->
        </template>

        <template #cell(shop_code)="{ item: department }">
          <!--   <Link v-tooltip='department["shop_name"]' :href="departmentsInShopRoute(department) as string" class="secondaryLink"> -->
                {{ department["shop_code"] }}
      <!--       </Link> -->
        </template>

        <template #cell(state)="{ item: department }">
            <Icon :data="department.state">
            </Icon>
        </template>
        <template #cell(code)="{ item: department }">
          <!--   <Link :href="departmentRoute(department) as string" class="primaryLink"> -->
                {{ department["code"] }}
         <!--    </Link> -->
        </template>
        <template #cell(number_current_families)="{ item: department }">
           <!--  <Link :href="familyRoute(department) as string" class="secondaryLink"> -->
                {{ department["number_current_families"] }}
            <!-- </Link> -->
        </template>
        <template #cell(number_current_sub_departments)="{ item: department }">
          <!--   <Link :href="subDepartmentsRoute(department) as string" class="secondaryLink"> -->
                {{ department["number_current_sub_departments"] }}
        <!--     </Link> -->
        </template>
        <template #cell(number_current_collections)="{ item: department }">
            <!-- <Link :href="subCollectionsRoute(department) as string" class="secondaryLink"> -->
                {{ department["number_current_collections"] }}
          <!--   </Link> -->
        </template>
        <template #cell(number_current_products)="{ item: department }">
            <!-- <Link :href="productRoute(department) as string" class="secondaryLink"> -->
                {{ department["number_current_products"] }}
      <!--       </Link> -->
        </template>
    </Table>
</template>
