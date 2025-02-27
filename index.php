<?php
session_start();

// Flat file storage
$roomsFile = 'rooms.txt';
$reservationsFile = 'reservations.txt';
$usersFile = 'users.txt';

// Helper functions for user management
function getUsers() {
    global $usersFile;
    $users = [];
    if (file_exists($usersFile)) {
        $lines = file($usersFile, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            list($username, $password, $isAdmin) = explode("|", $line);
            $users[$username] = ['password' => $password, 'isAdmin' => (int)$isAdmin];
        }
    }
    return $users;
}

function saveUsers($users) {
    global $usersFile;
    $lines = [];
    foreach ($users as $username => $userData) {
        $lines[] = "$username|{$userData['password']}|{$userData['isAdmin']}";
    }
    file_put_contents($usersFile, implode("\n", $lines));
}

function addUser($username, $password, $isAdmin) {
    $users = getUsers();
    if (!isset($users[$username])) {
        $users[$username] = ['password' => $password, 'isAdmin' => (int)$isAdmin];
        saveUsers($users);
        return true; // Indicate success
    }
    return false; // Indicate username already exists
}


function deleteUser($username) {
    $users = getUsers();
    if (isset($users[$username])) {
        unset($users[$username]);
        saveUsers($users);
        return true; // Indicate success
    }
     return false; // Indicate user not found.

}



// Helper functions for room and reservation management
function getRooms() {
    global $roomsFile;
    return file_exists($roomsFile) ? file($roomsFile, FILE_IGNORE_NEW_LINES) : [];
}

function saveRooms($rooms) {
    global $roomsFile;
    file_put_contents($roomsFile, implode("\n", $rooms));
}

