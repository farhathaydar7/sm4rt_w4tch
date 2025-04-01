#!/usr/bin/env python3
import requests
import json
import argparse
import sys
from datetime import datetime, timedelta
import random

def generate_sample_data(num_days=14):
    """Generate sample activity metrics data for testing"""
    today = datetime.now()
    data = []

    for i in range(num_days):
        date = (today - timedelta(days=i)).strftime('%Y-%m-%d')
        # Generate some random but reasonable values
        steps = random.randint(5000, 15000)
        active_minutes = random.randint(20, 60)
        distance = round(steps * 0.0008, 2)  # Rough estimate: 1 step ≈ 0.8m

        data.append({
            'date': date,
            'steps': steps,
            'active_minutes': active_minutes,
            'distance': distance
        })

    return data

def test_connection(base_url, token):
    """Test the AI service connection endpoint"""
    print("Testing AI service connection...")

    endpoint = f"{base_url}/ai/test"
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    }

    try:
        response = requests.get(endpoint, headers=headers)
        print(f"Status Code: {response.status_code}")

        if response.status_code == 200:
            print("Connection successful:")
            print(json.dumps(response.json(), indent=2))
            return True
        else:
            print("Connection failed:")
            print(json.dumps(response.json(), indent=2))
            return False
    except Exception as e:
        print(f"Error connecting to AI service: {str(e)}")
        return False

def test_insights(base_url, token, use_sample_data=True):
    """Test the AI insights endpoint"""
    print("\nTesting AI insights endpoint...")

    endpoint = f"{base_url}/ai/insights"
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    }

    # Use sample data or let the API get data from the database
    if use_sample_data:
        activity_metrics = {
            'daily_steps': 8542,
            'active_minutes': 35,
            'distance': 6.83
        }

        data = {
            'data': {
                'activity_metrics': activity_metrics
            }
        }
    else:
        data = {'data': {}}

    try:
        response = requests.post(endpoint, headers=headers, json=data)
        print(f"Status Code: {response.status_code}")

        if response.status_code == 200:
            print("Insights retrieved successfully:")
            result = response.json()

            # Check if using fallback
            is_fallback = result.get('data', {}).get('is_fallback', False)
            if is_fallback:
                print("WARNING: Using fallback insights (AI service might be down)")

            # Print insights in a readable format
            insights = result.get('data', {}).get('insights', {})
            if insights:
                print("\n=== INSIGHTS SUMMARY ===")
                if 'summary' in insights:
                    print(f"\nSummary: {insights['summary']}")

                if 'health_impact' in insights and insights['health_impact']:
                    print("\nHealth Impact:")
                    if isinstance(insights['health_impact'], list):
                        for item in insights['health_impact']:
                            print(f"- {item}")
                    else:
                        print(f"- {insights['health_impact']}")

                if 'recommendations' in insights and insights['recommendations']:
                    print("\nRecommendations:")
                    if isinstance(insights['recommendations'], list):
                        for item in insights['recommendations']:
                            print(f"- {item}")
                    else:
                        print(f"- {insights['recommendations']}")

                if 'next_steps' in insights and insights['next_steps']:
                    print("\nNext Steps:")
                    if isinstance(insights['next_steps'], list):
                        for i, item in enumerate(insights['next_steps']):
                            print(f"{i+1}. {item}")
                    else:
                        print(f"1. {insights['next_steps']}")
            else:
                print("No insights data found in the response")

            return True
        else:
            print("Failed to retrieve insights:")
            print(json.dumps(response.json(), indent=2))
            return False
    except Exception as e:
        print(f"Error testing insights endpoint: {str(e)}")
        return False

