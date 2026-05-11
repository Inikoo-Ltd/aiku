# Frontend Instructions

## Vue files
- always SnakeCase for components names
- inside <template></template>, always translate the text using ctrans function (example: ctrans('hello :customerName', { customerName: customer.name })), to ensure that all text is translatable and consistent across the application. ctrans is global import so no need import if used inside <template></template> in .vue file
- inside <script setup lang="ts"></script>, need to import the ctrans function, example: `import { ctrans } from "@/Composables/useTrans"`