function getReservations($room, $month, $year) {
    global $reservationsFile;
    $reservations = [];
    if (file_exists($reservationsFile)) {
        $lines = file($reservationsFile, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            list($r, $date, $time, $label, $repeat) = explode("|", $line);
            $resDate = new DateTime($date);
            $resMonth = (int)$resDate->format('m');
            $resYear = (int)$resDate->format('Y');

            if ($r == $room) {
                // Check if the reservation date matches the requested month and year.
                if ($resYear == $year && $resMonth == $month) {
                    $reservations[$resDate->format('Y-m-d')][] = [$time, $label, $repeat];
                }

                // Handle weekly repeating reservations.
                $originalDate = new DateTime($date);
                 if ($repeat == 1) {
                    $startDate = clone $originalDate;
                    $endDate = new DateTime($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-31');
                    while ($startDate <= $endDate) {
                        // Only add the reservation if it falls within the target month and year
                        if ($startDate->format('Y') == $year && (int)$startDate->format('m') == $month) {
                           //Check to make sure we don't add duplicate entries.
                            $formattedDate = $startDate->format('Y-m-d');
                            if (!isset($reservations[$formattedDate]) || !in_array([$time, $label, $repeat], $reservations[$formattedDate])) {
                                $reservations[$formattedDate][] = [$time, $label, $repeat];

                            }

                        }
                         $startDate->modify('+7 days');
                    }
                }
            }
        }
    }
    return $reservations;
}



function saveReservation($room, $date, $time, $label, $repeat) {
    global $reservationsFile;
    $newReservation = "$room|$date|$time|$label|$repeat";
    // Append the new reservation without reading and rewriting the whole file
    file_put_contents($reservationsFile, $newReservation . "\n", FILE_APPEND);
}


function deleteReservation($room, $date, $time) {
    global $reservationsFile;
    $lines = file($reservationsFile, FILE_IGNORE_NEW_LINES);
    $newLines = [];
    foreach ($lines as $line) {
        $parts = explode("|", $line);
        if ($parts[0] != $room || $parts[1] != $date || $parts[2] != $time) {
            $newLines[] = $line;
        }
    }
    file_put_contents($reservationsFile, implode("\n", $newLines));
}


// Authentication
if (isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $users = getUsers();

    if (isset($users[$user]) && $users[$user]['password'] == $pass) {
        $_SESSION['user'] = $user;
        $_SESSION['isAdmin'] = $users[$user]['isAdmin']; // Store admin status in session
    } else {
        $error = "Invalid username or password.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}



// Room management (admin only)
if (isset($_POST['addRoom']) && isset($_SESSION['user']) && $_SESSION['isAdmin'] == 1) {
    $newRoom = $_POST['newRoom'];
    $rooms = getRooms();
    if (!in_array($newRoom, $rooms)) {
        $rooms[] = $newRoom;
        saveRooms($rooms);
    }
}

if (isset($_POST['removeRoom']) && isset($_SESSION['user']) && $_SESSION['isAdmin'] == 1) {
    $roomToRemove = $_POST['roomToRemove'];
    $rooms = getRooms();
    $rooms = array_diff($rooms, [$roomToRemove]); // Remove the room
    saveRooms($rooms);
}

// User management (admin only)

if (isset($_POST['addUser']) && isset($_SESSION['user']) && $_SESSION['isAdmin'] == 1) {
    $newUsername = $_POST['newUsername'];
    $newPassword = $_POST['newPassword'];
    $newIsAdmin = isset($_POST['newIsAdmin']) ? 1 : 0;
    if (addUser($newUsername, $newPassword, $newIsAdmin)) {
        $userCreationSuccess = "User '$newUsername' created successfully.";
    } else {
        $userCreationError = "Username '$newUsername' already exists.";
    }
}

if (isset($_POST['deleteUser']) && isset($_SESSION['user']) && $_SESSION['isAdmin'] == 1) {
    $userToDelete = $_POST['userToDelete'];
      if(deleteUser($userToDelete)){
        $userDeletionSuccess = "User '$userToDelete' deleted successfully.";
      }
       else{
           $userDeletionError =  "User '$userToDelete' could not be deleted or does not exist.";
       }
}



//Reservation Handling

if (isset($_POST['addReservation']) && (isset($_SESSION['user']))) {
    $room = $_POST['room'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $label = $_POST['label'];
    $repeat = isset($_POST['repeat']) ? 1 : 0; // 1 for weekly, 0 for once
  saveReservation($room, $date, $time, $label, $repeat);
  header("Location: " . $_SERVER['REQUEST_URI']); // Refresh to avoid form resubmission
    exit();
}

if (isset($_POST['deleteReservation']) && isset($_SESSION['user']) && $_SESSION['isAdmin'] == 1) {
    $room = $_POST['room'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    deleteReservation($room, $date, $time);
     header("Location: " . $_SERVER['REQUEST_URI']);  //redirect to prevent form resubmission.
    exit;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Reservation System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="./reservation-style.css">
</head>
<body>
<div class="container">
    <h1><img src="./UCSOUTHCAMPUS.png" width="120px" length="120px">UCPT Laboratory Reservation System</h1>
  <div class ="main-content">
    <?php
    // --- Calendar Display Logic (Common to all views) ---
    $rooms = getRooms();
    $currentMonth = isset($_GET['month']) ? $_GET['month'] : date('n');
    $currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
    $selectedRoom = isset($_GET['room']) ? $_GET['room'] : (count($rooms) > 0 ? $rooms[0] : null); // Default to the first room

        // Room selection dropdown
        echo '<div class="form-group">';
        echo '<label for="roomSelect">Select Room:</label>';
        echo '<select class="form-control" id="roomSelect" onchange="location = this.value;">';

        foreach ($rooms as $room) {
            $selected = ($selectedRoom == $room) ? 'selected' : '';
            echo "<option value='?room=" . htmlspecialchars($room) . "' $selected>" . htmlspecialchars($room) . "</option>";
        }
        echo '</select></div>';

          // Month/Year Navigation (only if a room is selected)
            if($selectedRoom != null){
                echo '<div class="mb-3">';
            echo '<a href="?room=' . htmlspecialchars($selectedRoom) . '&month=' . ($currentMonth == 1 ? 12 : $currentMonth - 1) . '&year=' . ($currentMonth == 1 ? $currentYear - 1 : $currentYear) . '" class="btn btn-sm btn-secondary">< Previous</a> ';
            echo date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
            echo ' <a href="?room=' . htmlspecialchars($selectedRoom) . '&month=' . ($currentMonth == 12 ? 1 : $currentMonth + 1) . '&year=' . ($currentMonth == 12 ? $currentYear + 1 : $currentYear) . '" class="btn btn-sm btn-secondary">Next ></a>';
            echo '</div>';
            }
            // Calendar Display
             if ($selectedRoom != null) {
            echo '<div class="calendar-section">';  // Added calendar-section class
                echo '<h2>' . htmlspecialchars($selectedRoom) . ' Calendar</h2>';

                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
                $firstDay = date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));

                echo '<div class="calendar">';

                // Days of the Week
                $daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                foreach ($daysOfWeek as $day) {
                    echo '<div class="day">' . $day . '</div>';
                }

                // Empty Days Before
                for ($i = 0; $i < $firstDay; $i++) {
                    echo '<div class="day"></div>';
                }

                // Get Reservations
                $reservations = getReservations($selectedRoom, $currentMonth, $currentYear);

                // Days of the Month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = $currentYear . "-" . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                    echo '<div class="day" data-date="' . $date . '">';
                    echo '<span class="day-number">' . $day . '</span>';

                    // Display Reservations
                    if (isset($reservations[$date])) {
                        foreach ($reservations[$date] as $reservation) {
                            echo "<div class='reservation'>";
                            echo htmlspecialchars($reservation[0]) . " - " . htmlspecialchars($reservation[1]);

                            // Delete Button (admin only)
                            if (isset($_SESSION['user']) && $_SESSION['isAdmin'] == 1) {
                                echo " <form method='post' style='display:inline;'>";
                                echo "<input type='hidden' name='room' value='" . htmlspecialchars($selectedRoom) . "'>";
                                echo "<input type='hidden' name='date' value='" . $date . "'>";
                                echo "<input type='hidden' name='time' value='" . htmlspecialchars($reservation[0]) . "'>";
                                echo "<button type='submit' class='btn btn-danger btn-sm' name='deleteReservation'>Delete</button>";
                                echo "</form>";
                            }
                            echo "</div>";
                        }
                    }
                    echo '</div>';
                }
                echo '</div>'; // calendar
            echo '</div>';  // calendar-section
             }

    ?>

    <?php if (!isset($_SESSION['user'])): ?>
        <!-- Login Form -->
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" class="form-control" name="username" id="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" class="form-control" name="password" id="password" required>
            </div>
            <button type="submit" class="btn btn-primary" name="login">Login</button>
        </form>



    <?php else: ?>
        <!-- Logged-in User View -->
        <p>Welcome, <?php echo $_SESSION['user']; ?>! <a href="?logout" class="btn btn-danger btn-sm">Logout</a></p>

        <?php if ($_SESSION['isAdmin'] == 1): ?>
            <!-- Admin-only Sections -->
            <h2>Room Management</h2>
            <form method="post">
                <div class="form-group">
                    <label for="newRoom">New Room:</label>
                    <input type="text" class="form-control" name="newRoom" id="newRoom" required>
                </div>
                <button type="submit" class="btn btn-success" name="addRoom">Add Room</button>
            </form>

            <form method="post">
                <div class="form-group">
                    <label for="roomToRemove">Room to Remove:</label>
                    <select class="form-control" name="roomToRemove" id="roomToRemove">
                        <?php foreach (getRooms() as $room): ?>
                            <option value="<?php echo $room; ?>"><?php echo $room; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-danger" name="removeRoom">Remove Room</button>
            </form>

            <h2>User Management</h2>
             <?php if (isset($userCreationSuccess)): ?>
                <div class="alert alert-success"><?php echo $userCreationSuccess; ?></div>
            <?php endif; ?>
            <?php if (isset($userCreationError)): ?>
                <div class="alert alert-danger"><?php echo $userCreationError; ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="newUsername">New Username:</label>
                    <input type="text" class="form-control" name="newUsername" id="newUsername" required>
                </div>
                <div class="form-group">
                    <label for="newPassword">New Password:</label>
                    <input type="password" class="form-control" name="newPassword" id="newPassword" required>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="newIsAdmin" id="newIsAdmin">
                    <label class="form-check-label" for="newIsAdmin">Admin User</label>
                </div>
                <button type="submit" class="btn btn-success" name="addUser">Add User</button>
            </form>


             <?php if (isset($userDeletionSuccess)): ?>
                <div class="alert alert-success"><?php echo $userDeletionSuccess; ?></div>
            <?php endif; ?>
            <?php if (isset($userDeletionError)): ?>
                <div class="alert alert-danger"><?php echo $userDeletionError; ?></div>
            <?php endif; ?>
            <form method="post" class = "mt-3">
                <div class="form-group">
                    <label for="userToDelete">User to Delete:</label>
                    <select class="form-control" name="userToDelete" id="userToDelete">
                        <?php foreach (getUsers() as $username => $userData): ?>
                            <?php if ($username !== "admin"): ?>
                                 <option value="<?php echo $username; ?>"><?php echo $username; ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-danger" name="deleteUser">Delete User</button>
            </form>

        <?php endif; ?>

        <!-- Reservation Form (teachers and admins) -->
           <?php  if (isset($_SESSION['user']) && $selectedRoom): ?>
            <h2>Add Reservation</h2>
                <form method="post">
                <input type='hidden' name='room' value='<?php echo htmlspecialchars($selectedRoom); ?>'>
                <div class='form-group'>
                <label for='date'>Date:</label>
                <input type='date' class='form-control' name='date' id='date' required>
                </div>
                <div class='form-group'>
                <label for='time'>Time:</label>
                <input type='time' class='form-control' name='time' id='time' required>
                </div>
                <div class='form-group'>
                <label for='label'>Label (Please Indicate the Class Name/Code, Teacher and Number of Hour[s]):</label>
                <input type='text' class='form-control' name='label' id='label' required>
                </div>
                <div class='form-check'>
                <input class='form-check-input' type='checkbox' name='repeat' id='repeat'>
                <label class='form-check-label' for='repeat'>Repeat Weekly</label>
                </div>
                <button type='submit' class='btn btn-primary' name='addReservation'>Add Reservation</button>
                </form>
        <?php endif; ?>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
