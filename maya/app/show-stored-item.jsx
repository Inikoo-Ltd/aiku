import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { useLocalSearchParams } from 'expo-router';
import { useContext, useEffect, useState } from 'react';
import { ActivityIndicator, Text as RNText, ScrollView, useColorScheme, View } from 'react-native';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';

import { AuthContext } from '@/components/context/AuthContext';
import Description from '@/components/Description';
import { createGlobalStyles } from '@/globalStyles';
import {
    faCheck,
    faCheckDouble,
    faCircle,
    faSeedling,
    faShare,
    faSortSizeUp,
    faSpellCheck,
} from '@/private/fa/pro-light-svg-icons';
import request from '@/utils/Request';

library.add(faSeedling, faShare, faSpellCheck, faCheck, faCheckDouble, faSortSizeUp);

// === UI components ===
const Card = ({ children, className = '', style }) => (
  <View
    className={`rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 ${className}`}
    style={style}
  >
    {children}
  </View>
);

const Heading = ({ children, className = '' }) => (
  <RNText className={`text-xl font-bold text-gray-900 dark:text-white mb-2 ${className}`}>
    {children}
  </RNText>
);

const Text = ({ children, className = '' }) => (
  <RNText className={`text-base text-gray-700 dark:text-gray-300 ${className}`}>
    {children}
  </RNText>
);

// === Main component ===
const ShowStoredItem = () => {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const styles = createGlobalStyles(isDark);

  const { organisation, warehouse } = useContext(AuthContext);
  const { id } = useLocalSearchParams();

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);

  const fetchStoredItem = () => {
    setLoading(true);
    request({
      urlKey: 'get-stored-item',
      args: [organisation.id, warehouse.id, id],
      onSuccess: (response) => {
        setData(response.data);
        setLoading(false);
      },
      onFailed: (error) => {
        setLoading(false);
        Toast.show({
          type: ALERT_TYPE.DANGER,
          title: 'Error',
          textBody: error.detail?.message || 'Failed to load stored item',
        });
      },
    });
  };

  useEffect(() => {
    if (id) fetchStoredItem();
  }, [id]);

  if (loading) {
    return (
      <View style={[styles.container, { justifyContent: 'center', alignItems: 'center' }]}>
        <ActivityIndicator size="large" color="#4F46E5" />
      </View>
    );
  }

  if (!data) {
    return (
      <View style={[styles.container, { justifyContent: 'center', alignItems: 'center' }]}>
        <Text>No data found</Text>
      </View>
    );
  }

  const schema = [
    { label: 'Customer', value: data?.customer_name },
    {
      label: 'State',
      value: (
        <View className="flex-row items-center justify-center gap-2">
          <FontAwesomeIcon icon={data?.state_icon?.icon || faCircle} color={data?.state_icon?.color} />
          <Text>{data?.state}</Text>
        </View>
      ),
    },
    { label: 'Location', value: data?.location },
    { label: 'Quantity', value: data?.quantity?.toString() || '0' },
  ];

  return (
    <ScrollView style={styles.container}>
      {/* Header */}
      <Card className="bg-indigo-600 mb-4">
        <Heading className="text-white text-2xl">{data.reference || 'N/A'}</Heading>
        <Text className="text-white text-lg">Status: {data.state?.toUpperCase()}</Text>
      </Card>

      {/* Details */}
      <Card className="mt-4">
        <Heading>Details</Heading>
        <Description schema={schema} />
      </Card>
    </ScrollView>
  );
};

export default ShowStoredItem;
