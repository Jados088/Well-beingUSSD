<?php

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $this->conn = new mysqli('localhost', 'root', '', 'bmi_app');
            if ($this->conn->connect_error) {
                throw new Exception("Database connection failed: " . $this->conn->connect_error);
            }
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database Error: " . $e->getMessage());
            throw new Exception("Could not connect to database. Please check your database configuration.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}

class User {
    private $db;
    private $phone;
    private $data;

    public function __construct($phone) {
        $this->db = Database::getInstance()->getConnection();
        $this->phone = $phone;
        $this->load();
    }

    private function load() {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->bind_param("s", $this->phone);
        $stmt->execute();

        $result = $stmt->get_result();
        $this->data = $result->fetch_assoc();
    }

    public function isRegistered() {
        return $this->data !== null;

    }

    public function register($name, $pin) {
        $stmt = $this->db->prepare("INSERT INTO users (phone, name, pin) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $this->phone, $name, $pin);
        return $stmt->execute();
        
    }

    public function update($field, $value) {
        $stmt = $this->db->prepare("UPDATE users SET $field = ? WHERE phone = ?");
        $stmt->bind_param("ss", $value, $this->phone);
        return $stmt->execute();
    }

    public function get($field) {
        return $this->data[$field] ?? null;
    }
}

class BMICalculator {
    public static function calculate($weight, $height) {
        if ($height <= 0) return 0;
        return round($weight / ($height * $height), 1);
    }

    public static function getCategory($bmi) {
        if ($bmi < 18.5) return "Underweight";
        elseif ($bmi < 25) return "Normal weight";
        elseif ($bmi < 30) return "Overweight";
        else return "Obesity";
    }

    public static function getRecommendation($category) {
        $recommendations = [
            "Underweight" => "Consider consulting a nutritionist to develop a healthy weight gain plan.",
            "Normal weight" => "Maintain your healthy lifestyle and regular exercise routine.",
            "Overweight" => "Consider increasing physical activity and consulting a nutritionist for a balanced diet plan.",
            "Obesity" => "Please consult a healthcare provider for a comprehensive weight management plan."
        ];
        return $recommendations[$category] ?? "Please consult a healthcare provider for personalized advice.";
    }
}

class SMS {
    private static $username = 'sandbox';
    private static $apiKey = 'atsk_c7549df09d0ad8b5d5716bd97d59faae27016a9bc5e03fe2f08c59fff67ec434830d9e27';
    private static $senderId = 'BMI_APP';

    public static function send($to, $message) {
        try {
            // Format phone number if needed
            $to = self::formatPhoneNumber($to);
            
            $data = http_build_query([
                'username' => self::$username,
                'to' => $to,
                'message' => $message,
                'from' => self::$senderId
            ]);

            $headers = [
                "apikey: " . self::$apiKey,
                "Content-Type: application/x-www-form-urlencoded"
            ];

            $ch = curl_init('https://api.africastalking.com/version1/messaging');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);

            error_log("SMS Response: " . $response);
            error_log("SMS HTTP Code: " . $httpCode);

            if ($error) {
                error_log("SMS Error: " . $error);
                throw new Exception('Failed to send SMS: ' . $error);
            }

            if ($httpCode !== 200) {
                error_log("SMS API Error: HTTP Code " . $httpCode);
                throw new Exception('SMS service error: ' . $httpCode);
            }

            return true;
        } catch (Exception $e) {
            error_log("SMS Exception: " . $e->getMessage());
            throw $e;
        }
    }

    private static function formatPhoneNumber($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If number starts with 0, replace with 254
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        // If number starts with +, remove it
        if (substr($phone, 0, 1) === '+') {
            $phone = substr($phone, 1);
        }
        
        // If number doesn't start with 254, add it
        if (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
}

class USSDHandler {
    private $user;
    private $input;
    private $phone;
    private $serviceCode;

    public function __construct($phone, $text, $serviceCode) {
        $this->phone = $phone;
        $this->input = $text ? explode("*", $text) : [];
        $this->serviceCode = $serviceCode;
        $this->user = new User($phone);
    }

    public function handle() {
        try {
            if (!$this->user->isRegistered()) {
                return $this->handleRegistration();
            }
            return $this->handleBMICalculation();
        } catch (Exception $e) {
            error_log("USSD Error: " . $e->getMessage());
            return "END " . $e->getMessage();
        }
    }

    private function handleRegistration() {
        $step = count($this->input);
        error_log("Registration Step: " . $step . ", Input: " . print_r($this->input, true));
        
        switch ($step) {
            case 0:
                return "CON Welcome to BMI Calculator!\nEnter your name:";
            case 1:
                if (strlen($this->input[0]) < 2) {
                    return "CON Name must be at least 2 characters.\nEnter your name:";
                }
                return "CON Create a 4-digit PIN:";
            case 2:
                if (!preg_match('/^\d{4}$/', $this->input[1])) {
                    return "CON PIN must be 4 digits.\nCreate a 4-digit PIN:";
                }
                return "CON Confirm your PIN:";
            case 3:
                if ($this->input[1] === $this->input[2]) {
                    if ($this->user->register($this->input[0], $this->input[1])) {
                        SMS::send($this->phone, "Hi {$this->input[0]}, you're registered. You can now check your BMI.");
                        return "END Registration successful!";
                    }
                    return "END Registration failed. Please try again.";
                }
                return "END PINs do not match. Please try again.";
            default:
                return "END Invalid input. Please start again.";
        }
    }

    private function handleBMICalculation() {
        $step = count($this->input);
        $name = $this->user->get('name');
        error_log("BMI Calculation Step: " . $step . ", Input: " . print_r($this->input, true));

        switch ($step) {
            case 0:
                return "CON Hi $name!\nEnter your height in meters (e.g. 1.75):";
            case 1:
                $height = floatval($this->input[0]);
                if ($height <= 0 || $height > 3) {
                    return "CON Invalid height. Enter height in meters (e.g. 1.75):";
                }
                $this->user->update('height', $height);
                return "CON Enter your weight in kg:";
            case 2:
                $weight = floatval($this->input[1]);
                if ($weight <= 0 || $weight > 500) {
                    return "CON Invalid weight. Enter weight in kg:";
                }
                $this->user->update('weight', $weight);
                return "CON Confirm BMI calculation:\n1. Yes\n2. Cancel\n3. Re-enter weight\n4. Main menu";
            case 3:
                switch ($this->input[2]) {
                    case "1":
                        $bmi = BMICalculator::calculate(
                            $this->user->get('weight'),
                            $this->user->get('height')
                        );
                        $category = BMICalculator::getCategory($bmi);
                        $recommendation = BMICalculator::getRecommendation($category);
                        
                        $message = "Hi $name, your BMI is $bmi. You are $category.\n\nRecommendation: $recommendation";
                        SMS::send($this->phone, $message);
                        return "END BMI sent via SMS.";
                    case "2":
                        return "END Session cancelled.";
                    case "3":
                        return "CON Enter your weight in kg:";
                    case "4":
                        return "CON Hi $name!\nEnter your height in meters (e.g. 1.75):";
                    default:
                        return "END Invalid option. Please try again.";
                }
            default:
                return "END Invalid input. Please start again.";
        }
    }
}

// Main application logic
try {
    $sessionId = $_POST['sessionId'] ?? '';
    $serviceCode = $_POST['serviceCode'] ?? '';
    $phoneNumber = $_POST['phoneNumber'] ?? '';
    $text = $_POST['text'] ?? '';

    if (empty($phoneNumber)) {
        throw new Exception("Phone number is required");
    }

    error_log("USSD Request - Session: $sessionId, Service: $serviceCode, Phone: $phoneNumber, Text: $text");

    $handler = new USSDHandler($phoneNumber, $text, $serviceCode);
    $response = $handler->handle();

    header('Content-type: text/plain');
    echo $response;
} catch (Exception $e) {
    error_log("Application Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    header('Content-type: text/plain');
    echo "END Error: " . $e->getMessage();
}
