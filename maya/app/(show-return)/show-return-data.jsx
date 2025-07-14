import { faChevronDown, faChevronUp, faNarwhal } from '@/private/fa/pro-light-svg-icons';
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import dayjs from 'dayjs';
import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  RefreshControl,
  ScrollView,
  Text,
  TouchableOpacity,
  useColorScheme,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useReturns } from '@/components/context/return';
import TimelineCard from '@/components/TimelineCard';
import { createGlobalStyles } from '@/globalStyles';

library.add(faChevronDown, faChevronUp, faNarwhal);

const ShowFulffilmentReturn = () => {
  const { data, loading, refetch, setParams } = useReturns();
  const [isTimelineOpen, setIsTimelineOpen] = useState(false);
  const [totalPallets, setTotalPallets] = useState(0);

  const { id } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const styles = createGlobalStyles(isDark);

  useEffect(() => {
    if (id) setParams(prev => ({ ...prev, id }));
  }, [id]);

  useEffect(() => {
    if (!data) return;
    if (data.type === 'stored_item') {
      setTotalPallets(data.number_picked_items || 0);
    } else {
      setTotalPallets(
        (data.number_pallet_picked || 0) +
        (data.number_pallets_state_other_incident || 0) +
        (data.number_pallets_state_lost || 0) +
        (data.number_pallets_state_damaged || 0)
      );
    }
  }, [data]);

  if (!data) {
    return (
      <View style={styles.scanner.centered}>
        <Text style={styles.list.description}>No Data Available</Text>
      </View>
    );
  }

  const schema = [
    { label: 'Customer reference', value: data.customer_reference || '-' },
    {
      label: 'Type',
      value: data.type,
      icon: data?.type_icon,
    },
    {
      label: 'State',
      value: data.state_label,
      icon: data?.state_icon,
    },
    { label: 'Number pallets', value: `${data.number_pallets || 0}` },
    { label: 'Number services', value: `${data.number_services || 0}` },
    { label: 'Number physical goods', value: `${data.number_physical_goods || 0}` },
    {
      label: 'Dispatched at',
      value: data.dispatched_at
        ? dayjs(data.dispatched_at).format('MMMM D, YYYY')
        : 'N/A',
    },
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
      refreshControl={
        <RefreshControl refreshing={loading} onRefresh={refetch} />
      }
    >
      {/* Reference */}
      <View style={[styles.list.card, { alignItems: 'center' }]}>
        <Text style={[styles.list.title]}>
          {data.reference}
        </Text>
      </View>

      {/* Return Details */}
      <View style={styles.list.card}>
        <Text style={styles.list.title}>Return Details</Text>
        {schema.map((item, idx) => (
          <View
            key={idx}
            style={{
              flexDirection: 'row',
              justifyContent: 'space-between',
              alignItems: 'center',
              marginTop: 12,
              gap: 6,
            }}
          >
            <Text style={[styles.list.description, { flex: 1 }]}>
              {item.label}
            </Text>
            <View
              style={{
                flexDirection: 'row',
                alignItems: 'center',
                justifyContent: 'flex-end',
                flex: 1.5,
                flexWrap: 'wrap',
              }}
            >
              {item.icon?.icon && (
                <FontAwesomeIcon
                  icon={item.icon.icon}
                  color={item.icon.color || '#000'}
                  size={16}
                  style={{ marginRight: 6 }}
                />
              )}
              <Text
                style={[styles.list.title, { flexShrink: 1, textAlign: 'right' }]}
                numberOfLines={2}
              >
                {item.value}
              </Text>
            </View>
          </View>
        ))}
      </View>

      {/* Timeline Section */}
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
          style={{
            flexDirection: 'row',
            justifyContent: 'space-between',
            alignItems: 'center',
          }}
        >
          {!isTimelineOpen && data.state_icon?.icon && data.state_label && (
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
              <FontAwesomeIcon
                icon={data.state_icon.icon}
                color={data.state_icon.color || '#000'}
                size={20}
              />
              <Text style={[styles.list.title, { color: 'white' }]}>
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

export default ShowFulffilmentReturn;
