import React, {useState, useEffect} from 'react';
import {
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  View,
  ScrollView,
  ActivityIndicator,
  Pressable,
} from 'react-native';
import {Avatar, Card, Divider, Text, Chip} from 'react-native-paper';
import {COLORS, ROUTES} from '~/constants';
import Request from '~/utils/request';
import {showMessage} from 'react-native-flash-message';
import {useNavigation} from '@react-navigation/native';

const HomeHR = () => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(false);
  const dayjs = require('dayjs');
  const navigation = useNavigation();

  const getDataList = () => {
    setLoading(true);
    Request('get', 'hr-clocking-machines', {}, {}, [], onSuccess, onFailed);
  };
  const onSuccess = async res => {
    console.log(res);
    setData(res.data);
    setLoading(false);
  };
  const onFailed = res => {
    console.log(res)
    setLoading(false);
    showMessage({
      message: 'failed to get  data',
      type: 'danger',
    });
  };

  useEffect(() => {
    getDataList();
  }, []);

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.cardContainer}>
        <Card style={styles.card}>
          <Card.Title
            title="Working Places"
            subtitle="67"
            titleStyle={styles.cardTitle}
            subtitleStyle={styles.cardSubtitle}
            left={props => (
              <Avatar.Icon
                {...props}
                icon="google-maps"
                style={styles.avatarStyle}
              />
            )}
          />
        </Card>

        <Card style={styles.card}>
          <Card.Title
            title="Time Sheets"
            subtitle="19"
            titleStyle={styles.cardTitle}
            subtitleStyle={styles.cardSubtitle}
            left={props => (
              <Avatar.Icon
                {...props}
                icon="clock-check"
                style={styles.avatarStyle}
              />
            )}
          />
        </Card>
      </View>
      <Divider bold={true} />
      <View style={styles.menuContainer}>
        <Pressable
          style={styles.avatarContainer}
          onPress={() => navigation.navigate(ROUTES.WORKING_PLACES)}>
          <Avatar.Icon
            icon="google-maps"
            size={50}
            style={styles.avatarStyle}
          />
          <Text style={styles.avatarText}>Working Places</Text>
        </Pressable>
        <Pressable
          style={styles.avatarContainer}
          onPress={() => navigation.navigate(ROUTES.CLOCKING_MACHINE)}>
          <Avatar.Icon
            icon="clock-check"
            size={50}
            style={styles.avatarStyle}
          />
          <Text style={styles.avatarText}>Clocking Machines</Text>
        </Pressable>
      </View>
      <View style={{marginVertical: 5}}>
        <Text>Today's Attendance</Text>
        <Divider bold={true} style={{marginVertical: 5}} />
      </View>
      {loading ? (
        <ActivityIndicator size="large" color={COLORS.primary} />
      ) : (
        <ScrollView style={styles.listContainer}>
          {data.map((item, index) => (
            <Card key={index} style={styles.card}>
              <Card.Content>
                <Text variant="titleLarge">{item.name}</Text>
                <View style={styles.listCardContainer}>
                  <View style={styles.chipContainer}>
                    <Chip style={styles.chip}>{item.workplace.name}</Chip>
                  </View>
                  <Text variant="bodyMedium">
                    {dayjs(item.updated_at).format('dddd mm YYYY')}
                  </Text>
                </View>
              </Card.Content>
            </Card>
          ))}
        </ScrollView>
      )}
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 10,
  },
  listContainer: {
    paddingVertical: 10,
  },
  cardContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginVertical: 10,
  },
  listCardContainer: {
    flexDirection: 'column', // Use column direction for better spacing
    alignItems: 'flex-start', // Align items to the start of the container
    marginVertical: 10,
  },
  chipContainer: {
    flexDirection: 'row', // Use row direction for chips
    flexWrap: 'wrap', // Allow chips to wrap to the next line if needed
  },
  chip: {
    marginVertical: 5,
    marginRight: 5,
    backgroundColor: COLORS.primary,
  },
  menuContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    margin: 10,
    backgroundColor: '#ffff',
    borderRadius: 10,
  },
  card: {
    flex: 1,
    margin: 8,
    backgroundColor: '#ffff',
  },
  cardTitle: {
    fontSize: 12,
  },
  cardSubtitle: {
    fontSize: 18,
  },
  avatarContainer: {
    alignItems: 'center',
    margin: 10,
  },
  avatarStyle: {
    backgroundColor: COLORS.primary,
    margin: 10,
  },
  avatarText: {
    fontSize: 10,
  },
});

export default HomeHR;
