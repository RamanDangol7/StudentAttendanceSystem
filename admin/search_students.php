<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: text/html');

$query = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';

if (!empty($query)) {
    $sql = "SELECT students.*, users.username 
            FROM students
            LEFT JOIN users ON students.user_id = users.id
            WHERE students.name LIKE '%$query%' OR students.roll_number LIKE '%$query%'
            ORDER BY students.name LIMIT 20";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        echo '<div class="table-responsive">';
        echo '<table class="table table-striped table-hover">';
        echo '<thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Roll Number</th>
                    <th>Account</th>
                    <th>Actions</th>
                </tr>
              </thead>
              <tbody>';
        
        while ($row = $result->fetch_assoc()) {
            echo '<tr>
                    <td>'.$row['id'].'</td>
                    <td>'.htmlspecialchars($row['name']).'</td>
                    <td>'.$row['roll_number'].'</td>
                    <td>';
            
            if ($row['username']) {
                echo '<span class="has-credentials"><i class="bi bi-check-circle-fill"></i> Active</span>';
            } else {
                echo '<span class="text-muted">None</span>';
            }
            
            echo '</td>
                  <td>
                      <a href="edit_student.php?id='.$row['id'].'" 
                         class="btn btn-sm btn-warning" title="Edit">
                          <i class="bi bi-pencil-square"></i>
                      </a>
                      <form method="POST" style="display:inline" 
                            onsubmit="return confirm(\'Delete this student?\');">
                          <input type="hidden" name="delete_id" value="'.$row['id'].'">
                          <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                              <i class="bi bi-trash"></i>
                          </button>
                      </form>
                  </td>
              </tr>';
        }
        
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-info">No matching students found</div>';
    }
}
?>