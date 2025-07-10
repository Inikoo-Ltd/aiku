import { faChevronRight } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import dayjs from 'dayjs';
import React from 'react';
import { Text, TouchableOpacity, View, useColorScheme } from 'react-native';

interface DeliveryNoteItemProps {
  item: {
    id: string | number;
    date?: string;
    reference?: string;
    customer_name?: string;
    weight?: number;
  };
  onPress?: () => void;
}

const DeliveryNoteItem: React.FC<DeliveryNoteItemProps> = ({ item, onPress }) => {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';

  const formattedDate = item.date
    ? dayjs(item.date).format('MMMM D, YYYY')
    : 'No Date Available';

  return (
    <TouchableOpacity
      onPress={onPress}
      activeOpacity={0.9}
      className="p-5 flex-row justify-between items-center
                 bg-white dark:bg-gray-800
                 border border-gray-200 dark:border-gray-700"
      style={{
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
        elevation: 2,
      }}
    >
      <View className="flex-1">
        <Text className="text-lg font-bold text-gray-900 dark:text-white">
          {formattedDate}
        </Text>
        <Text className="text-sm text-gray-600 dark:text-gray-400 mt-1">
          {item.reference || '[No reference]'} - {item.customer_name || 'No customer'}
        </Text>
        {item.weight != null && (
          <Text className="text-sm text-gray-800 dark:text-gray-300 mt-1 font-medium">
            {item.weight} kg
          </Text>
        )}
      </View>

      <FontAwesomeIcon
        icon={faChevronRight}
        size={16}
        color={isDark ? '#6B7280' : '#9CA3AF'}
      />
    </TouchableOpacity>
  );
};

export default DeliveryNoteItem;
