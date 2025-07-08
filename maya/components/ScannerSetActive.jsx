import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';

export default function ScannerSetActive({ onPress }) {
  return (
    <View style={styles.centered}>
      <TouchableOpacity onPress={onPress}>
        <Text style={styles.text}>Touch to scan</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#000', // Optional: dark background
  },
  text: {
    fontSize: 18,
    color: '#fff',
    padding: 12,
    backgroundColor: '#2196F3',
    borderRadius: 8,
    overflow: 'hidden',
  },
});
