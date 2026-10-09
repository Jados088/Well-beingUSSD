# BMI Calculator USSD Application

A USSD-based BMI (Body Mass Index) calculator application that allows users to calculate their BMI and receive personalized health recommendations via SMS.

## Features

- User registration with name and PIN
- BMI calculation based on height and weight
- Personalized health recommendations
- SMS notifications for BMI results
- Secure user data storage
- USSD menu navigation

## Prerequisites

- PHP 7.4 or higher
- MySQL/MariaDB
- XAMPP (or similar local development environment)
- Africa's Talking API account (for SMS functionality)

## Installation

1. Clone the repository to your XAMPP htdocs directory:
   ```
   cd /path/to/xampp/htdocs
   git clone [repository-url] bmi_app
   ```

2. Create the database:
   ```sql
   CREATE DATABASE bmi_app;
   ```

3. Import the database schema:
   ```sql
   USE bmi_app;
   CREATE TABLE users (
       id INT AUTO_INCREMENT PRIMARY KEY,
       phone VARCHAR(15) NOT NULL UNIQUE,
       name VARCHAR(100) NOT NULL,
       pin VARCHAR(255) NOT NULL,
       height DECIMAL(4,2),
       weight DECIMAL(5,2),
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```

4. Configure Africa's Talking API:
   - Sign up for an Africa's Talking account
   - Get your API key from the dashboard
   - Update the API credentials in `index.php`:
     ```php
     private static $username = 'sandbox';
     private static $apiKey = 'your_api_key';
     private static $senderId = 'BMI_APP';
     ```

## Usage

1. Access the USSD application through your mobile phone:
   ```
   *384*1234#
   ```

2. Follow the USSD menu:
   - First-time users will be prompted to register
   - Enter your name and create a 4-digit PIN
   - Calculate BMI by entering height and weight
   - Receive BMI results and recommendations via SMS

## USSD Flow

1. Registration:
   - Enter name
   - Create 4-digit PIN
   - Confirm PIN

2. BMI Calculation:
   - Enter height in meters
   - Enter weight in kilograms
   - Confirm calculation
   - Receive results via SMS

## SMS Notifications

The application sends SMS notifications for:
- Successful registration
- BMI calculation results
- Health recommendations

## Error Handling

The application includes comprehensive error handling for:
- Invalid user input
- Database connection issues
- SMS sending failures
- USSD session management

## Security Features

- PIN-based user authentication
- Secure database connections
- Input validation
- Error logging

## Development

To modify the application:

1. Update the USSD menu in `USSDHandler` class
2. Modify BMI calculations in `BMICalculator` class
3. Adjust SMS templates in the `SMS` class
4. Update database schema if needed

## Testing

1. Use Africa's Talking sandbox for testing:
   - Register test phone numbers
   - Use sandbox API credentials
   - Monitor SMS delivery in sandbox

2. Test USSD flow:
   - Registration process
   - BMI calculation
   - Error handling
   - SMS delivery

## Support

For issues and support:
1. Check error logs in your server
2. Verify Africa's Talking API status
3. Ensure database connectivity
4. Validate USSD session parameters

## License

This project is licensed under the MIT License - see the LICENSE file for details. 