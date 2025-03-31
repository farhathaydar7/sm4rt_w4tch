import React from "react";
import { useActivity } from "../hooks/useActivity";
import ActivityCard from "../components/ActivityCard";

const ActivityDashboard = () => {
  const { weeklySummary, stats, loading, error, refreshData } = useActivity();

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen">
        <p className="text-red-500 mb-4">{error}</p>
        <button
          onClick={refreshData}
          className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
        >
          Retry
        </button>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <h1 className="text-3xl font-bold mb-8">Activity Dashboard</h1>

      {/* Weekly Summary Section */}
      <section className="mb-8">
        <h2 className="text-xl font-semibold mb-4">Weekly Summary</h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <ActivityCard
            title="Total Steps"
            value={weeklySummary?.total_steps || 0}
            unit="steps"
          />
          <ActivityCard
            title="Total Distance"
            value={weeklySummary?.total_distance || 0}
            unit="km"
          />
          <ActivityCard
            title="Active Minutes"
            value={weeklySummary?.total_active_minutes || 0}
            unit="min"
          />
        </div>
      </section>

      {/* Overall Stats Section */}
      <section>
        <h2 className="text-xl font-semibold mb-4">Overall Statistics</h2>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <ActivityCard
            title="Total Steps"
            value={stats?.total_steps || 0}
            unit="steps"
          />
          <ActivityCard
            title="Total Distance"
            value={stats?.total_distance || 0}
            unit="km"
          />
          <ActivityCard
            title="Active Minutes"
            value={stats?.total_active_minutes || 0}
            unit="min"
          />
          <ActivityCard
            title="Days Tracked"
            value={stats?.total_days_tracked || 0}
            unit="days"
          />
        </div>
      </section>
    </div>
  );
};

export default ActivityDashboard;
