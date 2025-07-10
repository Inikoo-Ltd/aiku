import dayjs from 'dayjs';
import { Text, TouchableOpacity, View } from 'react-native';

const OrgStockItem = ({ item, onPress }) => {
  const formattedDate = item.date
    ? dayjs(item.date).format('MMMM D, YYYY')
    : 'No Date Available';

  return (
    <TouchableOpacity
      onPress={onPress}
      activeOpacity={0.7}
      className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 
                 p-4 shadow-sm flex-row justify-between items-center"
    >
      <View className="flex-1">
        <Text className="text-lg font-bold text-gray-900 dark:text-white">
          {item.name}
        </Text>
        <Text className="text-sm text-gray-600 dark:text-gray-400 mt-1">
          {item.code || '[No code available]'}
        </Text>
        <Text className="text-xs text-gray-500 dark:text-gray-500 mt-0.5">
          {formattedDate}
        </Text>
      </View>
    </TouchableOpacity>
  );
};

export default OrgStockItem;
