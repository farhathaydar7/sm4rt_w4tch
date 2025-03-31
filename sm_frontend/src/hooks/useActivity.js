import { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import {
  fetchAllActivities,
  fetchActivityByDate,
  fetchWeeklySummary,
  fetchActivityStats,
  clearError,
} from "../store/slices/activitySlice";

export const useActivity = () => {
  const dispatch = useDispatch();
  const {
    activities,
    currentDateActivity,
    weeklySummary,
    stats,
    loading,
    error,
  } = useSelector((state) => state.activity);

  useEffect(() => {
    dispatch(fetchAllActivities());
    dispatch(fetchWeeklySummary());
    dispatch(fetchActivityStats());
  }, [dispatch]);

  const getActivityByDate = (date) => {
    dispatch(fetchActivityByDate(date));
  };

  const getWeeklySummary = (weekId) => {
    dispatch(fetchWeeklySummary(weekId));
  };

  const refreshData = () => {
    dispatch(fetchAllActivities());
    dispatch(fetchWeeklySummary());
    dispatch(fetchActivityStats());
  };

  const clearActivityError = () => {
    dispatch(clearError());
  };

  return {
    activities,
    currentDateActivity,
    weeklySummary,
    stats,
    loading,
    error,
    getActivityByDate,
    getWeeklySummary,
    refreshData,
    clearActivityError,
  };
};
