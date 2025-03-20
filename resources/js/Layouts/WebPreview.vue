<script setup lang="ts">
// import Notification from '@/Components/Utils/Notification.vue'
// import IrisHeader from '@/Layouts/Iris/Header.vue'
// import NavigationMenu from '@/Layouts/Iris/NavigationMenu.vue'
// import Footer from '@/Layouts/Iris/Footer.vue'
// import { useColorTheme } from '@/Composables/useStockList'
// import { v4 as uuidv4 } from 'uuid';
// import { data as headerData, bluprintForm as bluprintFormHeader} from '@/Components/Websites/Header/HeaderTemplates/Header1/descriptor'
// import { data as footerData, bluprintForm as bluprintFormFooter } from '@/Components/Websites/Footer/FooterTemplates/Footer1/descriptor'
// import { navigation as navigationData } from '@/Components/Websites/Menu/Descriptor'
// import { usePage } from '@inertiajs/vue3'
// import ScreenWarning from '@/Components/Utils/ScreenWarning.vue'
// import { inject } from 'vue'
// import { layoutStructure } from '@/Composables/useLayoutStructure'
// import FamilyPage1 from '@/Components/Iris/AW/FamilyPage1.vue'
// import ProductPage1 from '@/Components/Iris/AW/ProductPage1.vue'


const props = defineProps<{}>()

import { initialiseApp } from "@/Composables/initialiseApp"
import { useLayoutStore } from "@/Stores/layout"
import { provide } from 'vue'


initialiseApp()
provide('layout', useLayoutStore())
</script>

<template>
    <div class="relative">
        <slot></slot>
    </div>
</template>
