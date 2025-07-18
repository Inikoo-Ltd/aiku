<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core";
import Tag from "@/Components/Tag.vue";
import { Link } from "@inertiajs/vue3";
import { faYinYang, faDotCircle, faCheck, faPlus} from "@fal";
import Button from "@/Components/Elements/Buttons/Button.vue";


library.add(faCheck,faYinYang, faDotCircle)

const props = defineProps<{
    data: object
    tab?: string,
    routes: {
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    }
    isCheckBox?: boolean
}>()



const emits = defineEmits<{
    (e: "selectedRow", value: {}): void
}>()


function familyRoute(family): string {
  const current = route().current()
  if (current === "retina.catalogue.families.index") {
    return route("retina.catalogue.families.show", [family.slug])
  }
  return route("retina.catalogue.families.show", [family.slug])
}


</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(state)="{ item: family }">
            <Tag :label="family.state.tooltip" v-tooltip="family.state.tooltip">
                <template #label>
                    <Icon :data="family.state" /> <span :class="family.state.class">{{ family.state.tooltip }}</span>
                </template>
            </Tag>
        </template>
        <template #cell(code)="{ item: family }">
             <Link :href="familyRoute(family)" class="primaryLink">
            {{ family["code"] }}
            </Link>
        </template>
        <template #cell(shop_code)="{ item: family }">
            <!--  <Link :href="shopRoute(family)" class="secondaryLink"> -->
            {{ family["shop_code"] }}
            <!--   </Link> -->
        </template>
        <template #cell(current_products)="{ item: family }">
            <!--       <Link :href="productRoute(family)" class="primaryLink"> -->
            {{ family["current_products"] }}
            <!--  </Link> -->
        </template>

        <!-- Column: Department code -->
        <template #cell(department_code)="{ item: family }">
            <!--  <Link v-if="family.department_slug" :href="departmentRoute(family)" class="secondaryLink"> -->
            {{ family["department_code"] }}
            <!-- </Link> -->
        </template>

        <!-- Column: Department name -->
        <template #cell(department_name)="{ item: family }">
            <!-- <Link v-if="family.department_slug" :href="departmentRoute(family)" class="secondaryLink"> -->
            {{ family["department_name"] }}
            <!--  </Link> -->
        </template>

        <template #cell(product_categories)="{ item: family }">
            <!-- <Link v-if="family.department_slug" :href="departmentRoute(family)" class="secondaryLink">
                {{ family["department_code"] }}
            </Link> -->
        </template>
        <template #cell(sub_department_name)="{ item: family }">
            <!-- <Link v-if="family.sub_department_slug" :href="subDepartmentRoute(family)" class="secondaryLink"> -->
            {{ family["sub_department_code"] }}
            <!--       </Link> -->
        </template>

         <template #cell(actions)="{ item: family }">
           <RetinaButtonAddPortofolio />
        </template>
    </Table>
</template>
