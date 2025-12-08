const { getDefaultConfig, mergeConfig } = require('@react-native/metro-config');
const path = require('path');

/**
 * Metro configuration
 * https://reactnative.dev/docs/metro
 *
 * @type {import('@react-native/metro-config').MetroConfig}
 */
const config = {
  // Add any specific configurations you need here
};

// Get the default configuration from Metro
const defaultConfig = getDefaultConfig(__dirname);

// Set up the alias for the 'src' directory
const sourceDir = path.resolve(__dirname, 'src');
defaultConfig.resolver.extraNodeModules = {
  '@': sourceDir,
};

// Merge the default config with your custom config
module.exports = mergeConfig(defaultConfig, config);
