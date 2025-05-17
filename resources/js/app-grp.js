/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Feb 2025 02:01:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

import { BrowserAgent } from "@newrelic/browser-agent/loaders/browser-agent";
import "./bootstrap";
import "../css/app.css";
import { createApp, h } from "vue";
import { createInertiaApp, router } from "@inertiajs/vue3";
import { ZiggyVue } from "../../vendor/tightenco/ziggy/dist/vue.m";
import { i18nVue } from "laravel-vue-i18n";
import Notifications from "@kyvg/vue3-notification";
import { createPinia } from "pinia";
import * as Sentry from "@sentry/vue";
import FloatingVue from "floating-vue";
import "floating-vue/dist/style.css";
import Layout from "@/Layouts/Grp.vue";
import PrimeVue from "primevue/config";
import Aura from "@primevue/themes/aura";
import { definePreset } from "@primevue/themes";
import ConfirmationService from "primevue/confirmationservice";

if (import.meta.env.VITE_NEW_RELIC_BROWSER_GRP_AGENT_ID) {
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
      applicationID: import.meta.env.VITE_NEW_RELIC_BROWSER_GRP_AGENT_ID,
      sa           : 1
    },
    loader_config: {
      accountID    : import.meta.env.VITE_NEW_RELIC_BROWSER_ACCOUNT_ID,
      trustKey     : import.meta.env.VITE_NEW_RELIC_BROWSER_ACCOUNT_ID,
      agentID      : import.meta.env.VITE_NEW_RELIC_BROWSER_GRP_AGENT_ID,
      licenseKey   : import.meta.env.VITE_NEW_RELIC_BROWSER_LICENCE_KEY,
      applicationID: import.meta.env.VITE_NEW_RELIC_BROWSER_GRP_AGENT_ID
    }
  };

  new BrowserAgent(options);
}

const appName = "aiku";

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

createInertiaApp(
  {
    title  : (title) => `${title} - ${appName}`,
    resolve: async name => {
      const pages = import.meta.glob("./Pages/Grp/**/*.vue");
      if (!pages) console.error(
        `File './Pages/Grp/${name}.vue' is not exist`);
      let page = await pages[`./Pages/Grp/${name}.vue`]();
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
                      debug                   : false,
                      tracesSampleRate        : 1.0,
                      replaysSessionSampleRate: 0.1,
                      replaysOnErrorSampleRate: 1.0,
                      profilesSampleRate      : 1.0,
                      integrations            : [
                        new Sentry.BrowserTracing({
                                                    routingInstrumentation: inertiaRoutingInstrumentation,
                                                    enableInp             : true
                                                  }),
                        Sentry.replayIntegration(),
                        Sentry.httpClientIntegration(),
                        Sentry.browserTracingIntegration(),
                        Sentry.browserProfilingIntegration()

                      ]
                    });
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
              darkModeSelector: ".my-app-dark"  // dark mode of Primevue
              // depends .my-add-dark in
              // <html>
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

//https://github.com/getsentry/sentry-javascript/issues/11362
function inertiaRoutingInstrumentation(
  customStartTransaction,
  startTransactionOnPageLoad       = true,
  startTransactionOnLocationChange = true
) {
  console.info("inertiaRoutingInstrumentation Started");

  let activeTransaction;
  let name;
  if (startTransactionOnPageLoad) {
    console.info("Start transaction on page load");
    name = "/" + route().current();

    activeTransaction = customStartTransaction({
                                                 name,
                                                 op      : "pageload",
                                                 metadata: {
                                                   source: "route"
                                                 }
                                               });
  }

  if (startTransactionOnLocationChange) {
    console.info("Start transaction on location change");

    router.on("before", (_to, _from) => {
      if (activeTransaction) {
        activeTransaction.finish();
      }

      const newName = "/" + route().current();
      console.info("Old name: " + name + ". New name: " + newName);

      if (newName !== name) {
        console.info("Old name is not equal to new name!");
        activeTransaction = customStartTransaction({
                                                     name    : newName,
                                                     op      : "navigation",
                                                     metadata: {
                                                       source: "route"
                                                     }
                                                   });
      }
    });

    router.on("finish", () => {
      console.info("Router on finish. Route: " + "/" + route().current());
      activeTransaction.setName("/" + route().current(), "route");
    });
  }
  console.info("inertiaRoutingInstrumentation Finished");
}


