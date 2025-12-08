module.exports = {
  presets: ['module:@react-native/babel-preset'],
  plugins: [
    [
      'module-resolver',
      {
        root: ['./src'],
        alias: {
          '~/components': './src/components',
          '~/constants': './src/constants',
          '~/store': './src/store',
          '~/utils': './src/utils',
          '~/screens': './src/screens',
        },
      },
    ],
  ]
};
