import {Dimensions} from 'react-native';
import {useCameraPermission} from 'react-native-vision-camera';
import {StyleSheet, Button, Alert} from 'react-native';

export const getWindowWidth = () => {
  return Dimensions.get('window').width;
};
export const getWindowHeight = () => {
  return Dimensions.get('window').height;
};

export const handleCameraPermission = async () => {
  const {hasPermission, requestPermission} = useCameraPermission();
  const granted = await requestPermission();
  if (!granted) {
    Alert.alert('Camera permission is required to use the scanner.');
  }
};

export const holesConfig = () => {
  return [
    {
      x: Math.round(getWindowWidth() * 0.1),
      y: Math.round(getWindowHeight() * 0.28),
      width: Math.round(getWindowWidth() * 0.8),
      height: Math.round(getWindowHeight() * 0.4),
      borderRadius: 10,
    },
  ];
};

export const ScannerConfig = {
    codeTypes: ['qr', 'ean-13', 'code-128'],
}
