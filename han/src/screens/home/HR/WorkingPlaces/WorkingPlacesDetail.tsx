import React, {useEffect, useState} from 'react';
import {
  Text,
  View,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Image,
  ActivityIndicator,
} from 'react-native';
import Request from '~/utils/request';
import {useNavigation} from '@react-navigation/native';
import {showMessage} from 'react-native-flash-message';
import { get } from 'lodash-es';
import { Avatar, Button } from 'react-native-paper';
import {ROUTES, COLORS} from '~/constants';

export default function WorkingSpaceDetail(p) {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(false);
  const navigation = useNavigation();

  const getDataList = () => {
    setLoading(true);
    Request(
      'get',
      'hr-retrive-working-places',
      {},
      {},
      [p.route.params.id],
      onSuccess,
      onFailed,
    );
  };
  const onSuccess = async res => {
    console.log(res);
    setData(res.data);
    setLoading(false);
  };
  const onFailed = res => {
    setLoading(false);
    showMessage({
      message: 'failed to get user data',
      type: 'danger',
    });
  };

  useEffect(() => {
    getDataList();
  }, []);

  return !loading ? (
    <ScrollView contentContainerStyle={styles.scrollContainer}>
      <Image style={styles.coverImage} />
      <View style={styles.avatarContainer}>
        <TouchableOpacity onPress={() => setModalVisible(true)}>
        <Avatar.Icon size={80} icon="google-maps" />
        </TouchableOpacity>

        <Text style={[styles.name, styles.textWithShadow]}>{get(data,'name','-')}</Text>
        <Text style={[styles.infoValue, styles.centerText]}>{get(data,"type",'-')}</Text>
      </View>
      <View style={styles.content}>
        <View style={styles.infoContainer}>
          <Text style={styles.infoLabel}>Address 1:</Text>
          <Text style={styles.infoValue}>{get(data,["address","address_line_1"])}</Text>
        </View>
        <View style={styles.infoContainer}>
          <Text style={styles.infoLabel}>Address 2:</Text>
          <Text style={styles.infoValue}>{get(data,["address","address_line_2"])}</Text>
        </View>
        <View style={styles.buttonContainer}>
          <Button
            icon="lead-pencil"
            mode="outlined"
            onPress={() => navigation.navigate(ROUTES.WORKING_PLACES + 'edit')}
            style={styles.editButton}>
            Edit
          </Button>
        </View>
      </View>
    </ScrollView>
  ) : (
    <View style={styles.loadingContainer}>
      <ActivityIndicator size="large" color={COLORS.primary} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
    padding: 20,
  },
  scrollContainer: {
    flexGrow: 1,
    backgroundColor: '#f5f5f5',
    padding: 20,
  },
  centerText: {
    textAlign: 'center',
  },
  coverImage: {
    height: 100,
    position: 'absolute',
    backgroundColor: COLORS.primary,
    top: 0,
    left: 0,
    right: 0,
  },
  avatarContainer: {
    alignItems: 'center',
    marginTop: 30,
  },
  name: {
    fontSize: 28,
    fontWeight: 'bold',
    marginTop: 15,
    color: COLORS.primary,
  },
  infoLabel: {
    fontWeight: 'bold',
    color: COLORS.primary,
  },
  infoValue: {
    marginTop: 5,
    color: '#333',
  },
  content: {
    marginTop: 20,
  },
  infoContainer: {
    marginTop: 10,
    paddingBottom: 15,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  avatar: {
    width: 150,
    height: 150,
    borderRadius: 75,
    borderWidth: 6,
    borderColor: '#fff',
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 6,
    },
    shadowOpacity: 0.5,
    shadowRadius: 6,
  },
  activeChip: {
    backgroundColor: '#87D068',
  },
  inactiveChip: {
    backgroundColor: '#FF5500',
  },
  statusText: {
    color: '#fff',
    fontWeight: 'bold',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  buttonContainer: {
    marginTop: 20,
  },
  editButton: {
    marginBottom: 10,
  },
  containerStyle: {
    backgroundColor: 'white',
    padding: 20,
  },
});
