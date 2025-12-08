import React, {useEffect, useState} from 'react';

import Request from '~/utils/request';
import {
  StyleSheet,
  View,
  Text,
  ActivityIndicator,
  Image,
  ScrollView,
} from 'react-native';
import {Button} from 'react-native-paper';
import {useNavigation} from '@react-navigation/native';
import {ROUTES, COLORS} from '~/constants';
import {useForm, Controller} from 'react-hook-form';
import FieldsFrom from '~/components/FieldsForm';
import descriptor from './Descriptor';
import {showMessage} from 'react-native-flash-message';
import {useDispatch,useSelector} from 'react-redux';
import Action from '~/store/Action';

const ProfileScreen = () => {
  const [profileData, setProfileData] = useState(null);
  const dispatch = useDispatch()
  const [loading, setLoading] = useState(true);
  const navigation = useNavigation();
  const user = useSelector(state => state.userReducer);
  const {
    control,
    handleSubmit,
    formState: {errors},
    setValue,
  } = useForm();


  const getData = () => {
    setLoading(true);
    Request('get', 'profile', {}, {}, [], onSuccess, onFailed);
  };

  const onSuccess = res => {
    setProfileData(res.data);
    setFormValues(res.data);
    setLoading(false);
  };

  const setFormValues = data => {
    for (const item of descriptor.columns) {
      if(data[item.dataIndex])
      setValue(item.dataIndex, data[item.dataIndex]);
    }
  };

  const onFailed = res => {
    setLoading(false);
  };

  const onSubmit = async (data : object) => {
    await Request(
       'post',
       'profile',
       {},
       {...data},
       [],
       onSubmitSuccess,
       onSubmitFailed,
     );
   };

   const onSubmitSuccess=(res)=>{
      dispatch(
        Action.CreateUserSessionProperties({...user, ...res}),
      );
      navigation.navigate(ROUTES.BOTTOMHOME);
    
      showMessage({
        message: 'Profile already updated',
        type: 'success',
      });
   }

   const onSubmitFailed=(error)=>{
    console.log(error)
   }

  useEffect(() => {
    getData();
  }, []);

  return !loading ? (
    <ScrollView contentContainerStyle={styles.scrollContainer}>
      <Image style={styles.coverImage} />
      <View style={styles.avatarContainer}>
        <View>
          <Image
            source={{
              uri: 'https://www.bootdey.com/img/Content/avatar/avatar1.png',
            }}
            style={styles.avatar}
          />
        </View>
      </View>
      <View style={styles.content}>
        {descriptor.columns.map((item, index) => (
          <View key={index} style={styles.formItem}>
            <Controller
              key={index}
              name={item.dataIndex}
              control={control}
              {...item.formItemProps}
              render={({field: {onChange, onBlur, value}}) => (
                <>
                  <Text style={styles.formLabel}>{item.title}</Text>
                  <FieldsFrom
                    mode="outlined"
                    onBlur={onBlur}
                    onChangeText={onChange}
                    value={value}
                    type={item.type}
                    {...item.fieldProps}
                  />
                </>
              )}
            />
            {errors[item.dataIndex] && (
              <Text style={{color: 'red'}}>This field is required.</Text>
            )}
          </View>
        ))}
      </View>
      <View style={styles.buttonContainer}>
        <Button
          icon="content-save"
          mode="contained"
          onPress={handleSubmit(onSubmit)}
          style={styles.editButton}>
          Save
        </Button>
        <Button
          mode="outlined"
          onPress={() => navigation.navigate(ROUTES.BOTTOMHOME)}
          style={styles.logoutButton}>
          Cancel
        </Button>
      </View>
    </ScrollView>
  ) : (
    <View style={styles.loadingContainer}>
      <ActivityIndicator size="large" color={COLORS.primary} />
    </View>
  );
};

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
    height: 150,
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
    // Properti lain sesuai kebutuhan gaya
  },
  logoutButton: {
    // Properti lain sesuai kebutuhan gaya
  },
  formItem: {
    padding: 5,
    margin: 4,
  },
  formLabel: {
    padding: 2,
    fontSize: 12,
    fontWeight: '500',
  },
});

export default ProfileScreen;
