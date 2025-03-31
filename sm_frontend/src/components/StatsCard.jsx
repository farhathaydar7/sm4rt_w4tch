import React from "react";
import { FaWalking, FaRunning, FaClock, FaCalendarAlt } from "react-icons/fa";
import styles from "./StatsCard.module.css";

const StatsCard = ({ title, value, unit, icon, color }) => {
  const getIcon = () => {
    switch (icon) {
      case "walking":
        return <FaWalking />;
      case "running":
        return <FaRunning />;
      case "clock":
        return <FaClock />;
      case "calendar":
        return <FaCalendarAlt />;
      default:
        return null;
    }
  };

  const getColorClass = () => {
    switch (color) {
      case "blue":
        return styles.blue;
      case "green":
        return styles.green;
      case "purple":
        return styles.purple;
      case "indigo":
        return styles.indigo;
      default:
        return styles.blue;
    }
  };

  return (
    <div className={styles.statsCard}>
      <div className={`${styles.header} ${getColorClass()}`}>
        <div className={styles.headerContent}>
          <h3 className={styles.title}>{title}</h3>
          <span className={styles.icon}>{getIcon()}</span>
        </div>
      </div>
      <div className={styles.content}>
        <p className={styles.value}>
          {value}
          <span className={styles.unit}>{unit}</span>
        </p>
      </div>
    </div>
  );
};

export default StatsCard;
