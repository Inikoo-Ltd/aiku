/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Feb 2025 02:01:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

import "./bootstrap";
import "../css/app.css";
import { createApp, h } from "vue";
import { createInertiaApp, router } from "@inertiajs/vue3";
import axios from "axios";
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
import { ZiggyVue, route as ziggyRoute } from "ziggy-js";
import { setAgentOrganisations, withAgentRoutes } from "@/Composables/useAgentRoutes";
import { cacheCurrentRoute } from "@/Composables/cacheCurrentRoute";
import { ctrans } from "@/Composables/useTrans";
import { sentryDenyUrls, sentryIgnoreErrors } from "@/Composables/sentryNoise";
import { trackVisitStarts } from "@/Composables/visitTiming";

const appName = "aiku";

// A request with no hand on the mouse or keyboard for this long came from a timer or a live
// event reload, not a person: the server must not count it as the user being active.
const BACKGROUND_AFTER_MS = 30_000;
let lastUserInputAt = Date.now();
["pointermove", "pointerdown", "keydown", "wheel", "touchstart"].forEach((type) =>
  window.addEventListener(type, () => (lastUserInputAt = Date.now()), { capture: true, passive: true })
);
axios.interceptors.request.use((config) => {
  if (Date.now() - lastUserInputAt > BACKGROUND_AFTER_MS) {
    config.headers["X-Background-Request"] = "1";
  }
  return config;
});

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
  },
  components: {
    dialog: {
      header: { padding: "1rem 1.25rem 0.5rem", gap: "0.5rem" },
      title : { fontSize: "0.875rem", fontWeight: "600" },
      css   : `
        .p-dialog .p-dialog-title { color: #374151; }
        .p-dialog .p-dialog-header .p-dialog-close-button.p-button { width: 1.75rem; height: 1.75rem; min-width: 0; padding: 0; border: 0; background: transparent; color: #9ca3af; box-shadow: none; outline: none; }
        .p-dialog .p-dialog-header .p-dialog-close-button.p-button:hover,
        .p-dialog .p-dialog-header .p-dialog-close-button.p-button:focus-visible { background: #f3f4f6; color: #374151; box-shadow: none; outline: none; }
      `
    }
  }
});

router.on("before", (event) => {
  event.detail.visit.headers["X-Current-Url"] = window.location.href;
});
trackVisitStarts(router);

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

      setAgentOrganisations(props.initialPage.props.layout?.agents?.data);
      router.on("navigate", (event) => setAgentOrganisations(event.detail.page.props.layout?.agents?.data));
      window.route = withAgentRoutes(window.route);
      cacheCurrentRoute(window.route());
      cacheCurrentRoute(ziggyRoute(undefined, undefined, undefined, Ziggy));

      if (import.meta.env.VITE_SENTRY_GRP_DSN) {
        Sentry.init({
                      app,
                      dsn                     : import.meta.env.VITE_SENTRY_GRP_DSN,
                      environment             : import.meta.env.VITE_APP_ENV,
                      release                 : import.meta.env.VITE_RELEASE,
                      debug                   : false,
                      tracesSampleRate        : 1.0,
                      profilesSampleRate      : 1.0,
                      ignoreErrors            : sentryIgnoreErrors,
                      denyUrls                : sentryDenyUrls,
                      integrations            : [
                        Sentry.httpClientIntegration(),
                        Sentry.browserTracingIntegration({
                                                           enableInp           : true,
                                                           instrumentNavigation: false,
                                                           beforeStartSpan     : nameSpanAfterRoute
                                                         }),
                        Sentry.browserProfilingIntegration()

                      ]
                    });

        const urlWithoutHash = (url) => new URL(url, window.location.href).href.split("#")[0];
        let tracedUrl = urlWithoutHash(props.initialPage.url);
        router.on("navigate", () => {
          if (urlWithoutHash(window.location.href) === tracedUrl) {
            return;
          }
          tracedUrl = urlWithoutHash(window.location.href);
          Sentry.startBrowserTracingNavigationSpan(Sentry.getClient(), {
            name      : window.location.pathname,
            op        : "navigation",
            attributes: { [Sentry.SEMANTIC_ATTRIBUTE_SENTRY_SOURCE]: "url" }
          });
        });
      }

      app.use(plugin);
      app.config.globalProperties.ctrans = ctrans;  // global function for <template> -- Custom translation
      
      app.use(createPinia()).
        use(ZiggyVue, Ziggy).
        use({ install: (vueApp) => { vueApp.config.globalProperties.route = withAgentRoutes(vueApp.config.globalProperties.route); } }).
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

window.addEventListener("pageshow", (event) => {
  if (event.persisted) {
    window.location.reload();
  }
});

function nameSpanAfterRoute(context) {
  const routeName = route().current();
  if (!routeName) {
    return context;
  }

  return {
    ...context,
    name      : "/" + routeName,
    attributes: { ...context.attributes, [Sentry.SEMANTIC_ATTRIBUTE_SENTRY_SOURCE]: "route" }
  };
}
