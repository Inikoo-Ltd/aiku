import { AuthContext } from '@/components/context/AuthContext';
import { createGlobalStyles } from '@/globalStyles';
import request from '@/utils/Request';
import { useLocalSearchParams } from 'expo-router';
import { useContext, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Text as RNText,
  ScrollView,
  useColorScheme,
  View,
} from 'react-native';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';
import Barcode from 'react-native-barcode-svg';

const ShowLocation = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const { id } = useLocalSearchParams();
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  console.log(organisation.id, warehouse.id, id)

  useEffect(() => {
    const fetchData = async () => {
      request({
        urlKey: 'get-location',
        args: [organisation.id, warehouse.id, id],
        onSuccess: (response) => {
          setData(response);
          setLoading(false);
        },
        onFailed: (error) => {
          setLoading(false);
          Toast.show({
            type: ALERT_TYPE.DANGER,
            title: 'Error',
            textBody: error?.data || 'Failed to fetch data',
          });
        },
      });
    };

    if (id && organisation?.id && warehouse?.id) {
      fetchData();
    }
  }, [id, organisation?.id, warehouse?.id]);

  const schema = [
    { label: 'Code', value: data?.code },
    { label: 'Status', value: data?.status },
    { label: 'Stock Value', value: data?.stock_value },
    { label: 'Empty', value: data?.is_empty ? 'Yes' : 'No' },
    { label: 'Max Weight', value: data?.max_weight?.toString() || '0' },
    { label: 'Max Volume', value: data?.max_volume?.toString() || '0' },
  ];

  if (loading) {
    return (
      <View
        style={[globalStyles.container, { justifyContent: 'center', alignItems: 'center' }]}
      >
        <ActivityIndicator size="large" color={isDark ? '#fff' : '#000'} />
      </View>
    );
  }

  if (!data) {
    return (
      <View
        style={[globalStyles.container, { justifyContent: 'center', alignItems: 'center' }]}
      >
        <RNText style={{ color: isDark ? '#ccc' : '#666', fontSize: 16 }}>
          No Data Available
        </RNText>
      </View>
    );
  }

  return (
    <ScrollView style={[globalStyles.container, { padding: 16 }]}>
      {/* Barcode + Slug */}
      <View
        style={{
          backgroundColor: isDark ? '#1f2937' : '#fff',
          padding: 16,
          borderRadius: 12,
          marginBottom: 16,
          alignItems: 'center',
        }}
      >
        {data?.slug && (
          <Barcode value={data.slug} format="CODE128" maxWidth={250} height={60} />
        )}
        <RNText
          style={{
            fontSize: 18,
            fontWeight: 'bold',
            color: isDark ? '#fff' : '#000',
            marginTop: 12,
          }}
        >
          {data?.slug}
        </RNText>
      </View>

      {/* Details Section */}
      <View
        style={{
          backgroundColor: isDark ? '#1f2937' : '#fff',
          padding: 16,
          borderRadius: 12,
        }}
      >
        <RNText
          style={{
            fontSize: 18,
            fontWeight: 'bold',
            marginBottom: 12,
            color: isDark ? '#fff' : '#000',
          }}
        >
          Details
        </RNText>

        {schema.map((item, index) => (
          <View
            key={index}
            style={{
              flexDirection: 'row',
              justifyContent: 'space-between',
              paddingVertical: 8,
              borderBottomWidth: index !== schema.length - 1 ? 1 : 0,
              borderBottomColor: isDark ? '#374151' : '#e5e7eb',
            }}
          >
            <RNText style={{ color: isDark ? '#ccc' : '#555' }}>{item.label}</RNText>
            <RNText style={{ fontWeight: '500', color: isDark ? '#fff' : '#111' }}>
              {item.value ?? '-'}
            </RNText>
          </View>
        ))}
      </View>
    </ScrollView>
  );
};

export default ShowLocation;
