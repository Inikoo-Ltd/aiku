import { faChevronRight } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { Text, TouchableOpacity, useColorScheme, View } from 'react-native';

const LocationItem = ({ item, onPress }) => {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';

  return (
    <TouchableOpacity
      onPress={onPress}
      activeOpacity={0.9}
      className="p-5  flex-row justify-between items-center
                 bg-white dark:bg-gray-800 
                 border border-gray-200 dark:border-gray-700
                 shadow-sm"
      style={{
        shadowColor: isDark ? '#000' : '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
        elevation: 2,
      }}
    >
      <View className="flex-1">
        <Text className="text-lg font-bold text-gray-900 dark:text-white">
          {item.code}
        </Text>
        <Text className="text-sm text-gray-600 dark:text-gray-400 mt-1">
          Stock Value:{' '}
          <Text className="font-medium text-gray-800 dark:text-gray-200">
            {Number(item.stock_value).toLocaleString()}
          </Text>
        </Text>
      </View>
      <FontAwesomeIcon
        icon={faChevronRight}
        size={16}
        color={isDark ? '#6B7280' : '#9CA3AF'}
      />
    </TouchableOpacity>
  );
};

export default LocationItem;
