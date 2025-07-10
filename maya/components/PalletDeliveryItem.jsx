// components/CardListItem.tsx

import { faChevronRight } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import {
    Text,
    TouchableOpacity,
    useColorScheme,
    View
} from 'react-native';



const PalletDeliveryItem  = ({
  icon,
  iconColor = '#6B7280',
  title,
  subtitle,
  onPress,
  style,
  showChevron = true,
}) => {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';

  return (
    <TouchableOpacity
      activeOpacity={0.9}
      onPress={onPress}
      className={`
        p-4 flex-row justify-between items-center
        bg-white dark:bg-gray-800
        border border-gray-200 dark:border-gray-700
      `}
      style={[
        {
          shadowColor: isDark ? '#000' : '#000',
          shadowOffset: { width: 0, height: 1 },
          shadowOpacity: 0.05,
          shadowRadius: 2,
          elevation: 2,
        },
        style,
      ]}
    >
      <View className="flex-row items-center space-x-4 flex-1">
        {icon && (
          <View className="w-10 h-10 items-center justify-center mx-2">
            <FontAwesomeIcon icon={icon} size={22} color={iconColor} />
          </View>
        )}
        <View className="flex-1">
          <Text className="text-base font-semibold text-gray-900 dark:text-white">
            {title}
          </Text>
          {!!subtitle && (
            <Text className="text-sm text-gray-600 dark:text-gray-400">
              {subtitle}
            </Text>
          )}
        </View>
      </View>

      {showChevron && (
        <FontAwesomeIcon
          icon={faChevronRight}
          size={16}
          color={isDark ? '#6B7280' : '#9CA3AF'}
        />
      )}
    </TouchableOpacity>
  );
};

export default PalletDeliveryItem;
