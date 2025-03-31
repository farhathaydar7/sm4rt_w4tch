import React, { useState, useEffect } from "react";
import { useAuth } from "../hooks/useAuth";
import StatsCard from "../components/StatsCard";
import styles from "./Dashboard.module.css";
import { activityMetrics } from "../services/api";

const Dashboard = () => {
  const { user, logout, isAuthenticated } = useAuth();
  const [metrics, setMetrics] = useState({
    daily: null,
    weekly: null,
    monthly: null,
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [debugInfo, setDebugInfo] = useState({
    authentication: null,
    requestStatus: null,
    responseData: null,
  });

  useEffect(() => {
    const fetchMetrics = async () => {
      try {
        setLoading(true);

        // Authentication check
        if (!isAuthenticated) {
          setDebugInfo((prev) => ({
            ...prev,
            authentication: "Not authenticated",
          }));
          throw new Error("You are not authenticated. Please log in.");
        }

        setDebugInfo((prev) => ({ ...prev, authentication: "Authenticated" }));
        setDebugInfo((prev) => ({
          ...prev,
          requestStatus: "sending requests",
        }));

        // Make the API calls using our service
        try {
          const [dailyRes, weeklyRes, monthlyRes] = await Promise.all([
            activityMetrics.daily(),
            activityMetrics.weekly(),
            activityMetrics.monthly(),
          ]);

          setDebugInfo((prev) => ({
            ...prev,
            requestStatus: "requests completed",
            responseData: {
              daily: dailyRes.data,
              weekly: weeklyRes.data,
              monthly: monthlyRes.data,
            },
          }));

          setMetrics({
            daily: dailyRes.data,
            weekly: weeklyRes.data,
            monthly: monthlyRes.data,
          });
        } catch (apiError) {
          console.error("API Error:", apiError);
          setDebugInfo((prev) => ({
            ...prev,
            requestStatus: "requests failed",
            responseError: {
              message: apiError.message,
              response: apiError.response
                ? {
                    status: apiError.response.status,
                    statusText: apiError.response.statusText,
                    data: apiError.response.data,
                  }
                : "No response",
            },
          }));

          throw apiError;
        }
      } catch (err) {
        console.error("Error fetching metrics:", err);
        setError(err.message || "An unknown error occurred");
      } finally {
        setLoading(false);
      }
    };

    if (isAuthenticated) {
      fetchMetrics();
    }
  }, [isAuthenticated]);

  if (loading) {
    return <div className={styles.loading}>Loading...</div>;
  }

  if (error) {
    return (
      <div className={styles.error}>
        <p>Error: {error}</p>
        <div className={styles.debugInfo}>
          <h3>Debug Information:</h3>
          <pre>{JSON.stringify(debugInfo, null, 2)}</pre>
        </div>
      </div>
    );
  }

  // If still not authenticated after loading
  if (!isAuthenticated) {
    return (
      <div className={styles.error}>
        <p>Please log in to view your dashboard</p>
      </div>
    );
  }

  return (
    <div className={styles.dashboard}>
      <div className={styles.header}>
        <h1>Dashboard</h1>
        {user && (
          <div className={styles.userInfo}>
            <p>Welcome, {user.name}!</p>
            <button onClick={logout} className={styles.logoutButton}>
              Logout
            </button>
          </div>
        )}
      </div>

      <div className={styles.statsGrid}>
        <StatsCard
          title="Daily Activity"
          value={metrics.daily?.total_steps || 0}
          unit="steps"
          color="blue"
          icon="walking"
        />
        <StatsCard
          title="Weekly Activity"
          value={metrics.weekly?.total_steps || 0}
          unit="steps"
          color="green"
          icon="chart-line"
        />
        <StatsCard
          title="Monthly Activity"
          value={metrics.monthly?.total_steps || 0}
          unit="steps"
          color="purple"
          icon="calendar"
        />
      </div>

      <div className={styles.debugInfo}>
        <h3>Debug Information:</h3>
        <pre>{JSON.stringify(debugInfo, null, 2)}</pre>
      </div>
    </div>
  );
};

export default Dashboard;
