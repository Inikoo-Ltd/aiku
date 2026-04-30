/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Feb 2024 09:03:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */
import { BrowserAgent } from '@newrelic/browser-agent/loaders/browser-agent'
import './bootstrap';
import '../css/app.css';

import {createApp, h} from 'vue';
import {createInertiaApp} from '@inertiajs/vue3';
import { ZiggyVue } from "ziggy-js";
import {i18nVue} from 'laravel-vue-i18n';
import Notifications from '@kyvg/vue3-notification';
import {createPinia} from 'pinia';
import * as Sentry from '@sentry/vue';
import FloatingVue from 'floating-vue'
import 'floating-vue/dist/style.css'
import Layout from '@/Layouts/AikuPublic.vue'

if (import.meta.env.VITE_NEW_RELIC_BROWSER_ENABLED) {
  const options = {
    "info": {
      "applicationID": import.meta.env.VITE_NEW_RELIC_BROWSER_APPLICATION_ID,
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
      "agentID": import.meta.env.VITE_NEW_RELIC_BROWSER_APPLICATION_ID,
      "applicationID": import.meta.env.VITE_NEW_RELIC_BROWSER_APPLICATION_ID,
      "licenseKey": import.meta.env.VITE_NEW_RELIC_BROWSER_LICENCE_KEY,
      "trustKey":  import.meta.env.VITE_NEW_RELIC_BROWSER_ACCOUNT_ID
    }
  }
  const nrba = new BrowserAgent(options)

}

const appName = window.document.getElementsByTagName('title')[0]?.innerText ||
    'aiku';

createInertiaApp(
    {
      title  : (title) => `${title} - ${appName}`,
        resolve: name => {
            const pages = import.meta.glob('./Pages/AikuPublic/**/*.vue', { eager: true })
            let page = pages[`./Pages/AikuPublic/${name}.vue`]
            if(!page) console.error(`File './Pages/AikuPublic/${name}.vue' is not exist`)
            page.default.layout = page.default.layout || Layout
            return page
        },
      setup({el, App, props, plugin}) {
        const app = createApp({render: () => h(App, props)});
        if (import.meta.env.VITE_SENTRY_DSN) {
          Sentry.init({
                        app,
                        dsn                     : import.meta.env.VITE_SENTRY_DSN,
                        environment             : import.meta.env.VITE_APP_ENV,
                          release: import.meta.env.VITE_RELEASE,
                        replaysSessionSampleRate: 0.1,
                        replaysOnErrorSampleRate: 1.0,
                        integrations: [new Sentry.Replay()]
                      });
        }


        app.use(plugin)
            .use(createPinia())
            .use(ZiggyVue, Ziggy)
            .use(Notifications)
            .use(FloatingVue)
            .use(i18nVue, {
              resolve: async (lang) => {
                const languages = import.meta.glob(
                    '../../lang/*.json');
                return await languages[`../../lang/${lang}.json`]();
              },
            }).
            mount(el);

      },
      progress: {
        color: '#4B5563',
      },
    });
