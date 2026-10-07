<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { Link } from "@inertiajs/vue3"
  import Table from "@/Components/Table/Table.vue"
  import { Order } from "@/types/order"
  import type { Links, Meta } from "@/types/Table"
  import { useFormatTime } from '@/Composables/useFormatTime'
  import Icon from "@/Components/Icon.vue"
  
  import { faSeedling, faPaperPlane, faWarehouse, faHandsHelping, faBox, faTasks, faShippingFast, faTimesCircle } from '@fal'
  import { library } from '@fortawesome/fontawesome-svg-core'
  library.add(faSeedling, faPaperPlane, faWarehouse, faHandsHelping, faBox, faTasks, faShippingFast, faTimesCircle)
  
  defineProps<{
      data: {
          data: {}[]
          links: Links
          meta: Meta
      },
      tab?: string
  }>()
  
  
  function orderRoute(purgedOrder: {}) {
      switch (route().current()) {
          case "grp.org.shops.show.ordering.purges.show":
              return route(
                  "grp.org.shops.show.ordering.purges.orders",
                  [route().params["organisation"], , route().params["shop"], route().params["purge"], purgedOrder.order_slug])
          default:
              return null
      }
  }
  </script>
  
  <template>
      <Table :resource="data" :name="tab" class="mt-5">
          <template #cell(order_reference)="{ item: purgedOrder }">
              <Link :href="orderRoute(purgedOrder)" class="primaryLink">
                  {{ purgedOrder["order_reference"] }}
              </Link>
          </template>
      </Table>
  </template>
  