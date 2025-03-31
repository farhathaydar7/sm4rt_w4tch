import React, { useState, useEffect } from "react";
import { useAuth } from "../hooks/useAuth";
import StatsCard from "../components/StatsCard";
import styles from "./Dashboard.module.css";

const Dashboard = () => {
  const { user, logout } = useAuth();
  const [metrics, setMetrics] = useState({
    daily: null,
    weekly: null,
    monthly: null,
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchMetrics = async () => {
      try {
        setLoading(true);
        const [dailyRes, weeklyRes, monthlyRes] = await Promise.all([
          fetch("/api/activity-metrics/daily"),
          fetch("/api/activity-metrics/weekly"),
          fetch("/api/activity-metrics/monthly"),
        ]);

        if (!dailyRes.ok || !weeklyRes.ok || !monthlyRes.ok) {
          throw new Error("Failed to fetch metrics");
        }

        const [daily, weekly, monthly] = await Promise.all([
          dailyRes.json(),
          weeklyRes.json(),
          monthlyRes.json(),
        ]);

        setMetrics({
          daily,
          weekly,
          monthly,
        });
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchMetrics();
  }, []);

  if (loading) {
    return <div className={styles.loading}>Loading...</div>;
  }

  if (error) {
    return <div className={styles.error}>Error: {error}</div>;
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
    </div>
  );
};

export default Dashboard;
