import { FontAwesome } from '@expo/vector-icons';
import { DrawerActions, useNavigation, useNavigationState } from '@react-navigation/native';
import { useRouter } from 'expo-router';
import {
  Platform,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function DrawerHeader({ title }) {
  const router = useRouter();
  const navigation = useNavigation();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const paddingTop = Platform.OS === 'android' ? StatusBar.currentHeight || 24 : 0;
  const canGoBack = navigation.canGoBack();

  // Check if current navigator is a drawer
  const isDrawerAvailable = useNavigationState((state) => state?.type === 'drawer');

  const handleLeftPress = () => {
    if (isDrawerAvailable) {
      navigation.dispatch(DrawerActions.toggleDrawer());
    } else if (canGoBack) {
      router.push('(drawer-setup)/home'); 
    }else { 
      router.push('(drawer-setup)/home'); 
    }
  };

  const iconName = isDrawerAvailable ? 'bars' : 'arrow-left';

  return (
    <SafeAreaView
      edges={['top']}
      style={[styles.safeArea, { backgroundColor: isDark ? '#000' : '#fff' }]}
    >
      <View
        style={[
          styles.container,
          {
            paddingTop,
            backgroundColor: isDark ? '#000' : '#fff',
            borderBottomColor: isDark ? '#374151' : '#E5E7EB',
          },
        ]}
      >
        {/* Left Icon */}
        <TouchableOpacity onPress={handleLeftPress} style={styles.leftIcon}>
          <FontAwesome name={iconName} size={24} color={isDark ? '#fff' : '#333'} />
        </TouchableOpacity>

        {/* Centered Title */}
        <View style={styles.titleWrapper}>
          <Text style={[styles.title, { color: isDark ? '#fff' : '#000' }]}>{title}</Text>
        </View>

        {/* Right Spacer */}
        <View style={{ width: 24 }} />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    zIndex: 10,
  },
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 16,
    borderBottomWidth: 1,
  },
  leftIcon: {
    zIndex: 10,
  },
  titleWrapper: {
    position: 'absolute',
    left: 0,
    right: 0,
    alignItems: 'center',
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
  },
});
