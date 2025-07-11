import { FontAwesome } from '@expo/vector-icons';
import {
  DrawerActions,
  useNavigation,
  useNavigationState,
  useTheme,
} from '@react-navigation/native';
import {
  Platform,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function DrawerHeader({ title }) {
  const navigation = useNavigation();
  const { dark: isDark } = useTheme();
  const paddingTop = Platform.OS === 'android' ? StatusBar.currentHeight || 24 : 0;

  const canGoBack = navigation.canGoBack();
  const isDrawerAvailable = useNavigationState(
    (state) => !!state?.key && state?.type === 'drawer'
  );

  const handleLeftPress = () => {
    if (isDrawerAvailable) {
      navigation.dispatch(DrawerActions.toggleDrawer());
    } else if (canGoBack) {
      navigation.goBack();
    }
  };

  return (
    <SafeAreaView
      edges={['top']}
      style={[styles.safeArea, isDark ? styles.darkBg : styles.lightBg]}
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
        {/* Left Icon: Drawer toggle or Back */}
        <TouchableOpacity onPress={handleLeftPress} style={styles.leftIcon}>
          <FontAwesome
            name={isDrawerAvailable ? 'bars' : 'arrow-left'}
            size={24}
            color={isDark ? '#fff' : '#333'}
          />
        </TouchableOpacity>

        {/* Center Title */}
        <View style={styles.titleWrapper}>
          <Text style={[styles.title, { color: isDark ? '#fff' : '#000' }]}>
            {title}
          </Text>
        </View>

        {/* Placeholder to balance right side */}
        <View style={{ width: 24 }} />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    zIndex: 10,
  },
  darkBg: {
    backgroundColor: '#000',
  },
  lightBg: {
    backgroundColor: '#fff',
  },
  container: {
    position: 'relative',
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
