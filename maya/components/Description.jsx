import { Text, useColorScheme, View } from 'react-native';

const Description = ({ schema }) => {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  return (
    <View
      className="rounded-lg p-2 mt-2"
      style={{
        borderWidth: 1,
        borderColor: isDark ? '#4B5563' : '#D1D5DB', // dark:border-gray-600 / border-gray-300
      }}
    >
      {schema.map((item, index) => (
        <View
          key={index}
          className={`flex-row items-center ${
            index !== schema.length - 1 ? 'pb-2 p-2 border-b' : 'px-2 p-2'
          }`}
          style={{
            borderColor: isDark ? '#374151' : '#E5E7EB', // dark:border-gray-700 / border-gray-200
            minHeight: 50,
            maxHeight: 120,
          }}
        >
          {/* Label */}
          <View
            className="px-2 py-1 rounded items-center justify-center"
            style={{
              backgroundColor: isDark ? '#374151' : '#E5E7EB', // dark:bg-gray-700 / bg-gray-200
              flex: 1,
            }}
          >
            {typeof item.label === 'string' ? (
              <Text
                style={{
                  fontWeight: '600',
                  textAlign: 'center',
                  color: isDark ? '#F9FAFB' : '#111827', // dark:text-gray-100 / text-gray-900
                }}
              >
                {item.label}
              </Text>
            ) : (
              item.label
            )}
          </View>

          {/* Value */}
          <View
            style={{
              flex: 2,
              justifyContent: 'center',
              paddingHorizontal: 6,
            }}
          >
            {typeof item.value === 'string' ? (
              <Text
                numberOfLines={3}
                ellipsizeMode="tail"
                style={{
                  textAlign: 'center',
                  color: isDark ? '#D1D5DB' : '#374151', // dark:text-gray-300 / text-gray-700
                }}
              >
                {item.value}
              </Text>
            ) : (
              item.value
            )}
          </View>
        </View>
      ))}
    </View>
  );
};

export default Description;
