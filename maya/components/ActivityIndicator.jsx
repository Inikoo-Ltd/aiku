import { ActivityIndicator } from 'react-native';

export const Spinner = ({ size = 'large', color = '#000' }) => (
  <ActivityIndicator size={size} color={color} />
);
