// components/TimelineCard.tsx
import { createGlobalStyles } from '@/globalStyles';
import React from 'react';
import { Text, useColorScheme, View } from 'react-native';

type TimelineItem = {
  time: string;
  title: string;
  description?: string;
  active?: boolean;
};

type Props = {
  data: TimelineItem[];
};

const TimelineCard = ({ data }: Props) => {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const styles = createGlobalStyles(isDark);

  return (
    <View style={{ position: 'relative', paddingLeft: 24, marginTop: 16 }}>
      {/* Vertical Line */}
      <View
        style={{
          position: 'absolute',
          top: 6,
          bottom: 0,
          left: 11,
          width: 2,
          backgroundColor: '#6366F1',
        }}
      />

      <Text style={[styles.list.title, { marginBottom: 12 }]}>Timeline</Text>

      {data.map((item, index) => (
        <View key={index} style={{ marginBottom: 24, position: 'relative' }}>
          {/* Bullet */}
          <View
            style={{
              position: 'absolute',
              left: -18,
              top: 2,
              width: 12,
              height: 12,
              borderRadius: 6,
              backgroundColor: item.active
                ? '#10B981'
                : isDark
                ? '#4B5563'
                : '#D1D5DB',
              borderWidth: 2,
              borderColor: '#6366F1',
            }}
          />

          <Text style={[styles.list.title, { fontSize: 14 }]}>
            {item.time} — {item.title}
          </Text>

          {item.description && (
            <Text style={[styles.list.description, { fontSize: 12, marginTop: 2 }]}>
              {item.description}
            </Text>
          )}
        </View>
      ))}
    </View>
  );
};

export default TimelineCard;
