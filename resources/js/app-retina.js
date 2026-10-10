/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Feb 2024 10:42:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

import { BrowserAgent } from '@newrelic/browser-agent/loaders/browser-agent'
import "./bootstrap";
import "../css/app.css";

import { createApp, h } from "vue";
import { createInertiaApp, router } from "@inertiajs/vue3";
import { ZiggyVue } from "ziggy-js";
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
import { ctrans } from "@/Composables/useTrans";
import { sentryDenyUrls, sentryIgnoreErrors } from "@/Composables/sentryNoise";

if (import.meta.env.VITE_NEW_RELIC_BROWSER_ENABLED) {
  const options = {
    "info": {
      "applicationID": import.meta.env.VITE_NEW_RELIC_BROWSER_RETINA,
      "beacon": "bam.nr-data.net",
      "errorBeacon": "bam.nr-data.net",
      "licenseKey": import.meta.env.VITE_NEW_RELIC_BROWSER_LICENCE_KEY,
      "sa": 1
    },
    "init": {
      "ajax": {
        "deny_list": [
          "bam.nr-data.net"
        ]
      },
      "browser_consent_mode": {
        "enabled": false
      },
      "distributed_tracing": {
        "enabled": true
      },
      "performance": {
        "capture_detail": false,
        "capture_marks": false,
        "capture_measures": true
      },
      "privacy": {
        "cookies_enabled": true
      }
    },
    "loader_config": {
      "accountID": import.meta.env.VITE_NEW_RELIC_BROWSER_ACCOUNT_ID,
      "agentID": import.meta.env.VITE_NEW_RELIC_BROWSER_RETINA,
      "applicationID": import.meta.env.VITE_NEW_RELIC_BROWSER_RETINA,
      "licenseKey": import.meta.env.VITE_NEW_RELIC_BROWSER_LICENCE_KEY,
      "trustKey":  import.meta.env.VITE_NEW_RELIC_BROWSER_ACCOUNT_ID
    }
  }
  const nrba = new BrowserAgent(options)

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

const THEME_FONT_WEIGHTS = ["400", "700"];
const THEME_FONT_WAIT_LIMIT_MS = 2000;

const loadThemeFonts = (iris) => {
  const fontFamilies = new Set([
    iris?.theme?.container?.properties?.text?.fontFamily,
    iris?.header?.topBar?.data?.fieldValue?.container?.properties?.text?.fontFamily
  ].filter(Boolean));

  if (!fontFamilies.size || !document.fonts) {
    return null;
  }

  const fontsLoaded = Promise.allSettled(
    [...fontFamilies].flatMap((fontFamily) => THEME_FONT_WEIGHTS.map((weight) => document.fonts.load(`${weight} 1em ${fontFamily}`)))
  );
  const waitLimitReached = new Promise((resolve) => setTimeout(resolve, THEME_FONT_WAIT_LIMIT_MS));

  return Promise.race([fontsLoaded, waitLimitReached]);
};

const startWebpagePreload = (page) => {
  const webBlocks = page?.props?.web_blocks;
  if (!webBlocks) {
    return null;
  }
  const iris = page.props.iris;
  const headerBlocks = [iris?.header?.topBar?.code, iris?.header?.header?.code, iris?.menu?.code].filter(Boolean).map((type) => ({ type }));

  return {
    webBlocks : [...Object.values(webBlocks), ...headerBlocks],
    shopType  : page.props.retina?.type,
    fontsReady: loadThemeFonts(iris)
  };
};

let nextWebpagePreload = startWebpagePreload(JSON.parse(document.getElementById("app")?.dataset.page ?? "null"));

router.on("beforeUpdate", (event) => {
  nextWebpagePreload = startWebpagePreload(event.detail.page);
});

createInertiaApp(
  {
    resolve: async name => {
      const pages = import.meta.glob("./Pages/Retina/**/*.vue");
      let page = await pages?.[`./Pages/Retina/${name}.vue`]?.();
      if (!page) console.error(`File './Pages/Retina/${name}.vue' is not exist`);
      page.default.layout = page.default?.layout || Layout;
      if (name === "RetinaWebpage" && nextWebpagePreload?.webBlocks) {
        const { webBlocks, shopType, fontsReady } = nextWebpagePreload;
        nextWebpagePreload = null;
        const { preloadIrisBlocks } = await import("@/Iris/Composables/getIrisComponents");
        await Promise.all([preloadIrisBlocks(webBlocks, shopType), fontsReady]);
      }
      return page;
    },
    setup({ el, App, props, plugin }) {
      const app = createApp({ render: () => h(App, props) });
      if (import.meta.env.VITE_SENTRY_RETINA_DSN) {
        Sentry.init({
                      app,
                      dsn                     : import.meta.env.VITE_SENTRY_RETINA_DSN,
                      environment             : import.meta.env.VITE_APP_ENV,
                      release                 : import.meta.env.VITE_RELEASE,
                      replaysSessionSampleRate: 0.01,
                      replaysOnErrorSampleRate: 1.0,
                      ignoreErrors            : sentryIgnoreErrors,
                      denyUrls                : sentryDenyUrls,
                      integrations            : [new Sentry.Replay()]
                    });
      }

      app.use(plugin);
      app.config.globalProperties.ctrans = ctrans;  // global function for <template> -- Custom translation
      app.
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
