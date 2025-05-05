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
    } catch {
        console.error('error onLogoutAuth')
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
    <!-- Section: Topbar -->
    <component v-if="data?.topBar?.data.fieldValue" :is="getIrisComponent(data?.topBar.code)"
        :fieldValue="data.topBar.data.fieldValue" v-model="data.topBar.data.fieldValue" class="hidden md:block" />

    <!-- Section: Header -->
    <component :is="getIrisComponent(data?.header?.code)" :fieldValue="data.header.data.fieldValue"
        class="hidden md:block" />

    <!-- Section: Menu desktop -->
    <component v-if="menu?.code" :is="getIrisComponent(menu?.code)" :navigations="menu.data.fieldValue.navigation"
        :colorThemed="colorThemed" class="hidden md:block" />

    <!-- Section: Menu mobile -->
    <div class="block md:hidden p-3">
        <div class="flex justify-between items-center">
            <MobileMenu :header="data.header.data.fieldValue" :menu="menu?.data?.fieldValue?.navigation" />
            <Image
                v-if="data?.header?.data?.fieldValue?.logo?.image?.source?.original"
                :src="data?.header?.data?.fieldValue?.logo?.image?.source"
                class="h-10 mx-2"
                :alt="data?.header?.data?.fieldValue?.logo?.alt"
            />

            <!-- Profile Icon with Dropdown Menu -->
            <div @click="toggle" class="flex items-center cursor-pointer">
                <FontAwesomeIcon :icon="faUserCircle" class="text-2xl" />
            </div>
        </div>
    </div>
</template>