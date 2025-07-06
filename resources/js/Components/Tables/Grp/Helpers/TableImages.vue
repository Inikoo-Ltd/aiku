<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 06 Jul 2025 14:45:03 British Summer Time, Sheffield, UK
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { routeType } from "@/types/route"
import { faTrashAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Components/Image.vue"

library.add(faTrashAlt)

defineProps<{
    data: object,
    tab?: string,
    detachRoute?: routeType
}>()






</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">

        <template #cell(image)="{ item: image }">
                <Image :src="image['image']"  />
        </template>


        <template #cell(scope)="{ item: image }">
            {{ image["scope"] }}
        </template>
        <template #cell(caption)="{ item: image }">
            {{ image["caption"] }}
        </template>
        <template #cell(name)="{ item: image }">
            <pre>{{ image.data}}</pre>
        </template>



    </Table>
</template>
