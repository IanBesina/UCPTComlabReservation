<?php
session_start();

// Flat file storage
$roomsFile = 'rooms.txt';
$reservationsFile = 'reservations.txt';
$usersFile = '.users';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Facility Reservation System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./reservation-style.css">
</head>
<body>
<div class="container py-5">
    <div class="d-flex align-items-center mb-4">
        <img src="./Logo.png" alt="Logo" width="80" class="mr-3" onerror="this.style.display='none'">
        <h1 class="mb-0">Facility Reservation System</h1>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            
            <?php if (!isset($_SESSION['user'])): ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h4">Login</h2>
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger rounded"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="form-group">
                                <label for="username" class="font-weight-bold text-muted">Username</label>
                                <input type="text" class="form-control" name="username" id="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password" class="font-weight-bold text-muted">Password</label>
                                <input type="password" class="form-control" name="password" id="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" name="login">Login</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <p class="mb-2 text-muted">Logged in as</p>
                        <h4 class="mb-3 text-success"><?php echo htmlspecialchars($_SESSION['user']); ?></h4>
                        <a href="?logout" class="btn btn-outline-danger btn-sm rounded-pill">Logout</a>
                    </div>
                </div>

                <?php 
                $rooms = getRooms();
                $selectedRoom = isset($_GET['room']) ? $_GET['room'] : (count($rooms) > 0 ? $rooms[0] : null);
                ?>

                <?php if ($selectedRoom): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h2 class="h4">Add Reservation</h2>
                            <form method="post">
                                <input type='hidden' name='room' value='<?php echo htmlspecialchars($selectedRoom); ?>'>
                                <div class='form-group'>
                                    <label for='date' class="font-weight-bold text-muted">Date</label>
                                    <input type='date' class='form-control' name='date' id='date' required>
                                </div>
                                <div class='form-group'>
                                    <label for='time' class="font-weight-bold text-muted">Time</label>
                                    <input type='time' class='form-control' name='time' id='time' required>
                                </div>
                                <div class='form-group'>
                                    <label for='label' class="font-weight-bold text-muted">Details (Group, Contact, Hours)</label>
                                    <input type='text' class='form-control' name='label' id='label' placeholder="e.g. IT Dept Meeting, 2 hrs" required>
                                </div>
                                <div class='form-check mb-3'>
                                    <input class='form-check-input' type='checkbox' name='repeat' id='repeat'>
                                    <label class='form-check-label' for='repeat'>Repeat Weekly</label>
                                </div>
                                <button type='submit' class='btn btn-primary w-100' name='addReservation'>Reserve Facility</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($_SESSION['isAdmin'] == 1): ?>
                    <div class="card shadow-sm mb-4 border-success">
                        <div class="card-header bg-success text-white rounded-top">
                            <h5 class="mb-0">Admin Toolkit</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="font-weight-bold mt-2">Manage Facilities</h6>
                            <hr class="mt-1 mb-3">
                            <form method="post" class="mb-3">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="newRoom" placeholder="New Facility Name" required>
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-success" name="addRoom">Add</button>
                                    </div>
                                </div>
                            </form>
                            <form method="post" class="mb-4">
                                <div class="input-group">
                                    <select class="form-control" name="roomToRemove">
                                        <?php foreach (getRooms() as $room): ?>
                                            <option value="<?php echo htmlspecialchars($room); ?>"><?php echo htmlspecialchars($room); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-danger" name="removeRoom">Remove</button>
                                    </div>
                                </div>
                            </form>

                            <h6 class="font-weight-bold">Manage Users</h6>
                            <hr class="mt-1 mb-3">
                            
                            <?php if (isset($userCreationSuccess)) echo "<div class='alert alert-success py-2'>$userCreationSuccess</div>"; ?>
                            <?php if (isset($userCreationError)) echo "<div class='alert alert-danger py-2'>$userCreationError</div>"; ?>
                            
                            <form method="post" class="mb-3">
                                <input type="text" class="form-control mb-2" name="newUsername" placeholder="Username" required>
                                <input type="password" class="form-control mb-2" name="newPassword" placeholder="Password" required>
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" name="newIsAdmin" id="newIsAdmin">
                                    <label class="form-check-label" for="newIsAdmin">Grant Admin Access</label>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100" name="addUser">Create User</button>
                            </form>

                            <?php if (isset($userDeletionSuccess)) echo "<div class='alert alert-success py-2'>$userDeletionSuccess</div>"; ?>
                            <?php if (isset($userDeletionError)) echo "<div class='alert alert-danger py-2'>$userDeletionError</div>"; ?>
                            
                            <form method="post">
                                <div class="input-group">
                                    <select class="form-control" name="userToDelete">
                                        <?php foreach (getUsers() as $username => $userData): ?>
                                            <?php if ($username !== "admin"): ?>
                                                <option value="<?php echo htmlspecialchars($username); ?>"><?php echo htmlspecialchars($username); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-outline-danger" name="deleteUser">Delete</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <?php
                    $rooms = getRooms();
                    $currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
                    $currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                    $selectedRoom = isset($_GET['room']) ? $_GET['room'] : (count($rooms) > 0 ? $rooms[0] : null);

                    if ($rooms): ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                            <div class="form-group mb-0" style="min-width: 250px;">
                                <select class="form-control form-control-lg text-success font-weight-bold" onchange="location = this.value;">
                                    <?php foreach ($rooms as $room): ?>
                                        <option value="?room=<?php echo urlencode($room); ?>" <?php echo ($selectedRoom === $room) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($room); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($selectedRoom): ?>
                                <div class="btn-group mt-3 mt-md-0 shadow-sm">
                                    <a href="?room=<?php echo urlencode($selectedRoom); ?>&month=<?php echo ($currentMonth == 1 ? 12 : $currentMonth - 1); ?>&year=<?php echo ($currentMonth == 1 ? $currentYear - 1 : $currentYear); ?>" class="btn btn-light border">&laquo; Prev</a>
                                    <button class="btn btn-light border font-weight-bold px-4" disabled>
                                        <?php echo date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); ?>
                                    </button>
                                    <a href="?room=<?php echo urlencode($selectedRoom); ?>&month=<?php echo ($currentMonth == 12 ? 1 : $currentMonth + 1); ?>&year=<?php echo ($currentMonth == 12 ? $currentYear + 1 : $currentYear); ?>" class="btn btn-light border">Next &raquo;</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($selectedRoom): ?>
                            <div class="calendar-section">
                                <div class="calendar calendar-header mb-2">
                                    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day) echo "<div class='day'>$day</div>"; ?>
                                </div>
                                <div class="calendar">
                                    <?php
                                    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
                                    $firstDay = date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
                                    $reservations = getReservations($selectedRoom, $currentMonth, $currentYear);

                                    for ($i = 0; $i < $firstDay; $i++) echo '<div class="day" style="background-color: #f8f9fa; border:none;"></div>';

                                    for ($day = 1; $day <= $daysInMonth; $day++) {
                                        $dateStr = sprintf("%04d-%02d-%02d", $currentYear, $currentMonth, $day);
                                        echo "<div class='day'>";
                                        echo "<span class='day-number'>$day</span>";

                                        if (isset($reservations[$dateStr])) {
                                            foreach ($reservations[$dateStr] as $reservation) {
                                                echo "<div class='reservation'>";
                                                echo "<strong>" . htmlspecialchars($reservation[0]) . "</strong>";
                                                echo "<span>" . htmlspecialchars($reservation[1]) . "</span>";

                                                if (isset($_SESSION['user']) && $_SESSION['isAdmin'] == 1) {
                                                    echo "<form method='post' class='mt-2'>";
                                                    echo "<input type='hidden' name='room' value='" . htmlspecialchars($selectedRoom) . "'>";
                                                    echo "<input type='hidden' name='date' value='$dateStr'>";
                                                    echo "<input type='hidden' name='time' value='" . htmlspecialchars($reservation[0]) . "'>";
                                                    echo "<button type='submit' class='btn btn-danger btn-sm py-0 w-100' name='deleteReservation' style='font-size: 0.8em;'>Delete</button>";
                                                    echo "</form>";
                                                }
                                                echo "</div>";
                                            }
                                        }
                                        echo "</div>";
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <h4>No facilities available.</h4>
                            <p>Please log in as an administrator to add rooms.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
