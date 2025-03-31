import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import api from "../../services/api";

// Async thunks
export const fetchAllActivities = createAsyncThunk(
  "activity/fetchAll",
  async () => {
    const response = await api.get("/activity/all");
    return response.data.data;
  }
);

export const fetchActivityByDate = createAsyncThunk(
  "activity/fetchByDate",
  async (date) => {
    const response = await api.get(`/activity/date/${date}`);
    return response.data.data;
  }
);

export const fetchWeeklySummary = createAsyncThunk(
  "activity/fetchWeeklySummary",
  async () => {
    const response = await api.get("/activity/week");
    return response.data.data;
  }
);

export const fetchActivityStats = createAsyncThunk(
  "activity/fetchStats",
  async () => {
    const response = await api.get("/activity/stats");
    return response.data.data;
  }
);

const initialState = {
  activities: [],
  currentDateActivity: null,
  weeklySummary: null,
  stats: null,
  loading: false,
  error: null,
};

const activitySlice = createSlice({
  name: "activity",
  initialState,
  reducers: {
    clearError: (state) => {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch All Activities
      .addCase(fetchAllActivities.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(fetchAllActivities.fulfilled, (state, action) => {
        state.loading = false;
        state.activities = action.payload;
      })
      .addCase(fetchAllActivities.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message;
      })
      // Fetch Activity By Date
      .addCase(fetchActivityByDate.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(fetchActivityByDate.fulfilled, (state, action) => {
        state.loading = false;
        state.currentDateActivity = action.payload;
      })
      .addCase(fetchActivityByDate.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message;
      })
      // Fetch Weekly Summary
      .addCase(fetchWeeklySummary.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(fetchWeeklySummary.fulfilled, (state, action) => {
        state.loading = false;
        state.weeklySummary = action.payload;
      })
      .addCase(fetchWeeklySummary.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message;
      })
      // Fetch Stats
      .addCase(fetchActivityStats.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(fetchActivityStats.fulfilled, (state, action) => {
        state.loading = false;
        state.stats = action.payload;
      })
      .addCase(fetchActivityStats.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message;
      });
  },
});

export const { clearError } = activitySlice.actions;
export default activitySlice.reducer;
