# Using the smrt.csv File for Activity Data Upload

This file explains how to use the `smrt.csv` file for uploading your activity data to the Sm4rt W4tch platform.

## How It Works

The system automatically processes the `smrt.csv` file located in the public directory when you log in. This means:

1. You need to manually edit this file with your activity data
2. Your data will be automatically uploaded and processed when you log in
3. No separate upload action is needed

## Requirements for the CSV File

The CSV file must follow these rules:

1. The first row must contain the header: `user_id,date,steps,distance_km,active_minutes`
2. Each data row must include:
    - Your user ID in the first column
    - Date in YYYY-MM-DD format
    - Steps count (integer)
    - Distance in kilometers (decimal)
    - Active minutes (integer)
3. At least one row must contain your correct user ID
4. Lines starting with `#` are treated as comments and ignored

## Example

Here's a correct example of the `smrt.csv` file content for a user with ID 1:

```
user_id,date,steps,distance_km,active_minutes
1,2023-04-01,8500,6.2,45
1,2023-04-02,10200,7.5,60
1,2023-04-03,5600,4.1,30
# This is a comment line - it will be ignored
```

## How to Update

1. Find your user ID (visible in your profile or in the API response after login)
2. Edit the `smrt.csv` file in a text editor or spreadsheet program
3. Replace the example data with your actual activity data using your user ID
4. Save the file
5. Log in to your account - your data will be automatically processed

## Processing Status

When you log in, the API response will include information about the status of your data upload:

-   If a valid file is found: Your data will be uploaded and you'll get a status message
-   If no valid file is found: You'll get a message instructing you to update the file

## Troubleshooting

If your data isn't being processed after login:

1. Ensure the file is named exactly `smrt.csv` (case-sensitive)
2. Verify the header row is exactly as specified above
3. Make sure your user ID in the data rows matches your actual user ID
4. Ensure the date format is YYYY-MM-DD (e.g., 2023-04-01)
5. Check that the file is properly saved in CSV format
