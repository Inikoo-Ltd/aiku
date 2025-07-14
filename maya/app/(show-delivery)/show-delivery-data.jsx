import TimelineCard from '@/components/TimelineCard';
import { useDeliveries } from '@/components/context/delivery';
import { createGlobalStyles } from '@/globalStyles';
import { faChevronDown, faChevronUp } from '@/private/fa/pro-light-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import dayjs from 'dayjs';
import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  Text,
  TouchableOpacity,
  useColorScheme,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const ShowFulfilmentDelivery = () => {
  const { id } = useLocalSearchParams();
  const { data, loading, refetch, setParams } = useDeliveries();

  const [refreshing, setRefreshing] = useState(false);
  const [isTimelineOpen, setIsTimelineOpen] = useState(false);

  const insets = useSafeAreaInsets();
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const styles = createGlobalStyles(isDark);

  useEffect(() => {
    if (id) setParams(prev => ({ ...prev, id }));
  }, [id]);

  const onRefresh = async () => {
    setRefreshing(true);
    await refetch();
    setRefreshing(false);
  };

  if (loading && !refreshing) {
    return (
      <View style={styles.scanner.centered}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  if (!data) {
    return (
      <View style={styles.scanner.centered}>
        <Text style={styles.list.description}>No Data Available</Text>
      </View>
    );
  }

  const schema = [
    { label: 'Customer', value: data.customer_name },
    { label: 'Customer Reference', value: data.customer_reference },
    { label: 'Boxes', value: `${data.number_boxes ?? '-'}` },
    { label: 'Oversizes', value: `${data.number_oversizes ?? '-'}` },
    { label: 'Pallets', value: `${data.number_pallets ?? '-'}` },
    { label: 'Services', value: `${data.number_services ?? '-'}` },
    {
      label: 'Estimated delivery',
      value: data.estimated_delivery_date
        ? dayjs(data.estimated_delivery_date).format('MMMM D, YYYY')
        : 'N/A',
    },
    { label: 'Note', value: data.public_notes || '-' },
  ];

  const timelineData = (data.timeline ? Object.values(data.timeline) : []).map(event => ({
    time: dayjs(event.timestamp).isValid() ? dayjs(event.timestamp).format('HH:mm') : 'N/A',
    title: event.label,
    description: dayjs(event.timestamp).isValid()
      ? dayjs(event.timestamp).format('YYYY-MM-DD HH:mm')
      : 'N/A',
    active: event.label === data.state_label,
  }));

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ paddingBottom: insets.bottom + 120 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
    >
      {/* Reference Card */}
      <View style={[styles.list.card, { alignItems: 'center' }]}>
        <Text
          style={[styles.list.title, { textAlign: 'center', flexWrap: 'wrap' }]}
        >
          {data.reference}
        </Text>
      </View>

      {/* Delivery Details Card */}
      <View style={styles.list.card}>
        <Text style={styles.list.title}>Delivery Details</Text>
        {schema.map((item, idx) => (
          <View
            key={idx}
            style={{
              flexDirection: 'row',
              justifyContent: 'space-between',
              marginTop: 12,
              gap: 8,
              flexWrap: 'wrap',
            }}
          >
            <Text style={[styles.list.description, { flex: 1 }]}>{item.label}</Text>
            <Text
              style={[styles.list.title, { flex: 2, textAlign: 'right', flexWrap: 'wrap' }]}
              numberOfLines={0}
            >
              {item.value}
            </Text>
          </View>
        ))}
      </View>

      {/* Timeline Toggle Card */}
      <View
        style={[
          styles.list.card,
          {
            padding: 12,
            backgroundColor: !isTimelineOpen ? '#818cf8' : styles.list.card.backgroundColor,
          },
        ]}
      >
        <TouchableOpacity
          onPress={() => setIsTimelineOpen(prev => !prev)}
          style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}
        >
          {!isTimelineOpen && (
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
              <FontAwesomeIcon
                icon={data.state_icon.icon}
                color={data.state_icon.color}
                size={20}
              />
              <Text style={[styles.list.title, { color: 'white', flexWrap: 'wrap' }]}>
                {data.state_label}
              </Text>
            </View>
          )}
          <FontAwesomeIcon
            icon={isTimelineOpen ? faChevronUp : faChevronDown}
            size={20}
            color={isDark ? '#fff' : '#000'}
          />
        </TouchableOpacity>

        {isTimelineOpen && <TimelineCard data={timelineData} />}
      </View>
    </ScrollView>
  );
};

export default ShowFulfilmentDelivery;
