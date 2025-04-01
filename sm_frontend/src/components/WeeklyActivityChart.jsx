import React from "react";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from "chart.js";
import { Bar } from "react-chartjs-2";

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
);

const WeeklyActivityChart = ({ weeklyData }) => {
  if (
    !weeklyData ||
    !weeklyData.daily_data ||
    weeklyData.daily_data.length === 0
  ) {
    return (
      <div className="p-4 text-center text-gray-500">
        No weekly data available
      </div>
    );
  }

  const labels = weeklyData.daily_data.map((day) => {
    // Format date as "Mon", "Tue", etc.
    return new Date(day.date).toLocaleDateString("en-US", { weekday: "short" });
  });

  const stepsData = weeklyData.daily_data.map((day) => day.steps);
  const distanceData = weeklyData.daily_data.map((day) => day.distance);
  const activeMinutesData = weeklyData.daily_data.map(
    (day) => day.active_minutes
  );

  const data = {
    labels,
    datasets: [
      {
        label: "Steps",
        data: stepsData,
        backgroundColor: "rgba(53, 162, 235, 0.5)",
      },
    ],
  };

  const options = {
    responsive: true,
    plugins: {
      legend: {
        position: "top",
      },
      title: {
        display: true,
        text: "Weekly Steps Activity",
      },
    },
  };

  return (
    <div className="bg-white p-4 rounded-lg shadow-md">
      <h3 className="text-lg font-medium mb-4">Weekly Activity</h3>
      <Bar options={options} data={data} />

      <div className="mt-4 grid grid-cols-3 gap-4">
        <div className="text-center">
          <p className="text-sm text-gray-500">Total Steps</p>
          <p className="text-xl font-bold">{weeklyData.total_steps}</p>
        </div>
        <div className="text-center">
          <p className="text-sm text-gray-500">Total Distance</p>
          <p className="text-xl font-bold">{weeklyData.total_distance} km</p>
        </div>
        <div className="text-center">
          <p className="text-sm text-gray-500">Active Minutes</p>
          <p className="text-xl font-bold">
            {weeklyData.total_active_minutes} min
          </p>
        </div>
      </div>
    </div>
  );
};

export default WeeklyActivityChart;
