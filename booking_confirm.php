<?php
include('config.php');

session_start();

if (!isset($_SESSION['username'])) {
    echo "User not logged in.";
    exit;
}

$currentUsername = $_SESSION['username'];

// Fetch user details
$stmt = $conn->prepare("SELECT FullName, Phone, EMail FROM users WHERE UserName = :username");
$stmt->bindParam(':username', $currentUsername);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch the latest booking details
$stmt = $conn->prepare("SELECT * FROM bookings WHERE UserName = :username ORDER BY created_time DESC LIMIT 1");
$stmt->bindParam(':username', $currentUsername);
$stmt->execute();
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch the most recent car booking details for the user
$stmt = $conn->prepare("
    SELECT cd.*, c.title, c.price, c.details AS car_details 
    FROM car_details cd
    JOIN cars c ON cd.car_id = c.car_id
    WHERE cd.username = :username
    ORDER BY cd.booking_date DESC, cd.detail_id DESC
    LIMIT 1
");
$stmt->bindParam(':username', $currentUsername);
$stmt->execute();
$carDetails = $stmt->fetch(PDO::FETCH_ASSOC);

// Pass the data to JavaScript
echo '<script>';
echo 'var user = ' . json_encode($user) . ';';
echo 'var booking = ' . json_encode($booking) . ';';
echo 'var car = ' . json_encode($carDetails) . ';';
echo '</script>';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking</title>
    <link rel="stylesheet" href="css/booking_confirm.css">
</head>

<body>
    <div id="wrapper">
        <div class="card">
            <div class="icon"></div>
            <h1>Your car is ready to book!</h1>
            <p>Contact our team through WhatsApp for Booking Confirmation.</p>
        </div>
        <div class="card">
            <ul>
                <li><span>Car Name</span><span id="carName"><?php echo htmlspecialchars($carDetails['title']); ?></span></li>
                <li><span>Booking Date</span><span id="bookingDate"><?php echo htmlspecialchars($carDetails['booking_date']); ?></span></li>
                <li><span>Pickup Location</span><span id="pickupLocation"><?php echo htmlspecialchars($booking['pickup']); ?></span></li>
                <li><span>Pickup Date</span><span id="pickupDate"><?php echo htmlspecialchars($booking['pickup_date']); ?></span></li>
                <li><span>Pickup Time</span><span id="pickupTime"><?php echo htmlspecialchars($booking['pickup_time']); ?></span></li>
                <li><span>No. of Days</span>
                    <span>
                        <div class="daysbtn">
                            <button onclick="decrementDays()">-</button>
                            <input type="number" class="days" id="days" value="1" min="1" oninput="updateTotalAmount()">
                            <button onclick="incrementDays()">+</button>
                        </div>
                    </span>
                </li>
                <li><span>Total Amount</span><span id="totalAmount">₹<?php echo htmlspecialchars($carDetails['price']); ?></span></li>
            </ul>
        </div>
        <div class="card">
            <div class="cta-row">
                <button class="secondary" onclick="updateBookingStatus()">Back to dashboard</button>
                <button id="rent-btn" onclick="redirectToWhatsApp()">Rent now</button>
            </div>
        </div>
    </div>

    <script>
        function updateBookingStatus() {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "update_booking_status.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function() {
                if (xhr.status === 200) {
                    window.location.href = 'index.php';
                } else {
                    alert('Failed to update booking status.');
                }
            };
            xhr.send("username=" + encodeURIComponent(user.UserName));
        }

        document.addEventListener("DOMContentLoaded", function() {
            const pricePerDay = <?php echo $carDetails['price']; ?>;
            document.getElementById("days").setAttribute("data-price", pricePerDay);
            updateTotalAmount();
        });

        function updateTotalAmount() {
            const days = parseInt(document.getElementById("days").value);
            const pricePerDay = parseInt(document.getElementById("days").getAttribute("data-price"));
            const totalAmount = days * pricePerDay;
            document.getElementById("totalAmount").textContent = `₹${totalAmount}`;
        }

        function incrementDays() {
            const daysInput = document.getElementById("days");
            let days = parseInt(daysInput.value);
            daysInput.value = ++days;
            updateTotalAmount();
        }

        function decrementDays() {
            const daysInput = document.getElementById("days");
            let days = parseInt(daysInput.value);
            if (days > 1) {
                daysInput.value = --days;
                updateTotalAmount();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const rentNowButton = document.getElementById('rent-btn');
            rentNowButton.addEventListener('click', redirectToWhatsApp);
        });

        function redirectToWhatsApp() {
            const fullName = user.FullName;
            const phone = user.Phone;
            const email = user.EMail;
            const pickup = booking.pickup;
            const pickupDate = booking.pickup_date;
            const pickupTime = booking.pickup_time;
            const returnDate = booking.return_date;
            const airportType = booking.airport_type;
            const carName = car.title;
            const carPrice = car.price;
            const carDetails = car.car_details;
            const days = document.getElementById("days").value;
            const totalAmount = document.getElementById("totalAmount").textContent.replace('₹', '');

            // Update the database with the new values
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "update_car_details.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function() {
                if (xhr.status === 200) {
                    console.log("Car details updated successfully");
                } else {
                    console.error("Failed to update car details");
                }
            };
            xhr.send(`username=${encodeURIComponent(user.UserName)}&days=${encodeURIComponent(days)}&totalAmount=${encodeURIComponent(totalAmount)}`);


            let message = `Thank You for renting a car\nName: ${fullName}\nPhone no: ${phone}\nEmail: ${email}\nBooking Details:\n`;
            if (airportType) message += `AIRPORT TYPE: ${airportType}\n`;
            message += `Pickup: ${pickup}\nDropoff: ${booking.dropoff}\nPickup Date: ${pickupDate}\nPickup Time: ${pickupTime}\n`;
            if (returnDate) message += `Return Date: ${returnDate}\n`;
            message += `Car Details:\nCar Name: ${carName}\nCar Price: ${carPrice}\nCar Details: ${carDetails}\n`;
            message += `No. of Days: ${days}\nTotal Amount: ${totalAmount}`;

            const encodedMessage = encodeURIComponent(message);
            const whatsappURL = `https://wa.me/917989481578?text=${encodedMessage}`;
            const whatsappWindow = window.open(whatsappURL, '_blank');

            if (whatsappWindow) {
                setTimeout(function() {
                    window.location.href = 'mybookings.php';
                }, 2000);
            } else {
                alert('Please allow pop-ups to open WhatsApp.');
                window.location.href = 'mybookings.php';
            }
        }
    </script>
</body>

</html>