import { AuthContext } from '@/components/context/AuthContext';
import Description from '@/components/Description';
import { createGlobalStyles } from '@/globalStyles';
import { faFragile } from '@/private/fa/pro-light-svg-icons';
import request from '@/utils/Request';
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
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

library.add(faFragile);

// Inline UI Components with global styles
const Card = ({ children, style = {} }) => (
  <View style={[{
    borderRadius: 12,
    borderWidth: 1,
    padding: 16,
    marginBottom: 16,
  }, style]}>
    {children}
  </View>
);

const Heading = ({ children, isDark }) => (
  <RNText
    style={{
      fontSize: 20,
      fontWeight: 'bold',
      marginBottom: 8,
      color: isDark ? '#ffffff' : '#111827',
    }}
  >
    {children}
  </RNText>
);

const Text = ({ children, isDark, style = {} }) => (
  <RNText
    style={[
      {
        fontSize: 16,
        color: isDark ? '#D1D5DB' : '#374151',
      },
      style,
    ]}
  >
    {children}
  </RNText>
);

const Center = ({ children }) => (
  <View style={{ alignItems: 'center', justifyContent: 'center' }}>
    {children}
  </View>
);

// Main component
const ShowPallet = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const styles = createGlobalStyles(isDark);
  const { id } = useLocalSearchParams();

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);

  const fetchPallet = () => {
    setLoading(true);
    request({
      urlKey: 'get-pallet',
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
          textBody: error.detail?.message || 'Failed to load pallet data',
        });
      },
    });
  };

  useEffect(() => {
    if (id) fetchPallet();
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
        <Text isDark={isDark}>No data found</Text>
      </View>
    );
  }

  const schema = [
    { label: 'Customer', value: data.customer?.name },
    { label: 'Customer Reference', value: data.customer_reference },
    { label: 'Location', value: data?.location?.resource?.code || '-' },
    // If you want to re-enable Status/Notes, you can uncomment below
    { label: 'State', value: data.state },
    {
      label: 'Status',
      value: (
        <View style={{ flexDirection: 'row', justifyContent: 'center', alignItems: 'center', gap: 8 }}>
          <FontAwesomeIcon icon={data.status_icon?.icon} color={isDark ? '#fff' : '#000'} />
          <Text isDark={isDark}>{data.status}</Text>
        </View>
      ),
    },

    { label: 'Notes', value: data.notes },
  ];

  return (
    <ScrollView style={styles.container}>
      {/* Barcode Section */}
      <Card style={{
        backgroundColor: isDark ? '#1F2937' : '#ffffff',
        borderColor: isDark ? '#374151' : '#e5e7eb',
      }}>
        <Center>
          {/* <Barcode value={data.reference} format="CODE128" maxWidth={250} height={60} /> */}
        </Center>
        <Center style={{ marginTop: 8 }}>
          <Heading isDark={isDark}>{data.reference}</Heading>
        </Center>
      </Card>

      {/* Delivery Details */}
      <Card style={{
        backgroundColor: isDark ? '#1F2937' : '#ffffff',
        borderColor: isDark ? '#374151' : '#e5e7eb',
      }}>
        <Heading isDark={isDark}>Delivery Details</Heading>
        <Description schema={schema} />
      </Card>
    </ScrollView>
  );
};

export default ShowPallet;
