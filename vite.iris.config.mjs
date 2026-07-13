/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Sep 2023 20:46:11 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import i18n from "laravel-vue-i18n/vite";
import { fileURLToPath, URL } from "node:url";
import path from "node:path";
import fs from "node:fs";
import tailwindcss from 'tailwindcss';
import { analyzer } from 'vite-bundle-analyzer'

/*
 * The lang/*.json files hold translations for every app (grp backoffice included).
 * Iris only ever looks up keys that exist as string literals in resources/js
 * (dynamic trans(data) keys are backend data with no entries in the lang files),
 * so the iris locale chunks keep only those keys. Runs in both client and ssr
 * builds of this config, keeping hydration consistent.
 */
const irisLangFilter = () => {
    let usedKeys = null;

    const collectSourceStrings = () => {
        const strings = new Set();
        const stringLiteral = /(['"`])((?:\\.|(?!\1).)*?)\1/g;
        const walk = (dir) => {
            for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
                const full = path.join(dir, entry.name);
                if (entry.isDirectory()) {
                    walk(full);
                } else if (/\.(vue|ts|js|mjs)$/.test(entry.name)) {
                    const code = fs.readFileSync(full, "utf8");
                    for (const match of code.matchAll(stringLiteral)) {
                        const value = match[2].replace(/\\'/g, "'").replace(/\\"/g, '"');
                        if (value && value.length <= 500) {
                            strings.add(value);
                        }
                    }
                }
            }
        };
        walk(path.resolve(process.cwd(), "resources/js"));
        return strings;
    };

    return {
        name: "iris-lang-filter",
        enforce: "pre",
        transform(code, id) {
            if (!/\/lang\/[A-Za-z-]+\.json$/.test(id)) {
                return null;
            }
            usedKeys ??= collectSourceStrings();
            const full = JSON.parse(code);
            const kept = Object.fromEntries(
                Object.entries(full).filter(([key]) => usedKeys.has(key))
            );
            return { code: JSON.stringify(kept), map: null };
        },
    };
};

export default defineConfig(
  {
    server : {
      cors : true,
      watch: {
        usePolling: false,
        ignored: ["**/storage/media/**"]
      }
    },
    plugins: [
      laravel({
                hotFile       : "public/iris.hot",
                buildDirectory: "iris",
                input         : [
                  "resources/css/app.css",
                  "resources/js/app-iris.js"
                ],
                ssr           : "resources/js/ssr-iris.js",
                refresh       : true
              }),
      vue({
            template: {
              transformAssetUrls: {
                base           : null,
                includeAbsolute: false
              }
            }
          }),
      i18n(),
      irisLangFilter()
     // , analyzer()
    ],
    ssr    : {
      noExternal: ["@inertiajs/server", "vue-countup-v3", "floating-vue", "tailwindcss", "@fortawesome/*"]
    },
    resolve: {
      alias: {
        "@": fileURLToPath(new URL("./resources/js", import.meta.url)),
        "@iris": fileURLToPath(new URL("./resources/js/Iris", import.meta.url)),
        "@common": fileURLToPath(new URL("./resources/js/Common", import.meta.url)),
        "@fad"  : fileURLToPath(
          new URL("./private/fa/pro-duotone-svg-icons",
                  import.meta.url)),
        "@fal"  : fileURLToPath(
          new URL("./private/fa/pro-light-svg-icons",
                  import.meta.url)),
        "@far"  : fileURLToPath(
          new URL("./private/fa/pro-regular-svg-icons",
                  import.meta.url)),
        "@fas"  : fileURLToPath(
          new URL("./private/fa/pro-solid-svg-icons",
                  import.meta.url)),
        "@fonts": fileURLToPath(new URL("./public/assets/Fonts/", import.meta.url)),
        "@art"  : fileURLToPath(new URL("./public/art/", import.meta.url))
      }
    },
    build  : {
      sourcemap    : true,
      // transpile: ["@fortawesome/vue-fontawesome", "@fortawesome/fontawesome-svg-core"],        
      devSourcemap : true,
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (id.includes("node_modules") && !id.includes('sentry') && !id.includes('node_modules/primevue/')) {
              return id.toString().
                split("node_modules/")[1].split(
                "/")[0].toString();
            }
          }
        }
      }
    },
    css    : {
      postcss: {
        plugins: [tailwindcss],
      },
      preprocessorOptions: {
        scss: {
          silenceDeprecations: ["legacy-js-api"]
        }
      }
    }
  });
