<?php
// api.php - Task Management API with Workspaces & Projects Support
// Place this file in your server root: https://successunlock.com/api.php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u859946658_tasks');
define('DB_PASS', 'Hk!d/2eU');
define('DB_NAME', 'u859946658_tasks');

// Create database connection
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit();
    }
    
    return $conn;
}

// Initialize database tables if they don't exist
function initializeDatabase() {
    $conn = getDbConnection();
    
    // Users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        secret_code VARCHAR(255) NOT NULL UNIQUE,
        gender ENUM('male', 'female') DEFAULT 'male',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (secret_code)
    )";
    $conn->query($sql);
    
    // Workspaces table
    $sql = "CREATE TABLE IF NOT EXISTS workspaces (
        id BIGINT PRIMARY KEY,
        user_code VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        icon VARCHAR(10) DEFAULT '📚',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_code),
        FOREIGN KEY (user_code) REFERENCES users(secret_code) ON DELETE CASCADE
    )";
    $conn->query($sql);
    
    // Projects table
    $sql = "CREATE TABLE IF NOT EXISTS projects (
        id BIGINT PRIMARY KEY,
        user_code VARCHAR(255) NOT NULL,
        workspace_id BIGINT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        icon VARCHAR(10) DEFAULT '💼',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_code),
        INDEX (workspace_id),
        FOREIGN KEY (user_code) REFERENCES users(secret_code) ON DELETE CASCADE,
        FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
    )";
    $conn->query($sql);
    
    // Tasks table
    $sql = "CREATE TABLE IF NOT EXISTS tasks (
        id BIGINT PRIMARY KEY,
        user_code VARCHAR(255) NOT NULL,
        project_id BIGINT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        start_date DATE NOT NULL,
        due_date DATE NOT NULL,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        status ENUM('open', 'in-progress', 'done') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_code),
        INDEX (project_id),
        INDEX (start_date),
        INDEX (due_date),
        FOREIGN KEY (user_code) REFERENCES users(secret_code) ON DELETE CASCADE,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )";
    $conn->query($sql);
    
    $conn->close();
}

// Get request parameter
function getParam($key, $default = null) {
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    $input = json_decode(file_get_contents('php://input'), true);
    return $input[$key] ?? $default;
}

// Verify user by secret code
function verifyUser($code) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT name, gender FROM users WHERE secret_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response = [
            'success' => true,
            'name' => $row['name'],
            'gender' => $row['gender']
        ];
    } else {
        $response = [
            'success' => false,
            'error' => 'Invalid secret code'
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    return $response;
}

// Register new user
function registerUser($name, $code, $gender) {
    $conn = getDbConnection();
    
    // Check if code already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE secret_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return [
            'success' => false,
            'error' => 'Secret code already exists'
        ];
    }
    $stmt->close();
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (name, secret_code, gender) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $code, $gender);
    
    if ($stmt->execute()) {
        $response = [
            'success' => true,
            'message' => 'User registered successfully'
        ];
    } else {
        $response = [
            'success' => false,
            'error' => 'Failed to register user'
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    return $response;
}

// Get all data for a user (workspaces, projects, tasks)
function getAllData($code) {
    $conn = getDbConnection();
    
    // Get workspaces
    $stmt = $conn->prepare("SELECT * FROM workspaces WHERE user_code = ? ORDER BY created_at ASC");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $workspaces = [];
    while ($row = $result->fetch_assoc()) {
        $workspaces[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'icon' => $row['icon'],
            'createdAt' => $row['created_at']
        ];
    }
    $stmt->close();
    
    // Get projects
    $stmt = $conn->prepare("SELECT * FROM projects WHERE user_code = ? ORDER BY created_at ASC");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $projects[] = [
            'id' => (int)$row['id'],
            'workspaceId' => (int)$row['workspace_id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'icon' => $row['icon'],
            'createdAt' => $row['created_at']
        ];
    }
    $stmt->close();
    
    // Get tasks
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE user_code = ? ORDER BY start_date DESC");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = [
            'id' => (int)$row['id'],
            'projectId' => (int)$row['project_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'startDate' => $row['start_date'],
            'dueDate' => $row['due_date'],
            'priority' => $row['priority'],
            'status' => $row['status'],
            'createdAt' => $row['created_at']
        ];
    }
    $stmt->close();
    
    $conn->close();
    
    return [
        'success' => true,
        'workspaces' => $workspaces,
        'projects' => $projects,
        'tasks' => $tasks
    ];
}

