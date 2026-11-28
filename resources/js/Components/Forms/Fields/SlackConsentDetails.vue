<script setup lang="ts">
import { ctrans } from '@/Composables/useTrans'

const terms = [
    'Verba is provided by the company as a work tool. Messages, files and pictures in Verba are company records.',
    'Use Verba for work. Occasional social messages are allowed. Do not use it for private or non-work conversations, pictures or files. Offensive, discriminatory, harassing, sexual or illegal content breaks company rules and may lead to disciplinary action.',
    'By connecting your Slack, you agree that Verba copies, and keeps up to date, the messages and files you wrote in your direct messages and group chats, and the private channels you are a member of.',
    'Each conversation can be seen only by its members. The company does not monitor conversations.',
    'Anyone else may open a direct message, a deleted message or a message kept for a legal reason only to meet a legal obligation or for a formal investigation. This needs the approval of two group administrators and is recorded. The members are told, unless the law forbids it.',
    'When you delete a message, it disappears from the conversation for everyone. A record of it is kept, and nobody can see it unless the law requires it or for a formal investigation, and only if two group administrators both approve.',
    'Messages are kept as company records. Messages in public and private channels stay. After you leave the company, you can ask for your own words in direct messages and group chats to be removed. A group administrator decides. They are removed at once, unless a legal reason (a dispute or claim, an investigation or a legal duty) requires keeping them; then they are kept hidden until that reason ends, at most 6 years. You are told either way. To ask, contact the help desk. While you work here, you can read, edit and delete your own messages in Verba yourself.',
    'Messages are protected like the most sensitive data we hold, such as passwords and login details: only the people in a conversation can open it, and the tools developers and AI use cannot read them. They are stored in a secure data centre in Finland, away from our offices, with backups kept in other secure locations in the European Union. They are not end-to-end encrypted, but they cannot be reached from outside: the database is closed to the internet, and only a few people can get into the servers, each with their own security key, never with a password, and every access to the servers is recorded.',
    'These terms may change. You will be told before they do.',
]
</script>

<template>
    <div class="space-y-3 text-xs text-gray-600">
        <p><span class="font-semibold text-gray-800">{{ ctrans('Your direct messages and private groups can be seen only by the people in them. Not your manager, not administrators, and not the developers: their tools can\'t read your messages.') }}</span> {{ ctrans('That holds wherever it appears in Verba: the chat, search, alerts and AI tools.') }}</p>
        <p><span class="font-semibold text-gray-800">{{ ctrans('Nobody else opens your messages unless the law requires it, or for a formal investigation, and only if two group administrators both approve.') }}</span> {{ ctrans('It is recorded, and you are told, unless the law forbids it.') }}</p>
        <p><span class="font-semibold text-gray-800">{{ ctrans('When you leave the company, you can ask for your words to be removed.') }}</span> {{ ctrans('Your own words in direct messages and group chats. Private channels stay, because they belong to everyone in them. Otherwise messages are kept as work records.') }}</p>
        <p><span class="font-semibold text-gray-800">{{ ctrans('aiku is open source and auditable.') }}</span> {{ ctrans('Anyone can read its code, as it is today and as it was at any time in the past, and check how Verba handles your messages:') }} <a href="https://github.com/Inikoo-Ltd/aiku" target="_blank" rel="noopener" class="underline">github.com/Inikoo-Ltd/aiku</a></p>
        <p>{{ ctrans('Here\'s what Verba will keep, and keep up to date from now on:') }}</p>
        <ul class="list-disc pl-5 space-y-1">
            <li>{{ ctrans('Your own messages and files in direct messages and group chats. Only your words: the others\' messages come over only when they bring them themselves. Until then you\'ll see a little gap where their message was.') }}</li>
            <li>{{ ctrans('The private channels you\'re in, as a whole. They\'re shared work spaces, so everyone in the channel will see that you brought it.') }}</li>
        </ul>
        <p class="italic">{{ ctrans('A quick reminder: Verba is the company\'s work chat. Birthday wishes and the odd "well done" are very welcome, just keep it work-friendly. If an old Slack conversation has something private or non-work in it, delete those messages in Slack before you bring it over.') }}</p>
        <ol class="list-decimal pl-5 space-y-1">
            <li v-for="term in terms" :key="term">{{ ctrans(term) }}</li>
        </ol>
    </div>
</template>
