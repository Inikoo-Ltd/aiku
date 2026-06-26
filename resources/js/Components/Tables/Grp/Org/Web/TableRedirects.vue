<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Table from "@/Components/Table/Table.vue"
import { faSkull } from "@fal";
import { Link, router } from '@inertiajs/vue3';
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue";
import { ref } from "vue";
import { notify } from "@kyvg/vue3-notification";
import Toggle from "@/Components/Pure/Toggle.vue";

defineProps<{
    data: {}
    tab?: string
}>()

const isLoadingDelete = ref(null);

function webpageRoute(data: {}) {

    switch (route().current()) {
      case 'grp.org.shops.show.web.redirect.index':
        return route(
            'grp.org.shops.show.web.webpages.show',
            [
                route().params['organisation'],
                route().params['shop'],
                route().params['website'],
                data.to_webpage_slug
            ]);
    }
}

function editRedirect(data: {}) {

    switch (route().current()) {
        case 'grp.org.fulfilments.show.web.webpages.show':
            return route(
                'grp.org.fulfilments.show.web.webpages.redirect.edit',
                {
                    organisation: route().params['organisation'],
                    fulfilment: route().params['fulfilment'],
                    website: route().params['website'],
                    webpage: route().params['webpage'],
                    redirect: data.id,
                }
            )
        case 'grp.org.fulfilments.show.web.redirect.index':
            return route(
                'grp.org.fulfilments.show.web.redirect.edit',
                {
                    organisation: route().params['organisation'],
                    fulfilment: route().params['fulfilment'],
                    website: route().params['website'],
                    redirect: data.id,
                }
            )
        case 'grp.org.shops.show.web.webpages.show':
            return route(
                'grp.org.shops.show.web.webpages.redirect.edit',
                {
                    organisation: route().params['organisation'],
                    shop: route().params['shop'],
                    website: route().params['website'],
                    webpage: route().params['webpage'],
                    redirect: data.id,
                }
            )
        case 'grp.org.shops.show.web.redirect.index':
            return route(
                'grp.org.shops.show.web.redirect.edit',
                {
                    organisation: route().params['organisation'],
                    shop: route().params['shop'],
                    website: route().params['website'],
                    redirect: data.id,
                }
            )
    }
}

const deleteRedirect = (item) => {
    router.delete(route('grp.models.redirect.delete', {
        redirect: item.id
    }), {
        onStart: () => {
            isLoadingDelete.value = item.id
        },
        onSuccess: () => {
            notify({
            title: trans("Success"),
            text: trans("Redirect has been deleted"),
            type: "success",
            })
        },
        onError: (err) => {
            notify({
                title: trans("Fail"),
                text: trans("Fail to delete redirect"),
                type: "error",
            })
        },
        onFinish: () => {
            isLoadingDelete.value = null;
        }
    })
}

// console.log('dddd', route())
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(to_webpage_url)="{ item: data }">
            <!-- <pre>{{ data }}</pre> -->
            <Link v-if="data.to_webpage_code" :href="webpageRoute(data)" class="primaryLink">
                {{ data['to_webpage_code'] }}
            </Link>
            <div v-else class="text-gray-400 italic">
                {{ trans("No target webpage") }}
            </div>
        </template>

        <template #cell(path)="{ item: data }">
            {{ data.full_path }} 
            <span class="italic" v-if="data.full_path != data.path">
                <br>
                ({{ data.path }})
            </span>
        </template>
        
        <template #cell(actions_from_website)="{ item: data }">
            <div class="flex align-items-center">
                <ButtonWithLink
                    v-tooltip="trans('Edit redirect')"
                    type="edit"
                    :url="editRedirect(data)"
                    size="sm"
                />
                <Button
                    v-tooltip="trans('Delete redirect')"
                    @click="deleteRedirect(data)"
                    :icon="faSkull"
                    :style="'negative'"
                    size="sm"
                    class="ml-2"
                    :loading="isLoadingDelete == data.id"
                />
            </div>
        </template>
    </Table>
</template>