def test_predictions(base_url, token, use_sample_data=True):
    """Test the AI predictions endpoint"""
    print("\nTesting AI predictions endpoint...")

    endpoint = f"{base_url}/ai/predict"
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    }

    # Use sample data or let the API get data from the database
    if use_sample_data:
        activity_history = generate_sample_data(14)  # Generate 14 days of sample data

        data = {
            'data': {
                'activity_history': activity_history,
                'goals': {
                    'daily_steps': 10000,
                    'weekly_active_minutes': 150
                }
            }
        }
    else:
        data = {'data': {}}

    try:
        response = requests.post(endpoint, headers=headers, json=data)
        print(f"Status Code: {response.status_code}")

        if response.status_code == 200:
            print("Predictions retrieved successfully:")
            result = response.json()

            # Check if using fallback
            is_fallback = result.get('data', {}).get('is_fallback', False)
            if is_fallback:
                print("WARNING: Using fallback predictions (AI service might be down)")

            # Print predictions in a readable format
            predictions = result.get('data', {}).get('predictions', {})
            if predictions:
                print("\n=== PREDICTIONS SUMMARY ===")

                if 'goal_achievement' in predictions:
                    goal_data = predictions['goal_achievement']
                    print("\nGoal Achievement:")
                    print(f"- Daily Step Goal: {goal_data.get('daily_step_goal', 'N/A')} steps")
                    print(f"- Step Goal Likelihood: {goal_data.get('step_goal_likelihood', 'N/A')}")
                    print(f"- Weekly Active Minutes Goal: {goal_data.get('weekly_active_minutes_goal', 'N/A')} minutes")
                    print(f"- Active Minutes Goal Likelihood: {goal_data.get('active_minutes_goal_likelihood', 'N/A')}")

                if 'anomaly_detection' in predictions and 'anomalies' in predictions['anomaly_detection']:
                    anomalies = predictions['anomaly_detection']['anomalies']
                    if anomalies:
                        print("\nDetected Anomalies:")
                        for anomaly in anomalies:
                            print(f"- {anomaly.get('date', 'N/A')}: {anomaly.get('reason', 'N/A')} ({anomaly.get('steps', 'N/A')} steps)")
                    else:
                        print("\nNo anomalies detected")

                if 'future_projections' in predictions:
                    projections = predictions['future_projections']
                    if projections:
                        print("\nActivity Projections for Next 7 Days:")
                        for proj in projections[:7]:  # Limit to 7 days
                            date = proj.get('date', 'N/A')
                            day = proj.get('day_of_week', '')
                            steps = proj.get('projected_steps', 'N/A')
                            minutes = proj.get('projected_active_minutes', 'N/A')
                            print(f"- {date} ({day}): {steps} steps, {minutes} active minutes")

                if 'actionable_insights' in predictions:
                    insights = predictions['actionable_insights']
                    if insights:
                        print("\nActionable Insights:")
                        if isinstance(insights, list):
                            for insight in insights:
                                print(f"- {insight}")
                        else:
                            print(f"- {insights}")
            else:
                print("No prediction data found in the response")

            return True
        else:
            print("Failed to retrieve predictions:")
            print(json.dumps(response.json(), indent=2))
            return False
    except Exception as e:
        print(f"Error testing predictions endpoint: {str(e)}")
        return False

def get_auth_token(base_url, email, password):
    """Get authentication token using credentials"""
    print("Authenticating...")

    auth_endpoint = f"{base_url}/auth/login"
    data = {
        'email': email,
        'password': password
    }

    try:
        response = requests.post(auth_endpoint, json=data)
        if response.status_code == 200:
            token = response.json().get('token', '')
            if token:
                print("Authentication successful")
                return token
            else:
                print("Authentication failed: No token in response")
                return None
        else:
            print(f"Authentication failed: {response.status_code}")
            print(json.dumps(response.json(), indent=2))
            return None
    except Exception as e:
        print(f"Error during authentication: {str(e)}")
        return None

def main():
    parser = argparse.ArgumentParser(description='Test SM4RT W4TCH AI Service Endpoints')
    parser.add_argument('--url', default='http://localhost:8000/api', help='Base URL for the API')
    parser.add_argument('--email', required=True, help='User email for authentication')
    parser.add_argument('--password', required=True, help='User password for authentication')
    parser.add_argument('--sample-data', action='store_true', help='Use sample data instead of database data')
    parser.add_argument('--test', choices=['all', 'connection', 'insights', 'predictions'], default='all',
                        help='Specify which test to run')

    args = parser.parse_args()

    # Get authentication token
    token = get_auth_token(args.url, args.email, args.password)
    if not token:
        print("Failed to authenticate. Exiting.")
        sys.exit(1)

    # Execute requested tests
    if args.test in ['all', 'connection']:
        connection_ok = test_connection(args.url, token)
        if args.test == 'connection' and not connection_ok:
            sys.exit(1)

    if args.test in ['all', 'insights']:
        insights_ok = test_insights(args.url, token, args.sample_data)
        if args.test == 'insights' and not insights_ok:
            sys.exit(1)

    if args.test in ['all', 'predictions']:
        predictions_ok = test_predictions(args.url, token, args.sample_data)
        if args.test == 'predictions' and not predictions_ok:
            sys.exit(1)

    print("\nAll tests completed.")

if __name__ == "__main__":
    main()
