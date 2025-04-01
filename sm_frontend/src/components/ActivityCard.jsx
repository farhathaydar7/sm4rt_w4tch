import React from "react";

const ActivityCard = ({ title, value, unit, icon }) => {
  return (
    <div className="bg-white rounded-lg shadow-md p-6">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-gray-500 text-sm font-medium">{title}</h3>
          <p className="text-2xl font-bold mt-1">
            {value}
            <span className="text-sm text-gray-500 ml-1">{unit}</span>
          </p>
        </div>
        {icon && <div className="text-gray-400">{icon}</div>}
      </div>
    </div>
  );
};

export default ActivityCard;
