import React, { useState, useEffect, useRef } from "react";
import { useAuth } from "../hooks/useAuth";
import StatsCard from "../components/StatsCard";
import styles from "./Dashboard.module.css";
import { activityMetrics } from "../services/api";
import axios from "axios";

const Dashboard = () => {
  const { user, logout, isAuthenticated } = useAuth();
  const [metrics, setMetrics] = useState({
    daily: null,
    weekly: null,
    monthly: null,
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  // Use a ref to track authentication attempts
  const authAttempted = useRef(false);
  const dataFetchAttempted = useRef(false);

  const [debugInfo, setDebugInfo] = useState({
    authentication: null,
    requestStatus: null,
    responseData: null,
    lastNetworkError: null,
    token: null,
  });

  // Debug function to test direct API access with axios
  const testDirectApiCall = async () => {
    try {
      const token = localStorage.getItem("token");
      setDebugInfo((prev) => ({
        ...prev,
        token: token ? `${token.substring(0, 15)}...` : "missing",
      }));

      // Try a direct call to test the API
      const response = await axios.get("/api/auth/me", {
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
      });

      setDebugInfo((prev) => ({
        ...prev,
        testApiCall: {
          success: true,
          data: response.data,
        },
      }));

      return true;
    } catch (err) {
      setDebugInfo((prev) => ({
        ...prev,
        testApiCall: {
          success: false,
          error: {
            message: err.message,
            response: err.response
              ? {
                  status: err.response.status,
                  statusText: err.response.statusText,
                  data: err.response.data,
                }
              : "No response",
          },
        },
      }));

      return false;
    }
  };

  useEffect(() => {
    // Reset the attempt flags when auth status changes
    if (!isAuthenticated) {
      dataFetchAttempted.current = false;
    }
  }, [isAuthenticated]);

  useEffect(() => {
    const fetchMetrics = async () => {
      // Prevent multiple fetch attempts
      if (dataFetchAttempted.current) return;
      dataFetchAttempted.current = true;

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
          // Make individual calls rather than Promise.all to better identify issues
          const dailyRes = await activityMetrics.daily();
          setDebugInfo((prev) => ({
            ...prev,
            dailyResponse: {
              status: dailyRes.status,
              data: dailyRes.data,
            },
          }));

          const weeklyRes = await activityMetrics.weekly();
          setDebugInfo((prev) => ({
            ...prev,
            weeklyResponse: {
              status: weeklyRes.status,
              data: weeklyRes.data,
            },
          }));

          const monthlyRes = await activityMetrics.monthly();
          setDebugInfo((prev) => ({
            ...prev,
            monthlyResponse: {
              status: monthlyRes.status,
              data: monthlyRes.data,
            },
          }));

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
            lastNetworkError: {
              message: apiError.message,
              response: apiError.response
                ? {
                    status: apiError.response.status,
                    statusText: apiError.response.statusText,
                    data: apiError.response.data,
                  }
                : "No response",
              request: apiError.request
                ? "Request object exists"
                : "No request object",
              config: apiError.config
                ? {
                    url: apiError.config.url,
                    method: apiError.config.method,
                    headers: {
                      ...apiError.config.headers,
                      Authorization: "Bearer [HIDDEN]",
                    },
                  }
                : "No config",
            },
          }));

          // Instead of trying to refresh token here, just pass the error up
          throw apiError;
        }
      } catch (err) {
        console.error("Error fetching metrics:", err);
        setError(err.message || "An unknown error occurred");
      } finally {
        setLoading(false);
      }
    };

    if (isAuthenticated && !dataFetchAttempted.current) {
      fetchMetrics();
    } else if (!isAuthenticated) {
      setLoading(false);
    }

    // No checkAuth in dependency array to prevent loops
  }, [isAuthenticated]);

  // Function to retry API calls
  const handleRetry = () => {
    setLoading(true);
    setError(null);
    dataFetchAttempted.current = false;
    authAttempted.current = false;

    setDebugInfo({
      authentication: null,
      requestStatus: null,
      responseData: null,
      lastNetworkError: null,
      token: null,
    });

    // Force a page reload instead of auth check to avoid loops
    window.location.reload();
  };

  if (loading) {
    return <div className={styles.loading}>Loading...</div>;
  }

  if (error) {
    return (
      <div className={styles.errorPage}>
        <div className={styles.error}>
          <p>Error: {error}</p>
          <button onClick={handleRetry} className={styles.retryButton}>
            Retry
          </button>
        </div>
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
        <button onClick={testDirectApiCall} className={styles.testButton}>
          Test API Connection
        </button>
        <pre>{JSON.stringify(debugInfo, null, 2)}</pre>
      </div>
    </div>
  );
};

export default Dashboard;
