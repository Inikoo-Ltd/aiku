import { Text, TouchableOpacity, View } from 'react-native';

const AreaItem = ({ item, onPress }) => {
  return (
    <TouchableOpacity
      onPress={onPress}
      activeOpacity={0.7}
      className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 
                 p-4  shadow-sm"
    >
      <View className="flex-row items-center">
        <View className="flex-1">
          <Text className="text-lg font-bold text-gray-900 dark:text-white">
            {item.name}
          </Text>
          <Text className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Stock: {item.stock_value} | Location: {item.number_locations}
          </Text>
        </View>
      </View>
    </TouchableOpacity>
  );
};

export default AreaItem;
