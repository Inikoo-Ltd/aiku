import { faInbox } from '@/private/fa/pro-regular-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { Text, View } from 'react-native';

const Empty = ({
  title = 'Empty',
  description = 'No data available.',
  icon = faInbox,
  iconColor = '#9CA3AF', // Tailwind gray-400
  bgColor = '#F3F4F6',   // Tailwind gray-100
  size = 48,
}) => {
  return (
    <View className="flex-1 items-center justify-center px-6">
      <View className="items-center space-y-3">
        <View
          style={{
            backgroundColor: bgColor,
            borderRadius: size,
            padding: size * 0.5,
          }}
        >
          <FontAwesomeIcon icon={icon} size={size} color={iconColor} />
        </View>
        <Text className="text-xl font-semibold text-gray-600">{title}</Text>
        <Text className="text-sm text-gray-500 text-center">{description}</Text>
      </View>
    </View>
  );
};

export default Empty;
