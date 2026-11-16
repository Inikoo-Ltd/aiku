<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Nov 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { closeGuruWelcome, guruWelcomeOpen } from "@/Composables/useAssistantQuestions"
</script>

<template>
    <Dialog
        :visible="guruWelcomeOpen"
        modal
        :closable="false"
        :header="ctrans('You are one of Merlin\'s gurus')"
        :style="{ width: '34rem' }"
        :breakpoints="{ '640px': '95vw' }">
        <div class="space-y-3 text-sm text-gray-700">
            <p>{{ ctrans("Merlin is the assistant that answers our customers on the website, day and night. It knows our catalogue and our guides, but some questions need what only years in this business teach: how a product really behaves, what a customer really means, what works in practice.") }}</p>
            <p class="font-medium text-gray-900">{{ ctrans("That is why we chose you. When Merlin is not sure, it would rather ask you than guess.") }}</p>
            <ul class="list-disc space-y-1.5 pl-5">
                <li>{{ ctrans("A small window appears when a customer asks something Merlin cannot answer. Write the answer in your own words, in your language: Merlin passes it to the customer in theirs.") }}</li>
                <li>{{ ctrans("When the answer is good for every customer of the shop, tick the box: once a second guru agrees, Merlin uses it for the next customer who asks. You answer once; many customers are helped.") }}</li>
                <li>{{ ctrans("Merlin's researcher sometimes prepares a draft from our data. Check it, improve it, then send it.") }}</li>
                <li>{{ ctrans("Each morning you get one message: the questions still waiting, a few answers to check, and how many customers your answers helped.") }}</li>
                <li>{{ ctrans("Not your area? Pass the question to the colleague who knows.") }}</li>
            </ul>
            <p>{{ ctrans("Thank you. Your experience is what makes Merlin worth talking to.") }}</p>
        </div>
        <template #footer>
            <div class="flex w-full flex-col items-center gap-2">
                <Button type="tertiary" full :label="ctrans('Count me in')" @click="closeGuruWelcome()" />
                <button type="button" class="text-xs text-gray-400 hover:underline" @click="closeGuruWelcome(true)">
                    {{ ctrans("I don't think I'm the right person") }}
                </button>
            </div>
        </template>
    </Dialog>
</template>
