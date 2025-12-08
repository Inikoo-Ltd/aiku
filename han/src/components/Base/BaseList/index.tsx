import React, {useEffect, useState} from 'react';
import {StyleSheet, View, ActivityIndicator,ScrollView} from 'react-native';
import {Card, Text, AnimatedFAB, Avatar, IconButton} from 'react-native-paper';
import Request from '~/utils/request';
import {useNavigation} from '@react-navigation/native';
import {COLORS} from '~/constants';
import {showMessage} from 'react-native-flash-message';
import Header from '~/components/Header/HeaderList';

const BaseList = (p: object) => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(false);
  const navigation = useNavigation();

  const getDataList = () => {
    setLoading(true);
    Request('get', p.urlKey, {}, {}, p.args, onSuccess, onFailed);
  };
  const onSuccess = async res => {
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

  const renderHeader = () => {
    return p.useHeader ? <Header record={{count: data.length}} /> : null;
  };

  const renderCardContent = data => {
    return p.cardContent ? (
      p.cardContent(data)
    ) : (
      <Card.Title
        title={data.name}
        subtitle={data.type}
        left={props => <Avatar.Icon {...props} icon="map-marker" />}
        right={props => (
          <IconButton
            {...props}
            icon="chevron-right"
            onPress={() =>
              navigation.navigate(p.urlPrefix + ' Edit', {id: data.id})
            }
          />
        )}
      />
    );
  };

  const renderList = () => {
    return data.length > 0 ? (
      <Card style={styles.card}>
        {data.map((data, index) => (
          <View key={index}>{renderCardContent(data)}</View>
        ))}
      </Card>
    ) : (
      <Text>No data</Text>
    );
  };

  const renderAddButton = () => {
    return p.useAddButton ? (
      p.renderAddButton ? (
        p.renderAddButton()
      ) : (
        <AnimatedFAB
          icon={'plus'}
          label={'Label'}
          onPress={() => navigation.navigate(p.urlPrefix + 'add')}
          visible={true}
          animateFrom={'right'}
          iconMode={'static'}
          style={[styles.fabStyle]}
        />
      )
    ) : null;
  };

  useEffect(() => {
    getDataList();
  }, []);

  return !loading ? (
    <View style={styles.container}>
      {renderHeader()}
      <ScrollView >
      {renderList()}
      </ScrollView>
      {renderAddButton()}
      
    </View>
  ) : (
    <View
      style={{
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
      }}>
      <ActivityIndicator size="large" color={COLORS.primary} />
    </View>
  );
};

BaseList.defaultProps = {
  useHeader: true,
  header: {},
  urlKey: '',
  urlPrefix: '',
  useAddButton: true,
  args: [],
};

export default BaseList;

const styles = StyleSheet.create({
  container: {
    flex: 1,
    paddingHorizontal: 10,
    paddingVertical: 0,
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 10,
  },
  card: {
    marginVertical: 10,
    padding: 10,
    borderRadius: 8,
    elevation: 4,
  },
  cardContent: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  title: {
    fontSize: 16,
    fontWeight: 'bold',
  },
  buttonContainer: {
    flexDirection: 'row',
  },
  button: {
    marginHorizontal: 5,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 16,
    paddingBottom: 8,
  },
  titleContainer: {},
  headerTitle: {
    fontSize: 16,
    padding: 0,
  },
  iconContainer: {
    flexDirection: 'row',
  },
  iconArea: {
    marginHorizontal: 5,
  },
  fabStyle: {
    bottom: 16,
    right: 16,
    position: 'absolute',
  },
});
