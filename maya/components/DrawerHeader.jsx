import { FontAwesome } from '@expo/vector-icons';
import { DrawerActions, useNavigation } from '@react-navigation/native';
import { Platform, StatusBar, Text, TouchableOpacity, useColorScheme, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function DrawerHeader({ title }) {
  const navigation = useNavigation();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const paddingTop = Platform.OS === 'android' ? StatusBar.currentHeight || 24 : 0;

  return (
    <SafeAreaView
      edges={['top']}
      className={isDark ? 'bg-black' : 'bg-white'}
    >
      <View
        className={`relative flex-row items-center justify-between px-4 py-4 border-b ${
          isDark ? 'border-gray-700 bg-black' : 'border-gray-200 bg-white'
        }`}
        style={{ paddingTop }}
      >
        {/* Drawer Button */}
        <TouchableOpacity
          onPress={() => navigation.dispatch(DrawerActions.toggleDrawer())}
          className="z-10"
        >
          <FontAwesome name="bars" size={24} color={isDark ? '#fff' : '#333'} />
        </TouchableOpacity>

        {/* Centered Title */}
        <View className="absolute left-0 right-0 items-center">
          <Text className={`text-xl font-bold ${isDark ? 'text-white' : 'text-black'}`}>
            {title}
          </Text>
        </View>

        {/* Placeholder for right side (to balance layout) */}
        <View style={{ width: 24 }} />
      </View>
    </SafeAreaView>
  );
}
