import { configureStore } from "@reduxjs/toolkit";
import activityReducer from "./slices/activitySlice";
import authReducer from "./slices/authSlice";

export const store = configureStore({
  reducer: {
    activity: activityReducer,
    auth: authReducer,
  },
});
