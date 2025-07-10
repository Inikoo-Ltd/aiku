import { faChevronRight } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { Text, TouchableOpacity, useColorScheme, View } from 'react-native';

const ReturnGroupItem = ({ item, navigation }) => {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';

  const handlePress = () => {
    navigation?.navigate('show-fulfilment-return', { id: item.id });
  };

  return (
    <TouchableOpacity
      activeOpacity={0.9}
      onPress={handlePress}
      className={`
        p-4 flex-row justify-between items-center
        bg-white dark:bg-gray-800
        border border-gray-200 dark:border-gray-700
      `}
      style={{
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
        elevation: 2,
      }}
    >
      <View className="flex-row items-center space-x-4 flex-1 mr-2">
        {/* Icon Section */}
        <View className="w-10 h-10 justify-center items-center">
          {item?.state_icon && (
            <FontAwesomeIcon
              icon={item.state_icon.icon}
              color={item.state_icon.color}
              size={item.state_icon.size || 22}
            />
          )}
          {!item?.state_icon && item?.type_icon && (
            <FontAwesomeIcon
              icon={item.type_icon.icon}
              color={item.type_icon.color || '#6B7280'}
              size={item.type_icon.size || 22}
            />
          )}
        </View>

        {/* Text Section */}
        <View className="flex-1">
          <Text className="text-base font-semibold text-gray-900 dark:text-white">
            {item?.reference || 'No reference available'}
          </Text>
          <Text className="text-sm text-gray-600 dark:text-gray-400">
            {item?.customer_reference || 'No customer reference available'}
          </Text>
        </View>
      </View>

      {/* Chevron */}
      <FontAwesomeIcon
        icon={faChevronRight}
        size={16}
        color={isDark ? '#6B7280' : '#9CA3AF'}
      />
    </TouchableOpacity>
  );
};

export default ReturnGroupItem;
