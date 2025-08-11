<?php
session_start();

/**
 * Class to manage user sessions, including login and logout.
 */
class SessionManager {
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public static function login($userId) {
        $_SESSION['user_id'] = $userId;
    }

    public static function logout() {
        session_unset();
        session_destroy();
    }
}

/**
 * A simple data object to represent a User.
 */
class User {
    public $id;
    public $username;
    public $password;

    public function __construct($id, $username, $password) {
        $this->id = $id;
        $this->username = $username;
        $this->password = $password;
    }
}

/**
 * A simple data object to represent a Student.
 */
class Student {
    public $id;
    public $name;
    public $age;
    public $email;
    public $course;

    public function __construct($id, $name, $age, $email, $course) {
        $this->id = $id;
        $this->name = $name;
        $this->age = $age;
        $this->email = $email;
        $this->course = $course;
    }
}

/**
 * Manages reading from and writing to JSON files.
 */
class FileManager {
    public static function readJson($filename) {
        if (!file_exists($filename)) {
            return [];
        }
        $data = file_get_contents($filename);
        return json_decode($data, true) ?: [];
    }

    public static function writeJson($filename, $data) {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($filename, $json);
    }
}

// Ensure the necessary JSON files exist.
if (!file_exists('users.json')) {
    $initialUsers = [
        ['id' => 1, 'username' => 'admin', 'password' => 'password']
    ];
    FileManager::writeJson('users.json', $initialUsers);
}
if (!file_exists('students.json')) {
    FileManager::writeJson('students.json', []);
}

// Handle login POST request
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $users = FileManager::readJson('users.json');

    foreach ($users as $user) {
        if ($user['username'] === $username && $user['password'] === $password) {
            SessionManager::login($user['id']);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    $loginError = 'Invalid username or password.';
}

// Handle logout GET request
if (isset($_GET['logout'])) {
    SessionManager::logout();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check if user is logged in.
if (!SessionManager::isLoggedIn()) {
    // Display login form if not logged in.
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Login</title>
    </head>
    <body>
        <h2>Login</h2>
        <?php if (isset($loginError)): ?>
            <p style="color: red;"><?php echo $loginError; ?></p>
        <?php endif; ?>
        <form method="POST">
            <label>Username: <input type="text" name="username"></label><br>
            <label>Password: <input type="password" name="password"></label><br>
            <button type="submit" name="login">Log In</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// User is logged in, handle student management actions.

// Function to generate a new student ID
function generateStudentId($students) {
    $maxId = 0;
    foreach ($students as $student) {
        if ($student['id'] > $maxId) {
            $maxId = $student['id'];
        }
    }
    return $maxId + 1;
}

// Handle form submissions for students
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $students = FileManager::readJson('students.json');
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $newId = generateStudentId($students);
                $students[] = new Student(
                    $newId,
                    $_POST['name'],
                    (int)$_POST['age'],
                    $_POST['email'],
                    $_POST['course']
                );
                FileManager::writeJson('students.json', $students);
                break;
            case 'update':
                $id = (int)$_POST['id'];
                foreach ($students as $key => $student) {
                    if ($student['id'] === $id) {
                        $students[$key]['name'] = $_POST['name'];
                        $students[$key]['age'] = (int)$_POST['age'];
                        $students[$key]['email'] = $_POST['email'];
                        $students[$key]['course'] = $_POST['course'];
                        break;
                    }
                }
                FileManager::writeJson('students.json', $students);
                break;
            case 'delete':
                $id = (int)$_POST['id'];
                $students = array_filter($students, function($student) use ($id) {
                    return $student['id'] !== $id;
                });
                FileManager::writeJson('students.json', array_values($students));
                break;
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get all students for display
$students = FileManager::readJson('students.json');

// Check for an update request to pre-fill the form
$editStudent = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editId = (int)$_GET['id'];
    foreach ($students as $student) {
        if ($student['id'] === $editId) {
            $editStudent = $student;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Management System</title>
</head>
<body>
    <p>Welcome! <a href="?logout">Logout</a></p>

    <h2><?php echo $editStudent ? 'Edit Student' : 'Add New Student'; ?></h2>
    <form method="POST">
        <input type="hidden" name="action" value="<?php echo $editStudent ? 'update' : 'add'; ?>">
        <?php if ($editStudent): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editStudent['id']); ?>">
        <?php endif; ?>
        <label>Name: <input type="text" name="name" value="<?php echo $editStudent ? htmlspecialchars($editStudent['name']) : ''; ?>" required></label><br>
        <label>Age: <input type="number" name="age" value="<?php echo $editStudent ? htmlspecialchars($editStudent['age']) : ''; ?>" required></label><br>
        <label>Email: <input type="text" name="email" value="<?php echo $editStudent ? htmlspecialchars($editStudent['email']) : ''; ?>" required></label><br>
        <label>Course: <input type="text" name="course" value="<?php echo $editStudent ? htmlspecialchars($editStudent['course']) : ''; ?>" required></label><br>
        <button type="submit"><?php echo $editStudent ? 'Update Student' : 'Add Student'; ?></button>
    </form>

    <hr>

    <h2>Student List</h2>
    <?php if (empty($students)): ?>
        <p>No students found.</p>
    <?php else: ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['id']); ?></td>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td><?php echo htmlspecialchars($student['age']); ?></td>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                        <td><?php echo htmlspecialchars($student['course']); ?></td>
                        <td>
                            <a href="?action=edit&id=<?php echo htmlspecialchars($student['id']); ?>">Edit</a>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($student['id']); ?>">
                                <button type="submit" onclick="return confirm('Are you sure?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>