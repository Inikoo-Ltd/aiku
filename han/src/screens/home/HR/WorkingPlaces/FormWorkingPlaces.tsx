import { useState } from "react";
import { Text, View, StyleSheet, ScrollView } from "react-native";
import { useForm, Controller } from "react-hook-form";
import { TextInput, Button } from "react-native-paper";
import FieldsFrom from "~/components/FieldsForm";
import descriptor from "./Descriptor";
import Request from "~/utils/request";
import { showMessage } from "react-native-flash-message";

export default function FormWorkingSpace({ route, navigation }) {
  const [loading, setLoading] = useState(false);
  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm();

  const onSubmit = (data) => {
    console.log('dddd',data)
    setLoading(true);
/*     Request("get", p.urlKey, {}, data, [], onSuccess, onFailed); */
  };

  const onSuccess = async (res) => {
    setData(res.data);
    setLoading(false);
  };
  const onFailed = (res) => {
    setLoading(false);
    showMessage({
      message: "failed to get user data",
      type: "danger",
    });
  }

    return (
      <ScrollView contentContainerStyle={styles.container}>
        {descriptor.columns.map((item, index) => (
          <View key={index} style={styles.formItem}>
            <Controller
              key={index}
              name={item.dataIndex}
              control={control}
              rules={{ required: true }}
              render={({ field: { onChange, onBlur, value } }) => (
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
              <Text style={{ color: "red" }}>This field is required.</Text>
            )}
          </View>
        ))}
        <View style={{ alignSelf: "flex-end", marginTop: 10, marginRight: 10 }}>
          <Button
            mode="contained"
            onPress={handleSubmit(onSubmit)}
            style={{ width: 100 }}
          >
            Submit
          </Button>
        </View>
      </ScrollView>
    );
  };


const styles = StyleSheet.create({
  container: {
    flexGrow: 1,
    paddingHorizontal: 10,
    paddingVertical: 10,
  },
  formItem: {
    padding: 5,
    margin: 4,
  },
  formLabel: {
    padding: 2,
    fontSize: 12,
    fontWeight: "500",
  },
});
