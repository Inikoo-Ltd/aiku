<script setup lang='ts'>
import { getIrisComponent } from '@/Composables/getIrisComponents'
import { routeType } from "@/types/route"
import MobileMenu from '@/Components/MobileMenu.vue'
import Menu from 'primevue/menu'
import { ref, inject, provide } from 'vue'
import { faUserCircle } from '@fal'
import { router } from '@inertiajs/vue3'
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Image from '@/Components/Image.vue'
import axios from 'axios';


const props = defineProps<{
    data: {
        key: string,
        data: object,
        blueprint: object
        loginRoute?: routeType
    }
    menu: {
        key: string,
        data: object,
        blueprint: object
    }
    colorThemed: object
}>()
const _menu = ref();
const toggle = (event) => {
    _menu.value.toggle(event)
};

const layout = inject('layout', {})
const isLoggedIn = ref(layout.iris.user_auth ? true : false)
provide('isPreviewLoggedIn', isLoggedIn)

const onLogoutAuth = async (link) => {
    try {
        await axios.post(route('iris.logout'));
        window.location.reload();
    } catch (error) {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to logout"),
            type: "error"
        });
    }
};


provide('onLogout', onLogoutAuth)

</script>

<template>
    <!-- Section: Header-Topbar -->
    <component v-if="data?.topBar?.data.fieldValue" :is="getIrisComponent(data?.topBar.code)"
        :fieldValue="data.topBar.data.fieldValue" v-model="data.topBar.data.fieldValue" class="hidden md:block" />

    <!-- Section: Header-Menu -->
    <component :is="getIrisComponent(data?.header?.code)" :fieldValue="data.header.data.fieldValue"
        class="hidden md:block" />

    <!-- <pre>{{ menu.data }}</pre> -->
    <component v-if="menu?.code" :is="getIrisComponent(menu?.code)" :navigations="menu.data.fieldValue.navigation"
        :colorThemed="colorThemed" class="hidden md:block" />

        
    <div class="block md:hidden p-3">
        <div class="flex justify-between items-center">
            <MobileMenu :header="data.header.data.fieldValue" :menu="menu?.data?.fieldValue?.navigation" />
            <!-- Logo for Mobile -->
            <!-- <pre> {{ data.header.data.fieldValue?.logo.image.source }}</pre>  -->
            <Image
                v-if="data?.header?.data?.fieldValue?.logo?.image?.source?.original"
                :src="data?.header?.data?.fieldValue?.logo?.image?.source"
                class="h-10 mx-2"
                :alt="data?.header?.data?.fieldValue?.logo?.alt"
            />

            <!-- Profile Icon with Dropdown Menu -->
            <div @click="toggle" class="flex items-center cursor-pointer">
                <FontAwesomeIcon :icon="faUserCircle" class="text-2xl" />
                <!-- <Menu ref="_menu" id="overlay_menu" :model="items" :popup="true">
                    <template #itemicon="{ item }">
                        <FontAwesomeIcon :icon="item.icon" />
                    </template>
                </Menu> -->
            </div>
        </div>

        <!-- Mobile Search Bar -->
        <!--  <div class="relative mt-2">
                <input type="text" placeholder="Search Products"
                    class="border border-gray-300 py-2 px-4 rounded-md w-full shadow-inner focus:outline-none focus:border-gray-500">
                <FontAwesomeIcon icon="fas fa-search" class="absolute top-1/2 -translate-y-1/2 right-4 text-gray-500"
                    fixed-width />
            </div> -->
    </div>
</template>