// Save workspaces
function saveWorkspaces($code, $workspaces) {
    $conn = getDbConnection();
    $conn->begin_transaction();
    
    try {
        // Get existing workspace IDs
        $stmt = $conn->prepare("SELECT id FROM workspaces WHERE user_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $existingIds = [];
        while ($row = $result->fetch_assoc()) {
            $existingIds[] = $row['id'];
        }
        $stmt->close();
        
        $currentIds = [];
        
        // Insert or update each workspace
        $stmt = $conn->prepare("INSERT INTO workspaces (id, user_code, name, icon, created_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon)");
        
        foreach ($workspaces as $ws) {
            $currentIds[] = $ws['id'];
            $stmt->bind_param("issss", $ws['id'], $code, $ws['name'], $ws['icon'], $ws['createdAt']);
            $stmt->execute();
        }
        $stmt->close();
        
        // Delete workspaces that are no longer in the list
        $toDelete = array_diff($existingIds, $currentIds);
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $deleteStmt = $conn->prepare("DELETE FROM workspaces WHERE user_code = ? AND id IN ($placeholders)");
            
            $types = str_repeat('i', count($toDelete));
            $params = array_merge([$code], $toDelete);
            $deleteStmt->bind_param("s" . $types, ...$params);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
        
        $conn->commit();
        $conn->close();
        
        return ['success' => true, 'message' => 'Workspaces saved successfully'];
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        return ['success' => false, 'error' => 'Failed to save workspaces: ' . $e->getMessage()];
    }
}

// Save projects
function saveProjects($code, $projects) {
    $conn = getDbConnection();
    $conn->begin_transaction();
    
    try {
        // Get existing project IDs
        $stmt = $conn->prepare("SELECT id FROM projects WHERE user_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $existingIds = [];
        while ($row = $result->fetch_assoc()) {
            $existingIds[] = $row['id'];
        }
        $stmt->close();
        
        $currentIds = [];
        
        // Insert or update each project
        $stmt = $conn->prepare("INSERT INTO projects (id, user_code, workspace_id, name, description, icon, created_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE workspace_id = VALUES(workspace_id), name = VALUES(name), description = VALUES(description), icon = VALUES(icon)");
        
        foreach ($projects as $proj) {
            $currentIds[] = $proj['id'];
            $stmt->bind_param("isissss", $proj['id'], $code, $proj['workspaceId'], $proj['name'], $proj['description'], $proj['icon'], $proj['createdAt']);
            $stmt->execute();
        }
        $stmt->close();
        
        // Delete projects that are no longer in the list
        $toDelete = array_diff($existingIds, $currentIds);
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $deleteStmt = $conn->prepare("DELETE FROM projects WHERE user_code = ? AND id IN ($placeholders)");
            
            $types = str_repeat('i', count($toDelete));
            $params = array_merge([$code], $toDelete);
            $deleteStmt->bind_param("s" . $types, ...$params);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
        
        $conn->commit();
        $conn->close();
        
        return ['success' => true, 'message' => 'Projects saved successfully'];
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        return ['success' => false, 'error' => 'Failed to save projects: ' . $e->getMessage()];
    }
}

// Save tasks (existing function - updated to work with new structure)
function saveTasks($code, $tasks) {
    $conn = getDbConnection();
    $conn->begin_transaction();
    
    try {
        // Validate input
        if (empty($code)) {
            throw new Exception('User code is required');
        }
        
        if (!is_array($tasks)) {
            throw new Exception('Tasks must be an array');
        }
        
        // Get existing task IDs for this user
        $stmt = $conn->prepare("SELECT id FROM tasks WHERE user_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $existingIds = [];
        while ($row = $result->fetch_assoc()) {
            $existingIds[] = $row['id'];
        }
        $stmt->close();
        
        $currentIds = [];
        
        // Insert or update each task
        $insertStmt = $conn->prepare("INSERT INTO tasks (id, user_code, project_id, title, description, start_date, due_date, priority, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE project_id = VALUES(project_id), title = VALUES(title), description = VALUES(description), start_date = VALUES(start_date), due_date = VALUES(due_date), priority = VALUES(priority), status = VALUES(status)");
        
        foreach ($tasks as $task) {
            // Validate task data
            if (!isset($task['id']) || !isset($task['projectId']) || !isset($task['title'])) {
                throw new Exception('Invalid task data: missing required fields (id, projectId, or title)');
            }
            
            $currentIds[] = $task['id'];
            
            $insertStmt->bind_param(
                "isisssssss",
                $task['id'],
                $code,
                $task['projectId'],
                $task['title'],
                $task['description'],
                $task['startDate'],
                $task['dueDate'],
                $task['priority'],
                $task['status'],
                $task['createdAt']
            );
            
            if (!$insertStmt->execute()) {
                throw new Exception('Failed to save task: ' . $insertStmt->error);
            }
        }
        
        $insertStmt->close();
        
        // Delete tasks that are no longer in the list
        $toDelete = array_diff($existingIds, $currentIds);
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $deleteStmt = $conn->prepare("DELETE FROM tasks WHERE user_code = ? AND id IN ($placeholders)");
            
            $types = str_repeat('i', count($toDelete));
            $params = array_merge([$code], $toDelete);
            $deleteStmt->bind_param("s" . $types, ...$params);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
        
        $conn->commit();
        
        $response = [
            'success' => true,
            'message' => 'Tasks saved successfully',
            'tasksCount' => count($tasks),
            'deletedCount' => count($toDelete)
        ];
    } catch (Exception $e) {
        $conn->rollback();
        $response = [
            'success' => false,
            'error' => 'Failed to save tasks: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    $conn->close();
    return $response;
}

// Initialize database
initializeDatabase();

// Route handling
$action = getParam('action', '');

switch ($action) {
    case 'verify-user':
        $code = getParam('code');
        if (!$code) {
            echo json_encode(['success' => false, 'error' => 'Secret code required']);
            exit();
        }
        echo json_encode(verifyUser($code));
        break;
        
    case 'register-user':
        $name = getParam('name');
        $code = getParam('code');
        $gender = getParam('gender', 'male');
        
        if (!$name || !$code) {
            echo json_encode(['success' => false, 'error' => 'Name and secret code required']);
            exit();
        }
        echo json_encode(registerUser($name, $code, $gender));
        break;
    
    case 'get-all-data':
        $code = getParam('code');
        if (!$code) {
            echo json_encode(['success' => false, 'error' => 'Secret code required']);
            exit();
        }
        echo json_encode(getAllData($code));
        break;
    
    case 'save-workspaces':
        $code = getParam('code');
        $workspaces = getParam('workspaces', []);
        
        if (!$code) {
            echo json_encode(['success' => false, 'error' => 'Secret code required']);
            exit();
        }
        echo json_encode(saveWorkspaces($code, $workspaces));
        break;
    
    case 'save-projects':
        $code = getParam('code');
        $projects = getParam('projects', []);
        
        if (!$code) {
            echo json_encode(['success' => false, 'error' => 'Secret code required']);
            exit();
        }
        echo json_encode(saveProjects($code, $projects));
        break;
        
    case 'save-tasks':
        $code = getParam('code');
        $tasks = getParam('tasks', []);
        
        if (!$code) {
            echo json_encode(['success' => false, 'error' => 'Secret code required']);
            exit();
        }
        echo json_encode(saveTasks($code, $tasks));
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action',
            'available_actions' => [
                'verify-user',
                'register-user',
                'get-all-data',
                'save-workspaces',
                'save-projects',
                'save-tasks'
            ]
        ]);
        break;
}
?>
