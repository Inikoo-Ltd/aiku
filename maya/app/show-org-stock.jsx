import { useLocalSearchParams } from 'expo-router';
import { useContext, useEffect, useState } from 'react';
import { ActivityIndicator, Text as RNText, ScrollView, useColorScheme, View } from 'react-native';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';

import { AuthContext } from '@/components/context/AuthContext';
import { createGlobalStyles } from '@/globalStyles';
import request from '@/utils/Request';

// ✅ Inline UI Components using globalStyles
const Card = ({ children, style }) => (
  <View style={style}>{children}</View>
);

const Heading = ({ children, style }) => (
  <RNText style={[{ fontSize: 20, fontWeight: 'bold' }, style]}>{children}</RNText>
);

const Text = ({ children, style }) => (
  <RNText style={[{ fontSize: 16 }, style]}>{children}</RNText>
);

const Description = ({ schema, styles }) => (
  <View style={{ marginTop: 12 }}>
    {schema.map((item, index) => (
      <View
        key={index}
        style={{
          flexDirection: 'row',
          justifyContent: 'space-between',
          borderBottomWidth: 1,
          borderBottomColor: styles.list.card.borderColor,
          paddingBottom: 4,
          marginBottom: 8,
        }}
      >
        <Text style={styles.list.description}>{item.label}</Text>
        <Text style={styles.list.title}>{item.value}</Text>
      </View>
    ))}
  </View>
);

const ShowOrgStock = () => {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const styles = createGlobalStyles(isDark);

  const { organisation, warehouse } = useContext(AuthContext);
  const { id } = useLocalSearchParams();

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  const getDataFromServer = async () => {
    try {
      const response = await request({
        urlKey: 'get-org-stock',
        args: [organisation.id, warehouse.id, id],
      });
      setData(response);
    } catch (error) {
      Toast.show({
        type: ALERT_TYPE.DANGER,
        title: 'Error',
        textBody: error?.detail?.message || 'Failed to fetch data',
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (id) getDataFromServer();
  }, [id]);

  const schema = [
    { label: 'Code', value: data?.code || '-' },
    { label: 'Unit', value: data?.unit_value || '-' },
    { label: 'Locations', value: data?.number_locations?.toString() || '0' },
  ];

  // ✅ Loading
  if (loading) {
    return (
      <View style={[styles.container, { justifyContent: 'center', alignItems: 'center' }]}>
        <ActivityIndicator size="large" color="#4F46E5" />
      </View>
    );
  }

  // ✅ Empty
  if (!data) {
    return (
      <View style={[styles.container, { justifyContent: 'center', alignItems: 'center' }]}>
        <Text style={{ color: isDark ? '#9CA3AF' : '#4B5563', fontSize: 18 }}>No Data Available</Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container}>
      {/* Header Card */}
      <Card style={[styles.list.card, { backgroundColor: '#4F46E5', borderColor: '#4F46E5' }]}>
        <Heading style={{ color: '#fff', fontSize: 22 }}>Code: {data.code}</Heading>
        <Text style={{ color: '#E0E7FF', marginTop: 6 }}>Name: {data.name}</Text>
      </Card>

      {/* Detail Card */}
      <Card style={styles.list.card}>
        <Heading style={styles.list.title}>Details</Heading>
        <Description schema={schema} styles={styles} />
      </Card>
    </ScrollView>
  );
};

export default ShowOrgStock;
