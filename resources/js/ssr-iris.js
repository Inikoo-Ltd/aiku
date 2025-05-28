/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Sep 2023 21:16:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { createSSRApp, h } from "vue";
import { renderToString } from "@vue/server-renderer";
import { createInertiaApp } from "@inertiajs/vue3";
import createServer from "@inertiajs/vue3/server";
import { ZiggyVue } from "../../vendor/tightenco/ziggy/dist/vue.m";
import { createPinia } from "pinia";
import Notifications from "@kyvg/vue3-notification";
import FloatingVue from "floating-vue";
import "floating-vue/dist/style.css";
import PrimeVue from "primevue/config";
import Aura from "@primevue/themes/aura";
import { definePreset } from "@primevue/themes";

const pinia = createPinia();

const appName = "iris";

const MyPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50 : "{gray.50}",
      100: "{gray.100}",
      200: "{gray.200}",
      300: "{gray.300}",
      400: "{gray.400}",
      500: "{gray.500}",
      600: "{gray.600}",
      700: "{gray.700}",
      800: "{gray.800}",
      900: "{gray.900}",
      950: "{gray.950}"
    }
  }
});

createServer(
  (page) =>
    createInertiaApp(
      {
        page,
        render : renderToString,
        title  : (title) => `${title}`,
        resolve: name => {
          const pages = import.meta.glob(
            "./Pages/Iris/**/*.vue",
            { eager: true });
          let page = pages[`./Pages/Iris/${name}.vue`];

          return page;
        },
        setup({ App, props, plugin }) {
          return createSSRApp(
            { render: () => h(App, props) }).
            use(Notifications).
            use(FloatingVue).
            use(PrimeVue, {
              theme: {
                preset : MyPreset,
                options: {
                  darkModeSelector: ".my-app-dark"  // dark mode of Primevue
                                                     // depends .my-add-dark in
                                                     // <html>
                }
              }
            }).
            use(plugin).
            use(pinia).
            use(ZiggyVue, {
              ...page.props.ziggy,
              location: new URL(
                page.props.ziggy.location)
            });
        }
      }), import.meta.env.VITE_INERTIA_SSR_PORT ?? 13714
);
