<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import Table from "@/Components/Table/Table.vue";
  import { routeType } from "@/types/route";
  import { faTrashAlt } from '@fal'
  import { library } from '@fortawesome/fontawesome-svg-core'
  library.add(faTrashAlt)
  
  const props = defineProps<{
      data: object,
      tab?: string,
      detachRoute?: routeType
  }>();
  
console.log(props)
  </script>
  
  <template>
      <Table :resource="data" :name="tab" class="mt-5">
      </Table>
  </template>
  