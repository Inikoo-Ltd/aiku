/*
 * Build plugins shared by the per-app vite configs. Each takes whatever app-specific
 * input it needs so the same code can serve iris, retina and anything added later.
 */

import fs from "node:fs";
import path from "node:path";

/*
 * The lang/*.json files hold translations for every app (grp backoffice included).
 * An app only ever looks up keys that exist as string literals in the modules its own
 * entries reach (dynamic trans(data) keys are backend data with no entries in the lang
 * files), so its locale chunks keep only those keys. Walking the import graph rather than
 * all of resources/js keeps the other apps' keys out. Runs in both client and ssr builds
 * of a config, keeping hydration consistent.
 */
export const langFilter = (moduleGraph) => {
    let usedKeys = null;

    const collectSourceStrings = () => {
        const strings = new Set();
        const stringLiteral = /(['"`])((?:\\.|(?!\1).)*?)\1/g;
        /*
         * :label="ctrans('Some key')" is captured as "ctrans('Some key')"
         * now :label="ctrans('Some key')" is captured as "Some key"
         */
        const collect = (code) => {
            for (const match of code.matchAll(stringLiteral)) {
                const value = match[2].replace(/\\'/g, "'").replace(/\\"/g, '"');
                if (!value || value.length > 500 || strings.has(value)) {
                    continue;
                }
                strings.add(value);
                collect(value);
            }
        };
        for (const file of moduleGraph.collectModules()) {
            if (fs.existsSync(file)) {
                collect(fs.readFileSync(file, "utf8"));
            }
        }

        return strings;
    };

    return {
        name: "app-lang-filter",
        enforce: "pre",
        transform(code, id) {
            if (!/\/lang\/[A-Za-z-]+\.json$/.test(id)) {
                return null;
            }
            usedKeys ??= collectSourceStrings();
            const full = JSON.parse(code);
            const kept = Object.fromEntries(
                Object.entries(full).filter(
                    ([key, value]) => usedKeys.has(key) && typeof value === "string" && value.trim() !== ""
                )
            );

            return { code: JSON.stringify(kept), map: null };
        },
    };
};

/*
 * @fal/@fas/@far/@fad each resolve to one index.es.js holding the whole pack, so rollup
 * hoists that single module into the entry chunk along with every icon used anywhere in
 * the app. The packs also ship one file per icon: importing those instead lets each icon
 * land only in the chunks that use it. Names with no matching file (IconDefinition and
 * other types) stay on the pack import for esbuild to drop with the rest of the types,
 * and replacements keep the statement's line count so the sourcemaps still line up.
 *
 * Build only: the per-icon files are commonjs, converted by the build.commonjsOptions
 * that each config pairs with this plugin, but served raw by the dev server, which keeps
 * the already esm pack import instead.
 */
export const faPerIconImports = () => {
    const ICON_PACK_DIRS = {
        "@fal": "private/fa/pro-light-svg-icons",
        "@fas": "private/fa/pro-solid-svg-icons",
        "@far": "private/fa/pro-regular-svg-icons",
        "@fad": "private/fa/pro-duotone-svg-icons"
    };
    const PACK_IMPORT = /import\s+\{([^}]*)\}\s*from\s*(["'])(@fa[lsrd])\2\s*;?/g;
    const HAS_PACK_IMPORT = /from\s*["']@fa[lsrd]["']/;
    const SOURCE_MODULE = /\/resources\/js\/.+\.(vue|ts|js)(\?|$)/;

    const iconModuleExists = (pack, name) =>
        /^fa[A-Z0-9]/.test(name)
        && fs.existsSync(path.resolve(process.cwd(), ICON_PACK_DIRS[pack], `${name}.js`));

    return {
        name: "fa-per-icon-imports",
        enforce: "pre",
        apply: "build",
        transform(code, id) {
            if (!SOURCE_MODULE.test(id) || !HAS_PACK_IMPORT.test(code)) {
                return null;
            }
            const rewritten = code.replace(PACK_IMPORT, (statement, specifiers, _quote, pack) => {
                const perIconImports = [];
                const keptOnPack = [];
                for (const specifier of specifiers.split(",")) {
                    const trimmed = specifier.trim();
                    if (!trimmed) {
                        continue;
                    }
                    const [name, alias] = trimmed.split(/\s+as\s+/).map((part) => part.trim());
                    if (iconModuleExists(pack, name)) {
                        perIconImports.push(`import { ${name}${alias ? ` as ${alias}` : ""} } from "${pack}/${name}";`);
                    } else {
                        keptOnPack.push(trimmed);
                    }
                }
                if (!perIconImports.length) {
                    return statement;
                }
                if (keptOnPack.length) {
                    perIconImports.push(`import { ${keptOnPack.join(", ")} } from "${pack}";`);
                }

                return perIconImports.join("") + "\n".repeat((statement.match(/\n/g) ?? []).length);
            });

            return { code: rewritten, map: null };
        },
    };
};

/*
 * The per-icon files live outside node_modules, which is the only place rollup converts
 * commonjs by default, so faPerIconImports needs this alongside it. CI links private/fa to a
 * shared artifacts/fa checkout and rollup matches the resolved real path, so the pattern keys
 * on the pack directory names rather than on private/fa.
 */
export const FA_COMMONJS_OPTIONS = {
    include: [/node_modules/, /[\\/]fa[\\/]pro-[a-z]+-svg-icons[\\/]/]
};
