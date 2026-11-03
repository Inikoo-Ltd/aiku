/*
 * Same config as tailwind.config.js, with the resources/js glob narrowed to what pupil
 * reaches so the grp backoffice classes stay out of the stylesheet pupil serves.
 */

const base = require('./tailwind.config.js');
const { collectModules } = require('./app-module-graph.cjs').pupil;

const ALL_COMPONENTS_GLOB = './resources/js/**/*.vue';

module.exports = {
    ...base,
    content: [
        ...base.content.filter((entry) => entry !== ALL_COMPONENTS_GLOB),
        ...[...collectModules()].filter((file) => file.endsWith('.vue')),
    ],
};
