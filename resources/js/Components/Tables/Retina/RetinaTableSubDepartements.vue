<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import Tag from "@/Components/Tag.vue"


const props = defineProps<{
    data: object
    tab?: string,
}>()


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: SubDepartment }">
            <Icon :data="SubDepartment.state">
            </Icon>
        </template>
        <template #cell(code)="{ item: SubDepartment }">
       <!--      <Link :href="subDepartmentRoute(SubDepartment)" class="primaryLink"> -->
            {{ SubDepartment["code"] }}
           <!--  </Link> -->
        </template>
        <template #cell(shop_code)="{ item: family }">
        <!--     <Link :href="shopRoute(family)" class="secondaryLink"> -->
            {{ family["shop_code"] }}
           <!--  </Link> -->
        </template>
        <template #cell(department_code)="{ item: family }">
          <!--   <Link v-if="family.department_slug" :href="departmentRoute(family)" class="secondaryLink"> -->
            {{ family["department_code"] }}
         <!--    </Link> -->
        </template>
    </Table>
</template>
