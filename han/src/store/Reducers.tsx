import { WriteCredential } from "../utils/auth";

const defaultReducer = {
  message: {
    inUnconfirmed: 0,
    outUnconfirmed: 0,
  },
  insertTag: {
    isOpen: false,
    newTag: null,
    navigation: null,
    openQRScan: () => null,
  },
};

export default {
  userReducer(state = {}, action : object) {
    switch (action.type) {
      case "CreateUserSession":
        state = {
          token: action.payload.token,
          id: action.payload.id,
          slug: action.payload.slug,
          username: action.payload.username,
          email: action.payload.email,
          avatar: action.payload.avatar,
          contact_name: action.payload.contact_name,
          created_at: action.payload.created_at,
          updated_at: action.payload.updated_at,
          status: action.payload.status,
          roles: action.payload.roles,
          permissions: action.payload.permissions,
        };
        WriteCredential(state);
        break;
    }
    return state;
  },
};