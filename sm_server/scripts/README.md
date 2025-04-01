# SM4RT W4TCH Scripts

This directory contains utility scripts for the SM4RT W4TCH application.

## AI Endpoints Testing Script

The `test_ai_endpoints.py` script allows you to test the AI service endpoints of the SM4RT W4TCH application. It tests the connection to the AI service and the functionality of the prediction and insights endpoints.

### Prerequisites

-   Python 3.6 or higher
-   Required Python packages:
    -   requests
    -   json
    -   argparse
    -   datetime

You can install the required packages using pip:

```
pip install requests
```

### Usage

```
python test_ai_endpoints.py --email YOUR_EMAIL --password YOUR_PASSWORD [options]
```

#### Required Arguments

-   `--email`: Your user email for authentication
-   `--password`: Your user password for authentication

#### Optional Arguments

-   `--url`: Base URL for the API (default: `http://localhost:8000/api`)
-   `--sample-data`: Use sample data instead of database data
-   `--test`: Specify which test to run. Options:
    -   `all`: Run all tests (default)
    -   `connection`: Test only the AI service connection
    -   `insights`: Test only the insights endpoint
    -   `predictions`: Test only the predictions endpoint

### Examples

Test all endpoints with sample data:

```
python test_ai_endpoints.py --email user@example.com --password secret123 --sample-data
```

Test only the AI service connection:

```
python test_ai_endpoints.py --email user@example.com --password secret123 --test connection
```

Test insights endpoint with database data:

```
python test_ai_endpoints.py --email user@example.com --password secret123 --test insights
```

Test with a custom API URL:

```
python test_ai_endpoints.py --email user@example.com --password secret123 --url https://api.example.com/api
```

### Output

The script provides detailed output for each test:

1. **Connection Test**: Tests if the AI service is running and accessible
2. **Insights Test**: Tests retrieving AI-generated insights about activity data
3. **Predictions Test**: Tests retrieving AI-generated predictions based on activity history

If any test fails, the script will show an error message and exit with a non-zero status code.

### Troubleshooting

If you encounter any issues:

1. Make sure the SM4RT W4TCH API server is running
2. Verify that your email and password are correct
3. Check that the AI service is properly configured and running
4. Check the server logs for more detailed error information

For fallback mode (when AI service is unavailable), the endpoints will still work but will use simplified algorithms instead of the AI model.
