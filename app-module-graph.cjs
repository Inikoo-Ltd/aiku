/*
 * Walks the modules an app's entries actually reach, so a build can be scoped to them
 * instead of to all of resources/js, which is shared between the grp backoffice, iris,
 * retina and pupil. The locale filter in vite.app-plugins.mjs and the tailwind content
 * lists both read these graphs, and must agree on what "part of this app" means.
 *
 * The walk is deliberately a superset of what ships: a module reached here but tree-shaken
 * later only keeps an extra translation key or css class, while a module missed here loses
 * them, so unreachable-but-listed is the safe direction to err in.
 */

const fs = require("node:fs");
const path = require("node:path");

const ALIASES = {
    "@iris"  : "resources/js/Iris",
    "@common": "resources/js/Common",
    "@"      : "resources/js"
};
const MODULE_SUFFIXES = ["", ".ts", ".js", ".vue", ".mjs", "/index.ts", "/index.js", "/index.vue"];
const SCANNABLE_MODULE = /\.(vue|ts|js|mjs)$/;
const IMPORT_SPECIFIER = /(?:\bfrom\s*|\bimport\s*\(\s*)["']([^"']+)["']|\bimport\s+["']([^"']+)["']/g;

/*
 * Typescript lets a module import "./thing.js" and resolve to thing.ts, which vite honours
 * and a plain suffix search does not, so the rewritten path is tried as well.
 */
const candidateBases = (base) => {
    const asTypescript = base.replace(/\.js$/, ".ts").replace(/\.mjs$/, ".mts");

    return asTypescript === base ? [base] : [base, asTypescript];
};

const resolveSpecifier = (specifier, importer) => {
    let base = null;
    for (const alias of Object.keys(ALIASES)) {
        if (specifier === alias || specifier.startsWith(`${alias}/`)) {
            base = path.resolve(process.cwd(), ALIASES[alias] + specifier.slice(alias.length));
            break;
        }
    }
    if (base === null) {
        if (!specifier.startsWith(".")) {
            return null;
        }
        base = path.resolve(path.dirname(importer), specifier);
    }
    for (const candidateBase of candidateBases(base)) {
        for (const suffix of MODULE_SUFFIXES) {
            const candidate = candidateBase + suffix;
            if (fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
                return candidate;
            }
        }
    }
    return null;
};

/*
 * globbedPageDirs are swept whole because their modules are reached through
 * import.meta.glob, which leaves no static import edge for the walk to follow.
 */
const createModuleGraph = ({ entries, globbedPageDirs }) => {
    let cached = null;

    const collectModules = () => {
        if (cached) {
            return cached;
        }
        const modules = new Set();
        const visit = (file) => {
            if (modules.has(file) || !SCANNABLE_MODULE.test(file)) {
                return;
            }
            modules.add(file);
            const code = fs.readFileSync(file, "utf8");
            for (const match of code.matchAll(IMPORT_SPECIFIER)) {
                const resolved = resolveSpecifier(match[1] ?? match[2], file);
                if (resolved) {
                    visit(resolved);
                }
            }
        };
        const visitDirectory = (dir) => {
            if (!fs.existsSync(dir)) {
                return;
            }
            for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
                const full = path.join(dir, entry.name);
                if (entry.isDirectory()) {
                    visitDirectory(full);
                } else {
                    visit(full);
                }
            }
        };
        for (const entry of entries) {
            visit(path.resolve(process.cwd(), entry));
        }
        for (const dir of globbedPageDirs) {
            visitDirectory(path.resolve(process.cwd(), dir));
        }
        cached = modules;

        return modules;
    };

    return { collectModules };
};

module.exports = {
    iris: createModuleGraph({
        entries        : ["resources/js/app-iris.js", "resources/js/ssr-iris.js"],
        globbedPageDirs: ["resources/js/Pages/Iris", "resources/js/Iris/Pages"]
    }),
    retina: createModuleGraph({
        entries        : ["resources/js/app-retina.js", "resources/js/ssr-retina.js"],
        globbedPageDirs: ["resources/js/Pages/Retina"]
    })
};
