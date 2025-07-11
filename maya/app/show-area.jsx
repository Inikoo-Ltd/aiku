import { useLocalSearchParams } from 'expo-router';
import { useContext, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Text as RNText,
  ScrollView,
  StyleSheet,
  View,
  useColorScheme,
} from 'react-native';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';

import { AuthContext } from '@/components/context/AuthContext';
import { createGlobalStyles } from '@/globalStyles';
import request from '@/utils/Request';

// 🧩 UI Components
const Card = ({ children, style }) => (
  <View style={[style, styles.cardShadow]}>{children}</View>
);

const Heading = ({ children, style }) => (
  <RNText style={[styles.heading, style]}>{children}</RNText>
);

const Text = ({ children, style }) => (
  <RNText style={[styles.text, style]}>{children}</RNText>
);

const Divider = ({ style }) => (
  <View style={[{ height: StyleSheet.hairlineWidth }, style]} />
);

const Spinner = ({ size = 'large', color = '#837FE1' }) => (
  <ActivityIndicator size={size} color={color} />
);

// 📌 Description
const Description = ({ schema, textStyles }) => (
  <View style={{ gap: 8 }}>
    {schema.map((item, index) => (
      <View
        key={index}
        style={{
          flexDirection: 'row',
          justifyContent: 'space-between',
          borderBottomWidth: StyleSheet.hairlineWidth,
          borderBottomColor: textStyles.dividerColor,
          paddingBottom: 4,
        }}
      >
        <Text style={{ color: textStyles.muted }}>{item.label}</Text>
        <Text style={{ fontWeight: '600', color: textStyles.default }}>{item.value}</Text>
      </View>
    ))}
  </View>
);

// 🚀 Main Component
const ShowArea = () => {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const global = createGlobalStyles(isDark);

  const { organisation, warehouse } = useContext(AuthContext);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const { id } = useLocalSearchParams();

  useEffect(() => {
    if (!id || typeof id !== 'string') return;

    const fetchAreaDetail = async () => {
      try {
        const response = await request({
          urlKey: 'get-area',
          args: [organisation.id, warehouse.id, id],
        });
        setData(response.data);
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

    fetchAreaDetail();
  }, [organisation.id, warehouse.id, id]);

  const textStyles = {
    default: isDark ? '#F9FAFB' : '#111827',
    muted: isDark ? '#9CA3AF' : '#6B7280',
    dividerColor: isDark ? '#4B5563' : '#E5E7EB',
  };

  if (loading) {
    return (
      <View style={global.container}>
        <Spinner />
      </View>
    );
  }

  if (!data) {
    return (
      <View style={global.container}>
        <Text style={{ fontSize: 16, color: textStyles.muted }}>
          No Data Available
        </Text>
      </View>
    );
  }

  const schema = [
    { label: 'Code', value: data.code },
    { label: 'Number of locations', value: data.number_of_locations || '0' },
    { label: 'Stock value', value: data.stock_value || '0' },
    { label: 'Number of empty locations', value: data.number_empty_locations || '0' },
  ];

  return (
    <ScrollView style={global.container}>
      <Card style={global.list.card}>
        <Heading style={{ color: isDark ? '#C7D2FE' : '#4F46E5', marginBottom: 8 }}>
          {data.name}
        </Heading>
        <Divider style={{ backgroundColor: textStyles.dividerColor, marginVertical: 12 }} />
        <Description schema={schema} textStyles={textStyles} />
      </Card>
    </ScrollView>
  );
};

export default ShowArea;

// Shared non-theme-dependent styles
const styles = StyleSheet.create({
  cardShadow: {
    borderRadius: 16,
    shadowColor: '#000',
    shadowOpacity: 0.1,
    shadowRadius: 4,
    shadowOffset: { width: 0, height: 2 },
    elevation: 3,
  },
  heading: {
    fontSize: 20,
    fontWeight: 'bold',
  },
  text: {
    fontSize: 16,
  },
});
