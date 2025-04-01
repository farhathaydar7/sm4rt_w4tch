import React, { useState } from "react";
import { Line } from "react-chartjs-2";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
} from "chart.js";

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
);

const ActivityHistory = ({ activities }) => {
  const [metric, setMetric] = useState("steps"); // 'steps', 'distance', or 'active_minutes'

  if (!activities || activities.length === 0) {
    return (
      <div className="p-4 text-center text-gray-500">
        No activity history available
      </div>
    );
  }

  // Sort activities by date
  const sortedActivities = [...activities].sort(
    (a, b) => new Date(a.activity_date) - new Date(b.activity_date)
  );

  // Get last 30 days of data or all if less than 30
  const recentActivities = sortedActivities.slice(-30);

  const labels = recentActivities.map((activity) => {
    const date = new Date(activity.activity_date);
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  });

  const getDatasetByMetric = () => {
    switch (metric) {
      case "steps":
        return {
          label: "Steps",
          data: recentActivities.map((activity) => activity.steps),
          borderColor: "rgb(53, 162, 235)",
          backgroundColor: "rgba(53, 162, 235, 0.5)",
        };
      case "distance":
        return {
          label: "Distance (km)",
          data: recentActivities.map((activity) => activity.distance),
          borderColor: "rgb(75, 192, 192)",
          backgroundColor: "rgba(75, 192, 192, 0.5)",
        };
      case "active_minutes":
        return {
          label: "Active Minutes",
          data: recentActivities.map((activity) => activity.active_minutes),
          borderColor: "rgb(255, 99, 132)",
          backgroundColor: "rgba(255, 99, 132, 0.5)",
        };
      default:
        return {
          label: "Steps",
          data: recentActivities.map((activity) => activity.steps),
          borderColor: "rgb(53, 162, 235)",
          backgroundColor: "rgba(53, 162, 235, 0.5)",
        };
    }
  };

  const data = {
    labels,
    datasets: [getDatasetByMetric()],
  };

  const options = {
    responsive: true,
    plugins: {
      legend: {
        position: "top",
      },
      title: {
        display: true,
        text: "Activity History",
      },
    },
    scales: {
      y: {
        beginAtZero: true,
      },
    },
  };

  return (
    <div className="bg-white p-4 rounded-lg shadow-md">
      <div className="flex justify-between items-center mb-4">
        <h3 className="text-lg font-medium">Activity History</h3>
        <div className="flex space-x-2">
          <button
            className={`px-3 py-1 rounded text-sm ${
              metric === "steps" ? "bg-blue-500 text-white" : "bg-gray-200"
            }`}
            onClick={() => setMetric("steps")}
          >
            Steps
          </button>
          <button
            className={`px-3 py-1 rounded text-sm ${
              metric === "distance" ? "bg-blue-500 text-white" : "bg-gray-200"
            }`}
            onClick={() => setMetric("distance")}
          >
            Distance
          </button>
          <button
            className={`px-3 py-1 rounded text-sm ${
              metric === "active_minutes"
                ? "bg-blue-500 text-white"
                : "bg-gray-200"
            }`}
            onClick={() => setMetric("active_minutes")}
          >
            Active Min
          </button>
        </div>
      </div>
      <Line options={options} data={data} />
    </div>
  );
};

export default ActivityHistory;
