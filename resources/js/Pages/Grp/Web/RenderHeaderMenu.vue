<script setup lang='ts'>
import { getComponent } from '@/Composables/getWorkshopComponents'
import { getIrisComponent } from '@/Composables/getIrisComponents'
import { sendMessageToParent } from '@/Composables/Workshop';
import MobileHeader from "@/Components/CMS/Website/Headers/MobileHeader.vue";
import { getStyles } from "@/Composables/styles";

const props = defineProps<{
    data: {
        topBar: {
            code : string
            data : {
                fieldValue : object
            }
        },
        header : {
            data : {
                fieldValue : object
            }
        }
    }
    menu: {
        key: string,
        data: object,
        menu : {
            code : string
            fieldValue : object
        }
    }
    screenType: 'mobile' | 'tablet' | 'desktop'
    loginMode:Boolean
    previewMode?:Boolean
}>()

const { mode } = route().params;

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

</script>

<template>
        <!-- Section: TopBars -->
        <component
            v-if="data?.topBar?.data?.fieldValue"
            :is="getComponent(data?.topBar.code)"
            v-model="data.topBar.data.fieldValue"
            :loginMode="loginMode"
            :fieldValue="data.topBar.data.fieldValue"
            @update:model-value="(e)=>emits('update:modelValue', e)"
            @setPanelActive="(data : string)=>sendMessageToParent('TopbarPanelOpen',data)"
        />

        <!-- Section: Header -->
        <component
            v-if="data?.header?.code"
            :is="mode == 'iris' ? getIrisComponent(data?.header?.code) : getComponent(data?.header?.code)"
            v-model="data.header.data.fieldValue"
            :loginMode="loginMode"
            :fieldValue="data.header.data.fieldValue"
             @update:model-value="(e)=>emits('update:modelValue', e)"
             @setPanelActive="(data : string)=>sendMessageToParent('HeaderPanelOpen',data)"
             :screenType="screenType"
              class="hidden md:block"
        />

        <!-- Section: Menu -->
        <component
            v-if="menu?.menu?.data"
            :is="getComponent(menu?.menu.code)"
            :fieldValue="menu?.menu?.data.fieldValue"
            :screenType="screenType"
             class="hidden md:block"
        />

         <!-- Section: mobile -->
          <div :style="getStyles(data.header.data.fieldValue.container.properties, screenType)">
            <MobileHeader 
                :header-data="data.header.data.fieldValue" 
                :menu-data="menu?.menu?.data.fieldValue" 
                :screenType="screenType" 
            />
        </div>
</template>
