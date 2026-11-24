<!--
  - Author Louis Perez
  - Created on 24-11-2026-16h-20m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { defineAsyncComponent } from "vue"
import Dialog from "primevue/dialog"
import { ctrans } from "@/Composables/useTrans"
import { isAllNotificationsOpen } from "@/Composables/useRailNotifications"

const AllNotifications = defineAsyncComponent(() => import("@/Components/Notifications/AllNotifications.vue"))
</script>

<template>
    <Dialog
        v-model:visible="isAllNotificationsOpen"
        modal
        dismissableMask
        :draggable="false"
        :header="ctrans('Notifications')"
        :style="{ width: 'min(68rem, 95vw)' }">
        <AllNotifications v-if="isAllNotificationsOpen" />
    </Dialog>
</template>
