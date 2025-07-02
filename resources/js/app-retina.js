/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Feb 2024 10:42:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

import { BrowserAgent } from "@newrelic/browser-agent/loaders/browser-agent";
import "./bootstrap";
import "../css/app.css";

import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { ZiggyVue } from "../../vendor/tightenco/ziggy/dist/vue.m";
import { i18nVue } from "laravel-vue-i18n";
import Notifications from "@kyvg/vue3-notification";
import { createPinia } from "pinia";
import * as Sentry from "@sentry/vue";
import FloatingVue from "floating-vue";
import "floating-vue/dist/style.css";
import Layout from "@/Layouts/Retina.vue";
import PrimeVue from "primevue/config";
import Aura from "@primevue/themes/aura";
import { definePreset } from "@primevue/themes";
import ConfirmationService from "primevue/confirmationservice";

if (import.meta.env.VITE_NEW_RELIC_BROWSER_RETINA_AGENT_ID) {
  const options = {
    init         : {
      distributed_tracing: { enabled: true },
      privacy            : { cookies_enabled: true },
      ajax               : { deny_list: ["bam.nr-data.net"] }
    },
    info         : {
      beacon       : "bam.nr-data.net",
      errorBeacon  : "bam.nr-data.net",
      licenseKey   : import.meta.env.VITE_NEW_RELIC_BROWSER_LICENCE_KEY,
      applicationID: import.meta.env.VITE_NEW_RELIC_BROWSER_RETINA_AGENT_ID,
      sa           : 1
    },
    loader_config: {
      accountID    : import.meta.env.VITE_NEW_RELIC_BROWSER_ACCOUNT_ID,
      trustKey     : import.meta.env.VITE_NEW_RELIC_BROWSER_ACCOUNT_ID,
      agentID      : import.meta.env.VITE_NEW_RELIC_BROWSER_RETINA_AGENT_ID,
      licenseKey   : import.meta.env.VITE_NEW_RELIC_BROWSER_LICENCE_KEY,
      applicationID: import.meta.env.VITE_NEW_RELIC_BROWSER_RETINA_AGENT_ID
    }
  };

  new BrowserAgent(options);
}

const MyPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50 : "{stone.50}",
      100: "{stone.100}",
      200: "{stone.200}",
      300: "{stone.300}",
      400: "{stone.400}",
      500: "{stone.500}",
      600: "{stone.600}",
      700: "{stone.700}",
      800: "{stone.800}",
      900: "{stone.900}",
      950: "{stone.950}"
    }
  }
});

createInertiaApp(
  {
    resolve: async name => {
      const pages = import.meta.glob("./Pages/Retina/**/*.vue");
      if (!pages) console.error(
        `File './Pages/Retina/${name}.vue' is not exist`);
      let page = await pages[`./Pages/Retina/${name}.vue`]();
      page.default.layout = page.default?.layout || Layout;
      return page;
    },
    setup({ el, App, props, plugin }) {
      const app = createApp({ render: () => h(App, props) });
      if (import.meta.env.VITE_SENTRY_DSN) {
        Sentry.init({
                      app,
                      dsn                     : import.meta.env.VITE_SENTRY_DSN,
                      environment             : import.meta.env.VITE_APP_ENV,
                      release                 : import.meta.env.VITE_RELEASE,
                      replaysSessionSampleRate: 0.1,
                      replaysOnErrorSampleRate: 1.0,
                      integrations            : [new Sentry.Replay()]
                    });
      }

      // To see Vue filename in console (component.vue())
      if (import.meta.env.VITE_APP_ENV === 'local') {
        window.component = {
          vue: () => {
            return props.initialPage.component
          }
        }
      }

      app.use(plugin).
        use(createPinia()).
        use(ZiggyVue, Ziggy).
        use(Notifications).
        use(FloatingVue).
        use(ConfirmationService).
        use(PrimeVue, {
          theme: {
            preset : MyPreset,
            options: {
              darkModeSelector: ".my-primevue-dark"  // dark mode of Primevue
                                                      // depends .my-add-dark
                                                      // in <html>
            }
          }
        }).
        use(i18nVue, {
          resolve: async (lang) => {
            const languages = import.meta.glob(
              "../../lang/*.json");
            return await languages[`../../lang/${lang}.json`]();
          }
        }).
        mount(el);

    },
    progress: {
      color: "#4B5563"
    }
  });
